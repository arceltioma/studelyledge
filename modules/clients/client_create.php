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

$successMessage = '';
$errorMessage = '';
$previewRows = [];
$isPreviewMode = false;

$formData = [
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

if (!function_exists('sl_client_create_preview_value')) {
    function sl_client_create_preview_value(array $formData, string $key, mixed $default = '—'): string
    {
        $value = $formData[$key] ?? $default;
        if ($value === '' || $value === null) {
            return (string)$default;
        }
        return (string)$value;
    }
}

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
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

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

        if ($formData['passport_issue_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_issue_date'])) {
            throw new RuntimeException('La date de délivrance du passport est invalide.');
        }

        if ($formData['passport_expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['passport_expiry_date'])) {
            throw new RuntimeException('La date d’expiration du passport est invalide.');
        }

        if ($formData['passport_issue_date'] !== '' && $formData['passport_expiry_date'] !== '') {
            if ($formData['passport_expiry_date'] < $formData['passport_issue_date']) {
                throw new RuntimeException('La date d’expiration du passport doit être postérieure à sa date de délivrance.');
            }
        }

        $previewClientCode = function_exists('generateClientCode')
            ? generateClientCode($pdo)
            : (string)random_int(100000000, 999999999);

        $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);

        $selectedTreasuryLabel = 'Aucun';
        if ($formData['initial_treasury_account_id'] !== '') {
            foreach ($treasuryAccounts as $account) {
                if ((int)$account['id'] === (int)$formData['initial_treasury_account_id']) {
                    $selectedTreasuryLabel = trim((string)($account['account_code'] ?? '') . ' - ' . (string)($account['account_label'] ?? ''));
                    break;
                }
            }
        }

        $previewRows = [
            'Code client prévisionnel' => $previewClientCode,
            'Nom complet' => $fullName !== '' ? $fullName : '—',
            'Type client' => sl_client_create_preview_value($formData, 'client_type'),
            'Email' => sl_client_create_preview_value($formData, 'email'),
            'Téléphone' => sl_client_create_preview_value($formData, 'phone'),
            'Adresse postale' => sl_client_create_preview_value($formData, 'postal_address'),
            'Passport' => sl_client_create_preview_value($formData, 'passport_number'),
            'Lieu délivrance passport' => sl_client_create_preview_value($formData, 'passport_issue_country'),
            'Date délivrance passport' => sl_client_create_preview_value($formData, 'passport_issue_date'),
            'Date expiration passport' => sl_client_create_preview_value($formData, 'passport_expiry_date'),
            'Pays origine' => sl_client_create_preview_value($formData, 'country_origin'),
            'Pays destination' => sl_client_create_preview_value($formData, 'country_destination'),
            'Pays commercial' => sl_client_create_preview_value($formData, 'country_commercial'),
            'Devise' => sl_client_create_preview_value($formData, 'currency', 'EUR'),
            'Compte 512 lié' => $selectedTreasuryLabel,
            'Statut' => (int)$formData['is_active'] === 1 ? 'Actif' : 'Inactif',
        ];

        if ($actionMode === 'preview') {
            $isPreviewMode = true;
            $successMessage = 'Prévisualisation prête. Vérifie le résumé dans le bloc de droite avant validation.';
        } else {
            $clientCode = $previewClientCode;

            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'client_code' => $clientCode,
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

            $successMessage = 'Client créé avec succès.';
            $previewRows = [];
            $isPreviewMode = false;

            $formData = [
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
        }
    } catch (Throwable $e) {
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
                            <input type="text" name="currency" value="<?= e($formData['currency']) ?>">
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
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <?php if ($previewRows): ?>
                    <div class="sl-data-list">
                        <?php foreach ($previewRows as $label => $value): ?>
                            <div class="sl-data-list__row">
                                <span><?= e((string)$label) ?></span>
                                <strong><?= e((string)$value) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($isPreviewMode): ?>
                        <div class="dashboard-note" style="margin-top:16px;">
                            La fiche est prête. Si tout est correct, clique sur <strong>Créer</strong> pour enregistrer définitivement le client.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        Renseigne la fiche dans le bloc de gauche puis clique sur <strong>Prévisualiser</strong> pour contrôler le résumé avant enregistrement.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>