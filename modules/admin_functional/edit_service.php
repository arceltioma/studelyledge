<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'services_manage_page');
} else {
    enforcePagePermission($pdo, 'services_manage');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Service invalide.');
}

/* =========================================================
   Helpers compatibilité
========================================================= */

if (!function_exists('esx_table')) {
    function esx_table(PDO $pdo, string $preferred, string $fallback): ?string
    {
        if (tableExists($pdo, $preferred)) {
            return $preferred;
        }
        if (tableExists($pdo, $fallback)) {
            return $fallback;
        }
        return null;
    }
}

if (!function_exists('esx_services_table')) {
    function esx_services_table(PDO $pdo): ?string
    {
        return esx_table($pdo, 'ref_services', 'services');
    }
}

if (!function_exists('esx_operation_types_table')) {
    function esx_operation_types_table(PDO $pdo): ?string
    {
        return esx_table($pdo, 'ref_operation_types', 'operation_types');
    }
}

if (!function_exists('esx_service_code_column')) {
    function esx_service_code_column(PDO $pdo, string $table): string
    {
        if (columnExists($pdo, $table, 'code')) {
            return 'code';
        }
        if (columnExists($pdo, $table, 'service_code')) {
            return 'service_code';
        }
        return 'code';
    }
}

if (!function_exists('esx_service_label_column')) {
    function esx_service_label_column(PDO $pdo, string $table): string
    {
        if (columnExists($pdo, $table, 'label')) {
            return 'label';
        }
        if (columnExists($pdo, $table, 'name')) {
            return 'name';
        }
        if (columnExists($pdo, $table, 'service_label')) {
            return 'service_label';
        }
        return 'label';
    }
}

if (!function_exists('esx_operation_type_code_column')) {
    function esx_operation_type_code_column(PDO $pdo, string $table): string
    {
        if (columnExists($pdo, $table, 'code')) {
            return 'code';
        }
        if (columnExists($pdo, $table, 'operation_code')) {
            return 'operation_code';
        }
        return 'code';
    }
}

if (!function_exists('esx_operation_type_label_column')) {
    function esx_operation_type_label_column(PDO $pdo, string $table): string
    {
        if (columnExists($pdo, $table, 'label')) {
            return 'label';
        }
        if (columnExists($pdo, $table, 'name')) {
            return 'name';
        }
        return 'label';
    }
}

