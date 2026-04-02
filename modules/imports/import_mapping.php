<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_preview_page');
} else {
    enforcePagePermission($pdo, 'imports_preview');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Mapping manuel assisté';
$pageSubtitle = 'Vérifie et corrige le rapprochement des colonnes avant la prévisualisation';

const SL_IMPORT_SESSION_KEY = 'studelyledger_operations_import_preview_v3';

if (empty($_SESSION[SL_IMPORT_SESSION_KEY]['raw_rows']) || !is_array($_SESSION[SL_IMPORT_SESSION_KEY]['raw_rows'])) {
    $_SESSION['error_message'] = 'Aucun import en attente.';
    header('Location: ' . APP_URL . 'modules/imports/import_upload.php');
    exit;
}

$importSession = &$_SESSION[SL_IMPORT_SESSION_KEY];
$fileName = (string)($importSession['file_name'] ?? 'import.csv');
$rawHeaders = $importSession['raw_headers'] ?? [];
$suggestedMapping = $importSession['suggested_mapping'] ?? [];

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

        header('Location: ' . APP_URL . 'modules/imports/import_preview.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
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