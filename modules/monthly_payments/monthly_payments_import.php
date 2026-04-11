<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_create_page');
} else {
    enforcePagePermission($pdo, 'operations_create');
}

$pageTitle = 'Import mensualités';
$pageSubtitle = 'Importer un fichier CSV de mensualités avec contrôle avant validation';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
            throw new RuntimeException('Fichier CSV obligatoire.');
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new RuntimeException('Seul le format CSV est accepté.');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO monthly_payment_imports (file_name, status, created_by, created_at)
            VALUES (?, 'draft', ?, NOW())
        ");
        $stmt->execute([
            (string)$file['name'],
            $_SESSION['user_id'] ?? null
        ]);

        $importId = (int)$pdo->lastInsertId();

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            throw new RuntimeException('Fichier vide.');
        }

        $header = array_map(static fn($v) => trim((string)$v), $header);

        $required = ['client_code', 'monthly_amount', 'treasury_account_code', 'monthly_day', 'label'];
        foreach ($required as $requiredColumn) {
            if (!in_array($requiredColumn, $header, true)) {
                throw new RuntimeException('Colonne manquante : ' . $requiredColumn);
            }
        }

        $positions = array_flip($header);
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            $clientCode = trim((string)($row[$positions['client_code']] ?? ''));
            $monthlyAmount = (float)str_replace(',', '.', (string)($row[$positions['monthly_amount']] ?? '0'));
            $treasuryAccountCode = trim((string)($row[$positions['treasury_account_code']] ?? ''));
            $monthlyDay = (int)($row[$positions['monthly_day']] ?? 26);
            $label = trim((string)($row[$positions['label']] ?? ''));

            $status = 'pending';
            $error = null;
            $resolvedClientId = null;

            if ($clientCode === '') {
                $status = 'error';
                $error = 'Client code vide.';
            } elseif ($monthlyAmount <= 0) {
                $status = 'error';
                $error = 'Montant invalide.';
            } elseif ($treasuryAccountCode === '') {
                $status = 'error';
                $error = 'Compte mensualité vide.';
            } elseif ($monthlyDay < 1 || $monthlyDay > 31) {
                $status = 'error';
                $error = 'Jour mensualité invalide.';
            } else {
                $stmtClient = $pdo->prepare("SELECT id FROM clients WHERE client_code = ? LIMIT 1");
                $stmtClient->execute([$clientCode]);
                $resolvedClientId = (int)($stmtClient->fetchColumn() ?: 0);

                if ($resolvedClientId <= 0) {
                    $status = 'error';
                    $error = 'Client introuvable.';
                } else {
                    $stmtTreasury = $pdo->prepare("SELECT COUNT(*) FROM treasury_accounts WHERE account_code = ? LIMIT 1");
                    $stmtTreasury->execute([$treasuryAccountCode]);
                    if ((int)$stmtTreasury->fetchColumn() <= 0) {
                        $status = 'error';
                        $error = 'Compte de mensualité introuvable.';
                    }
                }
            }

            $stmtRow = $pdo->prepare("
                INSERT INTO monthly_payment_import_rows (
                    import_id,
                    row_number,
                    client_code,
                    monthly_amount,
                    treasury_account_code,
                    monthly_day,
                    label,
                    status,
                    error_message,
                    resolved_client_id,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtRow->execute([
                $importId,
                $rowNumber,
                $clientCode,
                $monthlyAmount,
                $treasuryAccountCode,
                $monthlyDay,
                $label,
                $status,
                $error,
                $resolvedClientId ?: null
            ]);
        }

        fclose($handle);
        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/monthly_payments/monthly_payments_preview.php?import_id=' . $importId);
        exit;
    } catch (Throwable $e) {
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="card">
            <h3>Importer un CSV de mensualités</h3>

            <p>Colonnes attendues :</p>
            <pre>client_code;monthly_amount;treasury_account_code;monthly_day;label</pre>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div>
                    <label>Fichier CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Importer</button>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
