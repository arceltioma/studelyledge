<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_upload_page');
} else {
    enforcePagePermission($pdo, 'imports_upload');
}

$pageTitle = 'Import des mensualités';
$pageSubtitle = 'Import CSV des mensualités clients avec prévisualisation et validation manuelle';

$successMessage = '';
$errorMessage = '';

if (!function_exists('sl_monthly_payments_tables_exist')) {
    function sl_monthly_payments_tables_exist(PDO $pdo): bool
    {
        return tableExists($pdo, 'monthly_payment_imports') && tableExists($pdo, 'monthly_payment_import_rows');
    }
}

if (!function_exists('sl_parse_monthly_payment_csv')) {
    function sl_parse_monthly_payment_csv(string $filePath): array
    {
        $rows = [];

        if (!is_file($filePath)) {
            return $rows;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return $rows;
        }

        $header = null;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($header === null) {
                $header = array_map(static function ($value) {
                    return trim((string)$value);
                }, $data);
                continue;
            }

            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = trim((string)($data[$index] ?? ''));
            }

            if (implode('', $row) === '') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!sl_monthly_payments_tables_exist($pdo)) {
            throw new RuntimeException('Les tables d’import des mensualités sont absentes.');
        }

        if (empty($_FILES['monthly_file']['tmp_name'])) {
            throw new RuntimeException('Aucun fichier envoyé.');
        }

        $originalName = trim((string)($_FILES['monthly_file']['name'] ?? 'mensualites.csv'));
        $tmpPath = (string)$_FILES['monthly_file']['tmp_name'];

        $rows = sl_parse_monthly_payment_csv($tmpPath);
        if (!$rows) {
            throw new RuntimeException('Le fichier est vide ou invalide.');
        }

        $pdo->beginTransaction();

        $stmtImport = $pdo->prepare("
            INSERT INTO monthly_payment_imports (
                file_name,
                imported_by,
                status,
                created_at
            ) VALUES (?, ?, 'pending', NOW())
        ");
        $stmtImport->execute([
            $originalName,
            $_SESSION['user_id'] ?? null
        ]);

        $importId = (int)$pdo->lastInsertId();

        $stmtRow = $pdo->prepare("
            INSERT INTO monthly_payment_import_rows (
                import_id,
                client_code,
                monthly_amount,
                monthly_day,
                treasury_account_code,
                row_status,
                row_message,
                payload_json,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NULL, ?, NOW())
        ");

        foreach ($rows as $row) {
            $clientCode = trim((string)($row['client_code'] ?? ''));
            $amount = (float)str_replace(',', '.', (string)($row['monthly_amount'] ?? '0'));
            $day = (int)($row['monthly_day'] ?? 26);
            $treasuryCode = trim((string)($row['treasury_account_code'] ?? ''));

            if ($day < 1 || $day > 31) {
                $day = 26;
            }

            $payload = json_encode($row, JSON_UNESCAPED_UNICODE);

            $stmtRow->execute([
                $importId,
                $clientCode !== '' ? $clientCode : null,
                $amount,
                $day,
                $treasuryCode !== '' ? $treasuryCode : null,
                $payload
            ]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'import_monthly_payments',
                'monthly_payments',
                'monthly_payment_import',
                $importId,
                'Import du fichier de mensualités ' . $originalName
            );
        }

        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/monthly_payments/monthly_payments_preview.php?import_id=' . $importId);
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

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Importer un fichier CSV</h3>
                <p class="muted">Colonnes attendues : <strong>client_code</strong> ; <strong>monthly_amount</strong> ; <strong>monthly_day</strong> ; <strong>treasury_account_code</strong></p>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label>Fichier CSV</label>
                        <input type="file" name="monthly_file" accept=".csv" required>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Importer et prévisualiser</button>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Exemple de contenu CSV</h3>

                <pre class="sl-code-preview">client_code;monthly_amount;monthly_day;treasury_account_code
CLT0001;150;26;5120101
CLT0002;200;26;5120102
CLT0005;300;28;5121401</pre>

                <div class="dashboard-note" style="margin-top:16px;">
                    Après import, chaque ligne est contrôlée, enrichie puis soumise à validation manuelle.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
