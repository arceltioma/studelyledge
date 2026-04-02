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

$errorMessage = '';

$formData = [
    'first_name' => $client['first_name'] ?? '',
    'last_name' => $client['last_name'] ?? '',
    'email' => $client['email'] ?? '',
    'phone' => $client['phone'] ?? '',
    'postal_address' => $client['postal_address'] ?? '',
    'client_type' => $client['client_type'] ?? '',
    'country_origin' => $client['country_origin'] ?? '',
    'country_destination' => $client['country_destination'] ?? '',
    'country_commercial' => $client['country_commercial'] ?? '',
    'currency' => $client['currency'] ?? 'EUR',
    'initial_treasury_account_id' => $client['initial_treasury_account_id'] ?? '',
];

function clientEditValue(array $formData, string $key, mixed $default = ''): string
{
    return e((string)($formData[$key] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'first_name' => trim((string)($_POST['first_name'] ?? '')),
        'last_name' => trim((string)($_POST['last_name'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'postal_address' => trim((string)($_POST['postal_address'] ?? '')),
        'client_type' => trim((string)($_POST['client_type'] ?? '')),
        'country_origin' => trim((string)($_POST['country_origin'] ?? '')),
        'country_destination' => trim((string)($_POST['country_destination'] ?? '')),
        'country_commercial' => trim((string)($_POST['country_commercial'] ?? '')),
        'currency' => trim((string)($_POST['currency'] ?? 'EUR')),
        'initial_treasury_account_id' => ($_POST['initial_treasury_account_id'] ?? '') !== '' ? (int)$_POST['initial_treasury_account_id'] : '',
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

        $updateFields = [
            'first_name = ?',
            'last_name = ?',
            'full_name = ?',
            'email = ?',
            'phone = ?',
            'client_type = ?',
            'country_origin = ?',
            'country_destination = ?',
            'country_commercial = ?',
            'currency = ?',
            'initial_treasury_account_id = ?',
            'updated_at = NOW()'
        ];

        $params = [
            $firstName,
            $lastName,
            $fullName,
            $formData['email'] !== '' ? $formData['email'] : null,
            $formData['phone'] !== '' ? $formData['phone'] : null,
            $formData['client_type'],
            $formData['country_origin'] !== '' ? $formData['country_origin'] : null,
            $formData['country_destination'] !== '' ? $formData['country_destination'] : null,
            $formData['country_commercial'],
            $formData['currency'] !== '' ? $formData['currency'] : 'EUR',
            $formData['initial_treasury_account_id'] !== '' ? (int)$formData['initial_treasury_account_id'] : null,
        ];

        if (columnExists($pdo, 'clients', 'postal_address')) {
            $updateFields[] = 'postal_address = ?';
            $params[] = $formData['postal_address'] !== '' ? $formData['postal_address'] : null;
        }

        $params[] = $id;

        $stmtUpdate = $pdo->prepare("
            UPDATE clients
            SET " . implode(', ', $updateFields) . "
            WHERE id = ?
        ");
        $stmtUpdate->execute($params);

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

        header('Location: ' . APP_URL . 'modules/clients/client_view.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un client';
$pageSubtitle = 'Mise à jour du profil client et de son rattachement financier.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

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
                        <input type="text" name="currency" value="<?= clientEditValue($formData, 'currency', 'EUR') ?>">
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

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>