if (!function_exists('esx_operation_type_fk_column')) {
    function esx_operation_type_fk_column(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['operation_type_id', 'type_operation_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

if (!function_exists('esx_service_account_fk_column')) {
    function esx_service_account_fk_column(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['service_account_id', 'account_706_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

if (!function_exists('esx_treasury_account_fk_column')) {
    function esx_treasury_account_fk_column(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['treasury_account_id', 'account_512_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

if (!function_exists('esx_fetch_service_accounts')) {
    function esx_fetch_service_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return [];
        }

        return $pdo->query("
            SELECT id, account_code, account_label, operation_type_label, destination_country_label, commercial_country_label, is_postable, is_active
            FROM service_accounts
            WHERE COALESCE(is_active,1)=1
            ORDER BY account_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('esx_fetch_treasury_accounts')) {
    function esx_fetch_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        return $pdo->query("
            SELECT id, account_code, account_label, is_active
            FROM treasury_accounts
            WHERE COALESCE(is_active,1)=1
            ORDER BY account_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('esx_find_row_by_id')) {
    function esx_find_row_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('esx_find_existing_rule')) {
    function esx_find_existing_rule(PDO $pdo, int $operationTypeId, int $serviceId): ?array
    {
        if (!tableExists($pdo, 'accounting_rules')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM accounting_rules
            WHERE operation_type_id = ?
              AND service_id = ?
            ORDER BY COALESCE(is_active,1) DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$operationTypeId, $serviceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('esx_build_rule_code')) {
    function esx_build_rule_code(string $operationCode, string $serviceCode): string
    {
        return 'RULE_' . sl_normalize_code($operationCode . '_' . $serviceCode);
    }
}

if (!function_exists('esx_suggest_accounting_rule')) {
    function esx_suggest_accounting_rule(
        array $operationType,
        array $service,
        ?array $serviceAccount,
        ?array $treasuryAccount
    ): array {
        $operationCode = sl_normalize_code((string)($operationType['code'] ?? ''));
        $operationLabel = trim((string)($operationType['label'] ?? ''));
        $serviceCode = sl_normalize_code((string)($service['code'] ?? ''));
        $serviceLabel = trim((string)($service['label'] ?? ''));

        $summary = function_exists('sl_get_operation_rules_summary')
            ? sl_get_operation_rules_summary($operationCode, $serviceCode, null, null)
            : [
                'requires_client' => 1,
                'requires_linked_bank' => 0,
                'requires_manual_accounts' => 0,
                'service_account_search_text' => '',
            ];

        $direction = strtolower(trim((string)($operationType['direction'] ?? 'mixed')));
        $requiresManual = (int)($summary['requires_manual_accounts'] ?? 0) === 1;
        $requiresClient = (int)($summary['requires_client'] ?? 0) === 1;

        $debitMode = 'CLIENT_411';
        $creditMode = 'SERVICE_706';
        $debitFixed = null;
        $creditFixed = null;
        $labelPattern = null;

        if ($requiresManual) {
            $debitMode = 'MANUAL_DEBIT';
            $creditMode = 'MANUAL_CREDIT';
        } else {
            if ($operationCode === 'VERSEMENT') {
                $debitMode = 'CLIENT_512';
                $creditMode = 'CLIENT_411';
            } elseif ($operationCode === 'REGULARISATION' && $serviceCode === 'POSITIVE') {
                $debitMode = 'CLIENT_512';
                $creditMode = 'CLIENT_411';
            } elseif ($operationCode === 'REGULARISATION' && $serviceCode === 'NEGATIVE') {
                $debitMode = 'CLIENT_411';
                $creditMode = 'CLIENT_512';
            } elseif ($operationCode === 'VIREMENT' && $serviceCode === 'INTERNE') {
                $debitMode = 'SOURCE_512';
                $creditMode = 'TARGET_512';
            } elseif ($direction === 'credit') {
                $debitMode = $requiresClient ? 'CLIENT_411' : ($treasuryAccount ? 'FIXED_ACCOUNT' : 'CLIENT_411');
                $creditMode = $serviceAccount ? 'SERVICE_706' : ($treasuryAccount ? 'FIXED_ACCOUNT' : 'SERVICE_706');

                if ($debitMode === 'FIXED_ACCOUNT') {
                    $debitFixed = (string)($treasuryAccount['account_code'] ?? '');
                }
                if ($creditMode === 'FIXED_ACCOUNT') {
                    $creditFixed = (string)($treasuryAccount['account_code'] ?? '');
                }
            } elseif ($direction === 'debit') {
                $debitMode = $requiresClient ? 'CLIENT_411' : ($treasuryAccount ? 'FIXED_ACCOUNT' : 'CLIENT_411');
                $creditMode = 'CLIENT_512';

                if ($debitMode === 'FIXED_ACCOUNT') {
                    $debitFixed = (string)($treasuryAccount['account_code'] ?? '');
                }
            }
        }

        $searchText = trim((string)($summary['service_account_search_text'] ?? ''));
        if ($searchText !== '' && $searchText !== '—') {
            $labelPattern = $searchText;
        } elseif ($serviceAccount && !empty($serviceAccount['account_label'])) {
            $labelPattern = (string)$serviceAccount['account_label'];
        } elseif ($serviceLabel !== '') {
            $labelPattern = $serviceLabel;
        }

        return [
            'rule_code' => esx_build_rule_code($operationCode, $serviceCode),
            'rule_label' => 'Règle auto ' . ($operationLabel !== '' ? $operationLabel : $operationCode) . ' / ' . ($serviceLabel !== '' ? $serviceLabel : $serviceCode),
            'debit_mode' => $debitMode,
            'credit_mode' => $creditMode,
            'debit_fixed_account_code' => $debitFixed,
            'credit_fixed_account_code' => $creditFixed,
            'requires_client' => $requiresClient ? 1 : 0,
            'requires_manual_accounts' => $requiresManual ? 1 : 0,
            'label_pattern' => $labelPattern,
            'search_text' => $searchText,
        ];
    }
}

$servicesTable = esx_services_table($pdo);
$operationTypesTable = esx_operation_types_table($pdo);

if (!$servicesTable || !$operationTypesTable) {
    exit('Tables services / types d’opérations incompatibles ou absentes.');
}

$serviceCodeCol = esx_service_code_column($pdo, $servicesTable);
$serviceLabelCol = esx_service_label_column($pdo, $servicesTable);
$opCodeCol = esx_operation_type_code_column($pdo, $operationTypesTable);
$opLabelCol = esx_operation_type_label_column($pdo, $operationTypesTable);
$operationTypeFk = esx_operation_type_fk_column($pdo, $servicesTable);
$serviceAccountFk = esx_service_account_fk_column($pdo, $servicesTable);
$treasuryAccountFk = esx_treasury_account_fk_column($pdo, $servicesTable);

$stmt = $pdo->prepare("
    SELECT *
    FROM {$servicesTable}
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Service introuvable.');
}

$operationTypes = $pdo->query("
    SELECT id,
           {$opCodeCol} AS code,
           {$opLabelCol} AS label
           " . (columnExists($pdo, $operationTypesTable, 'direction') ? ", direction" : ", 'mixed' AS direction") . "
           " . (columnExists($pdo, $operationTypesTable, 'is_active') ? ", is_active" : ", 1 AS is_active") . "
    FROM {$operationTypesTable}
    ORDER BY {$opLabelCol} ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$serviceAccounts = esx_fetch_service_accounts($pdo);
$treasuryAccounts = esx_fetch_treasury_accounts($pdo);

$successMessage = '';
$errorMessage = '';
$previewMode = false;

$formData = [
    'code' => (string)($row[$serviceCodeCol] ?? ''),
    'label' => (string)($row[$serviceLabelCol] ?? ''),
    'operation_type_id' => $operationTypeFk ? (string)($row[$operationTypeFk] ?? '') : '',
    'service_account_id' => $serviceAccountFk ? (string)($row[$serviceAccountFk] ?? '') : '',
    'treasury_account_id' => $treasuryAccountFk ? (string)($row[$treasuryAccountFk] ?? '') : '',
    'is_active' => (int)($row['is_active'] ?? 1),
    'auto_create_accounting_rule' => 0,
];

$previewData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'code' => strtoupper(trim((string)($_POST['code'] ?? ''))),
        'label' => trim((string)($_POST['label'] ?? '')),
        'operation_type_id' => (string)($_POST['operation_type_id'] ?? ''),
        'service_account_id' => (string)($_POST['service_account_id'] ?? ''),
        'treasury_account_id' => (string)($_POST['treasury_account_id'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'auto_create_accounting_rule' => isset($_POST['auto_create_accounting_rule']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['code'] === '' || $formData['label'] === '') {
            throw new RuntimeException('Le code et le libellé du service sont obligatoires.');
        }

        if ($formData['operation_type_id'] === '') {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $operationType = esx_find_row_by_id($operationTypes, (int)$formData['operation_type_id']);
        if (!$operationType) {
            throw new RuntimeException('Le type d’opération sélectionné est introuvable.');
        }

        $serviceAccount = null;
        if ($formData['service_account_id'] !== '') {
            $serviceAccount = esx_find_row_by_id($serviceAccounts, (int)$formData['service_account_id']);
            if (!$serviceAccount) {
                throw new RuntimeException('Le compte 706 sélectionné est introuvable.');
            }
            if ((int)($serviceAccount['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le compte 706 sélectionné est archivé.');
            }
            if ((int)($serviceAccount['is_postable'] ?? 0) !== 1) {
                throw new RuntimeException('Le compte 706 sélectionné n’est pas mouvementable.');
            }
        }

        $treasuryAccount = null;
        if ($formData['treasury_account_id'] !== '') {
            $treasuryAccount = esx_find_row_by_id($treasuryAccounts, (int)$formData['treasury_account_id']);
            if (!$treasuryAccount) {
                throw new RuntimeException('Le compte 512 sélectionné est introuvable.');
            }
            if ((int)($treasuryAccount['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le compte 512 sélectionné est archivé.');
            }
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM {$servicesTable}
            WHERE {$serviceCodeCol} = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDup->execute([$formData['code'], $id]);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code service existe déjà.');
        }

        $virtualService = [
            'id' => $id,
            'code' => $formData['code'],
            'label' => $formData['label'],
        ];

        $ruleSuggestion = esx_suggest_accounting_rule($operationType, $virtualService, $serviceAccount, $treasuryAccount);
        $existingRule = esx_find_existing_rule($pdo, (int)$formData['operation_type_id'], $id);

        $previewData = [
            'code' => $formData['code'],
            'label' => $formData['label'],
            'operation_type' => $operationType,
            'service_account' => $serviceAccount,
            'treasury_account' => $treasuryAccount,
            'is_active' => (int)$formData['is_active'],
            'rule_suggestion' => $ruleSuggestion,
            'existing_rule' => $existingRule,
        ];

        $previewMode = true;

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $fields = [];
            $params = [];

            $map = [
                $serviceCodeCol => $formData['code'],
                $serviceLabelCol => $formData['label'],
            ];

            if ($operationTypeFk) {
                $map[$operationTypeFk] = (int)$formData['operation_type_id'];
            }
            if ($serviceAccountFk) {
                $map[$serviceAccountFk] = $formData['service_account_id'] !== '' ? (int)$formData['service_account_id'] : null;
            }
            if ($treasuryAccountFk) {
                $map[$treasuryAccountFk] = $formData['treasury_account_id'] !== '' ? (int)$formData['treasury_account_id'] : null;
            }
            if (columnExists($pdo, $servicesTable, 'is_active')) {
                $map['is_active'] = (int)$formData['is_active'];
            }

            foreach ($map as $column => $value) {
                if (columnExists($pdo, $servicesTable, $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, $servicesTable, 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            $params[] = $id;

            $stmtUpdate = $pdo->prepare("
                UPDATE {$servicesTable}
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute($params);

            if (
                $formData['auto_create_accounting_rule'] === 1
                && tableExists($pdo, 'accounting_rules')
                && !$existingRule
            ) {
                $ruleColumns = [];
                $ruleValues = [];
                $ruleParams = [];

                $ruleMap = [
                    'operation_type_id' => (int)$formData['operation_type_id'],
                    'service_id' => $id,
                    'rule_code' => (string)$ruleSuggestion['rule_code'],
                    'rule_label' => (string)$ruleSuggestion['rule_label'],
                    'debit_mode' => (string)$ruleSuggestion['debit_mode'],
                    'credit_mode' => (string)$ruleSuggestion['credit_mode'],
                    'debit_fixed_account_code' => $ruleSuggestion['debit_fixed_account_code'] ?: null,
                    'credit_fixed_account_code' => $ruleSuggestion['credit_fixed_account_code'] ?: null,
                    'requires_client' => (int)$ruleSuggestion['requires_client'],
                    'requires_manual_accounts' => (int)$ruleSuggestion['requires_manual_accounts'],
                    'label_pattern' => $ruleSuggestion['label_pattern'] ?: null,
                    'is_active' => 1,
                ];

                foreach ($ruleMap as $column => $value) {
                    if (columnExists($pdo, 'accounting_rules', $column)) {
                        $ruleColumns[] = $column;
                        $ruleValues[] = '?';
                        $ruleParams[] = $value;
                    }
                }

                if (columnExists($pdo, 'accounting_rules', 'created_at')) {
                    $ruleColumns[] = 'created_at';
                    $ruleValues[] = 'NOW()';
                }
                if (columnExists($pdo, 'accounting_rules', 'updated_at')) {
                    $ruleColumns[] = 'updated_at';
                    $ruleValues[] = 'NOW()';
                }

                $stmtRule = $pdo->prepare("
                    INSERT INTO accounting_rules (" . implode(', ', $ruleColumns) . ")
                    VALUES (" . implode(', ', $ruleValues) . ")
                ");
                $stmtRule->execute($ruleParams);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_service',
                    'admin_functional',
                    'service',
                    $id,
                    'Modification d’un service avec suggestion éventuelle de règle comptable'
                );
            }

            $pdo->commit();

            $successMessage = 'Service mis à jour.';
            if ($formData['auto_create_accounting_rule'] === 1 && !$existingRule) {
                $successMessage .= ' Règle comptable créée automatiquement.';
            }

            $previewMode = false;

            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;

            $formData = [
                'code' => (string)($row[$serviceCodeCol] ?? ''),
                'label' => (string)($row[$serviceLabelCol] ?? ''),
                'operation_type_id' => $operationTypeFk ? (string)($row[$operationTypeFk] ?? '') : '',
                'service_account_id' => $serviceAccountFk ? (string)($row[$serviceAccountFk] ?? '') : '',
                'treasury_account_id' => $treasuryAccountFk ? (string)($row[$treasuryAccountFk] ?? '') : '',
                'is_active' => (int)($row['is_active'] ?? 1),
                'auto_create_accounting_rule' => 0,
            ];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$currentOperationType = $operationTypeFk && !empty($row[$operationTypeFk]) ? esx_find_row_by_id($operationTypes, (int)$row[$operationTypeFk]) : null;
$current706 = $serviceAccountFk && !empty($row[$serviceAccountFk]) ? esx_find_row_by_id($serviceAccounts, (int)$row[$serviceAccountFk]) : null;
$current512 = $treasuryAccountFk && !empty($row[$treasuryAccountFk]) ? esx_find_row_by_id($treasuryAccounts, (int)$row[$treasuryAccountFk]) : null;
$currentRule = ($operationTypeFk && !empty($row[$operationTypeFk])) ? esx_find_existing_rule($pdo, (int)$row[$operationTypeFk], $id) : null;

$pageTitle = 'Modifier un service';
$pageSubtitle = 'Édition du service et suggestion automatique de règle comptable';
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
                            <label>Code service</label>
                            <input type="text" name="code" value="<?= e($formData['code']) ?>" required>
                        </div>

                        <div>
                            <label>Libellé service</label>
                            <input type="text" name="label" value="<?= e($formData['label']) ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $formData['operation_type_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(($item['label'] ?? '') . ' (' . ($item['code'] ?? '') . ')') ?><?= (int)($item['is_active'] ?? 1) !== 1 ? ' [archivé]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 706</label>
                            <select name="service_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($serviceAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $formData['service_account_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(($item['account_code'] ?? '') . ' - ' . ($item['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512</label>
                            <select name="treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $formData['treasury_account_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                        <?= e(($item['account_code'] ?? '') . ' - ' . ($item['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;align-items:flex-end; margin-top:16px;">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                            Service actif
                        </label>
                    </div>

                    <div style="margin-top:10px;">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" name="auto_create_accounting_rule" value="1" <?= (int)$formData['auto_create_accounting_rule'] === 1 ? 'checked' : '' ?>>
                            Créer automatiquement la règle comptable si absente
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title"><?= $previewMode ? 'Prévisualisation avant validation' : 'État actuel + règle' ?></h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Code service</span>
                        <strong><?= e($previewMode ? ($previewData['code'] ?? '') : (string)($row[$serviceCodeCol] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Libellé</span>
                        <strong><?= e($previewMode ? ($previewData['label'] ?? '') : (string)($row[$serviceLabelCol] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Type d’opération</span>
                        <strong>
                            <?= e($previewMode
                                ? trim((string)(($previewData['operation_type']['code'] ?? '') . ' - ' . ($previewData['operation_type']['label'] ?? '')))
                                : trim((string)(($currentOperationType['code'] ?? '') . ' - ' . ($currentOperationType['label'] ?? '')))) ?>
                        </strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Compte 706</span>
                        <strong>
                            <?= e($previewMode
                                ? ($previewData['service_account'] ? trim((string)(($previewData['service_account']['account_code'] ?? '') . ' - ' . ($previewData['service_account']['account_label'] ?? ''))) : 'Aucun')
                                : ($current706 ? trim((string)(($current706['account_code'] ?? '') . ' - ' . ($current706['account_label'] ?? ''))) : 'Aucun')) ?>
                        </strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Compte 512</span>
                        <strong>
                            <?= e($previewMode
                                ? ($previewData['treasury_account'] ? trim((string)(($previewData['treasury_account']['account_code'] ?? '') . ' - ' . ($previewData['treasury_account']['account_label'] ?? ''))) : 'Aucun')
                                : ($current512 ? trim((string)(($current512['account_code'] ?? '') . ' - ' . ($current512['account_label'] ?? ''))) : 'Aucun')) ?>
                        </strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Statut</span>
                        <strong><?= ((int)($previewMode ? ($previewData['is_active'] ?? 0) : ($row['is_active'] ?? 0)) === 1) ? 'Actif' : 'Archivé' ?></strong>
                    </div>
                </div>

                <div style="margin-top:18px;">
                    <h4 style="margin:0 0 10px;">Règle comptable</h4>

                    <?php
                    $ruleDisplay = $previewMode
                        ? ($previewData['rule_suggestion'] ?? null)
                        : ($currentOperationType ? esx_suggest_accounting_rule(
                            $currentOperationType,
                            ['id' => $id, 'code' => (string)($row[$serviceCodeCol] ?? ''), 'label' => (string)($row[$serviceLabelCol] ?? '')],
                            $current706,
                            $current512
                        ) : null);

                    $existingRuleDisplay = $previewMode ? ($previewData['existing_rule'] ?? null) : $currentRule;
                    ?>

                    <?php if ($ruleDisplay): ?>
                        <div class="sl-data-list">
                            <div class="sl-data-list__row"><span>Règle existante</span><strong><?= $existingRuleDisplay ? 'Oui (#' . (int)$existingRuleDisplay['id'] . ')' : 'Non' ?></strong></div>
                            <div class="sl-data-list__row"><span>Code règle suggéré</span><strong><?= e((string)($ruleDisplay['rule_code'] ?? '')) ?></strong></div>
                            <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($ruleDisplay['rule_label'] ?? '')) ?></strong></div>
                            <div class="sl-data-list__row"><span>Débit</span><strong><?= e((string)($ruleDisplay['debit_mode'] ?? '')) ?></strong></div>
                            <div class="sl-data-list__row"><span>Crédit</span><strong><?= e((string)($ruleDisplay['credit_mode'] ?? '')) ?></strong></div>
                            <div class="sl-data-list__row"><span>Client requis</span><strong><?= (int)($ruleDisplay['requires_client'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
                            <div class="sl-data-list__row"><span>Comptes manuels requis</span><strong><?= (int)($ruleDisplay['requires_manual_accounts'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
                            <div class="sl-data-list__row"><span>Pattern label</span><strong><?= e((string)($ruleDisplay['label_pattern'] ?? '—')) ?></strong></div>
                        </div>

                        <div class="btn-group" style="margin-top:14px;">
                            <?php if ($existingRuleDisplay): ?>
                                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_edit.php?id=<?= (int)$existingRuleDisplay['id'] ?>">Ouvrir la règle</a>
                            <?php else: ?>
                                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_create.php?service_id=<?= (int)$id ?>&operation_type_id=<?= (int)($previewMode ? ($previewData['operation_type']['id'] ?? 0) : ($currentOperationType['id'] ?? 0)) ?>">Créer manuellement la règle</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="dashboard-note">Aucune suggestion disponible.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>