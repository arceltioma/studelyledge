<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_manage_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$pageTitle = 'Créer un compte de service';
$pageSubtitle = 'Ajout complet d’un compte 706';

$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [['code' => 'EUR', 'label' => 'Euro']];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];
$destinationCountries = function_exists('studely_destination_countries') ? studely_destination_countries() : [];

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("SELECT id, label FROM ref_operation_types WHERE COALESCE(is_active,1)=1 ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$successMessage = '';
$errorMessage = '';

$formData = [
    'account_code' => '',
    'account_label' => '',
    'operation_type_id' => '',
    'commercial_country_label' => '',
    'destination_country_label' => '',
    'currency_code' => 'EUR',
    'current_balance' => '0',
    'is_postable' => 1,
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'account_code' => trim((string)($_POST['account_code'] ?? '')),
        'account_label' => trim((string)($_POST['account_label'] ?? '')),
        'operation_type_id' => trim((string)($_POST['operation_type_id'] ?? '')),
        'commercial_country_label' => trim((string)($_POST['commercial_country_label'] ?? '')),
        'destination_country_label' => trim((string)($_POST['destination_country_label'] ?? '')),
        'currency_code' => trim((string)($_POST['currency_code'] ?? 'EUR')),
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

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM service_accounts WHERE account_code = ?");
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
            'operation_type_id' => $formData['operation_type_id'] !== '' ? (int)$formData['operation_type_id'] : null,
            'commercial_country_label' => $formData['commercial_country_label'] !== '' ? $formData['commercial_country_label'] : null,
            'destination_country_label' => $formData['destination_country_label'] !== '' ? $formData['destination_country_label'] : null,
            'currency_code' => $formData['currency_code'] !== '' ? $formData['currency_code'] : 'EUR',
            'current_balance' => (float)$formData['current_balance'],
            'is_postable' => $formData['is_postable'],
            'is_active' => $formData['is_active'],
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'service_accounts', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'service_accounts', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }
        if (columnExists($pdo, 'service_accounts', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        $stmt = $pdo->prepare("
            INSERT INTO service_accounts (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);

        $successMessage = 'Compte de service créé avec succès.';
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

    <div>
        <label>Type d’opération</label>
        <select name="operation_type_id">
            <option value="">Choisir</option>
            <?php foreach ($operationTypes as $type): ?>
                <option value="<?= (int)$type['id'] ?>" <?= $formData['operation_type_id'] == $type['id'] ? 'selected' : '' ?>>
                    <?= e($type['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>Pays commercial</label>
        <select name="commercial_country_label">
            <option value="">Choisir</option>
            <?php foreach ($commercialCountries as $country): ?>
                <option value="<?= e($country) ?>" <?= $formData['commercial_country_label'] === $country ? 'selected' : '' ?>><?= e($country) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>Pays destination</label>
        <select name="destination_country_label">
            <option value="">Choisir</option>
            <?php foreach ($destinationCountries as $country): ?>
                <option value="<?= e($country) ?>" <?= $formData['destination_country_label'] === $country ? 'selected' : '' ?>><?= e($country) ?></option>
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
    <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Retour</a>
</div>
</form>
</div>

<div class="card">
    <h3>Lecture</h3>
    <div class="dashboard-note">Les comptes 706 enregistrent les produits et frais de service, en cohérence avec l’analyse du chiffre d’affaires attendue dans l’application. :contentReference[oaicite:5]{index=5}</div>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>