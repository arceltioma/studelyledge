<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_create_page');
} else {
    enforcePagePermission($pdo, 'clients_create');
}

$pageTitle = 'Créer un client';
$pageSubtitle = 'Création complète d’une fiche client avec rattachement financier et informations d’identité';

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
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

$successMessage = '';
$errorMessage = '';

$formData = [
    'client_code' => '',
    'generated_client_account' => '',
    'client_initial_balance' => '0',
    'client_current_balance' => '',
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
    'initial_treasury_account_id' => '',
    'is_active' => 1,
];

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

        if ($formData['first_name'] === '' || $formData['last_name'] === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($formData['client_type'] === '') {
            throw new RuntimeException('Le type de client est obligatoire.');
        }

        if ($formData['country_commercial'] === '') {
            throw new RuntimeException('Le pays commercial est obligatoire.');
        }

        if ($formData['passport_issue_date'] !== '' && $formData['passport_expiry_date'] !== '') {
            if ($formData['passport_expiry_date'] < $formData['passport_issue_date']) {
                throw new RuntimeException('La date d’expiration du passport doit être postérieure à sa date de délivrance.');
            }
        }

        $clientCode = $formData['client_code'] !== ''
            ? preg_replace('/\D+/', '', $formData['client_code'])
            : (function_exists('generateClientCode') ? generateClientCode($pdo) : (string)random_int(100000000, 999999999));

        if ($clientCode === '') {
            throw new RuntimeException('Code client invalide.');
        }

        $generated411 = trim($formData['generated_client_account']);
        if ($generated411 === '') {
            $generated411 = sl_client_generate_411_from_code($clientCode);
        }

        if ($generated411 === '') {
            throw new RuntimeException('Le compte client 411 généré est invalide.');
        }

        $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);
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

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'client_code' => $clientCode,
            'generated_client_account' => $generated411,
            'bank_account_id' => $bankAccountId,
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
            'currency' => $currencyCode,
            'initial_treasury_account_id' => $formData['initial_treasury_account_id'] !== '' ? (int)$formData['initial_treasury_account_id'] : null,
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

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_client',
                'clients',
                'client',
                $newId,
                'Création du client ' . $clientCode
            );
        }

        if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
            createNotification(
                $pdo,
                'client_create',
                'Client créé : ' . $clientCode . ' - ' . $fullName,
                'success',
                APP_URL . 'modules/clients/client_view.php?id=' . $newId,
                'client',
                $newId,
                (int)$_SESSION['user_id']
            );
        }

        $pdo->commit();

        $successMessage = 'Client créé avec succès.';
        $formData = [
            'client_code' => '',
            'generated_client_account' => '',
            'client_initial_balance' => '0',
            'client_current_balance' => '',
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
            'initial_treasury_account_id' => '',
            'is_active' => 1,
        ];
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

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code client</label>
                            <input type="text" name="client_code" id="client_code" value="<?= e($formData['client_code']) ?>" placeholder="Auto si vide">
                        </div>

                        <div>
                            <label>Compte 411 généré</label>
                            <input type="text" name="generated_client_account" id="generated_client_account" value="<?= e($formData['generated_client_account']) ?>" placeholder="Auto : 411 + code client">
                        </div>

                        <div>
                            <label>Solde initial du compte 411</label>
                            <input type="number" step="0.01" name="client_initial_balance" value="<?= e($formData['client_initial_balance']) ?>">
                        </div>

                        <div>
                            <label>Solde courant du compte 411</label>
                            <input type="number" step="0.01" name="client_current_balance" value="<?= e($formData['client_current_balance']) ?>" placeholder="Auto = solde initial si vide">
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
                            <label>Devise</label>
                            <select name="currency">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= $formData['currency'] === $currency['code'] ? 'selected' : '' ?>>
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
                            <label>Compte 512 lié</label>
                            <select name="initial_treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)$formData['initial_treasury_account_id'] === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                            Client actif
                        </label>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Créer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Lecture</h3>
                <div class="dashboard-note">
                    Le compte client 411 peut être généré automatiquement à partir du code client, tout en restant modifiable manuellement si nécessaire.
                    Les soldes affichés et pilotés sont ceux du compte lié dans <strong>bank_accounts</strong> via les colonnes
                    <strong>initial_balance</strong> et <strong>balance</strong>.
                </div>
            </div>
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