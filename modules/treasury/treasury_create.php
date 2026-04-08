<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_create_page');
} else {
    enforcePagePermission($pdo, 'treasury_create');
}

if (!tableExists($pdo, 'treasury_accounts')) {
    exit('Table treasury_accounts introuvable.');
}

$pageTitle = 'Créer un compte de trésorerie';
$pageSubtitle = 'Ajout complet d’un compte interne 512 avec prévisualisation avant validation';

$currencies = function_exists('sl_get_currency_options')
    ? sl_get_currency_options($pdo)
    : [['code' => 'EUR', 'label' => 'Euro']];

$commercialCountries = function_exists('studely_commercial_countries')
    ? studely_commercial_countries()
    : [];

$countryTypes = ['Filiale', 'Partenaire', 'Siège', 'Autre'];
$paymentPlaces = ['Local', 'International', 'Mixte'];

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$formData = [
    'account_code' => '',
    'account_label' => '',
    'bank_name' => '',
    'subsidiary_name' => '',
    'zone_code' => '',
    'country_label' => '',
    'country_type' => 'Filiale',
    'payment_place' => 'Local',
    'currency_code' => 'EUR',
    'opening_balance' => '0',
    'current_balance' => '0',
    'is_active' => 1,
];

if (!function_exists('sl_treasury_create_value')) {
    function sl_treasury_create_value(array $data, string $key, string $default = ''): string
    {
        return e((string)($data[$key] ?? $default));
    }
}

