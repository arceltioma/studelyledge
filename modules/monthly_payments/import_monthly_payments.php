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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

if (!function_exists('sl_monthly_normalize_header')) {
    function sl_monthly_normalize_header(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = str_replace(
            ['é', 'è', 'ê', 'à', 'â', 'î', 'ï', 'ô', 'ù', 'û', 'ç', ' '],
            ['e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c', '_'],
            $value
        );
        return $value;
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
                    return sl_monthly_normalize_header((string)$value);
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

if (!function_exists('sl_monthly_row_value')) {
    function sl_monthly_row_value(array $row, array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return $default;
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

        $importColumns = [];
        $importValues = [];
        $importParams = [];

        if (columnExists($pdo, 'monthly_payment_imports', 'file_name')) {
            $importColumns[] = 'file_name';
            $importValues[] = '?';
            $importParams[] = $originalName;
        }

        if (columnExists($pdo, 'monthly_payment_imports', 'imported_by')) {
            $importColumns[] = 'imported_by';
            $importValues[] = '?';
            $importParams[] = $_SESSION['user_id'] ?? null;
        } elseif (columnExists($pdo, 'monthly_payment_imports', 'created_by')) {
            $importColumns[] = 'created_by';
            $importValues[] = '?';
            $importParams[] = $_SESSION['user_id'] ?? null;
        }

        if (columnExists($pdo, 'monthly_payment_imports', 'status')) {
            $importColumns[] = 'status';
            $importValues[] = '?';
            $importParams[] = 'pending';
        }

        if (columnExists($pdo, 'monthly_payment_imports', 'created_at')) {
            $importColumns[] = 'created_at';
            $importValues[] = 'NOW()';
        }

        if (columnExists($pdo, 'monthly_payment_imports', 'updated_at')) {
            $importColumns[] = 'updated_at';
            $importValues[] = 'NOW()';
        }

        if (!$importColumns) {
            throw new RuntimeException('Structure de monthly_payment_imports invalide.');
        }

        $stmtImport = $pdo->prepare("
            INSERT INTO monthly_payment_imports (" . implode(', ', $importColumns) . ")
            VALUES (" . implode(', ', $importValues) . ")
        ");
        $stmtImport->execute($importParams);

        $importId = (int)$pdo->lastInsertId();
        $_SESSION['monthly_payment_current_import_id'] = $importId;

        foreach ($rows as $row) {
            $clientCode = sl_monthly_row_value($row, ['client_code', 'code_client'], '');
            $amountRaw = sl_monthly_row_value($row, ['monthly_amount', 'mensualite', 'montant', 'amount'], '0');
            $dayRaw = sl_monthly_row_value($row, ['monthly_day', 'jour', 'day'], '26');
            $treasuryCode = sl_monthly_row_value($row, ['treasury_account_code', 'compte_512', 'account_code'], '');
            $label = sl_monthly_row_value($row, ['label', 'libelle'], '');

            $amount = (float)str_replace(',', '.', (string)$amountRaw);
            $day = (int)$dayRaw;

            if ($day < 1 || $day > 31) {
                $day = 26;
            }

            $rowColumns = [];
            $rowValues = [];
            $rowParams = [];

            $rowMap = [
                'import_id' => $importId,
                'client_code' => $clientCode !== '' ? $clientCode : null,
                'monthly_amount' => $amount,
                'mensualite_amount' => $amount,
                'monthly_day' => $day,
                'treasury_account_code' => $treasuryCode !== '' ? $treasuryCode : null,
                'label' => $label !== '' ? $label : null,
                'row_status' => 'pending',
                'row_message' => null,
                'payload_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
            ];

            foreach ($rowMap as $column => $value) {
                if (columnExists($pdo, 'monthly_payment_import_rows', $column)) {
                    $rowColumns[] = $column;
                    $rowValues[] = '?';
                    $rowParams[] = $value;
                }
            }

            if (columnExists($pdo, 'monthly_payment_import_rows', 'created_at')) {
                $rowColumns[] = 'created_at';
                $rowValues[] = 'NOW()';
            }

            if (columnExists($pdo, 'monthly_payment_import_rows', 'updated_at')) {
                $rowColumns[] = 'updated_at';
                $rowValues[] = 'NOW()';
            }

            if (!$rowColumns) {
                throw new RuntimeException('Structure de monthly_payment_import_rows invalide.');
            }

            $stmtRow = $pdo->prepare("
                INSERT INTO monthly_payment_import_rows (" . implode(', ', $rowColumns) . ")
                VALUES (" . implode(', ', $rowValues) . ")
            ");
            $stmtRow->execute($rowParams);
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
                <p class="muted">
                    Colonnes acceptées :
                    <strong>client_code</strong> ;
                    <strong>monthly_amount</strong> ;
                    <strong>monthly_day</strong> ;
                    <strong>treasury_account_code</strong> ;
                    <strong>label</strong>
                </p>

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

                <pre class="sl-code-preview">client_code;monthly_amount;treasury_account_code;monthly_day;label
CLT0001;150;5120101;26;Mensualité standard
CLT0002;200;5120102;26;Mensualité standard
CLT0005;300;5121401;28;Mensualité premium</pre>

                <div class="dashboard-note" style="margin-top:16px;">
                    Après import, chaque ligne est contrôlée, prévisualisée puis soumise à validation manuelle.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>