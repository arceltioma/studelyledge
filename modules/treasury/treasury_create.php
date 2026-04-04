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
$pageSubtitle = 'Ajout complet d’un compte interne 512';

$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [['code' => 'EUR', 'label' => 'Euro']];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$countryTypes = ['Filiale', 'Partenaire', 'Siège', 'Autre'];
$paymentPlaces = ['Local', 'International', 'Mixte'];

$successMessage = '';
$errorMessage = '';

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
    'is_postable' => 1,
    'is_active' => 1,
];

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
        'is_postable' => isset($_POST['is_postable']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

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
            'is_postable' => $formData['is_postable'],
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
            logUserAction($pdo, (int)$_SESSION['user_id'], 'create_treasury_account', 'treasury', 'treasury_account', $newId, 'Création d’un compte interne 512');
        }

        $successMessage = 'Compte de trésorerie créé avec succès.';
        $formData['account_code'] = '';
        $formData['account_label'] = '';
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
                <div><label>Code compte</label><input type="text" name="account_code" value="<?= e($formData['account_code']) ?>" required></div>
                <div><label>Intitulé</label><input type="text" name="account_label" value="<?= e($formData['account_label']) ?>" required></div>
                <div><label>Banque</label><input type="text" name="bank_name" value="<?= e($formData['bank_name']) ?>"></div>
                <div><label>Filiale</label><input type="text" name="subsidiary_name" value="<?= e($formData['subsidiary_name']) ?>"></div>
                <div><label>Zone code</label><input type="text" name="zone_code" value="<?= e($formData['zone_code']) ?>"></div>

                <div>
                    <label>Pays</label>
                    <select name="country_label">
                        <option value="">Choisir</option>
                        <?php foreach ($commercialCountries as $country): ?>
                            <option value="<?= e($country) ?>" <?= $formData['country_label'] === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Type de pays</label>
                    <select name="country_type">
                        <?php foreach ($countryTypes as $item): ?>
                            <option value="<?= e($item) ?>" <?= $formData['country_type'] === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Lieu de paiement</label>
                    <select name="payment_place">
                        <?php foreach ($paymentPlaces as $item): ?>
                            <option value="<?= e($item) ?>" <?= $formData['payment_place'] === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Devise</label>
                    <select name="currency_code">
                        <?php foreach ($currencies as $currency): ?>
                            <option value="<?= e($currency['code']) ?>" <?= $formData['currency_code'] === $currency['code'] ? 'selected' : '' ?>>
                                <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div><label>Solde ouverture</label><input type="number" step="0.01" name="opening_balance" value="<?= e($formData['opening_balance']) ?>"></div>
                <div><label>Solde courant</label><input type="number" step="0.01" name="current_balance" value="<?= e($formData['current_balance']) ?>"></div>
            </div>

            <div style="margin-top:16px;">
                <label style="display:flex; gap:10px; align-items:center;">
                    <input type="checkbox" name="is_postable" value="1" <?= (int)$formData['is_postable'] === 1 ? 'checked' : '' ?>> Compte postable
                </label>
            </div>

            <div style="margin-top:10px;">
                <label style="display:flex; gap:10px; align-items:center;">
                    <input type="checkbox" name="is_active" value="1" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>> Compte actif
                </label>
            </div>

            <div class="btn-group" style="margin-top:20px;">
                <button type="submit" class="btn btn-success">Créer</button>
                <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Lecture</h3>
        <div class="dashboard-note">Les 512 servent au suivi de trésorerie bancaire. Ils peuvent être postables ou de structure selon l’usage métier. :contentReference[oaicite:4]{index=4}</div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>