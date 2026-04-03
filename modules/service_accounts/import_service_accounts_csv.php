<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_import_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$pageTitle = 'Import comptes de service CSV';
$pageSubtitle = 'Import des comptes 706 avec contrôle de doublons et journalisation';

$successMessage = '';
$errorMessage = '';
$importReport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            throw new RuntimeException('Fichier CSV manquant.');
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if (!$handle) {
            throw new RuntimeException('Impossible de lire le fichier.');
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('Le fichier est vide.');
        }

        $mapping = function_exists('sl_get_import_mapping_suggestions')
            ? sl_get_import_mapping_suggestions($headers)
            : [];

        $inserted = 0;
        $updated = 0;
        $ignored = 0;
        $lineNumber = 1;

        $pdo->beginTransaction();

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            $assoc = [];

            foreach ($headers as $index => $header) {
                $assoc[$header] = $row[$index] ?? '';
            }

            $accountCode = trim((string)($assoc[$mapping['account_code'] ?? ''] ?? ''));
            $accountLabel = trim((string)($assoc[$mapping['account_label'] ?? ''] ?? ''));
            $commercialCountry = trim((string)($assoc[$mapping['commercial_country_label'] ?? ''] ?? ''));
            $destinationCountry = trim((string)($assoc[$mapping['destination_country_label'] ?? ''] ?? ''));

            if ($accountCode === '' || $accountLabel === '') {
                $ignored++;
                continue;
            }

            $stmtCheck = $pdo->prepare("SELECT * FROM service_accounts WHERE account_code = ? LIMIT 1");
            $stmtCheck->execute([$accountCode]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $fields = [];
                $params = [];

                if (columnExists($pdo, 'service_accounts', 'account_label')) {
                    $fields[] = 'account_label = ?';
                    $params[] = $accountLabel;
                }
                if (columnExists($pdo, 'service_accounts', 'commercial_country_label')) {
                    $fields[] = 'commercial_country_label = ?';
                    $params[] = $commercialCountry !== '' ? $commercialCountry : null;
                }
                if (columnExists($pdo, 'service_accounts', 'destination_country_label')) {
                    $fields[] = 'destination_country_label = ?';
                    $params[] = $destinationCountry !== '' ? $destinationCountry : null;
                }
                if (columnExists($pdo, 'service_accounts', 'updated_at')) {
                    $fields[] = 'updated_at = NOW()';
                }

                if ($fields) {
                    $params[] = (int)$existing['id'];
                    $stmtUpdate = $pdo->prepare("
                        UPDATE service_accounts
                        SET " . implode(', ', $fields) . "
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute($params);
                    $updated++;
                } else {
                    $ignored++;
                }
            } else {
                $columns = [];
                $values = [];
                $params = [];

                $map = [
                    'account_code' => $accountCode,
                    'account_label' => $accountLabel,
                    'commercial_country_label' => $commercialCountry !== '' ? $commercialCountry : null,
                    'destination_country_label' => $destinationCountry !== '' ? $destinationCountry : null,
                    'is_active' => 1,
                    'is_postable' => 1,
                ];

                foreach ($map as $column => $value) {
                    if (columnExists($pdo, 'service_accounts', $column)) {
                        $columns[] = $column;
                        $values[] = '?';
                        $params[] = $value;
                    }
                }

                if (columnExists($pdo, 'service_accounts', 'created_at')) {
                    $columns[] = 'created_at';
                    $values[] = 'NOW()';
                }
                if (columnExists($pdo, 'service_accounts', 'updated_at')) {
                    $columns[] = 'updated_at';
                    $values[] = 'NOW()';
                }

                $stmtInsert = $pdo->prepare("
                    INSERT INTO service_accounts (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")
                ");
                $stmtInsert->execute($params);
                $inserted++;
            }
        }

        fclose($handle);
        $pdo->commit();

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'service_accounts_import',
                'service_accounts',
                'import',
                null,
                'Import CSV comptes de service : ' . $inserted . ' créés, ' . $updated . ' mis à jour, ' . $ignored . ' ignorés'
            );
        }

        if (function_exists('sl_create_entity_notification') && isset($_SESSION['user_id'])) {
            sl_create_entity_notification(
                $pdo,
                'service_accounts_import_summary',
                'Import comptes de service terminé : ' . $inserted . ' créés, ' . $updated . ' mis à jour, ' . $ignored . ' ignorés',
                'success',
                'import',
                0,
                (int)$_SESSION['user_id']
            );
        }

        $importReport = [
            'inserted' => $inserted,
            'updated' => $updated,
            'ignored' => $ignored,
        ];

        $successMessage = 'Import des comptes de service terminé avec succès.';
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

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Importer un CSV</h3>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_input() ?>

                    <div>
                        <label>Fichier CSV</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required>
                    </div>

                    <div class="dashboard-note" style="margin-top:14px;">
                        Colonnes recommandées :
                        <strong>account_code</strong>,
                        <strong>account_label</strong>,
                        <strong>commercial_country_label</strong>,
                        <strong>destination_country_label</strong>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Importer</button>
                        <a href="<?= e(APP_URL) ?>modules/imports/index.php" class="btn btn-outline">Hub Import</a>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-secondary">Comptes 706</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Rapport</h3>

                <?php if ($importReport): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Créés</span>
                            <strong><?= (int)$importReport['inserted'] ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Mis à jour</span>
                            <strong><?= (int)$importReport['updated'] ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Ignorés</span>
                            <strong><?= (int)$importReport['ignored'] ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucun import exécuté pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>