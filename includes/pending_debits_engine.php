<?php

if (!function_exists('sl_pending_table_exists')) {
    function sl_pending_table_exists(PDO $pdo, string $table): bool
    {
        return function_exists('tableExists') ? tableExists($pdo, $table) : false;
    }
}

if (!function_exists('sl_pending_column_exists')) {
    function sl_pending_column_exists(PDO $pdo, string $table, string $column): bool
    {
        return function_exists('columnExists') ? columnExists($pdo, $table, $column) : false;
    }
}

if (!function_exists('sl_pending_safe_now_column')) {
    function sl_pending_safe_now_column(PDO $pdo, string $table, string $column, array &$columns, array &$values): void
    {
        if (sl_pending_column_exists($pdo, $table, $column)) {
            $columns[] = $column;
            $values[] = 'NOW()';
        }
    }
}

if (!function_exists('sl_pending_append_column_value')) {
    function sl_pending_append_column_value(PDO $pdo, string $table, string $column, $value, array &$columns, array &$values, array &$params): void
    {
        if (sl_pending_column_exists($pdo, $table, $column)) {
            $columns[] = $column;
            $values[] = '?';
            $params[] = $value;
        }
    }
}

if (!function_exists('sl_get_client_411_account_row')) {
    function sl_get_client_411_account_row(PDO $pdo, int $clientId): ?array
    {
        if ($clientId <= 0 || !sl_pending_table_exists($pdo, 'client_bank_accounts') || !sl_pending_table_exists($pdo, 'bank_accounts')) {
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

if (!function_exists('sl_get_client_available_411_balance')) {
    function sl_get_client_available_411_balance(PDO $pdo, int $clientId): float
    {
        $account = sl_get_client_411_account_row($pdo, $clientId);
        if (!$account) {
            return 0.0;
        }

        return max(0, (float)($account['balance'] ?? 0));
    }
}

if (!function_exists('sl_is_client_411_code')) {
    function sl_is_client_411_code(?string $accountCode): bool
    {
        $accountCode = trim((string)$accountCode);
        return $accountCode !== '' && str_starts_with($accountCode, '411');
    }
}

if (!function_exists('sl_find_pending_client_debit_by_id')) {
    function sl_find_pending_client_debit_by_id(PDO $pdo, int $pendingDebitId): ?array
    {
        if ($pendingDebitId <= 0 || !sl_pending_table_exists($pdo, 'pending_client_debits')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT pd.*, c.client_code, c.full_name, c.generated_client_account, c.currency
            FROM pending_client_debits pd
            LEFT JOIN clients c ON c.id = pd.client_id
            WHERE pd.id = ?
            LIMIT 1
        ");
        $stmt->execute([$pendingDebitId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('sl_create_pending_client_debit_log')) {
    function sl_create_pending_client_debit_log(
        PDO $pdo,
        int $pendingDebitId,
        string $actionType,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?float $amount = null,
        ?string $message = null,
        ?int $createdBy = null
    ): void {
        if ($pendingDebitId <= 0 || !sl_pending_table_exists($pdo, 'pending_client_debit_logs')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'pending_debit_id', $pendingDebitId, $columns, $values, $params);
        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'action_type', $actionType, $columns, $values, $params);
        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'old_status', $oldStatus, $columns, $values, $params);
        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'new_status', $newStatus, $columns, $values, $params);
        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'amount', $amount, $columns, $values, $params);
        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'message', $message, $columns, $values, $params);
        sl_pending_append_column_value($pdo, 'pending_client_debit_logs', 'created_by', $createdBy, $columns, $values, $params);
        sl_pending_safe_now_column($pdo, 'pending_client_debit_logs', 'created_at', $columns, $values);

        if (!$columns) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO pending_client_debit_logs (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);
    }
}

if (!function_exists('sl_create_pending_client_debit')) {
    function sl_create_pending_client_debit(PDO $pdo, array $data): int
    {
        if (!sl_pending_table_exists($pdo, 'pending_client_debits')) {
            throw new RuntimeException('Table pending_client_debits introuvable.');
        }

        $initialAmount = round((float)($data['initial_amount'] ?? 0), 2);
        $executedAmount = round((float)($data['executed_amount'] ?? 0), 2);
        $remainingAmount = round((float)($data['remaining_amount'] ?? 0), 2);

        if ((int)($data['client_id'] ?? 0) <= 0 || $remainingAmount <= 0) {
            throw new RuntimeException('Impossible de créer le débit dû.');
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => (int)($data['client_id'] ?? 0),
            'client_code' => $data['client_code'] ?? null,
            'client_account_code' => $data['client_account_code'] ?? null,
            'source_operation_id' => $data['source_operation_id'] ?? null,
            'trigger_type' => $data['trigger_type'] ?? null,
            'label' => $data['label'] ?? null,
            'currency_code' => $data['currency_code'] ?? 'EUR',
            'initial_amount' => $initialAmount,
            'executed_amount' => $executedAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $data['status'] ?? 'pending',
            'priority_level' => $data['priority_level'] ?? 'normal',
            'notes' => $data['notes'] ?? null,
            'operation_type_code' => $data['operation_type_code'] ?? null,
            'operation_type_id' => $data['operation_type_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'linked_bank_account_id' => $data['linked_bank_account_id'] ?? null,
            'debit_account_code' => $data['debit_account_code'] ?? null,
            'credit_account_code' => $data['credit_account_code'] ?? null,
            'service_account_code' => $data['service_account_code'] ?? null,
            'operation_label' => $data['operation_label'] ?? null,
            'operation_reference' => $data['operation_reference'] ?? null,
            'source_module' => $data['source_module'] ?? null,
            'source_entity_type' => $data['source_entity_type'] ?? null,
            'source_entity_id' => $data['source_entity_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ];

        foreach ($map as $column => $value) {
            sl_pending_append_column_value($pdo, 'pending_client_debits', $column, $value, $columns, $values, $params);
        }

        if (sl_pending_column_exists($pdo, 'pending_client_debits', 'last_notification_at')) {
            $columns[] = 'last_notification_at';
            $values[] = 'NOW()';
        } elseif (sl_pending_column_exists($pdo, 'pending_client_debits', 'last_notification_sent_at')) {
            $columns[] = 'last_notification_sent_at';
            $values[] = 'NOW()';
        }

        sl_pending_safe_now_column($pdo, 'pending_client_debits', 'created_at', $columns, $values);
        sl_pending_safe_now_column($pdo, 'pending_client_debits', 'updated_at', $columns, $values);

        if (!$columns) {
            throw new RuntimeException('Aucune colonne exploitable dans pending_client_debits.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO pending_client_debits (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        $pendingDebitId = (int)$pdo->lastInsertId();

        sl_create_pending_client_debit_log(
            $pdo,
            $pendingDebitId,
            'create',
            null,
            (string)($data['status'] ?? 'pending'),
            $remainingAmount,
            'Création d’un débit dû 411',
            isset($data['created_by']) ? (int)$data['created_by'] : null
        );

        return $pendingDebitId;
    }
}

if (!function_exists('sl_update_pending_client_debit')) {
    function sl_update_pending_client_debit(PDO $pdo, int $pendingDebitId, array $data): void
    {
        if ($pendingDebitId <= 0 || !sl_pending_table_exists($pdo, 'pending_client_debits')) {
            return;
        }

        $fields = [];
        $params = [];

        foreach ($data as $column => $value) {
            if (sl_pending_column_exists($pdo, 'pending_client_debits', $column)) {
                $fields[] = $column . ' = ?';
                $params[] = $value;
            }
        }

        if (sl_pending_column_exists($pdo, 'pending_client_debits', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (!$fields) {
            return;
        }

        $params[] = $pendingDebitId;

        $stmt = $pdo->prepare("
            UPDATE pending_client_debits
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
    }
}

if (!function_exists('sl_notify_pending_client_debit_insufficient')) {
    function sl_notify_pending_client_debit_insufficient(
        PDO $pdo,
        array $clientRow,
        float $requestedAmount,
        float $executedAmount,
        float $remainingAmount,
        ?int $pendingDebitId = null,
        ?int $createdBy = null
    ): void {
        if (!function_exists('createNotification')) {
            return;
        }

        $clientCode = (string)($clientRow['client_code'] ?? '');
        $fullName = (string)($clientRow['full_name'] ?? '');

        $message = 'Insuffisance de solde 411 pour le client '
            . trim($clientCode . ' - ' . $fullName)
            . ' | demandé : ' . number_format($requestedAmount, 2, ',', ' ')
            . ' | exécuté : ' . number_format($executedAmount, 2, ',', ' ')
            . ' | restant dû : ' . number_format($remainingAmount, 2, ',', ' ');

        $linkUrl = null;
        if (defined('APP_URL') && $pendingDebitId && $pendingDebitId > 0) {
            $linkUrl = APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId;
        }

        createNotification(
            $pdo,
            'pending_debit_insufficient',
            $message,
            'warning',
            $linkUrl,
            'pending_client_debit',
            $pendingDebitId,
            $createdBy
        );
    }
}

if (!function_exists('sl_notify_pending_client_debit_ready')) {
    function sl_notify_pending_client_debit_ready(
        PDO $pdo,
        array $pendingDebit,
        array $clientRow,
        ?int $createdBy = null
    ): void {
        if (!function_exists('createNotification')) {
            return;
        }

        $clientCode = (string)($clientRow['client_code'] ?? '');
        $fullName = (string)($clientRow['full_name'] ?? '');
        $remaining = (float)($pendingDebit['remaining_amount'] ?? 0);

        $message = 'Le compte client 411 de '
            . trim($clientCode . ' - ' . $fullName)
            . ' est de nouveau alimenté. Débit restant dû disponible : '
            . number_format($remaining, 2, ',', ' ');

        $linkUrl = null;
        if (defined('APP_URL')) {
            $linkUrl = APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . (int)$pendingDebit['id'];
        }

        createNotification(
            $pdo,
            'pending_debit_ready',
            $message,
            'info',
            $linkUrl,
            'pending_client_debit',
            (int)$pendingDebit['id'],
            $createdBy
        );
    }
}

if (!function_exists('sl_refresh_client_pending_debits_readiness')) {
    function sl_refresh_client_pending_debits_readiness(PDO $pdo, int $clientId, ?int $createdBy = null): void
    {
        if ($clientId <= 0 || !sl_pending_table_exists($pdo, 'pending_client_debits') || !sl_pending_table_exists($pdo, 'clients')) {
            return;
        }

        $available = sl_get_client_available_411_balance($pdo, $clientId);

        $stmtClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
        $stmtClient->execute([$clientId]);
        $client = $stmtClient->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM pending_client_debits
            WHERE client_id = ?
              AND remaining_amount > 0
              AND status IN ('pending', 'partial', 'ready')
            ORDER BY id ASC
        ");
        $stmt->execute([$clientId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as $item) {
            $currentStatus = (string)($item['status'] ?? 'pending');

            if ($available > 0 && $currentStatus !== 'ready') {
                $updateData = ['status' => 'ready'];

                if (sl_pending_column_exists($pdo, 'pending_client_debits', 'last_notification_at')) {
                    $updateData['last_notification_at'] = date('Y-m-d H:i:s');
                }
                if (sl_pending_column_exists($pdo, 'pending_client_debits', 'last_notification_sent_at')) {
                    $updateData['last_notification_sent_at'] = date('Y-m-d H:i:s');
                }

                sl_update_pending_client_debit($pdo, (int)$item['id'], $updateData);

                sl_create_pending_client_debit_log(
                    $pdo,
                    (int)$item['id'],
                    'ready',
                    $currentStatus,
                    'ready',
                    (float)($item['remaining_amount'] ?? 0),
                    'Le compte client est redevenu positif. Débit initiable.',
                    $createdBy
                );

                sl_notify_pending_client_debit_ready($pdo, $item, $client, $createdBy);
            }

            if ($available <= 0 && $currentStatus === 'ready') {
                sl_update_pending_client_debit($pdo, (int)$item['id'], ['status' => 'pending']);

                sl_create_pending_client_debit_log(
                    $pdo,
                    (int)$item['id'],
                    'back_to_pending',
                    'ready',
                    'pending',
                    (float)($item['remaining_amount'] ?? 0),
                    'Le compte client n’est plus suffisamment alimenté.',
                    $createdBy
                );
            }
        }
    }
}

if (!function_exists('sl_handle_client_411_non_overdraft_guard')) {
    function sl_handle_client_411_non_overdraft_guard(PDO $pdo, array $payload, array $resolved, ?int $createdBy = null): array
    {
        $clientId = (int)($payload['client_id'] ?? 0);
        $amount = (float)($payload['amount'] ?? 0);
        $currencyCode = (string)($payload['currency_code'] ?? 'EUR');

        if ($clientId <= 0 || $amount <= 0) {
            return [
                'mode' => 'normal',
                'allowed_amount' => $amount,
                'remaining_amount' => 0,
                'bank_account_id' => 0,
                'pending_debit_id' => 0,
            ];
        }

        $client = function_exists('getClientAccountingContext') ? getClientAccountingContext($pdo, $clientId) : null;
        if (!$client) {
            return [
                'mode' => 'normal',
                'allowed_amount' => $amount,
                'remaining_amount' => 0,
                'bank_account_id' => 0,
                'pending_debit_id' => 0,
            ];
        }

        $clientAccountCode = (string)($client['generated_client_account'] ?? '');
        $debitCode = (string)($resolved['debit_account_code'] ?? '');

        if ($clientAccountCode === '' || $debitCode !== $clientAccountCode || !sl_is_client_411_code($debitCode)) {
            return [
                'mode' => 'normal',
                'allowed_amount' => $amount,
                'remaining_amount' => 0,
                'bank_account_id' => 0,
                'pending_debit_id' => 0,
            ];
        }

        $bankAccount = sl_get_client_411_account_row($pdo, $clientId);
        $bankAccountId = (int)($bankAccount['id'] ?? 0);
        $available = max(0, (float)($bankAccount['balance'] ?? 0));

        if ($available >= $amount) {
            return [
                'mode' => 'normal',
                'allowed_amount' => $amount,
                'remaining_amount' => 0,
                'bank_account_id' => $bankAccountId,
                'pending_debit_id' => 0,
            ];
        }

        $allowedAmount = min($available, $amount);
        $remainingAmount = round($amount - $allowedAmount, 2);

        $pendingDebitId = sl_create_pending_client_debit($pdo, [
            'client_id' => $clientId,
            'client_code' => $client['client_code'] ?? null,
            'client_account_code' => $client['generated_client_account'] ?? null,
            'source_operation_id' => null,
            'trigger_type' => (string)($payload['operation_type_code'] ?? 'operation'),
            'label' => (string)($payload['label'] ?? 'Débit dû client 411'),
            'currency_code' => $currencyCode !== '' ? $currencyCode : ($client['currency'] ?? 'EUR'),
            'initial_amount' => $amount,
            'executed_amount' => $allowedAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $allowedAmount > 0 ? 'partial' : 'pending',
            'priority_level' => 'normal',
            'notes' => 'Créé automatiquement suite à insuffisance de solde 411',
            'operation_type_code' => $payload['operation_type_code'] ?? null,
            'operation_type_id' => $payload['operation_type_id'] ?? null,
            'service_id' => $payload['service_id'] ?? null,
            'linked_bank_account_id' => $payload['linked_bank_account_id'] ?? $bankAccountId,
            'debit_account_code' => $resolved['debit_account_code'] ?? null,
            'credit_account_code' => $resolved['credit_account_code'] ?? null,
            'service_account_code' => $resolved['analytic_account']['account_code'] ?? null,
            'operation_label' => $payload['label'] ?? null,
            'operation_reference' => $payload['reference'] ?? null,
            'source_module' => $payload['source_type'] ?? 'manual',
            'source_entity_type' => 'operation',
            'source_entity_id' => null,
            'created_by' => $createdBy,
        ]);

        sl_notify_pending_client_debit_insufficient(
            $pdo,
            $client,
            $amount,
            $allowedAmount,
            $remainingAmount,
            $pendingDebitId,
            $createdBy
        );

        if ($allowedAmount <= 0) {
            return [
                'mode' => 'pending_only',
                'allowed_amount' => 0,
                'remaining_amount' => $remainingAmount,
                'bank_account_id' => $bankAccountId,
                'pending_debit_id' => $pendingDebitId,
            ];
        }

        return [
            'mode' => 'partial',
            'allowed_amount' => $allowedAmount,
            'remaining_amount' => $remainingAmount,
            'bank_account_id' => $bankAccountId,
            'pending_debit_id' => $pendingDebitId,
        ];
    }
}

if (!function_exists('sl_attach_operation_to_pending_debit')) {
    function sl_attach_operation_to_pending_debit(PDO $pdo, int $pendingDebitId, int $operationId, ?int $createdBy = null): void
    {
        if ($pendingDebitId <= 0 || $operationId <= 0 || !sl_pending_table_exists($pdo, 'pending_client_debits')) {
            return;
        }

        $pending = sl_find_pending_client_debit_by_id($pdo, $pendingDebitId);
        if (!$pending) {
            return;
        }

        $update = [];

        if (sl_pending_column_exists($pdo, 'pending_client_debits', 'source_operation_id') && empty($pending['source_operation_id'])) {
            $update['source_operation_id'] = $operationId;
        }

        if ($update) {
            sl_update_pending_client_debit($pdo, $pendingDebitId, $update);
        }

        sl_create_pending_client_debit_log(
            $pdo,
            $pendingDebitId,
            'attach_operation',
            null,
            null,
            null,
            'Opération partielle liée au débit dû',
            $createdBy
        );
    }
}

if (!function_exists('sl_execute_pending_client_debit')) {
    function sl_execute_pending_client_debit(PDO $pdo, int $pendingDebitId, ?float $requestedAmount = null, ?int $createdBy = null): array
    {
        $item = sl_find_pending_client_debit_by_id($pdo, $pendingDebitId);
        if (!$item) {
            throw new RuntimeException('Débit dû introuvable.');
        }

        if (in_array((string)($item['status'] ?? ''), ['resolved', 'settled', 'cancelled'], true)) {
            throw new RuntimeException('Ce débit dû ne peut plus être exécuté.');
        }

        $clientId = (int)($item['client_id'] ?? 0);
        $remaining = (float)($item['remaining_amount'] ?? 0);
        $available = sl_get_client_available_411_balance($pdo, $clientId);

        if ($remaining <= 0) {
            throw new RuntimeException('Aucun montant restant à débiter.');
        }

        if ($available <= 0) {
            throw new RuntimeException('Le compte 411 client n’est pas suffisamment alimenté.');
        }

        $targetAmount = ($requestedAmount !== null && $requestedAmount > 0)
            ? min($requestedAmount, $remaining)
            : $remaining;

        $executable = round(min($targetAmount, $available), 2);
        if ($executable <= 0) {
            throw new RuntimeException('Aucun montant exécutable.');
        }

        $clientBank = sl_get_client_411_account_row($pdo, $clientId);
        if (!$clientBank) {
            throw new RuntimeException('Compte 411 client introuvable.');
        }

        $debitCode = trim((string)($item['debit_account_code'] ?? $item['generated_client_account'] ?? ''));
        $creditCode = trim((string)($item['credit_account_code'] ?? ''));

        if ($debitCode === '' || $creditCode === '') {
            throw new RuntimeException('Le débit dû ne possède pas ses vrais comptes comptables mémorisés.');
        }

        if (!sl_pending_table_exists($pdo, 'operations')) {
            throw new RuntimeException('Table operations introuvable.');
        }

        $operationDate = date('Y-m-d');
        $operationLabel = 'Débit dû initié - ' . (string)($item['operation_label'] ?? $item['label'] ?? 'Débit dû 411');
        $referenceBase = trim((string)($item['operation_reference'] ?? ''));
        $reference = $referenceBase !== ''
            ? $referenceBase . '-RELQ-' . date('YmdHis')
            : 'PEND-' . $pendingDebitId . '-' . date('YmdHis');

        $operationHash = hash('sha256', implode('|', [
            'pending_debit_execute',
            $pendingDebitId,
            $clientId,
            $operationDate,
            number_format($executable, 2, '.', ''),
            $reference,
            $debitCode,
            $creditCode,
        ]));

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_id' => $clientId,
            'service_id' => $item['service_id'] ?? null,
            'operation_type_id' => $item['operation_type_id'] ?? null,
            'bank_account_id' => (int)($clientBank['id'] ?? 0),
            'linked_bank_account_id' => (int)($item['linked_bank_account_id'] ?? $clientBank['id'] ?? 0),
            'operation_date' => $operationDate,
            'operation_type_code' => $item['operation_type_code'] ?? 'PENDING_CLIENT_DEBIT_EXECUTE',
            'operation_kind' => 'manual_pending_debit',
            'label' => $operationLabel,
            'amount' => $executable,
            'currency_code' => (string)($item['currency_code'] ?? 'EUR'),
            'reference' => $reference,
            'source_type' => 'pending_debit',
            'debit_account_code' => $debitCode,
            'credit_account_code' => $creditCode,
            'service_account_code' => $item['service_account_code'] ?? null,
            'operation_hash' => $operationHash,
            'is_manual_accounting' => 0,
            'notes' => 'Exécution d’un reliquat de débit dû 411 #' . $pendingDebitId,
            'created_by' => $createdBy,
        ];

        foreach ($map as $column => $value) {
            if (sl_pending_column_exists($pdo, 'operations', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        sl_pending_safe_now_column($pdo, 'operations', 'created_at', $columns, $values);
        sl_pending_safe_now_column($pdo, 'operations', 'updated_at', $columns, $values);

        $stmtInsert = $pdo->prepare("
            INSERT INTO operations (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmtInsert->execute($params);

        $operationId = (int)$pdo->lastInsertId();

        $oldStatus = (string)($item['status'] ?? 'pending');
        $oldRemaining = (float)($item['remaining_amount'] ?? 0);
        $newExecuted = round((float)($item['executed_amount'] ?? 0) + $executable, 2);
        $newRemaining = max(0, round($oldRemaining - $executable, 2));
        $newStatus = $newRemaining > 0 ? 'partial' : (sl_pending_column_exists($pdo, 'pending_client_debits', 'settled_at') ? 'settled' : 'resolved');

        $updateData = [
            'executed_amount' => $newExecuted,
            'remaining_amount' => $newRemaining,
            'status' => $newStatus,
        ];

        if (sl_pending_column_exists($pdo, 'pending_client_debits', 'resolved_at') && in_array($newStatus, ['resolved'], true)) {
            $updateData['resolved_at'] = date('Y-m-d H:i:s');
        }

        if (sl_pending_column_exists($pdo, 'pending_client_debits', 'settled_at') && in_array($newStatus, ['settled'], true)) {
            $updateData['settled_at'] = date('Y-m-d H:i:s');
        }

        sl_update_pending_client_debit($pdo, $pendingDebitId, $updateData);

        sl_create_pending_client_debit_log(
            $pdo,
            $pendingDebitId,
            'execute',
            $oldStatus,
            $newStatus,
            $executable,
            'Exécution manuelle du débit dû',
            $createdBy
        );

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
        }

        sl_refresh_client_pending_debits_readiness($pdo, $clientId, $createdBy);

        if (function_exists('createNotification') && in_array($newStatus, ['resolved', 'settled'], true)) {
            createNotification(
                $pdo,
                'pending_debit_resolved',
                'Le débit dû du client ' . (string)($item['client_code'] ?? '') . ' a été totalement soldé.',
                'success',
                defined('APP_URL') ? APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId : null,
                'pending_client_debit',
                $pendingDebitId,
                $createdBy
            );
        }

        return [
            'operation_id' => $operationId,
            'executed_amount' => $executable,
            'remaining_amount' => $newRemaining,
            'status' => $newStatus,
        ];
    }
}

if (!function_exists('sl_get_pending_client_debits')) {
    function sl_get_pending_client_debits(PDO $pdo, string $statusFilter = 'open'): array
    {
        if (!sl_pending_table_exists($pdo, 'pending_client_debits')) {
            return [];
        }

        $where = [];
        $params = [];

        if ($statusFilter === 'open') {
            $where[] = "COALESCE(remaining_amount,0) > 0";
            if (sl_pending_column_exists($pdo, 'pending_client_debits', 'status')) {
                $where[] = "COALESCE(status,'pending') IN ('pending','partial','ready')";
            }
        } elseif ($statusFilter !== 'all' && sl_pending_column_exists($pdo, 'pending_client_debits', 'status')) {
            $where[] = "COALESCE(status,'pending') = ?";
            $params[] = $statusFilter;
        }

        $sql = "SELECT * FROM pending_client_debits";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $orderBy = sl_pending_column_exists($pdo, 'pending_client_debits', 'created_at')
            ? 'created_at DESC, id DESC'
            : 'id DESC';

        $sql .= " ORDER BY " . $orderBy;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_get_monthly_dashboard_metrics')) {
    function sl_get_monthly_dashboard_metrics(PDO $pdo): array
    {
        $metrics = [
            'monthly_enabled_clients' => 0,
            'monthly_amount_total' => 0.0,
            'open_pending_count' => 0,
            'open_pending_amount' => 0.0,
            'last_runs_count' => 0,
        ];

        if (sl_pending_table_exists($pdo, 'clients') && sl_pending_column_exists($pdo, 'clients', 'monthly_enabled')) {
            $stmt = $pdo->query("
                SELECT
                    COUNT(*) AS cnt,
                    COALESCE(SUM(COALESCE(monthly_amount,0)),0) AS total_amount
                FROM clients
                WHERE COALESCE(is_active,1) = 1
                  AND COALESCE(monthly_enabled,0) = 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $metrics['monthly_enabled_clients'] = (int)($row['cnt'] ?? 0);
            $metrics['monthly_amount_total'] = (float)($row['total_amount'] ?? 0);
        }

        if (sl_pending_table_exists($pdo, 'pending_client_debits')) {
            $stmt = $pdo->query("
                SELECT
                    COUNT(*) AS cnt,
                    COALESCE(SUM(CASE WHEN COALESCE(remaining_amount,0) > 0 THEN remaining_amount ELSE 0 END),0) AS total_amount
                FROM pending_client_debits
                WHERE COALESCE(status,'pending') IN ('pending','partial','ready')
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $metrics['open_pending_count'] = (int)($row['cnt'] ?? 0);
            $metrics['open_pending_amount'] = (float)($row['total_amount'] ?? 0);
        }

        if (sl_pending_table_exists($pdo, 'monthly_payment_runs')) {
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM monthly_payment_runs");
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $metrics['last_runs_count'] = (int)($row['cnt'] ?? 0);
        }

        return $metrics;
    }
}