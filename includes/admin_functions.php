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

if (!function_exists('getRoleOptions')) {
    function getRoleOptions(PDO $pdo): array
    {
        if (!tableExists($pdo, 'roles')) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label
            FROM roles
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getPermissionOptions')) {
    function getPermissionOptions(PDO $pdo): array
    {
        if (!tableExists($pdo, 'permissions')) {
            return [];
        }

        return $pdo->query("
            SELECT id, code, label
            FROM permissions
            ORDER BY label ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
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
            case 'CREDIT_CLIENT':
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
            case 'DEBIT_CLIENT':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
                break;

            case 'FRAIS_DE_SERVICE':
            case 'FRAIS_SERVICE':
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

            case 'MANUAL':
            case 'IMPORT_RELEVE':
            case 'REGULARISATION':
                if (!$clientContext) {
                    throw new RuntimeException('Client obligatoire.');
                }
                $debit = $clientContext['generated_client_account'] ?? null;
                $credit = $sourceTreasuryCode ?: ($clientContext['treasury_account_code'] ?? null);
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

        $stmt = $pdo->prepare("
            UPDATE treasury_accounts
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");
        $stmt->execute([$delta, $treasuryId]);
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
            if (in_array($operationTypeCode, ['VERSEMENT', 'REGULARISATION_POSITIVE', 'CREDIT_CLIENT'], true)) {
                updateBankAccountBalanceDelta($pdo, $bankAccountId, +$amount);
            } elseif (in_array($operationTypeCode, [
                'FRAIS_DE_SERVICE',
                'FRAIS_SERVICE',
                'VIREMENT_MENSUEL',
                'VIREMENT_EXCEPTIONEL',
                'VIREMENT_REGULIER',
                'REGULARISATION_NEGATIVE',
                'FRAIS_BANCAIRES',
                'DEBIT_CLIENT',
                'MANUAL',
                'IMPORT_RELEVE',
                'REGULARISATION'
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
            'operation_kind' => $payload['operation_kind'] ?? null,
            'label' => $payload['label'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'source_type' => $payload['source_type'] ?? null,
            'debit_account_code' => $resolved['debit_account_code'],
            'credit_account_code' => $resolved['credit_account_code'],
            'service_account_code' => $resolved['analytic_account']['account_code'] ?? null,
            'created_by' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
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

if (!function_exists('recomputeAllBalances')) {
    function recomputeAllBalances(PDO $pdo): array
    {
        $report = [
            'bank_accounts' => 0,
            'treasury_accounts' => 0,
            'service_accounts' => 0,
        ];

        if (tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'operations')) {
            $bankAccounts = $pdo->query("
                SELECT id, account_number, COALESCE(initial_balance, 0) AS initial_balance
                FROM bank_accounts
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtBank = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
                FROM operations
            ");

            $stmtUpdateBank = $pdo->prepare("
                UPDATE bank_accounts
                SET balance = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($bankAccounts as $account) {
                $stmtBank->execute([$account['account_number'], $account['account_number']]);
                $totals = $stmtBank->fetch(PDO::FETCH_ASSOC) ?: [];

                $newBalance = (float)$account['initial_balance']
                    + (float)($totals['total_debit'] ?? 0)
                    - (float)($totals['total_credit'] ?? 0);

                $stmtUpdateBank->execute([$newBalance, (int)$account['id']]);
                $report['bank_accounts']++;
            }
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $treasuryAccounts = $pdo->query("
                SELECT id, account_code, COALESCE(opening_balance,0) AS opening_balance
                FROM treasury_accounts
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtTreasuryOps = tableExists($pdo, 'operations')
                ? $pdo->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                        COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
                    FROM operations
                ")
                : null;

            $stmtTreasuryMov = tableExists($pdo, 'treasury_movements')
                ? $pdo->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN target_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_in,
                        COALESCE(SUM(CASE WHEN source_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_out
                    FROM treasury_movements
                ")
                : null;

            $stmtUpdateTreasury = $pdo->prepare("
                UPDATE treasury_accounts
                SET current_balance = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($treasuryAccounts as $account) {
                $opsDebit = 0.0;
                $opsCredit = 0.0;
                $movIn = 0.0;
                $movOut = 0.0;

                if ($stmtTreasuryOps) {
                    $stmtTreasuryOps->execute([$account['account_code'], $account['account_code']]);
                    $ops = $stmtTreasuryOps->fetch(PDO::FETCH_ASSOC) ?: [];
                    $opsDebit = (float)($ops['total_debit'] ?? 0);
                    $opsCredit = (float)($ops['total_credit'] ?? 0);
                }

                if ($stmtTreasuryMov) {
                    $stmtTreasuryMov->execute([(int)$account['id'], (int)$account['id']]);
                    $mov = $stmtTreasuryMov->fetch(PDO::FETCH_ASSOC) ?: [];
                    $movIn = (float)($mov['total_in'] ?? 0);
                    $movOut = (float)($mov['total_out'] ?? 0);
                }

                $newBalance = (float)$account['opening_balance'] + $opsDebit - $opsCredit + $movIn - $movOut;
                $stmtUpdateTreasury->execute([$newBalance, (int)$account['id']]);
                $report['treasury_accounts']++;
            }
        }

        if (tableExists($pdo, 'service_accounts') && tableExists($pdo, 'operations')) {
            $serviceAccounts = $pdo->query("
                SELECT id, account_code
                FROM service_accounts
            ")->fetchAll(PDO::FETCH_ASSOC);

            $stmtService = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit,
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit
                FROM operations
            ");

            $stmtUpdateService = $pdo->prepare("
                UPDATE service_accounts
                SET current_balance = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($serviceAccounts as $account) {
                $stmtService->execute([$account['account_code'], $account['account_code']]);
                $totals = $stmtService->fetch(PDO::FETCH_ASSOC) ?: [];

                $newBalance = (float)($totals['total_credit'] ?? 0) - (float)($totals['total_debit'] ?? 0);
                $stmtUpdateService->execute([$newBalance, (int)$account['id']]);
                $report['service_accounts']++;
            }
        }

        return $report;
    }
}

if (!function_exists('studely_destination_countries')) {
    function studely_destination_countries(): array
    {
        return [
            'Allemagne',
            'Belgique',
            'France',
            'Espagne',
            'Italie',
            'Autres destinations',
        ];
    }
}

if (!function_exists('studely_commercial_countries')) {
    function studely_commercial_countries(): array
    {
        return [
            'France','Allemagne','Belgique','Cameroun','Sénégal','Côte d\'Ivoire','Benin',
            'Burkina Faso','Congo Brazzaville','Congo Kinshasa','Gabon','Tchad','Mali','Togo',
            'Mexique','Inde','Algérie','Guinée','Tunisie','Maroc','Niger','Afrique de l\'est','Autres pays',
        ];
    }
}

if (!function_exists('studely_origin_countries')) {
    function studely_origin_countries(): array
    {
        return [
            'Afghanistan','Afrique du Sud','Albanie','Algérie','Allemagne','Andorre','Angola','Antigua-et-Barbuda',
            'Arabie saoudite','Argentine','Arménie','Australie','Autriche','Azerbaïdjan','Bahamas','Bahreïn',
            'Bangladesh','Barbade','Belgique','Belize','Bénin','Bhoutan','Biélorussie','Birmanie','Bolivie',
            'Bosnie-Herzégovine','Botswana','Brésil','Brunei','Bulgarie','Burkina Faso','Burundi','Cap-Vert',
            'Cambodge','Cameroun','Canada','République centrafricaine','Chili','Chine','Chypre','Colombie',
            'Comores','Congo','République démocratique du Congo','Corée du Nord','Corée du Sud','Costa Rica',
            'Côte d’Ivoire','Croatie','Cuba','Danemark','Djibouti','Dominique','Égypte','Émirats arabes unis',
            'Équateur','Érythrée','Espagne','Estonie','Eswatini','États-Unis','Éthiopie','Fidji','Finlande',
            'France','Gabon','Gambie','Géorgie','Ghana','Grèce','Grenade','Guatemala','Guinée','Guinée-Bissau',
            'Guinée équatoriale','Guyana','Haïti','Honduras','Hongrie','Inde','Indonésie','Irak','Iran',
            'Irlande','Islande','Israël','Italie','Jamaïque','Japon','Jordanie','Kazakhstan','Kenya',
            'Kirghizistan','Kiribati','Koweït','Laos','Lesotho','Lettonie','Liban','Liberia','Libye',
            'Liechtenstein','Lituanie','Luxembourg','Macédoine du Nord','Madagascar','Malaisie','Malawi',
            'Maldives','Mali','Malte','Maroc','Îles Marshall','Maurice','Mauritanie','Mexique','Micronésie',
            'Moldavie','Monaco','Mongolie','Monténégro','Mozambique','Namibie','Nauru','Népal','Nicaragua',
            'Niger','Nigeria','Norvège','Nouvelle-Zélande','Oman','Ouganda','Ouzbékistan','Pakistan',
            'Palaos','Palestine','Panama','Papouasie-Nouvelle-Guinée','Paraguay','Pays-Bas','Pérou',
            'Philippines','Pologne','Portugal','Qatar','Roumanie','Royaume-Uni','Russie','Rwanda',
            'Saint-Christophe-et-Niévès','Saint-Marin','Saint-Vincent-et-les-Grenadines','Sainte-Lucie',
            'Salomon','Salvador','Samoa','Sao Tomé-et-Principe','Sénégal','Serbie','Seychelles',
            'Sierra Leone','Singapour','Slovaquie','Slovénie','Somalie','Soudan','Soudan du Sud',
            'Sri Lanka','Suède','Suisse','Suriname','Syrie','Tadjikistan','Tanzanie','Tchad','Tchéquie',
            'Thaïlande','Timor oriental','Togo','Tonga','Trinité-et-Tobago','Tunisie','Turkménistan',
            'Turquie','Tuvalu','Ukraine','Uruguay','Vanuatu','Vatican','Venezuela','Vietnam','Yémen',
            'Zambie','Zimbabwe',
        ];
    }
}

if (!function_exists('studely_client_types')) {
    function studely_client_types(): array
    {
        return [
            'Etudiant',
            'Particulier',
            'Entreprise',
            'Partenaire',
        ];
    }
}