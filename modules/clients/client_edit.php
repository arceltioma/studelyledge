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

if (!function_exists('sl_client_generate_411_from_code')) {
    function sl_client_generate_411_from_code(string $clientCode): string
    {
        $clientCode = preg_replace('/\D+/', '', trim($clientCode));
        return $clientCode !== '' ? '411' . $clientCode : '';
    }
}

if (!function_exists('sl_client_find_bank_account_by_number')) {
    function sl_client_find_bank_account_by_number(PDO $pdo, string $accountNumber): ?array
    {
        if ($accountNumber === '' || !tableExists($pdo, 'bank_accounts')) {
            return null;
        }

        $candidateColumns = ['account_number', 'account_code', 'rib'];
        foreach ($candidateColumns as $column) {
            if (!columnExists($pdo, 'bank_accounts', $column)) {
                continue;
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM bank_accounts
                WHERE {$column} = ?
                LIMIT 1
            ");
            $stmt->execute([$accountNumber]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('sl_client_upsert_411_bank_account')) {
    function sl_client_upsert_411_bank_account(PDO $pdo, array $data): ?int
    {
        if (!tableExists($pdo, 'bank_accounts')) {
            return null;
        }

        $accountNumber = trim((string)($data['account_number'] ?? ''));
        if ($accountNumber === '') {
            return null;
        }

        $existing = sl_client_find_bank_account_by_number($pdo, $accountNumber);

        $currency = trim((string)($data['currency'] ?? 'EUR'));
        $initialBalance = (float)($data['initial_balance'] ?? 0);
        $currentBalance = (float)($data['balance'] ?? $initialBalance);
        $accountLabel = trim((string)($data['account_label'] ?? ('Compte client ' . $accountNumber)));

        $writeMap = [
            'account_name' => $accountLabel,
            'account_label' => $accountLabel,
            'account_number' => $accountNumber,
            'account_code' => $accountNumber,
            'currency' => $currency !== '' ? $currency : 'EUR',
            'initial_balance' => $initialBalance,
            'balance' => $currentBalance,
            'is_active' => 1,
        ];

        if ($existing) {
            $fields = [];
            $params = [];

            foreach ($writeMap as $column => $value) {
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

            return (int)$existing['id'];
        }

        $columns = [];
        $values = [];
        $params = [];

        foreach ($writeMap as $column => $value) {
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

        if (!$columns) {
            return null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO bank_accounts (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        return (int)$pdo->lastInsertId();
    }
}

$clientSql = "
    SELECT c.*
";

if (tableExists($pdo, 'bank_accounts')) {
    if (columnExists($pdo, 'clients', 'bank_account_id')) {
        $clientSql .= ",
            ba.id AS linked_bank_account_id,
            ba.initial_balance AS bank_initial_balance,
            ba.balance AS bank_balance,
            ba.currency AS bank_currency
        ";
        $clientSql .= "
            FROM clients c
            LEFT JOIN bank_accounts ba ON ba.id = c.bank_account_id
        ";
    } else {
        $clientSql .= "
            FROM clients c
        ";
    }
} else {
    $clientSql .= " FROM clients c ";
}

$clientSql .= " WHERE c.id = ? LIMIT 1";

$stmt = $pdo->prepare($clientSql);
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label, is_active
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$originCountries = function_exists('studely_origin_countries') ? studely_origin_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [['code' => 'EUR', 'label' => 'Euro']];

$errorMessage = '';
$successMessage = '';

$existing411 = trim((string)($client['generated_client_account'] ?? ''));
$existingInitialBalance = (string)($client['bank_initial_balance'] ?? '0');
$existingCurrentBalance = (string)($client['bank_balance'] ?? '0');

if ($existing411 !== '' && tableExists($pdo, 'bank_accounts') && !isset($client['linked_bank_account_id'])) {
    $resolvedBank = sl_client_find_bank_account_by_number($pdo, $existing411);
    if ($resolvedBank) {
        $existingInitialBalance = (string)($resolvedBank['initial_balance'] ?? '0');
        $existingCurrentBalance = (string)($resolvedBank['balance'] ?? '0');
    }
}

$formData = [
    'client_code' => $client['client_code'] ?? '',
    'generated_client_account' => $existing411,
    'client_initial_balance' => $existingInitialBalance,
    'client_current_balance' => $existingCurrentBalance,
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
    'currency' => $client['currency'] ?? ($client['bank_currency'] ?? 'EUR'),
    'initial_treasury_account_id' => $client['initial_treasury_account_id'] ?? '',
    'is_active' => (int)($client['is_active'] ?? 1),
];

if (!function_exists('clientEditValue')) {
    function clientEditValue(array $formData, string $key, mixed $default = ''): string
    {
        return e((string)($formData[$key] ?? $default));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'client_code' => trim((string)($_POST['client_code'] ?? '')),
        'generated_client_account' => trim((string)($_POST['generated_client_account'] ?? '')),
        'client_initial_balance' => trim((string)($_POST['client_initial_balance'] ?? '0')),
        'client_current_balance' => trim((string)($_POST['client_current_balance'] ?? '')),
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
        'initial_treasury_account_id' => ($_POST['initial_treasury_account_id'] ?? '') !== '' ? (int)$_POST['initial_treasury_account_id'] : '',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $firstName = $formData['first_name'];
        $lastName = $formData['last_name'];
        $fullName = trim($firstName . ' ' . $lastName);

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($formData['client_type'] === '') {
            throw new RuntimeException('Le type de client est obligatoire.');
        }

        if ($formData['country_commercial'] === '') {
            throw new RuntimeException('Le pays commercial est obligatoire.');
        }

        if ($formData['passport_issue_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_issue_date'])) {
            throw new RuntimeException('Date de délivrance du passport invalide.');
        }

        if ($formData['passport_expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_expiry_date'])) {
            throw new RuntimeException('Date d’expiration du passport invalide.');
        }

        if ($formData['passport_issue_date'] !== '' && $formData['passport_expiry_date'] !== '') {
            if ($formData['passport_expiry_date'] < $formData['passport_issue_date']) {
                throw new RuntimeException('La date d’expiration du passport doit être postérieure à la date de délivrance.');
            }
        }

        $before = $client;

        $clientCode = $formData['client_code'] !== ''
            ? preg_replace('/\D+/', '', $formData['client_code'])
            : preg_replace('/\D+/', '', (string)($client['client_code'] ?? ''));

        if ($clientCode === '') {
            throw new RuntimeException('Code client invalide.');
        }

        $generated411 = trim($formData['generated_client_account']);
        if ($generated411 === '') {
            $generated411 = sl_client_generate_411_from_code($clientCode);
        }

        if ($generated411 === '') {
            throw new RuntimeException('Le compte client 411 est invalide.');
        }

        $currencyCode = $formData['currency'] !== '' ? $formData['currency'] : 'EUR';
        $initialBalance = (float)str_replace(',', '.', $formData['client_initial_balance']);
        $currentBalance = $formData['client_current_balance'] !== ''
            ? (float)str_replace(',', '.', $formData['client_current_balance'])
            : $initialBalance;

        $pdo->beginTransaction();

        $bankAccountId = sl_client_upsert_411_bank_account($pdo, [
            'account_number' => $generated411,
            'account_label' => 'Compte client ' . $clientCode . ' - ' . $fullName,
            'currency' => $currencyCode,
            'initial_balance' => $initialBalance,
            'balance' => $currentBalance,
        ]);

        $updateFields = [];
        $params = [];

        $map = [
            'client_code' => $clientCode,
            'generated_client_account' => $generated411,
            'bank_account_id' => $bankAccountId,
            'first_name' => $firstName,
            'last_name' => $lastName,
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
            'currency' => $currencyCode,
            'initial_treasury_account_id' => $formData['initial_treasury_account_id'] !== '' ? (int)$formData['initial_treasury_account_id'] : null,
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

        $stmt = $pdo->prepare($clientSql);
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: $client;

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

        $pdo->commit();
        $successMessage = 'Client mis à jour avec succès.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un client';
$pageSubtitle = 'Mise à jour du profil client, du compte 411 et des soldes.';
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

        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="dashboard-grid-2">
                    <div>
                        <label>Code client</label>
                        <input type="text" name="client_code" id="client_code" value="<?= clientEditValue($formData, 'client_code') ?>" required>
                    </div>

                    <div>
                        <label>Compte 411 généré</label>
                        <input type="text" name="generated_client_account" id="generated_client_account" value="<?= clientEditValue($formData, 'generated_client_account') ?>">
                    </div>

                    <div>
                        <label>Solde initial du compte 411</label>
                        <input type="number" step="0.01" name="client_initial_balance" value="<?= clientEditValue($formData, 'client_initial_balance', '0') ?>">
                    </div>

                    <div>
                        <label>Solde courant du compte 411</label>
                        <input type="number" step="0.01" name="client_current_balance" value="<?= clientEditValue($formData, 'client_current_balance', '0') ?>">
                    </div>

                    <div>
                        <label>Prénom</label>
                        <input type="text" name="first_name" value="<?= clientEditValue($formData, 'first_name') ?>" required>
                    </div>

                    <div>
                        <label>Nom</label>
                        <input type="text" name="last_name" value="<?= clientEditValue($formData, 'last_name') ?>" required>
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?= clientEditValue($formData, 'email') ?>">
                    </div>

                    <div>
                        <label>Téléphone</label>
                        <input type="text" name="phone" value="<?= clientEditValue($formData, 'phone') ?>">
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label>Adresse postale</label>
                        <textarea name="postal_address" rows="3"><?= clientEditValue($formData, 'postal_address') ?></textarea>
                    </div>

                    <div>
                        <label>Numéro de passport</label>
                        <input type="text" name="passport_number" value="<?= clientEditValue($formData, 'passport_number') ?>">
                    </div>

                    <div>
                        <label>Lieu de délivrance du passport</label>
                        <select name="passport_issue_country">
                            <option value="">Choisir</option>
                            <?php foreach ($originCountries as $country): ?>
                                <option value="<?= e($country) ?>" <?= clientEditValue($formData, 'passport_issue_country') === $country ? 'selected' : '' ?>>
                                    <?= e($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Date de délivrance du passport</label>
                        <input type="date" name="passport_issue_date" value="<?= clientEditValue($formData, 'passport_issue_date') ?>">
                    </div>

                    <div>
                        <label>Date d’expiration du passport</label>
                        <input type="date" name="passport_expiry_date" value="<?= clientEditValue($formData, 'passport_expiry_date') ?>">
                    </div>

                    <div>
                        <label>Type de client</label>
                        <select name="client_type" required>
                            <option value="">Choisir</option>
                            <?php foreach ($clientTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= clientEditValue($formData, 'client_type') === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Devise</label>
                        <select name="currency">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= e($currency['code']) ?>" <?= clientEditValue($formData, 'currency', 'EUR') === $currency['code'] ? 'selected' : '' ?>>
                                    <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays d'origine</label>
                        <select name="country_origin">
                            <option value="">Choisir</option>
                            <?php foreach ($originCountries as $country): ?>
                                <option value="<?= e($country) ?>" <?= clientEditValue($formData, 'country_origin') === $country ? 'selected' : '' ?>>
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
                                <option value="<?= e($country) ?>" <?= clientEditValue($formData, 'country_destination') === $country ? 'selected' : '' ?>>
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
                                <option value="<?= e($country) ?>" <?= clientEditValue($formData, 'country_commercial') === $country ? 'selected' : '' ?>>
                                    <?= e($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Compte 512 lié</label>
                        <select name="initial_treasury_account_id">
                            <option value="">Aucun</option>
                            <?php foreach ($treasuryAccounts as $account): ?>
                                <option value="<?= (int)$account['id'] ?>" <?= clientEditValue($formData, 'initial_treasury_account_id') === (string)$account['id'] ? 'selected' : '' ?>>
                                    <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label style="display:flex; gap:10px; align-items:center;">
                        <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        Client actif
                    </label>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientCodeInput = document.getElementById('client_code');
            const clientAccountInput = document.getElementById('generated_client_account');

            if (!clientCodeInput || !clientAccountInput) {
                return;
            }

            function sync411Account() {
                const raw = (clientCodeInput.value || '').replace(/\D+/g, '');
                const current = (clientAccountInput.value || '').trim();

                if (current === '' || current.startsWith('411')) {
                    clientAccountInput.value = raw !== '' ? ('411' + raw) : '';
                }
            }

            clientCodeInput.addEventListener('input', sync411Account);
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>