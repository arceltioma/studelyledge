<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'edit_operation_type');
}
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Type invalide.');
}

if (!function_exists('eot_table_name')) {
    function eot_table_name(PDO $pdo, string $preferred, string $fallback): string
    {
        if (tableExists($pdo, $preferred)) {
            return $preferred;
        }
        return $fallback;
    }
}

if (!function_exists('eot_fetch_services')) {
    function eot_fetch_services(PDO $pdo): array
    {
        $serviceTable = eot_table_name($pdo, 'ref_services', 'services');
        if (!tableExists($pdo, $serviceTable)) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label, operation_type_id, service_account_id, treasury_account_id, is_active
            FROM {$serviceTable}
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('eot_fetch_service_accounts')) {
    function eot_fetch_service_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return [];
        }

        $hasParent = false;
        $hasSort = false;

        try {
            $columns = $pdo->query("SHOW COLUMNS FROM service_accounts")->fetchAll(PDO::FETCH_ASSOC);
            $names = array_map(static fn($c) => $c['Field'], $columns);
            $hasParent = in_array('parent_account_id', $names, true);
            $hasSort = in_array('sort_order', $names, true);
        } catch (Throwable $e) {
        }

        $sql = "
            SELECT
                sa.id,
                sa.account_code,
                sa.account_label,
                sa.operation_type_label,
                sa.destination_country_label,
                sa.commercial_country_label,
                sa.is_postable,
                sa.is_active
        ";

        if ($hasParent) {
            $sql .= ",
                sa.parent_account_id,
                p.account_code AS parent_account_code,
                p.account_label AS parent_account_label
            ";
        } else {
            $sql .= ",
                NULL AS parent_account_id,
                NULL AS parent_account_code,
                NULL AS parent_account_label
            ";
        }

        if ($hasSort) {
            $sql .= ", sa.sort_order";
        } else {
            $sql .= ", 0 AS sort_order";
        }

        $sql .= "
            FROM service_accounts sa
        ";

        if ($hasParent) {
            $sql .= " LEFT JOIN service_accounts p ON p.id = sa.parent_account_id ";
        }

        $sql .= "
            WHERE COALESCE(sa.is_active,1) = 1
              AND COALESCE(sa.is_postable,0) = 1
            ORDER BY COALESCE(sa.sort_order,0) ASC, sa.account_code ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('eot_fetch_treasury_accounts')) {
    function eot_fetch_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        return $pdo->query("
            SELECT id, account_code, account_label, is_active
            FROM treasury_accounts
            WHERE COALESCE(is_active,1) = 1
            ORDER BY account_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('eot_service_account_display')) {
    function eot_service_account_display(array $account): string
    {
        $parts = [];
        $parts[] = $account['account_code'] ?? '';
        $parts[] = $account['account_label'] ?? '';

        $meta = [];
        if (!empty($account['parent_account_code']) || !empty($account['parent_account_label'])) {
            $meta[] = 'Parent: ' . trim((string)($account['parent_account_code'] ?? '') . ' ' . (string)($account['parent_account_label'] ?? ''));
        }
        if (!empty($account['operation_type_label'])) {
            $meta[] = (string)$account['operation_type_label'];
        }
        if (!empty($account['destination_country_label'])) {
            $meta[] = 'Destination: ' . (string)$account['destination_country_label'];
        }
        if (!empty($account['commercial_country_label'])) {
            $meta[] = 'Commercial: ' . (string)$account['commercial_country_label'];
        }

        $label = trim(implode(' - ', array_filter($parts)));
        if ($meta) {
            $label .= ' [' . implode(' | ', $meta) . ']';
        }

        return $label;
    }
}

if (!function_exists('eot_find_service_account')) {
    function eot_find_service_account(PDO $pdo, ?int $serviceAccountId): ?array
    {
        if ($serviceAccountId === null || $serviceAccountId <= 0 || !tableExists($pdo, 'service_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                sa.id,
                sa.account_code,
                sa.account_label,
                sa.operation_type_label,
                sa.destination_country_label,
                sa.commercial_country_label,
                sa.is_postable,
                sa.is_active
            FROM service_accounts sa
            WHERE sa.id = ?
            LIMIT 1
        ");
        $stmt->execute([$serviceAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('eot_find_treasury_account')) {
    function eot_find_treasury_account(PDO $pdo, ?int $treasuryAccountId): ?array
    {
        if ($treasuryAccountId === null || $treasuryAccountId <= 0 || !tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT id, account_code, account_label, is_active
            FROM treasury_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$treasuryAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('eot_find_existing_rule')) {
    function eot_find_existing_rule(PDO $pdo, int $operationTypeId, int $serviceId): ?array
    {
        if ($operationTypeId <= 0 || $serviceId <= 0 || !tableExists($pdo, 'accounting_rules')) {
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

if (!function_exists('eot_normalize_code')) {
    function eot_normalize_code(?string $value): string
    {
        $value = strtoupper(trim((string)$value));
        $value = str_replace(
            ['É','È','Ê','Ë','À','Â','Ä','Î','Ï','Ô','Ö','Ù','Û','Ü','Ç',' ', '-', '/', '\''],
            ['E','E','E','E','A','A','A','I','I','O','O','U','U','U','C','_','_','_',''],
            $value
        );
        $value = preg_replace('/[^A-Z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('eot_suggest_accounting_rule')) {
    function eot_suggest_accounting_rule(string $operationTypeCode, string $serviceCode, string $direction = 'mixed'): ?array
    {
        $typeCode = eot_normalize_code($operationTypeCode);
        $serviceCode = eot_normalize_code($serviceCode);
        $direction = in_array($direction, ['credit', 'debit', 'mixed'], true) ? $direction : 'mixed';

        if ($typeCode === '' || $serviceCode === '') {
            return null;
        }

        $suggestion = [
            'rule_code' => substr('RULE_' . $typeCode . '_' . $serviceCode, 0, 190),
            'rule_label' => 'Règle ' . $typeCode . ' / ' . $serviceCode,
            'debit_mode' => '',
            'credit_mode' => '',
            'requires_client' => 1,
            'requires_manual_accounts' => 0,
            'label_pattern' => null,
            'explanation' => '',
        ];

        switch ($typeCode . '::' . $serviceCode) {
            case 'VERSEMENT::VERSEMENT':
                $suggestion['debit_mode'] = 'CLIENT_512';
                $suggestion['credit_mode'] = 'CLIENT_411';
                $suggestion['explanation'] = 'Versement : débit 512 client, crédit 411 client.';
                return $suggestion;

            case 'REGULARISATION::POSITIVE':
                $suggestion['debit_mode'] = 'CLIENT_512';
                $suggestion['credit_mode'] = 'CLIENT_411';
                $suggestion['explanation'] = 'Régularisation positive.';
                return $suggestion;

            case 'REGULARISATION::NEGATIVE':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'CLIENT_512';
                $suggestion['explanation'] = 'Régularisation négative.';
                return $suggestion;

            case 'VIREMENT::INTERNE':
                $suggestion['debit_mode'] = 'SOURCE_512';
                $suggestion['credit_mode'] = 'TARGET_512';
                $suggestion['requires_client'] = 0;
                $suggestion['requires_manual_accounts'] = 0;
                $suggestion['explanation'] = 'Virement interne entre deux comptes 512.';
                return $suggestion;

            case 'VIREMENT::MENSUEL':
            case 'VIREMENT::REGULIER':
            case 'VIREMENT::EXCEPTIONEL':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'CLIENT_512';
                $suggestion['explanation'] = 'Virement client sortant.';
                return $suggestion;

            case 'FRAIS_SERVICE::AVI':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'SERVICE_706';
                $suggestion['label_pattern'] = 'AVI';
                $suggestion['explanation'] = 'Frais AVI : recherche 706 par libellé AVI.';
                return $suggestion;

            case 'FRAIS_SERVICE::ATS':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'SERVICE_706';
                $suggestion['label_pattern'] = 'ATS';
                $suggestion['explanation'] = 'Frais ATS : recherche 706 par libellé ATS.';
                return $suggestion;

            case 'FRAIS_GESTION::GESTION':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'SERVICE_706';
                $suggestion['label_pattern'] = 'GESTION';
                $suggestion['explanation'] = 'Frais de gestion.';
                return $suggestion;

            case 'COMMISSION_DE_TRANSFERT::COMMISSION_DE_TRANSFERT':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'SERVICE_706';
                $suggestion['label_pattern'] = 'TRANSFERT';
                $suggestion['explanation'] = 'Commission de transfert.';
                return $suggestion;

            case 'CA_PLACEMENT::CA_PLACEMENT':
                $suggestion['debit_mode'] = 'CLIENT_411';
                $suggestion['credit_mode'] = 'SERVICE_706';
                $suggestion['label_pattern'] = 'CA PLACEMENT';
                $suggestion['explanation'] = 'CA placement.';
                return $suggestion;

            case 'CA_DIVERS::CA_DIVERS':
            case 'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE':
            case 'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE':
            case 'CA_COURTAGE_PRET::CA_COURTAGE_PRET':
            case 'CA_LOGEMENT::CA_LOGEMENT':
                $suggestion['debit_mode'] = 'MANUAL_DEBIT';
                $suggestion['credit_mode'] = 'MANUAL_CREDIT';
                $suggestion['requires_manual_accounts'] = 1;
                $suggestion['explanation'] = 'Cas manuel.';
                return $suggestion;
        }

        if ($direction === 'credit') {
            $suggestion['debit_mode'] = 'CLIENT_411';
            $suggestion['credit_mode'] = 'SERVICE_706';
            $suggestion['explanation'] = 'Suggestion générique produit.';
            return $suggestion;
        }

        if ($direction === 'debit') {
            $suggestion['debit_mode'] = 'CLIENT_411';
            $suggestion['credit_mode'] = 'CLIENT_512';
            $suggestion['explanation'] = 'Suggestion générique sortie.';
            return $suggestion;
        }

        return null;
    }
}

if (!function_exists('eot_insert_accounting_rule_if_missing')) {
    function eot_insert_accounting_rule_if_missing(PDO $pdo, int $operationTypeId, int $serviceId, array $suggestion): ?int
    {
        if (!tableExists($pdo, 'accounting_rules')) {
            return null;
        }

        $existing = eot_find_existing_rule($pdo, $operationTypeId, $serviceId);
        if ($existing) {
            return (int)($existing['id'] ?? 0);
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'operation_type_id' => $operationTypeId,
            'service_id' => $serviceId,
            'rule_code' => (string)($suggestion['rule_code'] ?? ''),
            'rule_label' => (string)($suggestion['rule_label'] ?? ''),
            'debit_mode' => (string)($suggestion['debit_mode'] ?? ''),
            'credit_mode' => (string)($suggestion['credit_mode'] ?? ''),
            'requires_client' => (int)($suggestion['requires_client'] ?? 0),
            'requires_manual_accounts' => (int)($suggestion['requires_manual_accounts'] ?? 0),
            'label_pattern' => $suggestion['label_pattern'] ?? null,
            'is_active' => 1,
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

        $stmt = $pdo->prepare("
            INSERT INTO accounting_rules (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}

$operationTypeTable = 'ref_operation_types';
$serviceTable = eot_table_name($pdo, 'ref_services', 'services');

$stmt = $pdo->prepare("
    SELECT *
    FROM {$operationTypeTable}
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Type introuvable.');
}

$allServices = eot_fetch_services($pdo);
$serviceAccounts = eot_fetch_service_accounts($pdo);
$treasuryAccounts = eot_fetch_treasury_accounts($pdo);

$successMessage = '';
$errorMessage = '';
$previewMode = false;

$formData = [
    'code' => (string)($row['code'] ?? ''),
    'label' => (string)($row['label'] ?? ''),
    'direction' => (string)($row['direction'] ?? 'mixed'),
    'is_active' => (int)($row['is_active'] ?? 1),
    'attach_service_ids' => [],
    'new_service_code' => ['', '', ''],
    'new_service_label' => ['', '', ''],
    'new_service_account_id' => ['', '', ''],
    'new_treasury_account_id' => ['', '', ''],
    'auto_create_accounting_rules' => 0,
];

$previewData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'code' => trim((string)($_POST['code'] ?? '')),
        'label' => trim((string)($_POST['label'] ?? '')),
        'direction' => trim((string)($_POST['direction'] ?? 'mixed')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'attach_service_ids' => array_values(array_filter(array_map('intval', $_POST['attach_service_ids'] ?? []), static fn($v) => $v > 0)),
        'new_service_code' => $_POST['new_service_code'] ?? ['', '', ''],
        'new_service_label' => $_POST['new_service_label'] ?? ['', '', ''],
        'new_service_account_id' => $_POST['new_service_account_id'] ?? ['', '', ''],
        'new_treasury_account_id' => $_POST['new_treasury_account_id'] ?? ['', '', ''],
        'auto_create_accounting_rules' => isset($_POST['auto_create_accounting_rules']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $code = strtoupper($formData['code']);
        $label = $formData['label'];
        $direction = in_array($formData['direction'], ['credit', 'debit', 'mixed'], true) ? $formData['direction'] : 'mixed';
        $isActive = (int)$formData['is_active'];
        $autoCreateRules = (int)$formData['auto_create_accounting_rules'];

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM {$operationTypeTable}
            WHERE code = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDup->execute([$code, $id]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code existe déjà.');
        }

        $attachServicesPreview = [];
        foreach ($formData['attach_service_ids'] as $serviceId) {
            $found = null;
            foreach ($allServices as $service) {
                if ((int)$service['id'] === (int)$serviceId) {
                    $found = $service;
                    break;
                }
            }

            if (!$found) {
                throw new RuntimeException('Un service à rattacher est introuvable.');
            }

            if ($isActive === 0 && (int)($found['is_active'] ?? 1) === 1) {
                throw new RuntimeException('Impossible de rattacher un service actif à un type d’opération archivé.');
            }

            $serviceCode = strtoupper(trim((string)($found['code'] ?? '')));
            $existingRule = eot_find_existing_rule($pdo, $id, (int)$serviceId);
            $suggestedRule = eot_suggest_accounting_rule($code, $serviceCode, $direction);

            $attachServicesPreview[] = [
                'id' => (int)$found['id'],
                'code' => $serviceCode,
                'label' => (string)($found['label'] ?? ''),
                'is_active' => (int)($found['is_active'] ?? 1),
                'existing_rule' => $existingRule,
                'suggested_rule' => $suggestedRule,
            ];
        }

        $newServicesPreview = [];
        $rowCount = max(
            count($formData['new_service_code']),
            count($formData['new_service_label']),
            count($formData['new_service_account_id']),
            count($formData['new_treasury_account_id'])
        );

        for ($i = 0; $i < $rowCount; $i++) {
            $serviceCode = strtoupper(trim((string)($formData['new_service_code'][$i] ?? '')));
            $serviceLabel = trim((string)($formData['new_service_label'][$i] ?? ''));
            $serviceAccountId = (($formData['new_service_account_id'][$i] ?? '') !== '') ? (int)$formData['new_service_account_id'][$i] : null;
            $treasuryAccountId = (($formData['new_treasury_account_id'][$i] ?? '') !== '') ? (int)$formData['new_treasury_account_id'][$i] : null;

            if ($serviceCode === '' && $serviceLabel === '' && $serviceAccountId === null && $treasuryAccountId === null) {
                continue;
            }

            if ($serviceCode === '' || $serviceLabel === '') {
                throw new RuntimeException('Chaque nouveau service doit avoir un code et un libellé.');
            }

            if ($serviceAccountId === null && $treasuryAccountId === null) {
                throw new RuntimeException('Chaque nouveau service doit être lié à un compte 706 ou 512.');
            }

            $stmtDupService = $pdo->prepare("
                SELECT id
                FROM {$serviceTable}
                WHERE code = ?
                LIMIT 1
            ");
            $stmtDupService->execute([$serviceCode]);
            if ($stmtDupService->fetch()) {
                throw new RuntimeException('Le code service "' . $serviceCode . '" existe déjà.');
            }

            $selected706 = eot_find_service_account($pdo, $serviceAccountId);
            if ($serviceAccountId !== null && !$selected706) {
                throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" est introuvable.');
            }

            if ($selected706) {
                if ((int)($selected706['is_active'] ?? 0) !== 1) {
                    throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" est archivé.');
                }

                if ((int)($selected706['is_postable'] ?? 0) !== 1) {
                    throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" n’est pas mouvementable.');
                }

                if (!empty($selected706['operation_type_label'])) {
                    $normalized706Type = strtoupper(trim((string)$selected706['operation_type_label']));
                    $normalizedType = strtoupper(trim((string)$code));

                    if ($normalized706Type !== $normalizedType) {
                        throw new RuntimeException('Le compte 706 sélectionné pour le service "' . $serviceCode . '" n’est pas cohérent avec le type d’opération.');
                    }
                }
            }

            $selected512 = eot_find_treasury_account($pdo, $treasuryAccountId);
            if ($treasuryAccountId !== null && !$selected512) {
                throw new RuntimeException('Le compte 512 sélectionné pour le service "' . $serviceCode . '" est introuvable.');
            }

            if ($selected512 && (int)($selected512['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le compte 512 sélectionné pour le service "' . $serviceCode . '" est archivé.');
            }

            $suggestedRule = eot_suggest_accounting_rule($code, $serviceCode, $direction);

            $newServicesPreview[] = [
                'code' => $serviceCode,
                'label' => $serviceLabel,
                'service_account' => $selected706,
                'treasury_account' => $selected512,
                'is_active' => $isActive,
                'suggested_rule' => $suggestedRule,
            ];
        }

        $previewData = [
            'code' => $code,
            'label' => $label,
            'direction' => $direction,
            'is_active' => $isActive,
            'auto_create_accounting_rules' => $autoCreateRules,
            'attach_services' => $attachServicesPreview,
            'new_services' => $newServicesPreview,
        ];

        $previewMode = true;

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("
                UPDATE {$operationTypeTable}
                SET
                    code = ?,
                    label = ?,
                    direction = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$code, $label, $direction, $isActive, $id]);

            $stmtAttach = $pdo->prepare("
                UPDATE {$serviceTable}
                SET operation_type_id = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($attachServicesPreview as $service) {
                $stmtAttach->execute([$id, (int)$service['id']]);

                if ($autoCreateRules === 1 && !$service['existing_rule'] && !empty($service['suggested_rule'])) {
                    eot_insert_accounting_rule_if_missing($pdo, $id, (int)$service['id'], $service['suggested_rule']);
                }
            }

            if ($newServicesPreview) {
                $stmtInsertService = $pdo->prepare("
                    INSERT INTO {$serviceTable} (
                        code, label, operation_type_id, service_account_id, treasury_account_id,
                        is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                foreach ($newServicesPreview as $newService) {
                    $stmtInsertService->execute([
                        $newService['code'],
                        $newService['label'],
                        $id,
                        $newService['service_account']['id'] ?? null,
                        $newService['treasury_account']['id'] ?? null,
                        $newService['is_active'],
                    ]);

                    $newServiceId = (int)$pdo->lastInsertId();

                    if ($autoCreateRules === 1 && !empty($newService['suggested_rule'])) {
                        eot_insert_accounting_rule_if_missing($pdo, $id, $newServiceId, $newService['suggested_rule']);
                    }
                }
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_operation_type',
                    'admin_functional',
                    'operation_type',
                    $id,
                    'Modification d’un type d’opération avec suggestions automatiques de règles comptables'
                );
            }

            $pdo->commit();
            $successMessage = 'Type d’opération mis à jour.';
            $previewMode = false;

            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;

            $allServices = eot_fetch_services($pdo);
            $serviceAccounts = eot_fetch_service_accounts($pdo);

            $formData = [
                'code' => (string)($row['code'] ?? ''),
                'label' => (string)($row['label'] ?? ''),
                'direction' => (string)($row['direction'] ?? 'mixed'),
                'is_active' => (int)($row['is_active'] ?? 1),
                'attach_service_ids' => [],
                'new_service_code' => ['', '', ''],
                'new_service_label' => ['', '', ''],
                'new_service_account_id' => ['', '', ''],
                'new_treasury_account_id' => ['', '', ''],
                'auto_create_accounting_rules' => 0,
            ];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$linkedServices = tableExists($pdo, $serviceTable)
    ? (function () use ($pdo, $id, $serviceTable) {
        $stmtLinked = $pdo->prepare("
            SELECT
                rs.*,
                sa.account_code AS service_account_code,
                sa.account_label AS service_account_label,
                ta.account_code AS treasury_account_code,
                ta.account_label AS treasury_account_label
            FROM {$serviceTable} rs
            LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
            LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
            WHERE rs.operation_type_id = ?
            ORDER BY rs.label ASC
        ");
        $stmtLinked->execute([$id]);
        return $stmtLinked->fetchAll(PDO::FETCH_ASSOC);
    })()
    : [];

$pageTitle = 'Modifier un type d’opération';
$pageSubtitle = 'Rattachement des services + suggestions automatiques de règles comptables.';
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
                            <label>Code</label>
                            <input type="text" name="code" value="<?= e($formData['code']) ?>" required>
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= e($formData['label']) ?>" required>
                        </div>
                    </div>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Direction</label>
                            <select name="direction">
                                <option value="mixed" <?= $formData['direction'] === 'mixed' ? 'selected' : '' ?>>Mixte</option>
                                <option value="credit" <?= $formData['direction'] === 'credit' ? 'selected' : '' ?>>Crédit</option>
                                <option value="debit" <?= $formData['direction'] === 'debit' ? 'selected' : '' ?>>Débit</option>
                            </select>
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                                Type actif
                            </label>
                        </div>
                    </div>

                    <div>
                        <label>Rattacher des services existants</label>
                        <select name="attach_service_ids[]" multiple size="8">
                            <?php foreach ($allServices as $service): ?>
                                <option value="<?= (int)$service['id'] ?>" <?= in_array((int)$service['id'], $formData['attach_service_ids'], true) ? 'selected' : '' ?>>
                                    <?= e(($service['code'] ?? '') . ' - ' . ($service['label'] ?? '')) ?><?= (int)($service['is_active'] ?? 1) !== 1 ? ' [archivé]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="table-card" style="margin-top:14px;">
                        <h3 class="section-title">Créer de nouveaux services liés à ce type</h3>

                        <table>
                            <thead>
                                <tr>
                                    <th>Code service</th>
                                    <th>Libellé service</th>
                                    <th>Compte 706 final</th>
                                    <th>Compte 512</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="new_service_code[]" value="<?= e((string)($formData['new_service_code'][$i] ?? '')) ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="new_service_label[]" value="<?= e((string)($formData['new_service_label'][$i] ?? '')) ?>">
                                        </td>
                                        <td>
                                            <select name="new_service_account_id[]">
                                                <option value="">Aucun</option>
                                                <?php foreach ($serviceAccounts as $account): ?>
                                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['new_service_account_id'][$i] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                                        <?= e(eot_service_account_display($account)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="new_treasury_account_id[]">
                                                <option value="">Aucun</option>
                                                <?php foreach ($treasuryAccounts as $account): ?>
                                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['new_treasury_account_id'][$i] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="auto_create_accounting_rules" value="1" <?= (int)($formData['auto_create_accounting_rules'] ?? 0) === 1 ? 'checked' : '' ?>>
                            Créer automatiquement les règles comptables manquantes quand une suggestion fiable existe
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="table-card">
                <h3 class="section-title"><?= $previewMode ? 'Prévisualisation avant validation' : 'Services déjà liés' ?></h3>

                <?php if ($previewMode): ?>
                    <div class="sl-data-list" style="margin-bottom:16px;">
                        <div class="sl-data-list__row"><span>Code</span><strong><?= e($previewData['code'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Libellé</span><strong><?= e($previewData['label'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Direction</span><strong><?= e($previewData['direction'] ?? '') ?></strong></div>
                        <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($previewData['is_active'] ?? 0) === 1) ? 'Actif' : 'Archivé' ?></strong></div>
                        <div class="sl-data-list__row"><span>Auto-règles</span><strong><?= ((int)($previewData['auto_create_accounting_rules'] ?? 0) === 1) ? 'Oui' : 'Non' ?></strong></div>
                    </div>

                    <h4 style="margin:16px 0 10px;">Services existants à rattacher</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Libellé</th>
                                <th>Règle existante</th>
                                <th>Suggestion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($previewData['attach_services'])): ?>
                                <?php foreach ($previewData['attach_services'] as $service): ?>
                                    <tr>
                                        <td><?= e($service['code'] ?? '') ?></td>
                                        <td><?= e($service['label'] ?? '') ?></td>
                                        <td>
                                            <?php if (!empty($service['existing_rule'])): ?>
                                                <?= e((string)($service['existing_rule']['rule_code'] ?? 'Règle existante')) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($service['suggested_rule'])): ?>
                                                <?= e((string)($service['suggested_rule']['debit_mode'] ?? '')) ?>
                                                →
                                                <?= e((string)($service['suggested_rule']['credit_mode'] ?? '')) ?>
                                            <?php else: ?>
                                                Aucune
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">Aucun rattachement supplémentaire.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h4 style="margin:16px 0 10px;">Nouveaux services à créer</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Libellé</th>
                                <th>706</th>
                                <th>512</th>
                                <th>Suggestion règle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($previewData['new_services'])): ?>
                                <?php foreach ($previewData['new_services'] as $service): ?>
                                    <tr>
                                        <td><?= e($service['code'] ?? '') ?></td>
                                        <td><?= e($service['label'] ?? '') ?></td>
                                        <td><?= e($service['service_account'] ? eot_service_account_display($service['service_account']) : 'Aucun') ?></td>
                                        <td><?= e(trim((string)(($service['treasury_account']['account_code'] ?? '') . ' - ' . ($service['treasury_account']['account_label'] ?? ''))) ?: 'Aucun') ?></td>
                                        <td>
                                            <?php if (!empty($service['suggested_rule'])): ?>
                                                <?= e((string)($service['suggested_rule']['debit_mode'] ?? '')) ?>
                                                →
                                                <?= e((string)($service['suggested_rule']['credit_mode'] ?? '')) ?>
                                            <?php else: ?>
                                                Aucune
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">Aucun nouveau service à créer.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Libellé</th>
                                <th>706</th>
                                <th>512</th>
                                <th>État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linkedServices as $service): ?>
                                <tr>
                                    <td><?= e($service['code'] ?? '') ?></td>
                                    <td><?= e($service['label'] ?? '') ?></td>
                                    <td><?= e(trim((string)($service['service_account_code'] ?? '') . ' - ' . (string)($service['service_account_label'] ?? ''))) ?></td>
                                    <td><?= e(trim((string)($service['treasury_account_code'] ?? '') . ' - ' . (string)($service['treasury_account_label'] ?? ''))) ?></td>
                                    <td><?= ((int)($service['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$linkedServices): ?>
                                <tr><td colspan="5">Aucun service rattaché.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>