if (!function_exists('sl_treasury_build_create_preview')) {
    function sl_treasury_build_create_preview(array $formData): array
    {
        $openingBalance = (float)($formData['opening_balance'] ?? 0);
        $currentBalance = (float)($formData['current_balance'] ?? 0);

        return [
            'account_code' => trim((string)($formData['account_code'] ?? '')),
            'account_label' => trim((string)($formData['account_label'] ?? '')),
            'bank_name' => trim((string)($formData['bank_name'] ?? '')),
            'subsidiary_name' => trim((string)($formData['subsidiary_name'] ?? '')),
            'zone_code' => trim((string)($formData['zone_code'] ?? '')),
            'country_label' => trim((string)($formData['country_label'] ?? '')),
            'country_type' => trim((string)($formData['country_type'] ?? '')),
            'payment_place' => trim((string)($formData['payment_place'] ?? '')),
            'currency_code' => trim((string)($formData['currency_code'] ?? 'EUR')),
            'opening_balance' => $openingBalance,
            'current_balance' => $currentBalance,
            'is_active' => (int)($formData['is_active'] ?? 0),
            'delta_balance' => $currentBalance - $openingBalance,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'account_code' => trim((string)($_POST['account_code'] ?? '')),
        'account_label' => trim((string)($_POST['account_label'] ?? '')),
        'bank_name' => trim((string)($_POST['bank_name'] ?? '')),
        'subsidiary_name' => trim((string)($_POST['subsidiary_name'] ?? '')),
        'zone_code' => trim((string)($_POST['zone_code'] ?? '')),
        'country_label' => trim((string)($_POST['country_label'] ?? '')),
        'country_type' => trim((string)($_POST['country_type'] ?? 'Filiale')),
        'payment_place' => trim((string)($_POST['payment_place'] ?? 'Local')),
        'currency_code' => trim((string)($_POST['currency_code'] ?? 'EUR')),
        'opening_balance' => trim((string)($_POST['opening_balance'] ?? '0')),
        'current_balance' => trim((string)($_POST['current_balance'] ?? '0')),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['account_code'] === '') {
            throw new RuntimeException('Le code compte est obligatoire.');
        }

        if ($formData['account_label'] === '') {
            throw new RuntimeException('L’intitulé est obligatoire.');
        }

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM treasury_accounts WHERE account_code = ?");
        $stmtCheck->execute([$formData['account_code']]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Ce code compte existe déjà.');
        }

        if ($actionMode === 'preview') {
            $previewMode = true;
            $previewData = sl_treasury_build_create_preview($formData);
        }

        if ($actionMode === 'save') {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'account_code' => $formData['account_code'],
                'account_label' => $formData['account_label'],
                'bank_name' => $formData['bank_name'] !== '' ? $formData['bank_name'] : null,
                'subsidiary_name' => $formData['subsidiary_name'] !== '' ? $formData['subsidiary_name'] : null,
                'zone_code' => $formData['zone_code'] !== '' ? $formData['zone_code'] : null,
                'country_label' => $formData['country_label'] !== '' ? $formData['country_label'] : null,
                'country_type' => $formData['country_type'] !== '' ? $formData['country_type'] : null,
                'payment_place' => $formData['payment_place'] !== '' ? $formData['payment_place'] : null,
                'currency_code' => $formData['currency_code'] !== '' ? $formData['currency_code'] : 'EUR',
                'opening_balance' => (float)$formData['opening_balance'],
                'current_balance' => (float)$formData['current_balance'],
                'is_active' => $formData['is_active'],
            ];

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'treasury_accounts', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'treasury_accounts', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }
            if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $stmt = $pdo->prepare("
                INSERT INTO treasury_accounts (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")
            ");
            $stmt->execute($params);

            $newId = (int)$pdo->lastInsertId();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_treasury_account',
                    'treasury',
                    'treasury_account',
                    $newId,
                    'Création d’un compte interne 512'
                );
            }

            $successMessage = 'Compte de trésorerie créé avec succès.';
            $previewMode = false;
            $previewData = null;

            $formData = [
                'account_code' => '',
                'account_label' => '',
                'bank_name' => '',
                'subsidiary_name' => '',
                'zone_code' => '',
                'country_label' => '',
                'country_type' => 'Filiale',
                'payment_place' => 'Local',
                'currency_code' => 'EUR',
                'opening_balance' => '0',
                'current_balance' => '0',
                'is_active' => 1,
            ];
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        $previewMode = false;
        $previewData = null;
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
                    <label>Code compte</label>
                    <input type="text" name="account_code" value="<?= sl_treasury_create_value($formData, 'account_code') ?>" required>
                </div>

                <div>
                    <label>Intitulé</label>
                    <input type="text" name="account_label" value="<?= sl_treasury_create_value($formData, 'account_label') ?>" required>
                </div>

                <div>
                    <label>Banque</label>
                    <input type="text" name="bank_name" value="<?= sl_treasury_create_value($formData, 'bank_name') ?>">
                </div>

                <div>
                    <label>Filiale</label>
                    <input type="text" name="subsidiary_name" value="<?= sl_treasury_create_value($formData, 'subsidiary_name') ?>">
                </div>

                <div>
                    <label>Zone code</label>
                    <input type="text" name="zone_code" value="<?= sl_treasury_create_value($formData, 'zone_code') ?>">
                </div>

                <div>
                    <label>Pays</label>
                    <select name="country_label">
                        <option value="">Choisir</option>
                        <?php foreach ($commercialCountries as $country): ?>
                            <option value="<?= e($country) ?>" <?= ($formData['country_label'] ?? '') === $country ? 'selected' : '' ?>>
                                <?= e($country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Type de pays</label>
                    <select name="country_type">
                        <?php foreach ($countryTypes as $item): ?>
                            <option value="<?= e($item) ?>" <?= ($formData['country_type'] ?? '') === $item ? 'selected' : '' ?>>
                                <?= e($item) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Lieu de paiement</label>
                    <select name="payment_place">
                        <?php foreach ($paymentPlaces as $item): ?>
                            <option value="<?= e($item) ?>" <?= ($formData['payment_place'] ?? '') === $item ? 'selected' : '' ?>>
                                <?= e($item) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Devise</label>
                    <select name="currency_code">
                        <?php foreach ($currencies as $currency): ?>
                            <option value="<?= e($currency['code']) ?>" <?= ($formData['currency_code'] ?? 'EUR') === $currency['code'] ? 'selected' : '' ?>>
                                <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Solde ouverture</label>
                    <input type="number" step="0.01" name="opening_balance" value="<?= sl_treasury_create_value($formData, 'opening_balance', '0') ?>">
                </div>

                <div>
                    <label>Solde courant</label>
                    <input type="number" step="0.01" name="current_balance" value="<?= sl_treasury_create_value($formData, 'current_balance', '0') ?>">
                </div>
            </div>

            <div style="margin-top:10px;">
                <label style="display:flex; gap:10px; align-items:center;">
                    <input type="checkbox" name="is_active" value="1" <?= (int)($formData['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Compte actif
                </label>
            </div>

            <div class="btn-group" style="margin-top:20px;">
                <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3><?= $previewMode ? 'Prévisualisation avant création' : 'Lecture' ?></h3>

        <?php if ($previewMode && $previewData): ?>
            <div class="sl-data-list">
                <div class="sl-data-list__row"><span>Code</span><strong><?= e($previewData['account_code']) ?></strong></div>
                <div class="sl-data-list__row"><span>Intitulé</span><strong><?= e($previewData['account_label']) ?></strong></div>
                <div class="sl-data-list__row"><span>Banque</span><strong><?= e($previewData['bank_name'] !== '' ? $previewData['bank_name'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Filiale</span><strong><?= e($previewData['subsidiary_name'] !== '' ? $previewData['subsidiary_name'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Zone</span><strong><?= e($previewData['zone_code'] !== '' ? $previewData['zone_code'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Pays</span><strong><?= e($previewData['country_label'] !== '' ? $previewData['country_label'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Type de pays</span><strong><?= e($previewData['country_type'] !== '' ? $previewData['country_type'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Lieu de paiement</span><strong><?= e($previewData['payment_place'] !== '' ? $previewData['payment_place'] : '—') ?></strong></div>
                <div class="sl-data-list__row"><span>Devise</span><strong><?= e($previewData['currency_code']) ?></strong></div>
                <div class="sl-data-list__row"><span>Solde ouverture</span><strong><?= number_format((float)$previewData['opening_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Solde courant</span><strong><?= number_format((float)$previewData['current_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Écart courant / ouverture</span><strong><?= number_format((float)$previewData['delta_balance'], 2, ',', ' ') ?></strong></div>
                <div class="sl-data-list__row"><span>Statut</span><strong><?= (int)$previewData['is_active'] === 1 ? 'Actif' : 'Archivé' ?></strong></div>
            </div>

            <div class="dashboard-note" style="margin-top:16px;">
                Vérifie le code 512, la devise et la cohérence entre le solde d’ouverture et le solde courant avant validation.
            </div>
        <?php else: ?>
            <div class="dashboard-note">
                Les comptes 512 servent au suivi de la trésorerie bancaire. Cette page est alignée sur la BDD actuelle :
                <strong>opening_balance</strong>, <strong>current_balance</strong>, <strong>currency_code</strong>, <strong>country_label</strong>,
                <strong>country_type</strong>, <strong>payment_place</strong>, <strong>is_active</strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>