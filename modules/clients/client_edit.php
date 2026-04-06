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
$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [['code' => 'EUR', 'label' => 'Euro']];

if (!function_exists('clientEditValue')) {
    function clientEditValue(array $formData, string $key, mixed $default = ''): string
    {
        return e((string)($formData[$key] ?? $default));
    }
}

$pageTitle = 'Modifier un client';
$pageSubtitle = 'Mise à jour du profil client avec prévisualisation avant validation.';

$errorMessage = '';
$successMessage = '';
$previewMode = false;

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
    'generated_client_account' => $client['generated_client_account'] ?? ('411' . ($client['client_code'] ?? '')),
    'initial_client_balance' => (string)($client['initial_client_balance'] ?? '0'),
    'is_active' => (int)($client['is_active'] ?? 1),
];

$previewData = $formData;

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
        'generated_client_account' => trim((string)($_POST['generated_client_account'] ?? ($client['generated_client_account'] ?? ''))),
        'initial_client_balance' => trim((string)($_POST['initial_client_balance'] ?? '0')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $previewData = $formData;
    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

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

        if ($formData['generated_client_account'] === '') {
            $formData['generated_client_account'] = (string)($client['generated_client_account'] ?? ('411' . ($client['client_code'] ?? '')));
        }

        if (!preg_match('/^411/i', $formData['generated_client_account'])) {
            throw new RuntimeException('Le compte client généré doit commencer par 411.');
        }

        if (!is_numeric($formData['initial_client_balance'])) {
            throw new RuntimeException('Le solde initial du compte 411 est invalide.');
        }

        $previewData = $formData;
        $previewMode = true;

        if ($actionMode === 'save') {
            $before = $client;

            $updateFields = [];
            $params = [];

            $map = [
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
                'currency' => $formData['currency'] !== '' ? $formData['currency'] : 'EUR',
                'initial_treasury_account_id' => $formData['initial_treasury_account_id'] !== '' ? (int)$formData['initial_treasury_account_id'] : null,
                'generated_client_account' => $formData['generated_client_account'],
                'initial_client_balance' => (float)$formData['initial_client_balance'],
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

            $successMessage = 'Client mis à jour avec succès.';
            $previewMode = false;

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
                'generated_client_account' => $client['generated_client_account'] ?? ('411' . ($client['client_code'] ?? '')),
                'initial_client_balance' => (string)($client['initial_client_balance'] ?? '0'),
                'is_active' => (int)($client['is_active'] ?? 1),
            ];
            $previewData = $formData;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$selectedTreasuryLabel = 'Aucun';
foreach ($treasuryAccounts as $account) {
    if ((string)$previewData['initial_treasury_account_id'] === (string)$account['id']) {
        $selectedTreasuryLabel = trim((string)($account['account_code'] ?? '') . ' - ' . (string)($account['account_label'] ?? ''));
        break;
    }
}

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
                            <select name="currency" required>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= clientEditValue($formData, 'currency', 'EUR') === (string)$currency['code'] ? 'selected' : '' ?>>
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

                        <div>
                            <label>Compte client généré (411)</label>
                            <input type="text" name="generated_client_account" value="<?= clientEditValue($formData, 'generated_client_account') ?>" required>
                        </div>

                        <div>
                            <label>Solde initial du compte 411</label>
                            <input type="number" step="0.01" name="initial_client_balance" value="<?= clientEditValue($formData, 'initial_client_balance', '0') ?>">
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
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Aperçu avant validation</h3>

                <?php if ($previewMode): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e(trim(($previewData['first_name'] ?? '') . ' ' . ($previewData['last_name'] ?? ''))) ?></strong></div>
                        <div class="sl-data-list__row"><span>Email</span><strong><?= e($previewData['email'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Téléphone</span><strong><?= e($previewData['phone'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Type client</span><strong><?= e($previewData['client_type'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Devise</span><strong><?= e($previewData['currency'] ?: 'EUR') ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays commercial</span><strong><?= e($previewData['country_commercial'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Pays destination</span><strong><?= e($previewData['country_destination'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411 généré</span><strong><?= e($previewData['generated_client_account'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde initial 411</span><strong><?= e(number_format((float)($previewData['initial_client_balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 512 lié</span><strong><?= e($selectedTreasuryLabel) ?></strong></div>
                        <div class="sl-data-list__row"><span>Passport</span><strong><?= e($previewData['passport_number'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Délivrance passport</span><strong><?= e(($previewData['passport_issue_country'] ?: '—') . ' / ' . ($previewData['passport_issue_date'] ?: '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Expiration passport</span><strong><?= e($previewData['passport_expiry_date'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>Adresse</span><strong><?= e($previewData['postal_address'] ?: '—') ?></strong></div>
                        <div class="sl-data-list__row"><span>État</span><strong><?= (int)($previewData['is_active'] ?? 0) === 1 ? 'Actif' : 'Archivé' ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Clique sur <strong>Prévisualiser</strong> pour afficher dans ce bloc le résumé complet des modifications avant validation.
                    </div>

                    <div class="sl-data-list" style="margin-top:16px;">
                        <div class="sl-data-list__row"><span>Client actuel</span><strong><?= e((string)($client['client_code'] ?? '') . ' - ' . (string)($client['full_name'] ?? '')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e((string)($client['generated_client_account'] ?? '—')) ?></strong></div>
                        <div class="sl-data-list__row"><span>Solde initial 411</span><strong><?= e(number_format((float)($client['initial_client_balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>