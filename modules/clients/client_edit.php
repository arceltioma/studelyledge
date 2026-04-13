<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_edit_page');
} else {
    enforcePagePermission($pdo, 'clients_edit');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

if (!function_exists('sl_client_fetch_treasury_accounts')) {
    function sl_client_fetch_treasury_accounts(PDO $pdo): array
    {
        if (!tableExists($pdo, 'treasury_accounts')) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT id, account_code, account_label, currency_code
            FROM treasury_accounts
            WHERE COALESCE(is_active,1)=1
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

if (!function_exists('sl_find_client_bank_account')) {
    function sl_find_client_bank_account(PDO $pdo, int $clientId, string $generatedAccount = ''): ?array
    {
        if (tableExists($pdo, 'client_bank_accounts') && tableExists($pdo, 'bank_accounts')) {
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
            if ($row) {
                return $row;
            }
        }

        if ($generatedAccount !== '' && tableExists($pdo, 'bank_accounts')) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM bank_accounts
                WHERE account_number = ?
                LIMIT 1
            ");
            $stmt->execute([$generatedAccount]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('sl_sync_client_bank_account')) {
    function sl_sync_client_bank_account(
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

$treasuryAccounts = sl_client_fetch_treasury_accounts($pdo);
$currencies = sl_client_fetch_currencies($pdo);

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$originCountries = function_exists('studely_origin_countries') ? studely_origin_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];

$linkedBankAccount = sl_find_client_bank_account($pdo, $id, (string)($client['generated_client_account'] ?? ''));

$errorMessage = '';
$successMessage = '';
$previewMode = false;

$formData = [
    'client_code' => $client['client_code'] ?? '',
    'first_name' => $client['first_name'] ?? '',
    'last_name' => $client['last_name'] ?? '',
    'email' => $client['email'] ?? '',
    'phone' => $client['phone'] ?? '',
    'postal_address' => $client['postal_address'] ?? '',
    'passport_number' => $client['passport_number'] ?? '',
    'passport_issue_country' => $client['passport_issue_country'] ?? '',
    'passport_issue_date' => $client['passport_issue_date'] ?? '',
    'passport_expiry_date' => $client['passport_expiry_date'] ?? '',
    'client_type' => $client['client_type'] ?? '',
    'country_origin' => $client['country_origin'] ?? '',
    'country_destination' => $client['country_destination'] ?? '',
    'country_commercial' => $client['country_commercial'] ?? '',
    'currency' => $client['currency'] ?? 'EUR',
    'generated_client_account' => $client['generated_client_account'] ?? ('411' . ($client['client_code'] ?? '')),
    'initial_balance' => isset($linkedBankAccount['initial_balance']) ? (string)$linkedBankAccount['initial_balance'] : '0',
    'balance' => isset($linkedBankAccount['balance']) ? (string)$linkedBankAccount['balance'] : '0',
    'initial_treasury_account_id' => $client['initial_treasury_account_id'] ?? '',
    'monthly_amount' => isset($client['monthly_amount']) ? (string)$client['monthly_amount'] : '0',
    'monthly_treasury_account_id' => $client['monthly_treasury_account_id'] ?? '',
    'monthly_day' => isset($client['monthly_day']) ? (string)$client['monthly_day'] : '26',
    'monthly_enabled' => (int)($client['monthly_enabled'] ?? 0),
    'is_active' => (int)($client['is_active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'client_code' => trim((string)($_POST['client_code'] ?? '')),
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

        if ($formData['passport_issue_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_issue_date'])) {
            throw new RuntimeException('Date de délivrance du passport invalide.');
        }

        if ($formData['passport_expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_expiry_date'])) {
            throw new RuntimeException('Date d’expiration du passport invalide.');
        }

        if (
            $formData['passport_issue_date'] !== ''
            && $formData['passport_expiry_date'] !== ''
            && $formData['passport_expiry_date'] < $formData['passport_issue_date']
        ) {
            throw new RuntimeException('La date d’expiration du passport doit être postérieure à la date de délivrance.');
        }

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

        $stmtCheck411 = $pdo->prepare("
            SELECT COUNT(*)
            FROM clients
            WHERE generated_client_account = ?
              AND id <> ?
        ");
        $stmtCheck411->execute([$formData['generated_client_account'], $id]);

        if ((int)$stmtCheck411->fetchColumn() > 0) {
            throw new RuntimeException('Ce compte 411 est déjà utilisé par un autre client.');
        }

        $initialBalance = (float)str_replace(',', '.', $formData['initial_balance']);
        $balance = (float)str_replace(',', '.', $formData['balance']);

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);
            $before = $client;

            $updateFields = [];
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
                    $updateFields[] = $column . ' = ?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'clients', 'updated_at')) {
                $updateFields[] = 'updated_at = NOW()';
            }

            $params[] = $id;

            $stmtUpdate = $pdo->prepare("
                UPDATE clients
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute($params);

            sl_sync_client_bank_account(
                $pdo,
                $id,
                $formData['client_code'],
                $fullName,
                $formData['generated_client_account'],
                $formData['country_commercial'],
                $initialBalance,
                $balance,
                $formData['is_active']
            );

            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: $client;

            $linkedBankAccount = sl_find_client_bank_account($pdo, $id, (string)$formData['generated_client_account']);

            if (function_exists('auditEntityChanges') && isset($_SESSION['user_id'])) {
                auditEntityChanges($pdo, 'client', $id, $before, $client, (int)$_SESSION['user_id']);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_client',
                    'clients',
                    'client',
                    $id,
                    'Modification du client ' . ($client['client_code'] ?? '')
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'client_update',
                    'Client mis à jour : ' . (($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')),
                    'info',
                    APP_URL . 'modules/clients/client_view.php?id=' . $id,
                    'client',
                    $id,
                    (int)$_SESSION['user_id']
                );
            }

            $successMessage = 'Client mis à jour avec succès.';
            $previewMode = false;

            $formData['initial_balance'] = isset($linkedBankAccount['initial_balance']) ? (string)$linkedBankAccount['initial_balance'] : $formData['initial_balance'];
            $formData['balance'] = isset($linkedBankAccount['balance']) ? (string)$linkedBankAccount['balance'] : $formData['balance'];

            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un client';
$pageSubtitle = 'Mise à jour du profil client, du compte 411, du 512 principal et des paramètres de mensualité.';

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
                            <label>Code client</label>
                            <input type="text" name="client_code" value="<?= e($formData['client_code']) ?>" required>
                        </div>

                        <div>
                            <label>Compte 411</label>
                            <input type="text" name="generated_client_account" value="<?= e($formData['generated_client_account']) ?>" required>
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
                            <input type="number" step="0.01" name="balance" value="<?= e($formData['balance']) ?>">
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
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
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
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Jour de mensualité</label>
                            <input type="number" min="1" max="31" name="monthly_day" value="<?= e($formData['monthly_day']) ?>">
                        </div>
                    </div>

                    <div style="margin-top:16px; display:grid; gap:12px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="monthly_enabled" value="1" <?= (int)$formData['monthly_enabled'] === 1 ? 'checked' : '' ?>>
                            Mensualité active
                        </label>

                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                            Client actif
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <?php if ($previewMode): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code client</span><strong><?= e($formData['client_code']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e(trim($formData['first_name'] . ' ' . $formData['last_name'])) ?></strong></div>
                        <div class="sl-data-list__row"><span>Email</span><strong><?= e($formData['email'] !== '' ? $formData['email'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Téléphone</span><strong><?= e($formData['phone'] !== '' ? $formData['phone'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Type client</span><strong><?= e($formData['client_type'] !== '' ? $formData['client_type'] : '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Devise</span><strong><?= e($formData['currency']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e($formData['generated_client_account']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde initial</span><strong><?= e(number_format((float)$formData['initial_balance'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde courant</span><strong><?= e(number_format((float)$formData['balance'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Mensualité</span><strong><?= e(number_format((float)$formData['monthly_amount'], 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Jour mensualité</span><strong><?= e($formData['monthly_day']) ?></strong></div>
                        <div class="sl-data-list__row"><span>Mensualité active</span><strong><?= (int)$formData['monthly_enabled'] === 1 ? 'Oui' : 'Non' ?></strong></div>
                    </div>

                    <div class="dashboard-note" style="margin-top:16px;">
                        Contrôle final avant mise à jour du client, du compte 411, du 512 principal et des paramètres de mensualité.
                    </div>
                <?php else: ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e((string)($formData['generated_client_account'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde initial</span><strong><?= e(number_format((float)($formData['initial_balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde courant</span><strong><?= e(number_format((float)($formData['balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Mensualité</span><strong><?= e(number_format((float)($formData['monthly_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    </div>

                    <div class="dashboard-note" style="margin-top:16px;">
                        Utilise le bouton <strong>Prévisualiser</strong> pour vérifier les changements avant validation.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>