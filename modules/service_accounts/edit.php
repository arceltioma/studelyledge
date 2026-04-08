<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_manage_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de service invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM service_accounts
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de service introuvable.');
}

$pageTitle = 'Modifier un compte de service (706)';
$pageSubtitle = 'Mise à jour sécurisée du compte de produit avec prévisualisation avant validation';

$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$allowedOperationLabels = array_map(
    static fn(array $row): string => (string)($row['label'] ?? ''),
    $operationTypes
);

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$selectedOperationTypeId = '';
foreach ($operationTypes as $type) {
    if ((string)($account['operation_type_label'] ?? '') === (string)($type['label'] ?? '')) {
        $selectedOperationTypeId = (string)($type['id'] ?? '');
        break;
    }
}

$formData = [
    'account_code' => $account['account_code'] ?? '',
    'account_label' => $account['account_label'] ?? '',
    'operation_type_id' => $selectedOperationTypeId,
    'operation_type_label' => $account['operation_type_label'] ?? '',
    'commercial_country_label' => $account['commercial_country_label'] ?? '',
    'destination_country_label' => $account['destination_country_label'] ?? '',
    'current_balance' => (string)($account['current_balance'] ?? '0'),
    'is_postable' => (int)($account['is_postable'] ?? 0),
    'is_active' => (int)($account['is_active'] ?? 1),
];

if (!function_exists('sl_service_account_edit_value')) {
    function sl_service_account_edit_value(array $data, string $key, mixed $default = ''): string
    {
        return e((string)($data[$key] ?? $default));
    }
}

if (!function_exists('sl_service_account_edit_label_from_id')) {
    function sl_service_account_edit_label_from_id(array $operationTypes, string $id): string
    {
        foreach ($operationTypes as $type) {
            if ((string)($type['id'] ?? '') === $id) {
                return trim((string)($type['label'] ?? ''));
            }
        }
        return '';
    }
}

