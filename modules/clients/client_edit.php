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
$currencies = function_exists('sl_get_currency_options')
    ? sl_get_currency_options($pdo)
    : [
        ['code' => 'EUR', 'label' => 'Euro'],
        ['code' => 'USD', 'label' => 'Dollar US'],
    ];

if (!function_exists('sl_client_currency_codes_edit')) {
    function sl_client_currency_codes_edit(array $currencies): array
    {
        $codes = [];
        foreach ($currencies as $currency) {
            $code = trim((string)($currency['code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return $codes;
    }
}

if (!function_exists('sl_format_money_input_edit')) {
    function sl_format_money_input_edit(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        return number_format((float)$value, 2, '.', '');
    }
}

if (!function_exists('sl_client_preview_account_411_edit')) {
    function sl_client_preview_account_411_edit(string $clientCode, string $manualValue = ''): string
    {
        $manualValue = trim($manualValue);
        if ($manualValue !== '') {
            return $manualValue;
        }

        $clientCode = preg_replace('/\D+/', '', trim($clientCode));
        if ($clientCode === '') {
            return '';
        }

        return '411' . $clientCode;
    }
}

if (!function_exists('sl_sync_client_411_account_edit')) {
    function sl_sync_client_411_account_edit(PDO $pdo, int $clientId, array $clientData): void
    {
        if (!tableExists($pdo, 'bank_accounts')) {
            return;
        }

        $accountNumber = trim((string)($clientData['generated_client_account'] ?? ''));
        if ($accountNumber === '') {
            return;
        }

        $accountName = 'Compte client 411 - ' . trim((string)($clientData['full_name'] ?? 'Client'));
        $currencyCode = trim((string)($clientData['currency'] ?? 'EUR'));
        $initialBalance = (float)($clientData['initial_balance'] ?? 0);
        $currentBalance = (float)($clientData['current_balance'] ?? $initialBalance);

        $existing = null;

        if (columnExists($pdo, 'bank_accounts', 'account_number')) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM bank_accounts
                WHERE account_number = ?
                LIMIT 1
            ");
            $stmt->execute([$accountNumber]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$existing && columnExists($pdo, 'bank_accounts', 'client_id')) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM bank_accounts
                WHERE client_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$clientId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $map = [
            'client_id' => $clientId,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'currency_code' => $currencyCode,
            'initial_balance' => $initialBalance,
            'balance' => $currentBalance,
            'is_active' => (int)($clientData['is_active'] ?? 1),
        ];

        if ($existing) {
            $fields = [];
            $params = [];

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
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

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

        if ($columns) {
            $stmt = $pdo->prepare("
                INSERT INTO bank_accounts (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmt->execute($params);
        }
    }
}

if (!function_exists('sl_client_edit_value')) {
    function sl_client_edit_value(array $formData, string $key, mixed $default = ''): string
    {
        return e((string)($formData[$key] ?? $default));
    }
}

$formData = [
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
    'initial_treasury_account_id' => $client['initial_treasury_account_id'] ?? '',
    'client_code_preview' => $client['client_code'] ?? '',
    'generated_client_account' => $client['generated_client_account'] ?? '',
    'initial_balance' => sl_format_money_input_edit($client['initial_balance'] ?? 0),
    'current_balance' => sl_format_money_input_edit($client['current_balance'] ?? ($client['balance'] ?? 0)),
    'is_active' => (int)($client['is_active'] ?? 1),
];

$errorMessage = '';
$successMessage = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
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
        'client_code_preview' => trim((string)($_POST['client_code_preview'] ?? '')),
        'generated_client_account' => trim((string)($_POST['generated_client_account'] ?? '')),
        'initial_balance' => sl_format_money_input_edit($_POST['initial_balance'] ?? '0'),
        'current_balance' => sl_format_money_input_edit($_POST['current_balance'] ?? '0'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($formData['first_name'] === '' || $formData['last_name'] === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($formData['client_type'] === '') {
            throw new RuntimeException('Le type de client est obligatoire.');
        }

        if ($formData['country_commercial'] === '') {
            throw new RuntimeException('Le pays commercial est obligatoire.');
        }

        if (!in_array($formData['currency'], sl_client_currency_codes_edit($currencies), true)) {
            throw new RuntimeException('La devise sélectionnée est invalide.');
        }

        if ($formData['passport_issue_country'] !== '' && !in_array($formData['passport_issue_country'], $originCountries, true)) {
            throw new RuntimeException('Le lieu de délivrance du passeport est invalide.');
        }

        if ($formData['passport_issue_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_issue_date'])) {
            throw new RuntimeException('Date de délivrance du passeport invalide.');
        }

        if ($formData['passport_expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_expiry_date'])) {
            throw new RuntimeException('Date d’expiration du passeport invalide.');
        }

        if ($formData['passport_issue_date'] !== '' && $formData['passport_expiry_date'] !== '') {
            if ($formData['passport_expiry_date'] < $formData['passport_issue_date']) {
                throw new RuntimeException('La date d’expiration du passeport doit être postérieure à la date de délivrance.');
            }
        }

        $clientCode = $formData['client_code_preview'] !== ''
            ? preg_replace('/\D+/', '', $formData['client_code_preview'])
            : preg_replace('/\D+/', '', (string)($client['client_code'] ?? ''));

        if ($clientCode === '') {
            throw new RuntimeException('Le code client est invalide.');
        }

        $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);
        $generated411 = sl_client_preview_account_411_edit($clientCode, $formData['generated_client_account']);
        $initialBalance = (float)$formData['initial_balance'];
        $currentBalance = (float)$formData['current_balance'];

        $preview = [
            'client_code' => $clientCode,
            'full_name' => $fullName,
            'generated_client_account' => $generated411,
            'initial_balance' => $initialBalance,
            'current_balance' => $currentBalance,
            'currency' => $formData['currency'],
            'is_manual_411' => trim($formData['generated_client_account']) !== '' && trim($formData['generated_client_account']) !== ('411' . $clientCode),
        ];

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $before = $client;

            $updateFields = [];
            $params = [];

            $map = [
                'client_code' => $clientCode,
                'generated_client_account' => $generated411,
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
                'currency' => $formData['currency'],
                'initial_treasury_account_id' => $formData['initial_treasury_account_id'] !== '' ? (int)$formData['initial_treasury_account_id'] : null,
                'initial_balance' => $initialBalance,
                'current_balance' => $currentBalance,
                'balance' => $currentBalance,
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

            sl_sync_client_411_account_edit($pdo, $id, [
                'generated_client_account' => $generated411,
                'full_name' => $fullName,
                'currency' => $formData['currency'],
                'initial_balance' => $initialBalance,
                'current_balance' => $currentBalance,
                'is_active' => $formData['is_active'],
            ]);

            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
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
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un client';
$pageSubtitle = 'Mise à jour du profil client, du compte 411 et des informations passeport.';
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
                <h3>Modification du client</h3>
                <p class="muted">Prévisualise les impacts avant d’enregistrer les modifications.</p>

                <form method="POST" id="clientEditForm">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Prénom</label>
                            <input type="text" name="first_name" value="<?= sl_client_edit_value($formData, 'first_name') ?>" required>
                        </div>

                        <div>
                            <label>Nom</label>
                            <input type="text" name="last_name" value="<?= sl_client_edit_value($formData, 'last_name') ?>" required>
                        </div>

                        <div>
                            <label>Code client</label>
                            <input type="text" name="client_code_preview" id="client_code_preview" value="<?= sl_client_edit_value($formData, 'client_code_preview') ?>" required>
                        </div>

                        <div>
                            <label>Compte 411 client</label>
                            <input type="text" name="generated_client_account" id="generated_client_account" value="<?= sl_client_edit_value($formData, 'generated_client_account') ?>" placeholder="Auto : 411 + code client">
                        </div>

                        <div>
                            <label>Email</label>
                            <input type="email" name="email" value="<?= sl_client_edit_value($formData, 'email') ?>">
                        </div>

                        <div>
                            <label>Téléphone</label>
                            <input type="text" name="phone" value="<?= sl_client_edit_value($formData, 'phone') ?>">
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency" required>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= ($formData['currency'] ?? 'EUR') === $currency['code'] ? 'selected' : '' ?>>
                                        <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type de client</label>
                            <select name="client_type" required>
                                <option value="">Choisir</option>
                                <?php foreach ($clientTypes as $type): ?>
                                    <option value="<?= e($type) ?>" <?= ($formData['client_type'] ?? '') === $type ? 'selected' : '' ?>>
                                        <?= e($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Solde initial du compte 411</label>
                            <input type="number" step="0.01" name="initial_balance" value="<?= sl_client_edit_value($formData, 'initial_balance') ?>">
                        </div>

                        <div>
                            <label>Solde courant du compte 411</label>
                            <input type="number" step="0.01" name="current_balance" value="<?= sl_client_edit_value($formData, 'current_balance') ?>">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label>Adresse postale</label>
                            <textarea name="postal_address" rows="3"><?= sl_client_edit_value($formData, 'postal_address') ?></textarea>
                        </div>

                        <div>
                            <label>Numéro de passeport</label>
                            <input type="text" name="passport_number" value="<?= sl_client_edit_value($formData, 'passport_number') ?>">
                        </div>

                        <div>
                            <label>Lieu de délivrance du passeport</label>
                            <select name="passport_issue_country">
                                <option value="">Choisir</option>
                                <?php foreach ($originCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= ($formData['passport_issue_country'] ?? '') === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Date de délivrance du passeport</label>
                            <input type="date" name="passport_issue_date" value="<?= sl_client_edit_value($formData, 'passport_issue_date') ?>">
                        </div>

                        <div>
                            <label>Date d’expiration du passeport</label>
                            <input type="date" name="passport_expiry_date" value="<?= sl_client_edit_value($formData, 'passport_expiry_date') ?>">
                        </div>

                        <div>
                            <label>Pays d'origine</label>
                            <select name="country_origin">
                                <option value="">Choisir</option>
                                <?php foreach ($originCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= ($formData['country_origin'] ?? '') === $country ? 'selected' : '' ?>>
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
                                    <option value="<?= e($country) ?>" <?= ($formData['country_destination'] ?? '') === $country ? 'selected' : '' ?>>
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
                                    <option value="<?= e($country) ?>" <?= ($formData['country_commercial'] ?? '') === $country ? 'selected' : '' ?>>
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
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($formData['initial_treasury_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
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
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <?php
                $displayPreview = $preview ?: [
                    'client_code' => (string)($formData['client_code_preview'] ?? ($client['client_code'] ?? '')),
                    'full_name' => trim((string)($formData['first_name'] ?? '') . ' ' . (string)($formData['last_name'] ?? '')),
                    'generated_client_account' => trim((string)($formData['generated_client_account'] ?? ($client['generated_client_account'] ?? ''))),
                    'initial_balance' => (float)($formData['initial_balance'] ?? ($client['initial_balance'] ?? 0)),
                    'current_balance' => (float)($formData['current_balance'] ?? ($client['current_balance'] ?? ($client['balance'] ?? 0))),
                    'currency' => (string)($formData['currency'] ?? ($client['currency'] ?? 'EUR')),
                    'is_manual_411' => false,
                ];
                ?>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Code client</span>
                        <strong><?= e($displayPreview['client_code']) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Nom complet</span>
                        <strong><?= e($displayPreview['full_name']) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Compte 411</span>
                        <strong><?= e($displayPreview['generated_client_account']) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Solde initial</span>
                        <strong><?= e(number_format((float)$displayPreview['initial_balance'], 2, ',', ' ')) ?> <?= e($displayPreview['currency']) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Solde courant</span>
                        <strong><?= e(number_format((float)$displayPreview['current_balance'], 2, ',', ' ')) ?> <?= e($displayPreview['currency']) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Compte 411 manuel</span>
                        <strong><?= !empty($displayPreview['is_manual_411']) ? 'Oui' : 'Non' ?></strong>
                    </div>
                </div>

                <div class="dashboard-note" style="margin-top:18px;">
                    Vérifie le compte 411, la devise et les soldes avant de valider les modifications.
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientCodeInput = document.getElementById('client_code_preview');
            const account411Input = document.getElementById('generated_client_account');

            if (!clientCodeInput || !account411Input) {
                return;
            }

            function normalizeDigits(value) {
                return String(value || '').replace(/\D+/g, '');
            }

            function autoBuild411() {
                const currentManual = account411Input.dataset.manual === '1';
                if (currentManual) return;

                const code = normalizeDigits(clientCodeInput.value);
                account411Input.value = code !== '' ? ('411' + code) : '';
            }

            account411Input.addEventListener('input', function () {
                const code = normalizeDigits(clientCodeInput.value);
                const autoValue = code !== '' ? ('411' + code) : '';
                account411Input.dataset.manual = (account411Input.value !== '' && account411Input.value !== autoValue) ? '1' : '0';
            });

            clientCodeInput.addEventListener('input', autoBuild411);

            const initialCode = normalizeDigits(clientCodeInput.value);
            const initialAuto = initialCode !== '' ? ('411' + initialCode) : '';
            account411Input.dataset.manual = (account411Input.value !== '' && account411Input.value !== initialAuto) ? '1' : '0';

            autoBuild411();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>