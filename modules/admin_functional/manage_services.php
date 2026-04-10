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

if (!tableExists($pdo, 'ref_services')) {
    exit('Table ref_services introuvable.');
}

$pageTitle = 'Gérer les services';
$pageSubtitle = 'Création, rattachement comptable et suggestion automatique de règles';

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

/* =========================================================
   Helpers compatibilité ref_/legacy
========================================================= */

if (!function_exists('sl_ms_table')) {
    function sl_ms_table(PDO $pdo, string $preferred, string $fallback): ?string
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

if (!function_exists('sl_ms_services_table')) {
    function sl_ms_services_table(PDO $pdo): ?string
    {
        return sl_ms_table($pdo, 'ref_services', 'services');
    }
}

if (!function_exists('sl_ms_operation_types_table')) {
    function sl_ms_operation_types_table(PDO $pdo): ?string
    {
        return sl_ms_table($pdo, 'ref_operation_types', 'operation_types');
    }
}

if (!function_exists('sl_ms_service_code_column')) {
    function sl_ms_service_code_column(PDO $pdo, string $table): string
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

if (!function_exists('sl_ms_service_label_column')) {
    function sl_ms_service_label_column(PDO $pdo, string $table): string
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

if (!function_exists('sl_ms_operation_type_code_column')) {
    function sl_ms_operation_type_code_column(PDO $pdo, string $table): string
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

if (!function_exists('sl_ms_operation_type_label_column')) {
    function sl_ms_operation_type_label_column(PDO $pdo, string $table): string
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

if (!function_exists('sl_ms_operation_type_fk_column')) {
    function sl_ms_operation_type_fk_column(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['operation_type_id', 'type_operation_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

if (!function_exists('sl_ms_service_account_fk_column')) {
    function sl_ms_service_account_fk_column(PDO $pdo, string $servicesTable): ?string
    {
        foreach (['service_account_id', 'account_706_id'] as $col) {
            if (columnExists($pdo, $servicesTable, $col)) {
                return $col;
            }
        }
        return null;
    }
}

if (!function_exists('sl_ms_treasury_account_fk_column')) {
    function sl_ms_treasury_account_fk_column(PDO $pdo, string $servicesTable): ?string
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
   Helpers métier
========================================================= */

if (!function_exists('sl_manage_service_value')) {
    function sl_manage_service_value(array $data, string $key, mixed $default = ''): string
    {
        return e((string)($data[$key] ?? $default));
    }
}

if (!function_exists('sl_manage_find_row_by_id')) {
    function sl_manage_find_row_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('ms_like')) {
    function ms_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

if (!function_exists('sl_ms_find_existing_rule')) {
    function sl_ms_find_existing_rule(PDO $pdo, int $operationTypeId, int $serviceId): ?array
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

if (!function_exists('sl_ms_build_rule_code')) {
    function sl_ms_build_rule_code(string $operationCode, string $serviceCode): string
    {
        $base = sl_normalize_code($operationCode . '_' . $serviceCode);
        return 'RULE_' . $base;
    }
}

if (!function_exists('sl_ms_suggest_accounting_rule')) {
    function sl_ms_suggest_accounting_rule(
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
            'rule_code' => sl_ms_build_rule_code($operationCode, $serviceCode),
            'rule_label' => 'Règle auto ' . ($operationLabel !== '' ? $operationLabel : $operationCode) . ' / ' . ($serviceLabel !== '' ? $serviceLabel : $serviceCode),
            'debit_mode' => $debitMode,
            'credit_mode' => $creditMode,
            'debit_fixed_account_code' => $debitFixed,
            'credit_fixed_account_code' => $creditFixed,
            'requires_client' => $requiresClient ? 1 : 0,
            'requires_manual_accounts' => $requiresManual ? 1 : 0,
            'label_pattern' => $labelPattern,
            'search_text' => $searchText,
            'direction' => $direction,
        ];
    }
}

if (!function_exists('sl_ms_rule_preview_rows')) {
    function sl_ms_rule_preview_rows(?array $ruleSuggestion, ?array $existingRule): array
    {
        if (!$ruleSuggestion) {
            return [];
        }

        return [
            'Règle existante' => $existingRule ? ('Oui (#' . (int)$existingRule['id'] . ')') : 'Non',
            'Code règle' => (string)($ruleSuggestion['rule_code'] ?? ''),
            'Libellé règle' => (string)($ruleSuggestion['rule_label'] ?? ''),
            'Débit' => (string)($ruleSuggestion['debit_mode'] ?? ''),
            'Crédit' => (string)($ruleSuggestion['credit_mode'] ?? ''),
            'Compte débit fixe' => (string)($ruleSuggestion['debit_fixed_account_code'] ?? '—'),
            'Compte crédit fixe' => (string)($ruleSuggestion['credit_fixed_account_code'] ?? '—'),
            'Client requis' => (int)($ruleSuggestion['requires_client'] ?? 0) === 1 ? 'Oui' : 'Non',
            'Comptes manuels requis' => (int)($ruleSuggestion['requires_manual_accounts'] ?? 0) === 1 ? 'Oui' : 'Non',
            'Pattern label' => (string)($ruleSuggestion['label_pattern'] ?? '—'),
            'Recherche 706' => (string)($ruleSuggestion['search_text'] ?? '—'),
        ];
    }
}

/* =========================================================
   Chargement référentiels
========================================================= */

$operationTypesTable = sl_ms_operation_types_table($pdo);
$servicesTable = sl_ms_services_table($pdo);

$operationTypes = [];
if ($operationTypesTable) {
    $opCodeCol = sl_ms_operation_type_code_column($pdo, $operationTypesTable);
    $opLabelCol = sl_ms_operation_type_label_column($pdo, $operationTypesTable);

    $sql = "
        SELECT id,
               {$opCodeCol} AS code,
               {$opLabelCol} AS label
               " . (columnExists($pdo, $operationTypesTable, 'direction') ? ", direction" : ", 'mixed' AS direction") . "
        FROM {$operationTypesTable}
        WHERE " . (columnExists($pdo, $operationTypesTable, 'is_active') ? "COALESCE(is_active,1)=1" : "1=1") . "
        ORDER BY {$opLabelCol} ASC
    ";
    $operationTypes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

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

$formData = [
    'code' => '',
    'label' => '',
    'operation_type_id' => '',
    'service_account_id' => '',
    'treasury_account_id' => '',
    'is_active' => 1,
    'auto_create_accounting_rule' => 0,
];

/* =========================================================
   POST create service + optional accounting rule
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'code' => strtoupper(trim((string)($_POST['code'] ?? ''))),
        'label' => trim((string)($_POST['label'] ?? '')),
        'operation_type_id' => trim((string)($_POST['operation_type_id'] ?? '')),
        'service_account_id' => trim((string)($_POST['service_account_id'] ?? '')),
        'treasury_account_id' => trim((string)($_POST['treasury_account_id'] ?? '')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'auto_create_accounting_rule' => isset($_POST['auto_create_accounting_rule']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['code'] === '') {
            throw new RuntimeException('Le code du service est obligatoire.');
        }

        if ($formData['label'] === '') {
            throw new RuntimeException('Le libellé du service est obligatoire.');
        }

        if ($formData['operation_type_id'] === '') {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        $operationType = sl_manage_find_row_by_id($operationTypes, (int)$formData['operation_type_id']);
        if (!$operationType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        $serviceAccount = null;
        if ($formData['service_account_id'] !== '') {
            $serviceAccount = sl_manage_find_row_by_id($serviceAccounts, (int)$formData['service_account_id']);
            if (!$serviceAccount) {
                throw new RuntimeException('Compte de service introuvable.');
            }
        }

        $treasuryAccount = null;
        if ($formData['treasury_account_id'] !== '') {
            $treasuryAccount = sl_manage_find_row_by_id($treasuryAccounts, (int)$formData['treasury_account_id']);
            if (!$treasuryAccount) {
                throw new RuntimeException('Compte de trésorerie introuvable.');
            }
        }

        if (!$servicesTable) {
            throw new RuntimeException('Aucune table service compatible trouvée.');
        }

        $serviceCodeCol = sl_ms_service_code_column($pdo, $servicesTable);
        $operationTypeFk = sl_ms_operation_type_fk_column($pdo, $servicesTable);

        $sqlCheck = "SELECT COUNT(*) FROM {$servicesTable} WHERE {$serviceCodeCol} = ?";
        $paramsCheck = [$formData['code']];

        if ($operationTypeFk) {
            $sqlCheck .= " AND {$operationTypeFk} = ?";
            $paramsCheck[] = (int)$formData['operation_type_id'];
        }

        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($paramsCheck);

        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Ce code service existe déjà pour ce type d’opération.');
        }

        $servicePreview = [
            'code' => $formData['code'],
            'label' => $formData['label'],
            'operation_type_label' => (string)($operationType['label'] ?? ''),
            'operation_type_code' => (string)($operationType['code'] ?? ''),
            'service_account' => $serviceAccount ? (($serviceAccount['account_code'] ?? '') . ' - ' . ($serviceAccount['account_label'] ?? '')) : '',
            'treasury_account' => $treasuryAccount ? (($treasuryAccount['account_code'] ?? '') . ' - ' . ($treasuryAccount['account_label'] ?? '')) : '',
            'is_active' => (int)$formData['is_active'],
        ];

        $virtualService = [
            'id' => 0,
            'code' => $formData['code'],
            'label' => $formData['label'],
        ];

        $ruleSuggestion = sl_ms_suggest_accounting_rule($operationType, $virtualService, $serviceAccount, $treasuryAccount);
        $existingRule = null;

        $previewData = [
            'service' => $servicePreview,
            'rule_suggestion' => $ruleSuggestion,
            'existing_rule' => $existingRule,
        ];
        $previewMode = true;

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $columns = [];
            $values = [];
            $params = [];

            $map = [
                $serviceCodeCol => $formData['code'],
                sl_ms_service_label_column($pdo, $servicesTable) => $formData['label'],
            ];

            $serviceAccountFk = sl_ms_service_account_fk_column($pdo, $servicesTable);
            $treasuryAccountFk = sl_ms_treasury_account_fk_column($pdo, $servicesTable);

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
                $map['is_active'] = $formData['is_active'];
            }

            foreach ($map as $column => $value) {
                if (columnExists($pdo, $servicesTable, $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, $servicesTable, 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }

            if (columnExists($pdo, $servicesTable, 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO {$servicesTable} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmtInsert->execute($params);

            $newServiceId = (int)$pdo->lastInsertId();

            if (
                $formData['auto_create_accounting_rule'] === 1
                && tableExists($pdo, 'accounting_rules')
                && !sl_ms_find_existing_rule($pdo, (int)$formData['operation_type_id'], $newServiceId)
            ) {
                $ruleColumns = [];
                $ruleValues = [];
                $ruleParams = [];

                $ruleMap = [
                    'operation_type_id' => (int)$formData['operation_type_id'],
                    'service_id' => $newServiceId,
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
                    'create_service',
                    'admin_functional',
                    'service',
                    $newServiceId,
                    'Création d’un service avec suggestion éventuelle de règle comptable'
                );
            }

            $pdo->commit();

            $successMessage = 'Service créé avec succès.';
            if ($formData['auto_create_accounting_rule'] === 1) {
                $successMessage .= ' Règle comptable créée automatiquement si absente.';
            }

            $previewMode = false;
            $previewData = null;

            $formData = [
                'code' => '',
                'label' => '',
                'operation_type_id' => '',
                'service_account_id' => '',
                'treasury_account_id' => '',
                'is_active' => 1,
                'auto_create_accounting_rule' => 0,
            ];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
        $previewMode = false;
        $previewData = null;
    }
}

/* =========================================================
   Listing + filtres
========================================================= */

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterTypeId = trim((string)($_GET['filter_type_id'] ?? ''));
$filter706Id = trim((string)($_GET['filter_706_id'] ?? ''));
$filter512Id = trim((string)($_GET['filter_512_id'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));

$rows = [];

if ($servicesTable && $operationTypesTable) {
    $serviceCodeCol = sl_ms_service_code_column($pdo, $servicesTable);
    $serviceLabelCol = sl_ms_service_label_column($pdo, $servicesTable);
    $opCodeCol = sl_ms_operation_type_code_column($pdo, $operationTypesTable);
    $opLabelCol = sl_ms_operation_type_label_column($pdo, $operationTypesTable);
    $operationTypeFk = sl_ms_operation_type_fk_column($pdo, $servicesTable);
    $serviceAccountFk = sl_ms_service_account_fk_column($pdo, $servicesTable);
    $treasuryAccountFk = sl_ms_treasury_account_fk_column($pdo, $servicesTable);

    $sqlRows = "
        SELECT
            rs.*,
            " . ($operationTypeFk ? "rot.{$opLabelCol}" : "NULL") . " AS operation_type_label,
            " . ($operationTypeFk ? "rot.{$opCodeCol}" : "NULL") . " AS operation_type_code,
            " . ($serviceAccountFk ? "sa.account_code" : "NULL") . " AS service_account_code,
            " . ($serviceAccountFk ? "sa.account_label" : "NULL") . " AS service_account_label,
            " . ($treasuryAccountFk ? "ta.account_code" : "NULL") . " AS treasury_account_code,
            " . ($treasuryAccountFk ? "ta.account_label" : "NULL") . " AS treasury_account_label
        FROM {$servicesTable} rs
        " . ($operationTypeFk ? "LEFT JOIN {$operationTypesTable} rot ON rot.id = rs.{$operationTypeFk}" : "") . "
        " . ($serviceAccountFk ? "LEFT JOIN service_accounts sa ON sa.id = rs.{$serviceAccountFk}" : "") . "
        " . ($treasuryAccountFk ? "LEFT JOIN treasury_accounts ta ON ta.id = rs.{$treasuryAccountFk}" : "") . "
        WHERE 1=1
    ";
    $paramsRows = [];

    if ($filterSearch !== '') {
        $sqlRows .= "
            AND (
                rs.{$serviceCodeCol} LIKE ?
                OR rs.{$serviceLabelCol} LIKE ?
                " . ($operationTypeFk ? "OR COALESCE(rot.{$opLabelCol}, '') LIKE ? OR COALESCE(rot.{$opCodeCol}, '') LIKE ?" : "") . "
                " . ($serviceAccountFk ? "OR COALESCE(sa.account_code, '') LIKE ? OR COALESCE(sa.account_label, '') LIKE ?" : "") . "
                " . ($treasuryAccountFk ? "OR COALESCE(ta.account_code, '') LIKE ? OR COALESCE(ta.account_label, '') LIKE ?" : "") . "
            )
        ";

        $paramsRows[] = ms_like($filterSearch);
        $paramsRows[] = ms_like($filterSearch);
        if ($operationTypeFk) {
            $paramsRows[] = ms_like($filterSearch);
            $paramsRows[] = ms_like($filterSearch);
        }
        if ($serviceAccountFk) {
            $paramsRows[] = ms_like($filterSearch);
            $paramsRows[] = ms_like($filterSearch);
        }
        if ($treasuryAccountFk) {
            $paramsRows[] = ms_like($filterSearch);
            $paramsRows[] = ms_like($filterSearch);
        }
    }

    if ($filterTypeId !== '' && $operationTypeFk) {
        $sqlRows .= " AND rs.{$operationTypeFk} = ? ";
        $paramsRows[] = (int)$filterTypeId;
    }

    if ($filter706Id !== '' && $serviceAccountFk) {
        $sqlRows .= " AND rs.{$serviceAccountFk} = ? ";
        $paramsRows[] = (int)$filter706Id;
    }

    if ($filter512Id !== '' && $treasuryAccountFk) {
        $sqlRows .= " AND rs.{$treasuryAccountFk} = ? ";
        $paramsRows[] = (int)$filter512Id;
    }

    if ($filterStatus === 'active' && columnExists($pdo, $servicesTable, 'is_active')) {
        $sqlRows .= " AND COALESCE(rs.is_active,1) = 1 ";
    } elseif ($filterStatus === 'inactive' && columnExists($pdo, $servicesTable, 'is_active')) {
        $sqlRows .= " AND COALESCE(rs.is_active,1) = 0 ";
    } elseif ($filterStatus === 'with_706' && $serviceAccountFk) {
        $sqlRows .= " AND rs.{$serviceAccountFk} IS NOT NULL ";
    } elseif ($filterStatus === 'with_512' && $treasuryAccountFk) {
        $sqlRows .= " AND rs.{$treasuryAccountFk} IS NOT NULL ";
    } elseif ($filterStatus === 'without_link') {
        if ($serviceAccountFk && $treasuryAccountFk) {
            $sqlRows .= " AND rs.{$serviceAccountFk} IS NULL AND rs.{$treasuryAccountFk} IS NULL ";
        }
    }

    $sqlRows .= " ORDER BY operation_type_label ASC, rs.{$serviceLabelCol} ASC, rs.id DESC ";

    $stmtRows = $pdo->prepare($sqlRows);
    $stmtRows->execute($paramsRows);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$dashboard = [
    'total' => count($rows),
    'active' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'with_706' => count(array_filter($rows, fn($r) => !empty($r['service_account_code']))),
    'with_512' => count(array_filter($rows, fn($r) => !empty($r['treasury_account_code']))),
    'without_link' => count(array_filter($rows, fn($r) => empty($r['service_account_code']) && empty($r['treasury_account_code']))),
];

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

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Dashboard services</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actifs</span><strong><?= (int)$dashboard['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactifs</span><strong><?= (int)$dashboard['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Liés à un 706</span><strong><?= (int)$dashboard['with_706'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Liés à un 512</span><strong><?= (int)$dashboard['with_512'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Sans liaison</span><strong><?= (int)$dashboard['without_link'] ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Prévisualisation création + règle</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list" style="margin-bottom:16px;">
                        <div class="sl-data-list__row"><span>Code</span><strong><?= e($previewData['service']['code'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($previewData['service']['label'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Type d’opération</span><strong><?= e($previewData['service']['operation_type_label'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Code type</span><strong><?= e($previewData['service']['operation_type_code'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 706</span><strong><?= e(($previewData['service']['service_account'] ?? '') !== '' ? $previewData['service']['service_account'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 512</span><strong><?= e(($previewData['service']['treasury_account'] ?? '') !== '' ? $previewData['service']['treasury_account'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)($previewData['service']['is_active'] ?? 0) === 1 ? 'Actif' : 'Inactif' ?></strong></div>
                    </div>

                    <h4 style="margin:0 0 12px;">Suggestion automatique de règle</h4>
                    <div class="sl-data-list">
                        <?php foreach (sl_ms_rule_preview_rows($previewData['rule_suggestion'] ?? null, $previewData['existing_rule'] ?? null) as $label => $value): ?>
                            <div class="sl-data-list__row">
                                <span><?= e($label) ?></span>
                                <strong><?= e((string)$value) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        La prévisualisation affiche à la fois le futur service et la règle comptable suggérée avant validation.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code service</label>
                            <input type="text" name="code" value="<?= sl_manage_service_value($formData, 'code') ?>" required>
                        </div>

                        <div>
                            <label>Libellé service</label>
                            <input type="text" name="label" value="<?= sl_manage_service_value($formData, 'label') ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= (string)($formData['operation_type_id'] ?? '') === (string)$type['id'] ? 'selected' : '' ?>>
                                        <?= e(($type['label'] ?? '') . ' (' . ($type['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 706 lié</label>
                            <select name="service_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($serviceAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['service_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512 lié</label>
                            <select name="treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['treasury_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            Service actif
                        </label>
                    </div>

                    <div style="margin-top:10px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="auto_create_accounting_rule" value="1" <?= (int)($formData['auto_create_accounting_rule'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Créer automatiquement la règle comptable si absente
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                    </div>
                </form>
            </div>

            <div class="form-card">
                <h3 class="section-title">Filtres liste services</h3>
                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input type="text" name="filter_search" value="<?= e($filterSearch) ?>" placeholder="Code, libellé, type, compte...">
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="filter_type_id">
                                <option value="">Tous</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= $filterTypeId === (string)$type['id'] ? 'selected' : '' ?>>
                                        <?= e(($type['label'] ?? '') . ' (' . ($type['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 706</label>
                            <select name="filter_706_id">
                                <option value="">Tous</option>
                                <?php foreach ($serviceAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= $filter706Id === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512</label>
                            <select name="filter_512_id">
                                <option value="">Tous</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= $filter512Id === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Statut / liaison</label>
                            <select name="filter_status">
                                <option value="">Tous</option>
                                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                                <option value="with_706" <?= $filterStatus === 'with_706' ? 'selected' : '' ?>>Avec 706</option>
                                <option value="with_512" <?= $filterStatus === 'with_512' ? 'selected' : '' ?>>Avec 512</option>
                                <option value="without_link" <?= $filterStatus === 'without_link' ? 'selected' : '' ?>>Sans liaison</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Liste des services</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Type opération</th>
                            <th>Compte 706</th>
                            <th>Compte 512</th>
                            <th>Statut</th>
                            <th>Règle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $existingRule = null;
                            if (tableExists($pdo, 'accounting_rules') && !empty($row['operation_type_id']) && !empty($row['id'])) {
                                $existingRule = sl_ms_find_existing_rule($pdo, (int)$row['operation_type_id'], (int)$row['id']);
                            }
                            ?>
                            <tr>
                                <td><?= e((string)($row['code'] ?? '')) ?></td>
                                <td><?= e((string)($row['label'] ?? '')) ?></td>
                                <td><?= e((string)($row['operation_type_label'] ?? '')) ?></td>
                                <td><?= e(trim((string)($row['service_account_code'] ?? '') . ' - ' . (string)($row['service_account_label'] ?? ''))) ?></td>
                                <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                                <td><?= !empty($row['is_active']) ? 'Actif' : 'Inactif' ?></td>
                                <td><?= $existingRule ? 'Oui' : 'Non' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/edit_service.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                        <?php if ($existingRule): ?>
                                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_edit.php?id=<?= (int)$existingRule['id'] ?>">Règle</a>
                                        <?php else: ?>
                                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_create.php?service_id=<?= (int)$row['id'] ?>&operation_type_id=<?= (int)($row['operation_type_id'] ?? 0) ?>">Créer règle</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="8">Aucun service trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>