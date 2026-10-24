<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'client_create');
}

$pageTitle = 'Créer un client';
$pageSubtitle = 'Création complète d’une fiche client avec compte 411, rattachement 512, mensualité, frais initiaux et prévisualisation';

if (!function_exists('sl_client_fetch_treasury_accounts')) {
    function sl_client_fetch_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT id, account_code, account_label, currency_code
            FROM treasury_accounts
            WHERE COALESCE(is_active,1) = 1
            ORDER BY account_code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_client_fetch_service_accounts_706')) {
    function sl_client_fetch_service_accounts_706(PDO $pdo): array
    {
        if (!tableExists($pdo, 'service_accounts')) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT id, account_code, account_label
            FROM service_accounts
            WHERE COALESCE(is_active,1) = 1
            ORDER BY account_code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_client_fetch_currencies')) {
    function sl_client_fetch_currencies(PDO $pdo): array
    {
        if (function_exists('sl_get_currency_options')) {
            $currencies = sl_get_currency_options($pdo);
            if (is_array($currencies) && $currencies) {
                return $currencies;
            }
        }

        if (tableExists($pdo, 'currencies')) {
            $stmt = $pdo->query("
                SELECT code, label
                FROM currencies
                WHERE COALESCE(is_active,1) = 1
                ORDER BY code ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [['code' => 'EUR', 'label' => 'Euro']];
    }
}

if (!function_exists('sl_generate_next_client_code')) {
    function sl_generate_next_client_code(PDO $pdo): string
    {
        if (function_exists('generateClientCode')) {
            return (string)generateClientCode($pdo);
        }

        $stmt = $pdo->query("
            SELECT client_code
            FROM clients
            WHERE client_code LIKE 'CLT%'
            ORDER BY id DESC
            LIMIT 1
        ");
        $lastCode = (string)($stmt->fetchColumn() ?: '');

        if (preg_match('/CLT(\d+)/', $lastCode, $m)) {
            $next = ((int)$m[1]) + 1;
            return 'CLT' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        }

        return 'CLT0001';
    }
}

if (!function_exists('sl_client_detect_service_default_amount_column')) {
    function sl_client_detect_service_default_amount_column(PDO $pdo): ?string
    {
        if (!tableExists($pdo, 'ref_services')) {
            return null;
        }

        foreach (['default_amount', 'amount', 'default_price', 'unit_price'] as $column) {
            if (columnExists($pdo, 'ref_services', $column)) {
                return $column;
            }
        }

        return null;
    }
}

if (!function_exists('sl_client_fetch_fee_services')) {
    function sl_client_fetch_fee_services(PDO $pdo): array
    {
        if (!tableExists($pdo, 'ref_services')) {
            return [];
        }

        $amountColumn = sl_client_detect_service_default_amount_column($pdo);
        $amountSql = $amountColumn !== null ? "COALESCE(rs.`{$amountColumn}`, 0)" : "0";

        $hasOpTypes = tableExists($pdo, 'ref_operation_types');

        $sql = "
            SELECT
                rs.id,
                rs.code,
                rs.label,
                rs.operation_type_id,
                {$amountSql} AS default_amount
                " . ($hasOpTypes ? ", rot.code AS operation_type_code, rot.label AS operation_type_label" : ", NULL AS operation_type_code, NULL AS operation_type_label") . "
            FROM ref_services rs
            " . ($hasOpTypes ? "LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id" : "") . "
            WHERE COALESCE(rs.is_active,1) = 1
            ORDER BY rs.label ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_client_parse_selected_fees')) {
    function sl_client_parse_selected_fees(array $post, array $feeServices): array
    {
        $selectedFlags = $post['fee_selected'] ?? [];
        $amountInputs = $post['fee_amount'] ?? [];
        $serviceAccountInputs = $post['fee_service_account_code'] ?? [];
        $serviceCodeInputs = $post['fee_service_code'] ?? [];
        $serviceLabelInputs = $post['fee_service_label'] ?? [];
        $operationTypeCodeInputs = $post['fee_operation_type_code'] ?? [];
        $operationTypeLabelInputs = $post['fee_operation_type_label'] ?? [];
        $operationTypeIdInputs = $post['fee_operation_type_id'] ?? [];

        $byId = [];
        foreach ($feeServices as $service) {
            $byId[(int)($service['id'] ?? 0)] = $service;
        }

        $selectedFees = [];

        foreach ($byId as $serviceId => $service) {
            $isChecked = isset($selectedFlags[$serviceId]);
            $amountRaw = (string)($amountInputs[$serviceId] ?? ($service['default_amount'] ?? '0'));
            $amount = (float)str_replace(',', '.', $amountRaw);
            $destinationAccountCode = trim((string)($serviceAccountInputs[$serviceId] ?? ''));

            if (!$isChecked) {
                continue;
            }

            if ($amount <= 0) {
                continue;
            }

            $selectedFees[] = [
                'service_id' => $serviceId,
                'service_code' => trim((string)($serviceCodeInputs[$serviceId] ?? ($service['code'] ?? ''))),
                'service_label' => trim((string)($serviceLabelInputs[$serviceId] ?? ($service['label'] ?? ''))),
                'operation_type_id' => (int)($operationTypeIdInputs[$serviceId] ?? ($service['operation_type_id'] ?? 0)),
                'operation_type_code' => trim((string)($operationTypeCodeInputs[$serviceId] ?? ($service['operation_type_code'] ?? 'FRAIS_SERVICE'))),
                'operation_type_label' => trim((string)($operationTypeLabelInputs[$serviceId] ?? ($service['operation_type_label'] ?? ''))),
                'amount' => $amount,
                'destination_account_code' => $destinationAccountCode,
            ];
        }

        return $selectedFees;
    }
}

if (!function_exists('sl_client_total_selected_fees')) {
    function sl_client_total_selected_fees(array $fees): float
    {
        $total = 0.0;
        foreach ($fees as $fee) {
            $total += (float)($fee['amount'] ?? 0);
        }
        return round($total, 2);
    }
}

if (!function_exists('sl_create_or_link_client_bank_account')) {
    function sl_create_or_link_client_bank_account(
        PDO $pdo,
        int $clientId,
        string $clientCode,
        string $fullName,
        string $accountNumber,
        string $country,
        float $initialBalance,
        float $balance,
        int $isActive
    ): int {
        if (!tableExists($pdo, 'bank_accounts')) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM bank_accounts
            WHERE account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$accountNumber]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $fields = [];
            $params = [];

            $map = [
                'account_name' => 'Compte client ' . $clientCode . ' - ' . $fullName,
                'bank_name' => 'Compte client interne',
                'country' => $country !== '' ? $country : null,
                'initial_balance' => $initialBalance,
                'balance' => $balance,
                'is_active' => $isActive,
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'bank_accounts', $column)) {
                    $fields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }

            if ($fields) {
                $params[] = (int)$existing['id'];
                $stmt = $pdo->prepare("
                    UPDATE bank_accounts
                    SET " . implode(', ', $fields) . "
                    WHERE id = ?
                ");
                $stmt->execute($params);
            }

            $bankAccountId = (int)$existing['id'];
        } else {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'account_name' => 'Compte client ' . $clientCode . ' - ' . $fullName,
                'account_number' => $accountNumber,
                'bank_name' => 'Compte client interne',
                'country' => $country !== '' ? $country : null,
                'initial_balance' => $initialBalance,
                'balance' => $balance,
                'is_active' => $isActive,
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'bank_accounts', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'bank_accounts', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }

            if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmt = $pdo->prepare("
                INSERT INTO bank_accounts (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmt->execute($params);
            $bankAccountId = (int)$pdo->lastInsertId();
        }

        if (tableExists($pdo, 'client_bank_accounts')) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM client_bank_accounts
                WHERE client_id = ? AND bank_account_id = ?
                LIMIT 1
            ");
            $stmt->execute([$clientId, $bankAccountId]);
            $link = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$link) {
                $stmt = $pdo->prepare("
                    INSERT INTO client_bank_accounts (client_id, bank_account_id, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$clientId, $bankAccountId]);
            }
        }

        return $bankAccountId;
    }
}

if (!function_exists('sl_client_create_operation_helper')) {
    function sl_client_create_operation_helper(PDO $pdo, array $payload): int
    {
        if (function_exists('createOperationWithAccountingV2')) {
            $result = createOperationWithAccountingV2($pdo, $payload);
            if (is_array($result)) {
                return (int)($result['operation_id'] ?? 0);
            }
            return (int)$result;
        }

        if (function_exists('createOperationWithAccounting')) {
            $result = createOperationWithAccounting($pdo, $payload);
            if (is_array($result)) {
                return (int)($result['operation_id'] ?? 0);
            }
            return (int)$result;
        }

        throw new RuntimeException('Le helper createOperationWithAccountingV2() ou createOperationWithAccounting() est introuvable.');
    }
}

$treasuryAccounts = sl_client_fetch_treasury_accounts($pdo);
$serviceAccounts706 = sl_client_fetch_service_accounts_706($pdo);
$currencies = sl_client_fetch_currencies($pdo);
$feeServices = sl_client_fetch_fee_services($pdo);

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$originCountries = function_exists('studely_origin_countries') ? studely_origin_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];

$successMessage = '';
$errorMessage = '';

$defaultClientCode = sl_generate_next_client_code($pdo);

$formData = [
    'client_code' => $defaultClientCode,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'postal_address' => '',
    'passport_number' => '',
    'passport_issue_country' => '',
    'passport_issue_date' => '',
    'passport_expiry_date' => '',
    'client_type' => '',
    'country_origin' => '',
    'country_destination' => '',
    'country_commercial' => '',
    'currency' => 'EUR',
    'generated_client_account' => '411' . $defaultClientCode,
    'initial_balance' => '0',
    'balance' => '0',
    'initial_treasury_account_id' => '',
    'monthly_amount' => '0',
    'monthly_treasury_account_id' => '',
    'monthly_day' => '26',
    'monthly_enabled' => 0,
    'is_active' => 1,
];

$previewMode = false;
$selectedFees = [];
$totalSelectedFees = 0.0;
$projectedBalance = 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'client_code' => trim((string)($_POST['client_code'] ?? $defaultClientCode)),
        'first_name' => trim((string)($_POST['first_name'] ?? '')),
        'last_name' => trim((string)($_POST['last_name'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'postal_address' => trim((string)($_POST['postal_address'] ?? '')),
        'passport_number' => trim((string)($_POST['passport_number'] ?? '')),
        'passport_issue_country' => trim((string)($_POST['passport_issue_country'] ?? '')),
        'passport_issue_date' => trim((string)($_POST['passport_issue_date'] ?? '')),
        'passport_expiry_date' => trim((string)($_POST['passport_expiry_date'] ?? '')),
        'client_type' => trim((string)($_POST['client_type'] ?? '')),
        'country_origin' => trim((string)($_POST['country_origin'] ?? '')),
        'country_destination' => trim((string)($_POST['country_destination'] ?? '')),
        'country_commercial' => trim((string)($_POST['country_commercial'] ?? '')),
        'currency' => trim((string)($_POST['currency'] ?? 'EUR')),
        'generated_client_account' => trim((string)($_POST['generated_client_account'] ?? '')),
        'initial_balance' => trim((string)($_POST['initial_balance'] ?? '0')),
        'balance' => trim((string)($_POST['balance'] ?? '0')),
        'initial_treasury_account_id' => ($_POST['initial_treasury_account_id'] ?? '') !== '' ? (int)$_POST['initial_treasury_account_id'] : '',
        'monthly_amount' => trim((string)($_POST['monthly_amount'] ?? '0')),
        'monthly_treasury_account_id' => ($_POST['monthly_treasury_account_id'] ?? '') !== '' ? (int)$_POST['monthly_treasury_account_id'] : '',
        'monthly_day' => trim((string)($_POST['monthly_day'] ?? '26')),
        'monthly_enabled' => isset($_POST['monthly_enabled']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $selectedFees = sl_client_parse_selected_fees($_POST, $feeServices);
    $totalSelectedFees = sl_client_total_selected_fees($selectedFees);

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));
    $previewMode = true;

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['client_code'] === '') {
            throw new RuntimeException('Le code client est obligatoire.');
        }

        if ($formData['first_name'] === '' || $formData['last_name'] === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($formData['client_type'] === '') {
            throw new RuntimeException('Le type de client est obligatoire.');
        }

        if ($formData['country_commercial'] === '') {
            throw new RuntimeException('Le pays commercial est obligatoire.');
        }

        if ($formData['generated_client_account'] === '') {
            throw new RuntimeException('Le compte 411 est obligatoire.');
        }

        if (!preg_match('/^411[A-Za-z0-9]+$/', $formData['generated_client_account'])) {
            throw new RuntimeException('Le compte 411 doit commencer par 411.');
        }

        foreach ($selectedFees as $fee) {
            if (trim((string)($fee['destination_account_code'] ?? '')) === '') {
                throw new RuntimeException('Chaque frais sélectionné doit avoir un compte 706 de destination.');
            }
        }

        if (
            $formData['passport_issue_date'] !== ''
            && $formData['passport_expiry_date'] !== ''
            && $formData['passport_expiry_date'] < $formData['passport_issue_date']
        ) {
            throw new RuntimeException('La date d’expiration du passport doit être postérieure à sa date de délivrance.');
        }

        $initialBalance = (float)str_replace(',', '.', $formData['initial_balance']);
        $balance = $initialBalance;
        $formData['balance'] = (string)$balance;
        $projectedBalance = round($balance - $totalSelectedFees, 2);

        $monthlyAmount = (float)str_replace(',', '.', $formData['monthly_amount']);
        $monthlyDay = (int)$formData['monthly_day'];

        if ($monthlyAmount < 0) {
            throw new RuntimeException('La mensualité ne peut pas être négative.');
        }

        if ($monthlyDay < 1 || $monthlyDay > 31) {
            throw new RuntimeException('Le jour de mensualité doit être compris entre 1 et 31.');
        }

        if ((int)$formData['monthly_enabled'] === 1 && $monthlyAmount <= 0) {
            throw new RuntimeException('La mensualité doit être supérieure à 0 pour être activée.');
        }

        $stmtCheckCode = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE client_code = ?");
        $stmtCheckCode->execute([$formData['client_code']]);
        if ((int)$stmtCheckCode->fetchColumn() > 0) {
            throw new RuntimeException('Ce code client existe déjà.');
        }

        $stmtCheck411 = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE generated_client_account = ?");
        $stmtCheck411->execute([$formData['generated_client_account']]);
        if ((int)$stmtCheck411->fetchColumn() > 0) {
            throw new RuntimeException('Ce compte 411 existe déjà dans les clients.');
        }

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);

            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'client_code' => $formData['client_code'],
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'full_name' => $fullName,
                'email' => $formData['email'] !== '' ? $formData['email'] : null,
                'phone' => $formData['phone'] !== '' ? $formData['phone'] : null,
                'postal_address' => $formData['postal_address'] !== '' ? $formData['postal_address'] : null,
                'passport_number' => $formData['passport_number'] !== '' ? $formData['passport_number'] : null,
                'passport_issue_country' => $formData['passport_issue_country'] !== '' ? $formData['passport_issue_country'] : null,
                'passport_issue_date' => $formData['passport_issue_date'] !== '' ? $formData['passport_issue_date'] : null,
                'passport_expiry_date' => $formData['passport_expiry_date'] !== '' ? $formData['passport_expiry_date'] : null,
                'client_type' => $formData['client_type'],
                'country_origin' => $formData['country_origin'] !== '' ? $formData['country_origin'] : null,
                'country_destination' => $formData['country_destination'] !== '' ? $formData['country_destination'] : null,
                'country_commercial' => $formData['country_commercial'],
                'currency' => $formData['currency'] !== '' ? $formData['currency'] : 'EUR',
                'generated_client_account' => $formData['generated_client_account'],
                'initial_treasury_account_id' => $formData['initial_treasury_account_id'] !== '' ? (int)$formData['initial_treasury_account_id'] : null,
                'monthly_amount' => $monthlyAmount,
                'monthly_treasury_account_id' => $formData['monthly_treasury_account_id'] !== '' ? (int)$formData['monthly_treasury_account_id'] : null,
                'monthly_day' => $monthlyDay,
                'monthly_enabled' => (int)$formData['monthly_enabled'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'clients', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'clients', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }
            if (columnExists($pdo, 'clients', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmt = $pdo->prepare("
                INSERT INTO clients (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmt->execute($params);

            $newId = (int)$pdo->lastInsertId();

            sl_create_or_link_client_bank_account(
                $pdo,
                $newId,
                $formData['client_code'],
                $fullName,
                $formData['generated_client_account'],
                $formData['country_commercial'],
                $initialBalance,
                $balance,
                $formData['is_active']
            );

            $feeResult = sl_client_apply_selected_fees(
                $pdo,
                $newId,
                $formData['client_code'],
                $fullName,
                $formData['currency'],
                $formData['generated_client_account'],
                $selectedFees
            );

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_client',
                    'clients',
                    'client',
                    $newId,
                    'Création du client ' . $formData['client_code'] . ' avec ' . (int)$feeResult['count'] . ' frais initiaux'
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'client_create',
                    'Client créé : ' . $formData['client_code'] . ' - ' . $fullName . ' | Frais appliqués : ' . number_format((float)$feeResult['total'], 2, ',', ' ') . ' ' . $formData['currency'],
                    'success',
                    APP_URL . 'modules/clients/client_view.php?id=' . $newId,
                    'client',
                    $newId,
                    (int)$_SESSION['user_id']
                );
            }

            $pdo->commit();

            $successMessage = 'Client créé avec succès. Frais appliqués : ' . number_format((float)$feeResult['total'], 2, ',', ' ') . ' ' . $formData['currency'] . '.';
            $newCode = sl_generate_next_client_code($pdo);

            $formData = [
                'client_code' => $newCode,
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'postal_address' => '',
                'passport_number' => '',
                'passport_issue_country' => '',
                'passport_issue_date' => '',
                'passport_expiry_date' => '',
                'client_type' => '',
                'country_origin' => '',
                'country_destination' => '',
                'country_commercial' => '',
                'currency' => 'EUR',
                'generated_client_account' => '411' . $newCode,
                'initial_balance' => '0',
                'balance' => '0',
                'initial_treasury_account_id' => '',
                'monthly_amount' => '0',
                'monthly_treasury_account_id' => '',
                'monthly_day' => '26',
                'monthly_enabled' => 0,
                'is_active' => 1,
            ];

            $selectedFees = [];
            $totalSelectedFees = 0.0;
            $projectedBalance = 0.0;
            $previewMode = false;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sl-toggle-stack {
                display: grid;
                gap: 12px;
                margin-top: 16px;
            }
            .sl-toggle-card {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                padding: 14px 16px;
                border-radius: 16px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.18);
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            }
            .sl-toggle-card--monthly { border-left: 4px solid #4f46e5; }
            .sl-toggle-card--active { border-left: 4px solid #059669; }
            .sl-toggle-card__content { min-width: 0; }
            .sl-toggle-card__title {
                display: block;
                font-size: 14px;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 3px;
            }
            .sl-toggle-card__desc {
                display: block;
                font-size: 12px;
                color: #64748b;
                line-height: 1.4;
            }
            .sl-toggle-card__right {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-shrink: 0;
            }
            .sl-toggle-badge {
                min-width: 44px;
                text-align: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: .04em;
                text-transform: uppercase;
            }
            .sl-toggle-badge.is-on {
                background: rgba(16, 185, 129, 0.12);
                color: #047857;
            }
            .sl-toggle-badge.is-off {
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
            }
            .sl-switch {
                position: relative;
                display: inline-flex;
                width: 46px;
                height: 26px;
            }
            .sl-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .sl-switch__slider {
                position: absolute;
                inset: 0;
                cursor: pointer;
                border-radius: 999px;
                background: #cbd5e1;
                transition: .2s ease;
            }
            .sl-switch__slider:before {
                content: "";
                position: absolute;
                width: 20px;
                height: 20px;
                left: 3px;
                top: 3px;
                border-radius: 50%;
                background: #fff;
                box-shadow: 0 2px 6px rgba(15, 23, 42, 0.18);
                transition: .2s ease;
            }
            .sl-switch input:checked + .sl-switch__slider {
                background: #10b981;
            }
            .sl-switch input:checked + .sl-switch__slider:before {
                transform: translateX(20px);
            }
            .sl-fees-table .sl-fee-check input[type="checkbox"] {
                width: 16px;
                height: 16px;
                margin: 0;
                accent-color: #4f46e5;
                cursor: pointer;
                transform: scale(0.95);
            }
            .sl-fees-table td, .sl-fees-table th {
                vertical-align: middle;
            }
            .sl-fee-service-cell {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .sl-fee-service-code {
                font-size: 11px;
                font-weight: 700;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: .04em;
            }
            .sl-fee-service-label {
                font-size: 14px;
                font-weight: 700;
                color: #0f172a;
            }
            .sl-fee-type-badge {
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                border-radius: 999px;
                background: rgba(79, 70, 229, 0.08);
                color: #4338ca;
                font-size: 12px;
                font-weight: 700;
            }
            .sl-fee-amount-input, .sl-fee-account-select {
                min-width: 180px;
            }
            .sl-fee-account-select.is-disabled,
            .sl-fee-amount-input.is-disabled {
                opacity: .55;
                background: #f8fafc;
            }
        </style>

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

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code client</label>
                            <input type="text" name="client_code" id="client_code" value="<?= e($formData['client_code']) ?>" required>
                        </div>

                        <div>
                            <label>Compte 411</label>
                            <input type="text" name="generated_client_account" id="generated_client_account" value="<?= e($formData['generated_client_account']) ?>" required>
                        </div>

                        <div>
                            <label>Prénom</label>
                            <input type="text" name="first_name" value="<?= e($formData['first_name']) ?>" required>
                        </div>

                        <div>
                            <label>Nom</label>
                            <input type="text" name="last_name" value="<?= e($formData['last_name']) ?>" required>
                        </div>

                        <div>
                            <label>Email</label>
                            <input type="email" name="email" value="<?= e($formData['email']) ?>">
                        </div>

                        <div>
                            <label>Téléphone</label>
                            <input type="text" name="phone" value="<?= e($formData['phone']) ?>">
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency" required>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= e((string)$currency['code']) ?>" <?= (string)$formData['currency'] === (string)$currency['code'] ? 'selected' : '' ?>>
                                        <?= e((string)$currency['code'] . ' - ' . (string)$currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type de client</label>
                            <select name="client_type" required>
                                <option value="">Choisir</option>
                                <?php foreach ($clientTypes as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $formData['client_type'] === $type ? 'selected' : '' ?>>
                                        <?= e($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Solde initial du 411</label>
                            <input type="number" step="0.01" name="initial_balance" value="<?= e($formData['initial_balance']) ?>">
                        </div>

                        <div>
                            <label>Solde courant du 411</label>
                            <input type="number" step="0.01" name="balance" value="<?= e($formData['balance']) ?>" readonly>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label>Adresse postale</label>
                            <textarea name="postal_address" rows="3"><?= e($formData['postal_address']) ?></textarea>
                        </div>

                        <div>
                            <label>Numéro de passport</label>
                            <input type="text" name="passport_number" value="<?= e($formData['passport_number']) ?>">
                        </div>

                        <div>
                            <label>Lieu de délivrance du passport</label>
                            <select name="passport_issue_country">
                                <option value="">Choisir</option>
                                <?php foreach ($originCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= $formData['passport_issue_country'] === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Date de délivrance du passport</label>
                            <input type="date" name="passport_issue_date" value="<?= e($formData['passport_issue_date']) ?>">
                        </div>

                        <div>
                            <label>Date d’expiration du passport</label>
                            <input type="date" name="passport_expiry_date" value="<?= e($formData['passport_expiry_date']) ?>">
                        </div>

                        <div>
                            <label>Pays d'origine</label>
                            <select name="country_origin">
                                <option value="">Choisir</option>
                                <?php foreach ($originCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= $formData['country_origin'] === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays de destination</label>
                            <select name="country_destination">
                                <option value="">Choisir</option>
                                <?php foreach ($destinationCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= $formData['country_destination'] === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <select name="country_commercial" required>
                                <option value="">Choisir</option>
                                <?php foreach ($commercialCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= $formData['country_commercial'] === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512 principal</label>
                            <select name="initial_treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)$formData['initial_treasury_account_id'] === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e((string)($account['account_code'] ?? '') . ' - ' . (string)($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Mensualité</label>
                            <input type="number" step="0.01" min="0" name="monthly_amount" value="<?= e($formData['monthly_amount']) ?>">
                        </div>

                        <div>
                            <label>Compte 512 mensualité</label>
                            <select name="monthly_treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)$formData['monthly_treasury_account_id'] === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e((string)($account['account_code'] ?? '') . ' - ' . (string)($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Jour de mensualité</label>
                            <input type="number" min="1" max="31" name="monthly_day" value="<?= e($formData['monthly_day']) ?>">
                        </div>
                    </div>

                    <div class="card" style="margin-top:20px;">
                        <h3>Frais complémentaires à appliquer maintenant</h3>

                        <div class="table-responsive">
                            <table class="modern-table sl-fees-table">
                                <thead>
                                    <tr>
                                        <th style="width:70px;">OK</th>
                                        <th>Service</th>
                                        <th>Type d’opération</th>
                                        <th>Compte 706 crédité</th>
                                        <th style="width:170px;">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$feeServices): ?>
                                        <tr>
                                            <td colspan="5">Aucun service disponible pour les frais.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        $selectedFeeMap = [];
                                        foreach ($selectedFees as $fee) {
                                            $selectedFeeMap[(int)$fee['service_id']] = $fee;
                                        }
                                        ?>
                                        <?php foreach ($feeServices as $service): ?>
                                            <?php
                                            $serviceId = (int)($service['id'] ?? 0);
                                            $isChecked = isset($selectedFeeMap[$serviceId]);
                                            $amountValue = $isChecked
                                                ? (string)$selectedFeeMap[$serviceId]['amount']
                                                : (string)(float)($service['default_amount'] ?? 0);
                                            $selected706 = $isChecked
                                                ? (string)($selectedFeeMap[$serviceId]['destination_account_code'] ?? '')
                                                : '';

                                            $serviceCode = (string)($service['code'] ?? '');
                                            $serviceLabel = (string)($service['label'] ?? '');
                                            $operationTypeId = (int)($service['operation_type_id'] ?? 0);
                                            $operationTypeCode = (string)($service['operation_type_code'] ?? 'FRAIS_SERVICE');
                                            $operationTypeLabel = (string)($service['operation_type_label'] ?? '');
                                            ?>
                                            <tr class="sl-fee-row" data-service-id="<?= $serviceId ?>">
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="fee_selected[<?= $serviceId ?>]"
                                                        value="1"
                                                        class="sl-fee-selector"
                                                        data-service-id="<?= $serviceId ?>"
                                                        <?= $isChecked ? 'checked' : '' ?>
                                                    >

                                                    <input type="hidden" name="fee_service_code[<?= $serviceId ?>]" value="<?= e($serviceCode) ?>">
                                                    <input type="hidden" name="fee_service_label[<?= $serviceId ?>]" value="<?= e($serviceLabel) ?>">
                                                    <input type="hidden" name="fee_operation_type_id[<?= $serviceId ?>]" value="<?= (int)$operationTypeId ?>">
                                                    <input type="hidden" name="fee_operation_type_code[<?= $serviceId ?>]" value="<?= e($operationTypeCode) ?>">
                                                    <input type="hidden" name="fee_operation_type_label[<?= $serviceId ?>]" value="<?= e($operationTypeLabel) ?>">
                                                </td>

                                                <td>
                                                    <div class="sl-fee-service-cell">
                                                        <span class="sl-fee-service-code"><?= e($serviceCode) ?></span>
                                                        <span class="sl-fee-service-label"><?= e($serviceLabel) ?></span>
                                                    </div>
                                                </td>

                                                <td>
                                                    <span class="sl-fee-type-badge">
                                                        <?= e($operationTypeLabel !== '' ? $operationTypeLabel : $operationTypeCode) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <select
                                                        name="fee_service_account_code[<?= $serviceId ?>]"
                                                        class="sl-fee-account-select"
                                                        data-service-id="<?= $serviceId ?>"
                                                        data-service-code="<?= e($serviceCode) ?>"
                                                        data-service-label="<?= e($serviceLabel) ?>"
                                                    >
                                                        <option value="">Choisir un compte 706</option>
                                                        <?php foreach ($serviceAccounts706 as $account706): ?>
                                                            <?php
                                                            $accountCode706 = (string)($account706['account_code'] ?? '');
                                                            $accountLabel706 = (string)($account706['account_label'] ?? '');
                                                            $search706 = mb_strtolower($accountCode706 . ' ' . $accountLabel706, 'UTF-8');
                                                            ?>
                                                            <option
                                                                value="<?= e($accountCode706) ?>"
                                                                data-search="<?= e($search706) ?>"
                                                                <?= $selected706 === $accountCode706 ? 'selected' : '' ?>
                                                            >
                                                                <?= e($accountCode706 . ' - ' . $accountLabel706) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>

                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="fee_amount[<?= $serviceId ?>]"
                                                        value="<?= e($amountValue) ?>"
                                                        class="sl-fee-amount-input"
                                                        data-service-id="<?= $serviceId ?>"
                                                    >
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sl-toggle-stack">
                        <div class="sl-toggle-card sl-toggle-card--monthly">
                            <div class="sl-toggle-card__content">
                                <span class="sl-toggle-card__title">Mensualité active</span>
                                <span class="sl-toggle-card__desc">Autorise la génération automatique des mensualités.</span>
                            </div>
                            <div class="sl-toggle-card__right">
                                <span class="sl-toggle-badge <?= (int)$formData['monthly_enabled'] === 1 ? 'is-on' : 'is-off' ?>">
                                    <?= (int)$formData['monthly_enabled'] === 1 ? 'ON' : 'OFF' ?>
                                </span>
                                <label class="sl-switch">
                                    <input type="checkbox" name="monthly_enabled" value="1" <?= (int)$formData['monthly_enabled'] === 1 ? 'checked' : '' ?>>
                                    <span class="sl-switch__slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="sl-toggle-card sl-toggle-card--active">
                            <div class="sl-toggle-card__content">
                                <span class="sl-toggle-card__title">Client actif</span>
                                <span class="sl-toggle-card__desc">Autorise le client dans les flux et traitements automatiques.</span>
                            </div>
                            <div class="sl-toggle-card__right">
                                <span class="sl-toggle-badge <?= (int)$formData['is_active'] === 1 ? 'is-on' : 'is-off' ?>">
                                    <?= (int)$formData['is_active'] === 1 ? 'ON' : 'OFF' ?>
                                </span>
                                <label class="sl-switch">
                                    <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                                    <span class="sl-switch__slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <?php if ($previewMode): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code client</span><strong><?= e($formData['client_code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e(trim($formData['first_name'] . ' ' . $formData['last_name'])) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e($formData['generated_client_account']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde initial</span><strong><?= e(number_format((float)$formData['initial_balance'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde courant actuel</span><strong><?= e(number_format((float)$formData['balance'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Total frais complémentaires</span><strong><?= e(number_format($totalSelectedFees, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde projeté après frais</span><strong><?= e(number_format($projectedBalance, 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Mensualité</span><strong><?= e(number_format((float)$formData['monthly_amount'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Jour mensualité</span><strong><?= e($formData['monthly_day']) ?></strong></div>
                    </div>

                    <?php if ($selectedFees): ?>
                        <div class="card" style="margin-top:16px;">
                            <h4>Détail des frais complémentaires</h4>
                            <div class="table-responsive">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Type</th>
                                            <th>706 crédité</th>
                                            <th>Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($selectedFees as $fee): ?>
                                            <tr>
                                                <td><?= e((string)$fee['service_label']) ?></td>
                                                <td><?= e((string)($fee['operation_type_label'] !== '' ? $fee['operation_type_label'] : $fee['operation_type_code'])) ?></td>
                                                <td><?= e((string)$fee['destination_account_code']) ?></td>
                                                <td><?= e(number_format((float)$fee['amount'], 2, ',', ' ')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        Utilise le bouton <strong>Prévisualiser</strong> pour vérifier la fiche et les frais avant création définitive.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientCodeInput = document.getElementById('client_code');
            const account411Input = document.getElementById('generated_client_account');
            const initialBalanceInput = document.querySelector('input[name="initial_balance"]');
            const balanceInput = document.querySelector('input[name="balance"]');

            if (clientCodeInput && account411Input) {
                let accountTouched = false;

                account411Input.addEventListener('input', function () {
                    accountTouched = true;
                });

                clientCodeInput.addEventListener('input', function () {
                    if (!accountTouched) {
                        account411Input.value = '411' + clientCodeInput.value.trim();
                    }
                });
            }

            if (initialBalanceInput && balanceInput) {
                const syncBalance = function () {
                    balanceInput.value = initialBalanceInput.value;
                };
                initialBalanceInput.addEventListener('input', syncBalance);
                syncBalance();
            }

            function normalize(str) {
                return (str || '')
                    .toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            }

            function filter706Options(select, serviceCode, serviceLabel) {
                const normalizedCode = normalize(serviceCode);
                const normalizedLabel = normalize(serviceLabel);
                let visibleCount = 0;

                Array.from(select.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    const text = normalize(option.getAttribute('data-search') || option.textContent || '');
                    const visible =
                        (normalizedCode !== '' && text.includes(normalizedCode)) ||
                        (normalizedLabel !== '' && text.includes(normalizedLabel));

                    option.hidden = !visible;
                    if (visible) {
                        visibleCount++;
                    }
                });

                if (visibleCount === 0) {
                    Array.from(select.options).forEach(function (option) {
                        option.hidden = false;
                    });
                }

                const current = select.options[select.selectedIndex];
                if (current && current.hidden) {
                    select.value = '';
                }
            }

            function syncFeeRow(serviceId) {
                const checkbox = document.querySelector('.sl-fee-selector[data-service-id="' + serviceId + '"]');
                const amountInput = document.querySelector('.sl-fee-amount-input[data-service-id="' + serviceId + '"]');
                const accountSelect = document.querySelector('.sl-fee-account-select[data-service-id="' + serviceId + '"]');
                const serviceCodeInput = document.querySelector('input[name="fee_service_code[' + serviceId + ']"]');
                const serviceLabelInput = document.querySelector('input[name="fee_service_label[' + serviceId + ']"]');

                if (!checkbox) return;

                const checked = checkbox.checked;
                const serviceCode = serviceCodeInput ? serviceCodeInput.value : '';
                const serviceLabel = serviceLabelInput ? serviceLabelInput.value : '';

                if (amountInput) {
                    amountInput.readOnly = !checked;
                    amountInput.classList.toggle('is-disabled', !checked);
                    if (!checked) {
                        amountInput.value = '0';
                    }
                }

                if (accountSelect) {
                    accountSelect.classList.toggle('is-disabled', !checked);
                    filter706Options(accountSelect, serviceCode, serviceLabel);

                    if (!checked) {
                        accountSelect.value = '';
                    }
                }
            }

            document.querySelectorAll('.sl-fee-selector').forEach(function (checkbox) {
                const serviceId = checkbox.getAttribute('data-service-id');
                syncFeeRow(serviceId);

                checkbox.addEventListener('change', function () {
                    syncFeeRow(serviceId);
                });
            });

            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function () {
                    document.querySelectorAll('.sl-fee-selector').forEach(function (checkbox) {
                        const serviceId = checkbox.getAttribute('data-service-id');
                        const amountInput = document.querySelector('.sl-fee-amount-input[data-service-id="' + serviceId + '"]');
                        const accountSelect = document.querySelector('.sl-fee-account-select[data-service-id="' + serviceId + '"]');

                        if (!checkbox.checked) {
                            if (amountInput) amountInput.value = '0';
                            if (accountSelect) accountSelect.value = '';
                        }
                    });
                });
            }
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>