if (!function_exists('sl_service_account_edit_preview')) {
    function sl_service_account_edit_preview(array $formData, array $account): array
    {
        return [
            'old_code' => (string)($account['account_code'] ?? ''),
            'new_code' => trim((string)($formData['account_code'] ?? '')),
            'account_label' => trim((string)($formData['account_label'] ?? '')),
            'operation_type_label' => trim((string)($formData['operation_type_label'] ?? '')),
            'commercial_country_label' => trim((string)($formData['commercial_country_label'] ?? '')),
            'destination_country_label' => trim((string)($formData['destination_country_label'] ?? '')),
            'current_balance' => (float)($formData['current_balance'] ?? 0),
            'is_postable' => (int)($formData['is_postable'] ?? 0),
            'is_active' => (int)($formData['is_active'] ?? 1),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedOperationTypeId = trim((string)($_POST['operation_type_id'] ?? ''));

    $formData = [
        'account_code' => trim((string)($_POST['account_code'] ?? '')),
        'account_label' => trim((string)($_POST['account_label'] ?? '')),
        'operation_type_id' => $selectedOperationTypeId,
        'operation_type_label' => sl_service_account_edit_label_from_id($operationTypes, $selectedOperationTypeId),
        'commercial_country_label' => trim((string)($_POST['commercial_country_label'] ?? '')),
        'destination_country_label' => trim((string)($_POST['destination_country_label'] ?? '')),
        'current_balance' => trim((string)($_POST['current_balance'] ?? '0')),
        'is_postable' => isset($_POST['is_postable']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['account_code'] === '') {
            throw new RuntimeException('Le code compte est obligatoire.');
        }

        if ($formData['account_label'] === '') {
            throw new RuntimeException('L’intitulé est obligatoire.');
        }

        if (!is_numeric($formData['current_balance'])) {
            throw new RuntimeException('Le solde courant est invalide.');
        }

        if (
            $formData['operation_type_label'] !== ''
            && !in_array($formData['operation_type_label'], $allowedOperationLabels, true)
        ) {
            throw new RuntimeException('Le type d’opération sélectionné est invalide.');
        }

        if (
            $formData['commercial_country_label'] !== ''
            && $commercialCountries
            && !in_array($formData['commercial_country_label'], $commercialCountries, true)
        ) {
            throw new RuntimeException('Le pays commercial est invalide.');
        }

        if (
            $formData['destination_country_label'] !== ''
            && $destinationCountries
            && !in_array($formData['destination_country_label'], $destinationCountries, true)
        ) {
            throw new RuntimeException('Le pays destination est invalide.');
        }

        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM service_accounts
            WHERE account_code = ?
              AND id <> ?
        ");
        $stmtCheck->execute([$formData['account_code'], $id]);

        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Un autre compte utilise déjà ce code.');
        }

        $before = $account;
        $previewData = sl_service_account_edit_preview($formData, $account);
        $previewMode = true;

        if ($actionMode === 'save') {
            $fields = [];
            $params = [];

            $map = [
                'account_code' => $formData['account_code'],
                'account_label' => $formData['account_label'],
                'operation_type_label' => $formData['operation_type_label'] !== '' ? $formData['operation_type_label'] : null,
                'commercial_country_label' => $formData['commercial_country_label'] !== '' ? $formData['commercial_country_label'] : null,
                'destination_country_label' => $formData['destination_country_label'] !== '' ? $formData['destination_country_label'] : null,
                'current_balance' => (float)$formData['current_balance'],
                'is_postable' => $formData['is_postable'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'service_accounts', $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'service_accounts', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            $params[] = $id;

            $stmtUpdate = $pdo->prepare("
                UPDATE service_accounts
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute($params);

            $stmt = $pdo->prepare("
                SELECT *
                FROM service_accounts
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: $account;

            if (function_exists('auditEntityChanges') && isset($_SESSION['user_id'])) {
                auditEntityChanges($pdo, 'service_account', $id, $before, $account, (int)$_SESSION['user_id']);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_service_account',
                    'service_accounts',
                    'service_account',
                    $id,
                    'Modification d’un compte de service 706'
                );
            }

            $successMessage = 'Compte de service mis à jour avec succès.';
            $previewMode = false;
            $previewData = null;

            $selectedOperationTypeId = '';
            foreach ($operationTypes as $type) {
                if ((string)($account['operation_type_label'] ?? '') === (string)($type['label'] ?? '')) {
                    $selectedOperationTypeId = (string)($type['id'] ?? '');
                    break;
                }
            }

            $formData = [
                'account_code' => $account['account_code'] ?? '',
                'account_label' => $account['account_label'] ?? '',
                'operation_type_id' => $selectedOperationTypeId,
                'operation_type_label' => $account['operation_type_label'] ?? '',
                'commercial_country_label' => $account['commercial_country_label'] ?? '',
                'destination_country_label' => $account['destination_country_label'] ?? '',
                'current_balance' => (string)($account['current_balance'] ?? '0'),
                'is_postable' => (int)($account['is_postable'] ?? 0),
                'is_active' => (int)($account['is_active'] ?? 1),
            ];
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $previewMode = false;
        $previewData = null;
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
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code compte</label>
                            <input type="text" name="account_code" value="<?= sl_service_account_edit_value($formData, 'account_code') ?>" required>
                        </div>

                        <div>
                            <label>Intitulé</label>
                            <input type="text" name="account_label" value="<?= sl_service_account_edit_value($formData, 'account_label') ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id">
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option
                                        value="<?= (int)$type['id'] ?>"
                                        <?= (string)($formData['operation_type_id'] ?? '') === (string)$type['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($type['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <select name="commercial_country_label">
                                <option value="">Choisir</option>
                                <?php foreach ($commercialCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= ($formData['commercial_country_label'] ?? '') === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays destination</label>
                            <select name="destination_country_label">
                                <option value="">Choisir</option>
                                <?php foreach ($destinationCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= ($formData['destination_country_label'] ?? '') === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Solde courant</label>
                            <input type="number" step="0.01" name="current_balance" value="<?= sl_service_account_edit_value($formData, 'current_balance', '0') ?>">
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="is_postable" value="1" <?= (int)($formData['is_postable'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Compte postable
                        </label>
                    </div>

                    <div style="margin-top:10px;">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            Compte actif
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Voir</a>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code actuel</span><strong><?= e($previewData['old_code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nouveau code</span><strong><?= e($previewData['new_code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Intitulé</span><strong><?= e($previewData['account_label']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e($previewData['operation_type_label'] !== '' ? $previewData['operation_type_label'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays commercial</span><strong><?= e($previewData['commercial_country_label'] !== '' ? $previewData['commercial_country_label'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays destination</span><strong><?= e($previewData['destination_country_label'] !== '' ? $previewData['destination_country_label'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde courant</span><strong><?= e(number_format((float)$previewData['current_balance'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Type</span><strong><?= (int)$previewData['is_postable'] === 1 ? 'Postable' : 'Structure' ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)$previewData['is_active'] === 1 ? 'Actif' : 'Archivé' ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code</span><strong><?= e((string)($account['account_code'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Intitulé</span><strong><?= e((string)($account['account_label'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e((string)($account['operation_type_label'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Type</span><strong><?= ((int)($account['is_postable'] ?? 0) === 1) ? 'Postable' : 'Structure' ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>