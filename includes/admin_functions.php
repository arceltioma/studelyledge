<?php

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tableName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('columnExists')) {
    function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('render_app_header_bar')) {
    function render_app_header_bar(string $title, string $subtitle = ''): void
    {
        echo '<div class="page-hero">';
        echo '<div>';
        echo '<h1>' . e($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="muted">' . e($subtitle) . '</p>';
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('currentUserCan')) {
    function currentUserCan(PDO $pdo, string $permissionCode): bool
    {
        return true;
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(PDO $pdo, string $permissionCode): void
    {
        if (!currentUserCan($pdo, $permissionCode)) {
            http_response_code(403);
            exit('Accès refusé.');
        }
    }
}

if (!function_exists('logUserAction')) {
    function logUserAction(
        PDO $pdo,
        int $userId,
        string $action,
        ?string $module = null,
        ?string $entityType = null,
        $entityId = null,
        ?string $details = null
    ): void {
        if (!tableExists($pdo, 'user_logs')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'user_logs', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'user_logs', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_logs (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);
    }
}

if (!function_exists('fetchSelectOptions')) {
    function fetchSelectOptions(PDO $pdo, string $tableName, string $labelColumn = 'label', string $where = '1=1'): array
    {
        if (!tableExists($pdo, $tableName) || !columnExists($pdo, $tableName, 'id')) {
            return [];
        }

        if (!columnExists($pdo, $tableName, $labelColumn)) {
            if (columnExists($pdo, $tableName, 'name')) {
                $labelColumn = 'name';
            } elseif (columnExists($pdo, $tableName, 'account_label')) {
                $labelColumn = 'account_label';
            } elseif (columnExists($pdo, $tableName, 'full_name')) {
                $labelColumn = 'full_name';
            } else {
                return [];
            }
        }

        $stmt = $pdo->query("
            SELECT id, {$labelColumn} AS text
            FROM {$tableName}
            WHERE {$where}
            ORDER BY {$labelColumn} ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('findPrimaryBankAccountForClient')) {
    function findPrimaryBankAccountForClient(PDO $pdo, int $clientId): ?array
    {
        if (!tableExists($pdo, 'client_bank_accounts') || !tableExists($pdo, 'bank_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT ba.*
            FROM client_bank_accounts cba
            INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            WHERE cba.client_id = ?
            ORDER BY cba.id ASC
            LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('findTreasuryAccountByCode')) {
    function findTreasuryAccountByCode(PDO $pdo, string $accountCode): ?array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM treasury_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmt->execute([$accountCode]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('findServiceAccountByCode')) {
    function findServiceAccountByCode(PDO $pdo, string $accountCode): ?array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM service_accounts
            WHERE account_code = ?
            LIMIT 1
        ");
        $stmt->execute([$accountCode]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getClientAccountingContext')) {
    function getClientAccountingContext(PDO $pdo, int $clientId): ?array
    {
        if (!tableExists($pdo, 'clients')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                c.*,
                ta.account_code AS treasury_account_code,
                ta.account_label AS treasury_account_label
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$clientId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('resolveServiceAccountFromServiceId')) {
    function resolveServiceAccountFromServiceId(PDO $pdo, ?int $serviceId): ?array
    {
        if ($serviceId === null || !tableExists($pdo, 'ref_services')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                rs.id,
                rs.code,
                rs.label,
                rs.service_account_id,
                sa.account_code,
                sa.account_label
            FROM ref_services rs
            LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
            WHERE rs.id = ?
            LIMIT 1
        ");
        $stmt->execute([$serviceId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('resolveAccountingOperation')) {
    function resolveAccountingOperation(PDO $pdo, array $payload): array
    {
        $operationTypeCode = (string)($payload['operation_type_code'] ?? '');
        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $serviceId = isset($payload['service_id']) ? (int)$payload['service_id'] : null;
        $sourceTreasuryCode = $payload['source_treasury_code'] ?? null;
        $targetTreasuryCode = $payload['target_treasury_code'] ?? null;

        if ($operationTypeCode === '') {
            throw new RuntimeException('Type d’opération manquant.');
        }

        $clientContext = null;
        if ($clientId) {
            $clientContext = getClientAccountingContext($pdo, $clientId);
            if (!$clientContext) {
                throw new RuntimeException('Client introuvable pour la résolution comptable.');
            }
        }

        $serviceInfo = $serviceId ? resolveServiceAccountFromServiceId($pdo, $serviceId) : null;

        $debit = null;
        $credit = null;
        $analytic = null;

        switch ($operationTypeCode) {
            case 'VERSEMENT':
            case 'REGULARISATION_POSITIVE':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
                $credit = $clientContext['generated_client_account'] ?? null;
                break;

            case 'VIREMENT_MENSUEL':
            case 'VIREMENT_EXCEPTIONEL':
            case 'VIREMENT_REGULIER':
            case 'REGULARISATION_NEGATIVE':
            case 'FRAIS_BANCAIRES':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
                break;

            case 'FRAIS_DE_SERVICE':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                if (!$serviceInfo || empty($serviceInfo['account_code'])) {
                    throw new RuntimeException('Le service choisi n’a pas de compte 706 associé.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $serviceInfo['account_code'];
                $analytic = [
                    'account_code' => $serviceInfo['account_code'],
                    'account_label' => $serviceInfo['account_label'] ?? null,
                ];
                break;

            case 'VIREMENT_INTERNE':
                if (!$sourceTreasuryCode || !$targetTreasuryCode) {
                    throw new RuntimeException('Les comptes source et cible sont obligatoires.');
                }
                $debit = $sourceTreasuryCode;
                $credit = $targetTreasuryCode;
                break;

            default:
                throw new RuntimeException('Type d’opération non géré par le moteur.');
        }

        if (!$debit || !$credit) {
            throw new RuntimeException('Impossible de résoudre les comptes débit/crédit.');
        }

        return [
            'debit_account_code' => $debit,
            'credit_account_code' => $credit,
            'analytic_account' => $analytic,
            'client_context' => $clientContext,
            'service_info' => $serviceInfo,
        ];
    }
}

if (!function_exists('updateBankAccountBalanceDelta')) {
    function updateBankAccountBalanceDelta(PDO $pdo, int $bankAccountId, float $delta): void
    {
        if (!tableExists($pdo, 'bank_accounts') || !columnExists($pdo, 'bank_accounts', 'balance')) {
            return;
        }

        $sql = "UPDATE bank_accounts SET balance = COALESCE(balance,0) + ?";
        if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$delta, $bankAccountId]);
    }
}

if (!function_exists('updateTreasuryBalanceDelta')) {
    function updateTreasuryBalanceDelta(PDO $pdo, int $treasuryId, float $delta): void
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return;
        }

        $sets = [];

        if (columnExists($pdo, 'treasury_accounts', 'current_balance')) {
            $sets[] = 'current_balance = COALESCE(current_balance,0) + ?';
        }

        if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        if (!$sets) {
            return;
        }

        $params = [$delta, $treasuryId];

        $stmt = $pdo->prepare("
            UPDATE treasury_accounts
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
    }
}

if (!function_exists('updateServiceAccountBalanceDelta')) {
    function updateServiceAccountBalanceDelta(PDO $pdo, int $serviceAccountId, float $delta): void
    {
        if (!tableExists($pdo, 'service_accounts') || !columnExists($pdo, 'service_accounts', 'current_balance')) {
            return;
        }

        $sql = "UPDATE service_accounts SET current_balance = COALESCE(current_balance,0) + ?";
        if (columnExists($pdo, 'service_accounts', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$delta, $serviceAccountId]);
    }
}

if (!function_exists('applyAccountingBalanceEffects')) {
    function applyAccountingBalanceEffects(PDO $pdo, array $payload, array $resolved, int $bankAccountId = 0): void
    {
        $operationTypeCode = (string)($payload['operation_type_code'] ?? '');
        $amount = (float)($payload['amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        if ($bankAccountId > 0) {
            if (in_array($operationTypeCode, ['VERSEMENT', 'REGULARISATION_POSITIVE'], true)) {
                updateBankAccountBalanceDelta($pdo, $bankAccountId, +$amount);
            } elseif (in_array($operationTypeCode, [
                'FRAIS_DE_SERVICE',
                'VIREMENT_MENSUEL',
                'VIREMENT_EXCEPTIONEL',
                'VIREMENT_REGULIER',
                'REGULARISATION_NEGATIVE',
                'FRAIS_BANCAIRES'
            ], true)) {
                updateBankAccountBalanceDelta($pdo, $bankAccountId, -$amount);
            }
        }

        $debitCode = (string)($resolved['debit_account_code'] ?? '');
        $creditCode = (string)($resolved['credit_account_code'] ?? '');

        $debitTreasury = findTreasuryAccountByCode($pdo, $debitCode);
        if ($debitTreasury) {
            updateTreasuryBalanceDelta($pdo, (int)$debitTreasury['id'], -$amount);
        }

        $creditTreasury = findTreasuryAccountByCode($pdo, $creditCode);
        if ($creditTreasury) {
            updateTreasuryBalanceDelta($pdo, (int)$creditTreasury['id'], +$amount);
        }

        $debitService = findServiceAccountByCode($pdo, $debitCode);
        if ($debitService) {
            updateServiceAccountBalanceDelta($pdo, (int)$debitService['id'], -$amount);
        }

        $creditService = findServiceAccountByCode($pdo, $creditCode);
        if ($creditService) {
            updateServiceAccountBalanceDelta($pdo, (int)$creditService['id'], +$amount);
        }
    }
}

if (!function_exists('createOperationWithAccounting')) {
    function createOperationWithAccounting(PDO $pdo, array $payload): int
    {
        $resolved = resolveAccountingOperation($pdo, $payload);

        $clientId = isset($payload['client_id']) ? (int)$payload['client_id'] : null;
        $bankAccountId = null;

        if ($clientId) {
            $bankAccount = findPrimaryBankAccountForClient($pdo, $clientId);
            $bankAccountId = $bankAccount['id'] ?? null;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => $clientId,
            'bank_account_id' => $bankAccountId,
            'operation_date' => $payload['operation_date'] ?? date('Y-m-d'),
            'amount' => (float)($payload['amount'] ?? 0),
            'operation_type_code' => $payload['operation_type_code'] ?? null,
            'label' => $payload['label'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'debit_account_code' => $resolved['debit_account_code'],
            'credit_account_code' => $resolved['credit_account_code'],
            'service_account_code' => $resolved['analytic_account']['account_code'] ?? null,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'operations', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'operations', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        $stmt = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        $operationId = (int)$pdo->lastInsertId();

        applyAccountingBalanceEffects($pdo, $payload, $resolved, (int)($bankAccountId ?? 0));

        return $operationId;
    }
}

if (!function_exists('createInternalTreasuryMovement')) {
    function createInternalTreasuryMovement(PDO $pdo, array $payload): int
    {
        if (!tableExists($pdo, 'treasury_movements')) {
            throw new RuntimeException('La table treasury_movements est absente.');
        }

        $sourceId = (int)($payload['source_treasury_account_id'] ?? 0);
        $targetId = (int)($payload['target_treasury_account_id'] ?? 0);
        $amount = (float)($payload['amount'] ?? 0);

        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            throw new RuntimeException('Virement interne invalide.');
        }

        $stmtSource = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
        $stmtSource->execute([$sourceId]);
        $source = $stmtSource->fetch(PDO::FETCH_ASSOC);

        $stmtTarget = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
        $stmtTarget->execute([$targetId]);
        $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);

        if (!$source || !$target) {
            throw new RuntimeException('Comptes internes introuvables.');
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'source_treasury_account_id' => $sourceId,
            'target_treasury_account_id' => $targetId,
            'amount' => $amount,
            'operation_date' => $payload['operation_date'] ?? date('Y-m-d'),
            'reference' => $payload['reference'] ?? null,
            'label' => $payload['label'] ?? 'Virement interne',
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'treasury_movements', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'treasury_movements', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        $stmt = $pdo->prepare("
            INSERT INTO treasury_movements (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        updateTreasuryBalanceDelta($pdo, $sourceId, -$amount);
        updateTreasuryBalanceDelta($pdo, $targetId, +$amount);

        return (int)$pdo->lastInsertId();
    }
}