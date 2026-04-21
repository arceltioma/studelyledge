<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'accounting_rule_edit');
}

if (!tableExists($pdo, 'accounting_rules')) {
    exit('Table accounting_rules introuvable.');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Règle comptable invalide.');
}

if (!function_exists('are_value')) {
    function are_value(array $data, string $key, mixed $default = ''): string
    {
        return e((string)($data[$key] ?? $default));
    }
}

if (!function_exists('are_find_by_id')) {
    function are_find_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

$stmtRule = $pdo->prepare("
    SELECT *
    FROM accounting_rules
    WHERE id = ?
    LIMIT 1
");
$stmtRule->execute([$id]);
$rule = $stmtRule->fetch(PDO::FETCH_ASSOC);

if (!$rule) {
    exit('Règle comptable introuvable.');
}

$pageTitle = 'Modifier une règle comptable';
$pageSubtitle = 'Ajustement sécurisé de la logique débit / crédit';

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label, direction, is_active
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, code, label, operation_type_id, is_active
        FROM ref_services
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$accountModes = [
    'CLIENT_411' => 'Compte client 411',
    'CLIENT_512' => 'Compte interne client 512',
    'SERVICE_706' => 'Compte de service 706',
    'SOURCE_512' => 'Compte source 512',
    'TARGET_512' => 'Compte cible 512',
    'MANUAL_DEBIT' => 'Compte manuel saisi au débit',
    'MANUAL_CREDIT' => 'Compte manuel saisi au crédit',
    'FIXED_ACCOUNT' => 'Compte fixe',
];

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$formData = [
    'operation_type_id' => (string)($rule['operation_type_id'] ?? ''),
    'service_id' => (string)($rule['service_id'] ?? ''),
    'rule_code' => (string)($rule['rule_code'] ?? ''),
    'rule_label' => (string)($rule['rule_label'] ?? ''),
    'debit_mode' => (string)($rule['debit_mode'] ?? 'CLIENT_411'),
    'credit_mode' => (string)($rule['credit_mode'] ?? 'SERVICE_706'),
    'debit_fixed_account_code' => (string)($rule['debit_fixed_account_code'] ?? ''),
    'credit_fixed_account_code' => (string)($rule['credit_fixed_account_code'] ?? ''),
    'label_pattern' => (string)($rule['label_pattern'] ?? ''),
    'requires_client' => (int)($rule['requires_client'] ?? 0),
    'requires_linked_bank' => (int)($rule['requires_linked_bank'] ?? 0),
    'requires_manual_accounts' => (int)($rule['requires_manual_accounts'] ?? 0),
    'is_active' => (int)($rule['is_active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'operation_type_id' => trim((string)($_POST['operation_type_id'] ?? '')),
        'service_id' => trim((string)($_POST['service_id'] ?? '')),
        'rule_code' => strtoupper(trim((string)($_POST['rule_code'] ?? ''))),
        'rule_label' => trim((string)($_POST['rule_label'] ?? '')),
        'debit_mode' => trim((string)($_POST['debit_mode'] ?? 'CLIENT_411')),
        'credit_mode' => trim((string)($_POST['credit_mode'] ?? 'SERVICE_706')),
        'debit_fixed_account_code' => strtoupper(trim((string)($_POST['debit_fixed_account_code'] ?? ''))),
        'credit_fixed_account_code' => strtoupper(trim((string)($_POST['credit_fixed_account_code'] ?? ''))),
        'label_pattern' => trim((string)($_POST['label_pattern'] ?? '')),
        'requires_client' => isset($_POST['requires_client']) ? 1 : 0,
        'requires_linked_bank' => isset($_POST['requires_linked_bank']) ? 1 : 0,
        'requires_manual_accounts' => isset($_POST['requires_manual_accounts']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $operationTypeId = (int)$formData['operation_type_id'];
        $serviceId = (int)$formData['service_id'];

        if ($operationTypeId <= 0) {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        if ($serviceId <= 0) {
            throw new RuntimeException('Le service est obligatoire.');
        }

        if ($formData['rule_code'] === '') {
            throw new RuntimeException('Le code règle est obligatoire.');
        }

        if (!isset($accountModes[$formData['debit_mode']])) {
            throw new RuntimeException('Le mode débit est invalide.');
        }

        if (!isset($accountModes[$formData['credit_mode']])) {
            throw new RuntimeException('Le mode crédit est invalide.');
        }

        $operationType = are_find_by_id($operationTypes, $operationTypeId);
        if (!$operationType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        $service = are_find_by_id($services, $serviceId);
        if (!$service) {
            throw new RuntimeException('Service introuvable.');
        }

        $stmtDupCode = $pdo->prepare("
            SELECT id
            FROM accounting_rules
            WHERE rule_code = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDupCode->execute([$formData['rule_code'], $id]);
        if ($stmtDupCode->fetch()) {
            throw new RuntimeException('Ce code règle existe déjà.');
        }

        $stmtDupPair = $pdo->prepare("
            SELECT id
            FROM accounting_rules
            WHERE operation_type_id = ?
              AND service_id = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDupPair->execute([$operationTypeId, $serviceId, $id]);
        if ($stmtDupPair->fetch()) {
            throw new RuntimeException('Une autre règle existe déjà pour ce couple type / service.');
        }

        if ((int)($service['operation_type_id'] ?? 0) > 0 && (int)($service['operation_type_id'] ?? 0) !== $operationTypeId) {
            throw new RuntimeException('Le service sélectionné n’est pas rattaché à ce type d’opération.');
        }

        if ($formData['debit_mode'] === 'FIXED_ACCOUNT' && $formData['debit_fixed_account_code'] === '') {
            throw new RuntimeException('Le compte fixe débit est obligatoire quand le mode débit = FIXED_ACCOUNT.');
        }

        if ($formData['credit_mode'] === 'FIXED_ACCOUNT' && $formData['credit_fixed_account_code'] === '') {
            throw new RuntimeException('Le compte fixe crédit est obligatoire quand le mode crédit = FIXED_ACCOUNT.');
        }

        if (in_array($formData['debit_mode'], ['CLIENT_411', 'CLIENT_512'], true) || in_array($formData['credit_mode'], ['CLIENT_411', 'CLIENT_512'], true)) {
            $formData['requires_client'] = 1;
        }

        if (in_array($formData['debit_mode'], ['MANUAL_DEBIT', 'MANUAL_CREDIT'], true) || in_array($formData['credit_mode'], ['MANUAL_DEBIT', 'MANUAL_CREDIT'], true)) {
            $formData['requires_manual_accounts'] = 1;
        }

        $previewData = [
            'operation_type' => $operationType,
            'service' => $service,
            'rule_code' => $formData['rule_code'],
            'rule_label' => $formData['rule_label'],
            'debit_mode' => $formData['debit_mode'],
            'credit_mode' => $formData['credit_mode'],
            'debit_fixed_account_code' => $formData['debit_fixed_account_code'],
            'credit_fixed_account_code' => $formData['credit_fixed_account_code'],
            'label_pattern' => $formData['label_pattern'],
            'requires_client' => $formData['requires_client'],
            'requires_linked_bank' => $formData['requires_linked_bank'],
            'requires_manual_accounts' => $formData['requires_manual_accounts'],
            'is_active' => $formData['is_active'],
        ];
        $previewMode = true;

        if ($actionMode === 'save') {
            $fields = [];
            $params = [];

            $map = [
                'operation_type_id' => $operationTypeId,
                'service_id' => $serviceId,
                'rule_code' => $formData['rule_code'],
                'rule_label' => $formData['rule_label'] !== '' ? $formData['rule_label'] : null,
                'debit_mode' => $formData['debit_mode'],
                'credit_mode' => $formData['credit_mode'],
                'debit_fixed_account_code' => $formData['debit_fixed_account_code'] !== '' ? $formData['debit_fixed_account_code'] : null,
                'credit_fixed_account_code' => $formData['credit_fixed_account_code'] !== '' ? $formData['credit_fixed_account_code'] : null,
                'label_pattern' => $formData['label_pattern'] !== '' ? $formData['label_pattern'] : null,
                'requires_client' => $formData['requires_client'],
                'requires_linked_bank' => $formData['requires_linked_bank'],
                'requires_manual_accounts' => $formData['requires_manual_accounts'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'accounting_rules', $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'accounting_rules', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            $params[] = $id;

            $stmtUpdate = $pdo->prepare("
                UPDATE accounting_rules
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute($params);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_accounting_rule',
                    'admin_functional',
                    'accounting_rule',
                    $id,
                    'Modification d’une règle comptable'
                );
            }

            $successMessage = 'Règle comptable mise à jour.';
            $previewMode = false;
            $previewData = null;

            $stmtRule->execute([$id]);
            $rule = $stmtRule->fetch(PDO::FETCH_ASSOC) ?: $rule;

            $formData = [
                'operation_type_id' => (string)($rule['operation_type_id'] ?? ''),
                'service_id' => (string)($rule['service_id'] ?? ''),
                'rule_code' => (string)($rule['rule_code'] ?? ''),
                'rule_label' => (string)($rule['rule_label'] ?? ''),
                'debit_mode' => (string)($rule['debit_mode'] ?? 'CLIENT_411'),
                'credit_mode' => (string)($rule['credit_mode'] ?? 'SERVICE_706'),
                'debit_fixed_account_code' => (string)($rule['debit_fixed_account_code'] ?? ''),
                'credit_fixed_account_code' => (string)($rule['credit_fixed_account_code'] ?? ''),
                'label_pattern' => (string)($rule['label_pattern'] ?? ''),
                'requires_client' => (int)($rule['requires_client'] ?? 0),
                'requires_linked_bank' => (int)($rule['requires_linked_bank'] ?? 0),
                'requires_manual_accounts' => (int)($rule['requires_manual_accounts'] ?? 0),
                'is_active' => (int)($rule['is_active'] ?? 1),
            ];
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$currentOperationType = are_find_by_id($operationTypes, (int)($rule['operation_type_id'] ?? 0));
$currentService = are_find_by_id($services, (int)($rule['service_id'] ?? 0));

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
                            <label>Type d’opération</label>
                            <select name="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= $formData['operation_type_id'] === (string)$type['id'] ? 'selected' : '' ?>>
                                        <?= e(($type['label'] ?? '') . ' (' . ($type['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= (int)$service['id'] ?>" <?= $formData['service_id'] === (string)$service['id'] ? 'selected' : '' ?>>
                                        <?= e(($service['label'] ?? '') . ' (' . ($service['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Code règle</label>
                            <input type="text" name="rule_code" value="<?= are_value($formData, 'rule_code') ?>" required>
                        </div>

                        <div>
                            <label>Libellé règle</label>
                            <input type="text" name="rule_label" value="<?= are_value($formData, 'rule_label') ?>">
                        </div>

                        <div>
                            <label>Mode débit</label>
                            <select name="debit_mode" required>
                                <?php foreach ($accountModes as $code => $label): ?>
                                    <option value="<?= e($code) ?>" <?= ($formData['debit_mode'] ?? '') === $code ? 'selected' : '' ?>>
                                        <?= e($code . ' — ' . $label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Mode crédit</label>
                            <select name="credit_mode" required>
                                <?php foreach ($accountModes as $code => $label): ?>
                                    <option value="<?= e($code) ?>" <?= ($formData['credit_mode'] ?? '') === $code ? 'selected' : '' ?>>
                                        <?= e($code . ' — ' . $label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte fixe débit</label>
                            <input type="text" name="debit_fixed_account_code" value="<?= are_value($formData, 'debit_fixed_account_code') ?>" placeholder="Ex: 471000">
                        </div>

                        <div>
                            <label>Compte fixe crédit</label>
                            <input type="text" name="credit_fixed_account_code" value="<?= are_value($formData, 'credit_fixed_account_code') ?>" placeholder="Ex: 471100">
                        </div>

                        <div>
                            <label>Pattern libellé / matching 706</label>
                            <input type="text" name="label_pattern" value="<?= are_value($formData, 'label_pattern') ?>" placeholder="Ex: AVI, ATS, GESTION, TRANSFERT">
                        </div>
                    </div>

                    <div class="dashboard-grid-2" style="margin-top:16px;">
                        <div>
                            <label style="display:flex; gap:10px; align-items:center;">
                                <input type="checkbox" name="requires_client" value="1" <?= (int)($formData['requires_client'] ?? 0) === 1 ? 'checked' : '' ?>>
                                Client requis
                            </label>
                        </div>

                        <div>
                            <label style="display:flex; gap:10px; align-items:center;">
                                <input type="checkbox" name="requires_linked_bank" value="1" <?= (int)($formData['requires_linked_bank'] ?? 0) === 1 ? 'checked' : '' ?>>
                                Banque liée requise
                            </label>
                        </div>

                        <div>
                            <label style="display:flex; gap:10px; align-items:center;">
                                <input type="checkbox" name="requires_manual_accounts" value="1" <?= (int)($formData['requires_manual_accounts'] ?? 0) === 1 ? 'checked' : '' ?>>
                                Comptes manuels requis
                            </label>
                        </div>

                        <div>
                            <label style="display:flex; gap:10px; align-items:center;">
                                <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                Règle active
                            </label>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title"><?= $previewMode ? 'Prévisualisation avant validation' : 'État actuel' ?></h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e(($previewData['operation_type']['label'] ?? '') . ' (' . ($previewData['operation_type']['code'] ?? '') . ')') ?></strong></div>
                        <div class="sl-data-list__row"><span>Service</span><strong><?= e(($previewData['service']['label'] ?? '') . ' (' . ($previewData['service']['code'] ?? '') . ')') ?></strong></div>
                        <div class="sl-data-list__row"><span>Code règle</span><strong><?= e($previewData['rule_code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($previewData['rule_label'] !== '' ? $previewData['rule_label'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Débit</span><strong><?= e($previewData['debit_mode']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Crédit</span><strong><?= e($previewData['credit_mode']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte fixe débit</span><strong><?= e($previewData['debit_fixed_account_code'] !== '' ? $previewData['debit_fixed_account_code'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte fixe crédit</span><strong><?= e($previewData['credit_fixed_account_code'] !== '' ? $previewData['credit_fixed_account_code'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Pattern</span><strong><?= e($previewData['label_pattern'] !== '' ? $previewData['label_pattern'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Client requis</span><strong><?= (int)$previewData['requires_client'] === 1 ? 'Oui' : 'Non' ?></strong></div>
                        <div class="sl-data-list__row"><span>Banque liée requise</span><strong><?= (int)$previewData['requires_linked_bank'] === 1 ? 'Oui' : 'Non' ?></strong></div>
                        <div class="sl-data-list__row"><span>Comptes manuels requis</span><strong><?= (int)$previewData['requires_manual_accounts'] === 1 ? 'Oui' : 'Non' ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)$previewData['is_active'] === 1 ? 'Active' : 'Inactive' ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e(trim((string)($currentOperationType['label'] ?? '') . ' (' . (string)($currentOperationType['code'] ?? '') . ')')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Service</span><strong><?= e(trim((string)($currentService['label'] ?? '') . ' (' . (string)($currentService['code'] ?? '') . ')')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Code règle</span><strong><?= e((string)($rule['rule_code'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($rule['rule_label'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Débit</span><strong><?= e((string)($rule['debit_mode'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Crédit</span><strong><?= e((string)($rule['credit_mode'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte fixe débit</span><strong><?= e((string)($rule['debit_fixed_account_code'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte fixe crédit</span><strong><?= e((string)($rule['credit_fixed_account_code'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Pattern</span><strong><?= e((string)($rule['label_pattern'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Client requis</span><strong><?= (int)($rule['requires_client'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
                        <div class="sl-data-list__row"><span>Banque liée requise</span><strong><?= (int)($rule['requires_linked_bank'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
                        <div class="sl-data-list__row"><span>Comptes manuels requis</span><strong><?= (int)($rule['requires_manual_accounts'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)($rule['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?></strong></div>
                    </div>
                <?php endif; ?>

                <div class="dashboard-note" style="margin-top:16px;">
                    Une règle active trouvée pour le couple type / service sera utilisée en priorité par `resolveAccountingOperationV2()`.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>