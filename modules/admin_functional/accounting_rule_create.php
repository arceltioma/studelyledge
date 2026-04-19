<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'accounting_rule_create');
}

$pageTitle = 'Créer règle comptable';
$pageSubtitle = 'Création manuelle ou assistée par suggestion automatique';

if (!tableExists($pdo, 'accounting_rules')) {
    exit('Table accounting_rules introuvable.');
}

/* =========================================================
   Compatibilité ref_/legacy
========================================================= */

if (!function_exists('arc_table')) {
    function arc_table(PDO $pdo, string $preferred, string $fallback): ?string
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

if (!function_exists('arc_operation_types_table')) {
    function arc_operation_types_table(PDO $pdo): ?string
    {
        return arc_table($pdo, 'ref_operation_types', 'operation_types');
    }
}

if (!function_exists('arc_services_table')) {
    function arc_services_table(PDO $pdo): ?string
    {
        return arc_table($pdo, 'ref_services', 'services');
    }
}

if (!function_exists('arc_operation_type_code_col')) {
    function arc_operation_type_code_col(PDO $pdo, string $table): string
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

if (!function_exists('arc_operation_type_label_col')) {
    function arc_operation_type_label_col(PDO $pdo, string $table): string
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

if (!function_exists('arc_service_code_col')) {
    function arc_service_code_col(PDO $pdo, string $table): string
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

if (!function_exists('arc_service_label_col')) {
    function arc_service_label_col(PDO $pdo, string $table): string
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

if (!function_exists('arc_service_account_fk_col')) {
    function arc_service_account_fk_col(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['service_account_id', 'account_706_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

if (!function_exists('arc_treasury_account_fk_col')) {
    function arc_treasury_account_fk_col(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['treasury_account_id', 'account_512_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

/* =========================================================
   Helpers
========================================================= */

if (!function_exists('arc_find_row_by_id')) {
    function arc_find_row_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('arc_find_existing_rule')) {
    function arc_find_existing_rule(PDO $pdo, int $operationTypeId, int $serviceId): ?array
    {
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

if (!function_exists('arc_suggest_rule')) {
    function arc_suggest_rule(array $operationType, array $service, ?array $serviceAccount, ?array $treasuryAccount): array
    {
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
            'rule_code' => 'RULE_' . sl_normalize_code($operationCode . '_' . $serviceCode),
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

$operationTypesTable = arc_operation_types_table($pdo);
$servicesTable = arc_services_table($pdo);

if (!$operationTypesTable || !$servicesTable) {
    exit('Tables types d’opérations / services introuvables.');
}

$opCodeCol = arc_operation_type_code_col($pdo, $operationTypesTable);
$opLabelCol = arc_operation_type_label_col($pdo, $operationTypesTable);
$serviceCodeCol = arc_service_code_col($pdo, $servicesTable);
$serviceLabelCol = arc_service_label_col($pdo, $servicesTable);
$serviceAccountFk = arc_service_account_fk_col($pdo, $servicesTable);
$treasuryAccountFk = arc_treasury_account_fk_col($pdo, $servicesTable);

$operationTypes = $pdo->query("
    SELECT id,
           {$opCodeCol} AS code,
           {$opLabelCol} AS label
           " . (columnExists($pdo, $operationTypesTable, 'direction') ? ", direction" : ", 'mixed' AS direction") . "
    FROM {$operationTypesTable}
    ORDER BY {$opLabelCol} ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$services = $pdo->query("
    SELECT id,
           {$serviceCodeCol} AS code,
           {$serviceLabelCol} AS label
           " . ($serviceAccountFk ? ", {$serviceAccountFk} AS service_account_id" : ", NULL AS service_account_id") . "
           " . ($treasuryAccountFk ? ", {$treasuryAccountFk} AS treasury_account_id" : ", NULL AS treasury_account_id") . "
    FROM {$servicesTable}
    ORDER BY {$serviceLabelCol} ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM service_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$formData = [
    'operation_type_id' => (string)($_GET['operation_type_id'] ?? ''),
    'service_id' => (string)($_GET['service_id'] ?? ''),
    'rule_code' => '',
    'rule_label' => '',
    'debit_mode' => '',
    'credit_mode' => '',
    'debit_fixed_account_code' => '',
    'credit_fixed_account_code' => '',
    'label_pattern' => '',
    'requires_client' => 0,
    'requires_manual_accounts' => 0,
    'is_active' => 1,
];

if ($formData['operation_type_id'] !== '' && $formData['service_id'] !== '') {
    $selectedOperationType = arc_find_row_by_id($operationTypes, (int)$formData['operation_type_id']);
    $selectedService = arc_find_row_by_id($services, (int)$formData['service_id']);

    if ($selectedOperationType && $selectedService) {
        $serviceAccount = !empty($selectedService['service_account_id'])
            ? arc_find_row_by_id($serviceAccounts, (int)$selectedService['service_account_id'])
            : null;

        $treasuryAccount = !empty($selectedService['treasury_account_id'])
            ? arc_find_row_by_id($treasuryAccounts, (int)$selectedService['treasury_account_id'])
            : null;

        $suggestion = arc_suggest_rule($selectedOperationType, $selectedService, $serviceAccount, $treasuryAccount);

        $formData['rule_code'] = (string)($suggestion['rule_code'] ?? '');
        $formData['rule_label'] = (string)($suggestion['rule_label'] ?? '');
        $formData['debit_mode'] = (string)($suggestion['debit_mode'] ?? '');
        $formData['credit_mode'] = (string)($suggestion['credit_mode'] ?? '');
        $formData['debit_fixed_account_code'] = (string)($suggestion['debit_fixed_account_code'] ?? '');
        $formData['credit_fixed_account_code'] = (string)($suggestion['credit_fixed_account_code'] ?? '');
        $formData['label_pattern'] = (string)($suggestion['label_pattern'] ?? '');
        $formData['requires_client'] = (int)($suggestion['requires_client'] ?? 0);
        $formData['requires_manual_accounts'] = (int)($suggestion['requires_manual_accounts'] ?? 0);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'operation_type_id' => (string)($_POST['operation_type_id'] ?? ''),
        'service_id' => (string)($_POST['service_id'] ?? ''),
        'rule_code' => trim((string)($_POST['rule_code'] ?? '')),
        'rule_label' => trim((string)($_POST['rule_label'] ?? '')),
        'debit_mode' => trim((string)($_POST['debit_mode'] ?? '')),
        'credit_mode' => trim((string)($_POST['credit_mode'] ?? '')),
        'debit_fixed_account_code' => trim((string)($_POST['debit_fixed_account_code'] ?? '')),
        'credit_fixed_account_code' => trim((string)($_POST['credit_fixed_account_code'] ?? '')),
        'label_pattern' => trim((string)($_POST['label_pattern'] ?? '')),
        'requires_client' => isset($_POST['requires_client']) ? 1 : 0,
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

        if ($operationTypeId <= 0 || $serviceId <= 0) {
            throw new RuntimeException('Le type d’opération et le service sont obligatoires.');
        }

        if ($formData['rule_code'] === '') {
            throw new RuntimeException('Le code règle est obligatoire.');
        }

        if ($formData['debit_mode'] === '' || $formData['credit_mode'] === '') {
            throw new RuntimeException('Les modes débit et crédit sont obligatoires.');
        }

        $selectedOperationType = arc_find_row_by_id($operationTypes, $operationTypeId);
        $selectedService = arc_find_row_by_id($services, $serviceId);

        if (!$selectedOperationType || !$selectedService) {
            throw new RuntimeException('Type d’opération ou service introuvable.');
        }

        $existingRule = arc_find_existing_rule($pdo, $operationTypeId, $serviceId);
        if ($existingRule) {
            throw new RuntimeException('Une règle existe déjà pour ce couple type/service.');
        }

        $previewMode = true;
        $previewData = [
            'operation_type' => $selectedOperationType,
            'service' => $selectedService,
            'rule' => $formData,
        ];

        if ($actionMode === 'save') {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'operation_type_id' => $operationTypeId,
                'service_id' => $serviceId,
                'rule_code' => $formData['rule_code'],
                'rule_label' => $formData['rule_label'],
                'debit_mode' => $formData['debit_mode'],
                'credit_mode' => $formData['credit_mode'],
                'debit_fixed_account_code' => $formData['debit_fixed_account_code'] !== '' ? $formData['debit_fixed_account_code'] : null,
                'credit_fixed_account_code' => $formData['credit_fixed_account_code'] !== '' ? $formData['credit_fixed_account_code'] : null,
                'label_pattern' => $formData['label_pattern'] !== '' ? $formData['label_pattern'] : null,
                'requires_client' => $formData['requires_client'],
                'requires_manual_accounts' => $formData['requires_manual_accounts'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'accounting_rules', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'accounting_rules', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }
            if (columnExists($pdo, 'accounting_rules', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO accounting_rules (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmtInsert->execute($params);

            $newRuleId = (int)$pdo->lastInsertId();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_accounting_rule',
                    'admin_functional',
                    'accounting_rule',
                    $newRuleId,
                    'Création d’une règle comptable'
                );
            }

            $successMessage = 'Règle comptable créée avec succès.';
            $previewMode = false;
        }
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

<?php if ($successMessage !== ''): ?>
    <div class="success"><?= e($successMessage) ?></div>
<?php endif; ?>

<?php if ($errorMessage !== ''): ?>
    <div class="error"><?= e($errorMessage) ?></div>
<?php endif; ?>

<div class="dashboard-grid-2">
    <div class="card">
        <form method="POST">
            <?= csrf_input() ?>

            <div class="dashboard-grid-2">
                <div>
                    <label>Type opération</label>
                    <select name="operation_type_id" required>
                        <option value="">Choisir</option>
                        <?php foreach ($operationTypes as $ot): ?>
                            <option value="<?= (int)$ot['id'] ?>" <?= $formData['operation_type_id'] === (string)$ot['id'] ? 'selected' : '' ?>>
                                <?= e(($ot['label'] ?? '') . ' (' . ($ot['code'] ?? '') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Service</label>
                    <select name="service_id" required>
                        <option value="">Choisir</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= $formData['service_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                                <?= e(($s['label'] ?? '') . ' (' . ($s['code'] ?? '') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Code règle</label>
                    <input type="text" name="rule_code" value="<?= e($formData['rule_code']) ?>" required>
                </div>

                <div>
                    <label>Label</label>
                    <input type="text" name="rule_label" value="<?= e($formData['rule_label']) ?>">
                </div>

                <div>
                    <label>Mode débit</label>
                    <input type="text" name="debit_mode" value="<?= e($formData['debit_mode']) ?>" placeholder="CLIENT_411">
                </div>

                <div>
                    <label>Mode crédit</label>
                    <input type="text" name="credit_mode" value="<?= e($formData['credit_mode']) ?>" placeholder="SERVICE_706">
                </div>

                <div>
                    <label>Compte débit fixe</label>
                    <input type="text" name="debit_fixed_account_code" value="<?= e($formData['debit_fixed_account_code']) ?>" placeholder="512xxx si FIXED_ACCOUNT">
                </div>

                <div>
                    <label>Compte crédit fixe</label>
                    <input type="text" name="credit_fixed_account_code" value="<?= e($formData['credit_fixed_account_code']) ?>" placeholder="706xxx ou 512xxx si FIXED_ACCOUNT">
                </div>

                <div>
                    <label>Pattern label</label>
                    <input type="text" name="label_pattern" value="<?= e($formData['label_pattern']) ?>" placeholder="AVI / ATS / GESTION / ...">
                </div>
            </div>

            <div style="margin-top:16px;">
                <label><input type="checkbox" name="requires_client" <?= (int)$formData['requires_client'] === 1 ? 'checked' : '' ?>> Nécessite client</label>
            </div>

            <div style="margin-top:8px;">
                <label><input type="checkbox" name="requires_manual_accounts" <?= (int)$formData['requires_manual_accounts'] === 1 ? 'checked' : '' ?>> Comptes manuels</label>
            </div>

            <div style="margin-top:8px;">
                <label><input type="checkbox" name="is_active" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>> Actif</label>
            </div>

            <div class="btn-group" style="margin-top:20px;">
                <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php" class="btn btn-outline">Retour</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Prévisualisation / suggestion</h3>

        <?php if ($previewMode && $previewData): ?>
            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>Type opération</span><strong><?= e(($previewData['operation_type']['label'] ?? '') . ' (' . ($previewData['operation_type']['code'] ?? '') . ')') ?></strong></div>
                <div class="sl-data-list__row"><span>Service</span><strong><?= e(($previewData['service']['label'] ?? '') . ' (' . ($previewData['service']['code'] ?? '') . ')') ?></strong></div>
                <div class="sl-data-list__row"><span>Code règle</span><strong><?= e($previewData['rule']['rule_code'] ?? '') ?></strong></div>
                <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($previewData['rule']['rule_label'] ?? '') ?></strong></div>
                <div class="sl-data-list__row"><span>Débit</span><strong><?= e($previewData['rule']['debit_mode'] ?? '') ?></strong></div>
                <div class="sl-data-list__row"><span>Crédit</span><strong><?= e($previewData['rule']['credit_mode'] ?? '') ?></strong></div>
                <div class="sl-data-list__row"><span>Compte débit fixe</span><strong><?= e($previewData['rule']['debit_fixed_account_code'] !== '' ? $previewData['rule']['debit_fixed_account_code'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Compte crédit fixe</span><strong><?= e($previewData['rule']['credit_fixed_account_code'] !== '' ? $previewData['rule']['credit_fixed_account_code'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Pattern label</span><strong><?= e($previewData['rule']['label_pattern'] !== '' ? $previewData['rule']['label_pattern'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Client requis</span><strong><?= (int)($previewData['rule']['requires_client'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
                <div class="sl-data-list__row"><span>Comptes manuels</span><strong><?= (int)($previewData['rule']['requires_manual_accounts'] ?? 0) === 1 ? 'Oui' : 'Non' ?></strong></div>
            </div>
        <?php else: ?>
            <div class="dashboard-note">
                Sélectionne un type d’opération et un service pour prévisualiser la règle comptable avant création.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>