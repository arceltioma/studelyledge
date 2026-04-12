<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_create_page');
} else {
    enforcePagePermission($pdo, 'imports_create');
}

$pageTitle = 'Créer un import de mensualités';
$pageSubtitle = 'Import CSV vers monthly_payment_imports et monthly_payment_import_rows';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            throw new RuntimeException('Fichier CSV obligatoire.');
        }

        $originalName = (string) ($_FILES['csv_file']['name'] ?? 'monthly_import.csv');
        $tmpPath = (string) $_FILES['csv_file']['tmp_name'];

        $rows = sl_monthly_payment_parse_csv($tmpPath, ';');
        if (!$rows) {
            throw new RuntimeException('Aucune ligne exploitable trouvée dans le fichier.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO monthly_payment_imports (
                file_name,
                status,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                ?,
                'draft',
                ?,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            $originalName,
            $_SESSION['user_id'] ?? null
        ]);

        $importId = (int) $pdo->lastInsertId();

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
                created_at,
                updated_at
            ) VALUES (
                :import_id,
                :row_number,
                :client_code,
                :monthly_amount,
                :treasury_account_code,
                :monthly_day,
                :label,
                'pending',
                NULL,
                NULL,
                NOW(),
                NOW()
            )
        ");

        foreach ($rows as $row) {
            $stmtRow->execute([
                ':import_id' => $importId,
                ':row_number' => (int) ($row['row_number'] ?? 0),
                ':client_code' => trim((string) ($row['client_code'] ?? '')),
                ':monthly_amount' => (float) str_replace(',', '.', (string) ($row['monthly_amount'] ?? '0')),
                ':treasury_account_code' => trim((string) ($row['treasury_account_code'] ?? '')),
                ':monthly_day' => (int) ($row['monthly_day'] ?? 26),
                ':label' => trim((string) ($row['label'] ?? '')),
            ]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int) $_SESSION['user_id'],
                'create_import',
                'monthly_payments',
                'monthly_payment_import',
                $importId,
                'Création d’un import de mensualités'
            );
        }

        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/monthly_payments/monthly_import_preview.php?id=' . $importId);
        exit;
    } catch (Throwable $e) {
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
            <h3>Importer un CSV</h3>
            <p class="muted">Colonnes attendues : <strong>client_code;monthly_amount;treasury_account_code;monthly_day;label</strong></p>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div class="dashboard-grid-2">
                    <div style="grid-column:1 / -1;">
                        <label>Fichier CSV</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Importer</button>
                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_runs_list.php" class="btn btn-outline">Retour</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Exemple CSV</h3>
            <pre>client_code;monthly_amount;treasury_account_code;monthly_day;label
CLT0001;150;5120101;26;Mensualité Aminata Diallo
983200894;200;5120410;10;Mensualité Arcel TIOMA</pre>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>