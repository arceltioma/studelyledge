<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'imports_preview');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Mapping manuel assisté';
$pageSubtitle = 'Vérifie et corrige le rapprochement des colonnes avant la prévisualisation';

const SL_IMPORT_SESSION_KEY = 'studelyledger_operations_import_preview_v3';

if (!function_exists('sl_import_mapping_create_notification')) {
    function sl_import_mapping_create_notification(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = 'import',
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $allowedLevels = ['info', 'success', 'warning', 'danger'];
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'info';
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => 0,
            'created_by' => $createdBy,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'notifications', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NULL';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (empty($_SESSION[SL_IMPORT_SESSION_KEY]['raw_rows']) || !is_array($_SESSION[SL_IMPORT_SESSION_KEY]['raw_rows'])) {
    $_SESSION['error_message'] = 'Aucun import en attente.';
    header('Location: ' . APP_URL . 'modules/imports/import_upload.php');
    exit;
}

$importSession = &$_SESSION[SL_IMPORT_SESSION_KEY];
$fileName = (string)($importSession['file_name'] ?? 'import.csv');
$rawHeaders = $importSession['raw_headers'] ?? [];
$suggestedMapping = $importSession['suggested_mapping'] ?? [];
$userId = (int)($_SESSION['user_id'] ?? 0);

$targetFields = [
    '' => '— Ignorer —',
    'operation_date' => 'Date opération',
    'amount' => 'Montant',
    'currency_code' => 'Devise',
    'client_code' => 'Client',
    'operation_type' => 'Type opération',
    'service' => 'Service',
    'reference' => 'Référence',
    'label' => 'Libellé',
    'notes' => 'Notes',
    'source_account_code' => 'Compte source',
    'destination_account_code' => 'Compte destination',
    'linked_bank_account_id' => 'Compte bancaire lié',
];

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $mapping = $_POST['mapping'] ?? [];
        if (!is_array($mapping)) {
            throw new RuntimeException('Mapping invalide.');
        }

        $sanitized = [];
        foreach ($rawHeaders as $header) {
            $value = (string)($mapping[$header] ?? '');
            if (!array_key_exists($value, $targetFields)) {
                $value = '';
            }
            $sanitized[$header] = $value;
        }

        $required = ['operation_date', 'amount', 'operation_type', 'service'];
        $mappedTargets = array_values(array_filter($sanitized, static fn($v) => $v !== ''));

        foreach ($required as $requiredField) {
            if (!in_array($requiredField, $mappedTargets, true)) {
                throw new RuntimeException('Le champ obligatoire "' . $targetFields[$requiredField] . '" doit être mappé.');
            }
        }

        $mappedRows = [];
        foreach ($importSession['raw_rows'] as $rawRow) {
            $mappedRow = ['_line_number' => $rawRow['_line_number'] ?? null];
            foreach ($sanitized as $sourceHeader => $targetField) {
                if ($targetField === '') {
                    continue;
                }
                $mappedRow[$targetField] = trim((string)($rawRow[$sourceHeader] ?? ''));
            }
            $mappedRows[] = $mappedRow;
        }

        $importSession['final_mapping'] = $sanitized;
        $importSession['rows'] = $mappedRows;

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'validate_import_mapping',
                'imports',
                'import',
                null,
                sprintf(
                    'Validation du mapping import pour %s : %d colonne(s) source, %d champ(s) mappé(s), %d ligne(s) préparée(s)',
                    $fileName,
                    count($rawHeaders),
                    count(array_filter($sanitized, static fn($v) => $v !== '')),
                    count($mappedRows)
                )
            );
        }

        sl_import_mapping_create_notification(
            $pdo,
            'import_mapping_validated',
            sprintf(
                'Mapping import validé pour %s : %d colonne(s) mappée(s), %d ligne(s) prêtes pour prévisualisation.',
                $fileName,
                count(array_filter($sanitized, static fn($v) => $v !== '')),
                count($mappedRows)
            ),
            'success',
            APP_URL . 'modules/imports/import_preview.php',
            'import',
            null,
            $userId > 0 ? $userId : null
        );

        header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'validate_import_mapping_failed',
                'imports',
                'import',
                null,
                'Échec validation mapping import pour ' . $fileName . ' : ' . $errorMessage
            );
        }

        sl_import_mapping_create_notification(
            $pdo,
            'import_mapping_failed',
            'Échec du mapping import pour ' . $fileName . ' : ' . $errorMessage,
            'danger',
            APP_URL . 'modules/imports/import_mapping.php',
            'import',
            null,
            $userId > 0 ? $userId : null
        );
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Fichier : <?= e($fileName) ?></h3>

            <form method="POST">
                <?= csrf_input() ?>

                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Colonne source</th>
                                <th>Champ cible</th>
                                <th>Suggestion auto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rawHeaders as $header): ?>
                                <?php $current = $suggestedMapping[$header] ?? ''; ?>
                                <tr>
                                    <td><?= e($header) ?></td>
                                    <td>
                                        <select name="mapping[<?= e($header) ?>]">
                                            <?php foreach ($targetFields as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= $current === $value ? 'selected' : '' ?>>
                                                    <?= e($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><?= e($targetFields[$current] ?? 'Aucune') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Valider le mapping</button>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-outline">Recommencer</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>