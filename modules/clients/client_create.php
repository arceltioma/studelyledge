<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_create');

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$statuses = tableExists($pdo, 'statuses')
    ? $pdo->query("
        SELECT id, name
        FROM statuses
        ORDER BY sort_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$destinationCountries = studely_destination_countries();
$commercialCountries = studely_commercial_countries();
$originCountries = studely_origin_countries();
$clientTypes = studely_client_types();

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $clientCode = trim((string)($_POST['client_code'] ?? ''));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $clientType = trim((string)($_POST['client_type'] ?? ''));
        $clientStatus = trim((string)($_POST['client_status'] ?? ''));
        $statusId = ($_POST['status_id'] ?? '') !== '' ? (int)$_POST['status_id'] : null;
        $currency = trim((string)($_POST['currency'] ?? 'EUR'));
        $countryOrigin = trim((string)($_POST['country_origin'] ?? ''));
        $countryDestination = trim((string)($_POST['country_destination'] ?? ''));
        $countryCommercial = trim((string)($_POST['country_commercial'] ?? ''));
        $initialTreasuryAccountId = ($_POST['initial_treasury_account_id'] ?? '') !== '' ? (int)$_POST['initial_treasury_account_id'] : null;

        if ($clientCode === '' || $firstName === '' || $lastName === '') {
            throw new RuntimeException('Code client, prénom et nom sont obligatoires.');
        }

        if ($fullName === '') {
            $fullName = trim($firstName . ' ' . $lastName);
        }

        if ($clientType === '' || !in_array($clientType, $clientTypes, true)) {
            throw new RuntimeException('Type client invalide.');
        }

        if ($countryOrigin === '' || !in_array($countryOrigin, $originCountries, true)) {
            throw new RuntimeException('Pays d’origine invalide.');
        }

        if ($countryDestination === '' || !in_array($countryDestination, $destinationCountries, true)) {
            throw new RuntimeException('Pays de destination invalide.');
        }

        if ($countryCommercial === '' || !in_array($countryCommercial, $commercialCountries, true)) {
            throw new RuntimeException('Pays commercial invalide.');
        }

        $stmtDup = $pdo->prepare("SELECT id FROM clients WHERE client_code = ? LIMIT 1");
        $stmtDup->execute([$clientCode]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code client existe déjà.');
        }

        $generatedClientAccount = '411' . str_pad((string)$clientCode, 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO clients (
                client_code, first_name, last_name, full_name, email, phone,
                client_type, client_status, status_id, currency,
                country_origin, country_destination, country_commercial,
                generated_client_account, initial_treasury_account_id,
                is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            $clientCode, $firstName, $lastName, $fullName, $email !== '' ? $email : null, $phone !== '' ? $phone : null,
            $clientType, $clientStatus !== '' ? $clientStatus : null, $statusId, $currency,
            $countryOrigin, $countryDestination, $countryCommercial,
            $generatedClientAccount, $initialTreasuryAccountId
        ]);

        $successMessage = 'Client créé.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Créer un client';
$pageSubtitle = 'Création d’un compte client avec listes déroulantes normalisées.';
require_once __DIR__ . '/../../includes/document_start.php';
?>
<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>

                <div class="dashboard-grid-2">
                    <div><label>Code client</label><input type="text" name="client_code" required></div>
                    <div><label>Prénom</label><input type="text" name="first_name" required></div>
                    <div><label>Nom</label><input type="text" name="last_name" required></div>
                    <div><label>Nom complet</label><input type="text" name="full_name"></div>
                    <div><label>Email</label><input type="email" name="email"></div>
                    <div><label>Téléphone</label><input type="text" name="phone"></div>

                    <div>
                        <label>Type client</label>
                        <select name="client_type" required>
                            <option value="">Choisir</option>
                            <?php foreach ($clientTypes as $item): ?>
                                <option value="<?= e($item) ?>"><?= e($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Statut client</label>
                        <input type="text" name="client_status">
                    </div>

                    <div>
                        <label>Statut paramétré</label>
                        <select name="status_id">
                            <option value="">Choisir</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= (int)$status['id'] ?>"><?= e($status['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Devise</label>
                        <select name="currency">
                            <?php foreach (['EUR','XAF','XOF','USD','CAD','DZD','GNF','TND','MAD'] as $currency): ?>
                                <option value="<?= e($currency) ?>" <?= $currency === 'EUR' ? 'selected' : '' ?>><?= e($currency) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays d’origine</label>
                        <select name="country_origin" required>
                            <option value="">Choisir</option>
                            <?php foreach ($originCountries as $item): ?>
                                <option value="<?= e($item) ?>"><?= e($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays de destination</label>
                        <select name="country_destination" required>
                            <option value="">Choisir</option>
                            <?php foreach ($destinationCountries as $item): ?>
                                <option value="<?= e($item) ?>"><?= e($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays commercial</label>
                        <select name="country_commercial" required>
                            <option value="">Choisir</option>
                            <?php foreach ($commercialCountries as $item): ?>
                                <option value="<?= e($item) ?>"><?= e($item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Compte interne lié</label>
                        <select name="initial_treasury_account_id">
                            <option value="">Choisir</option>
                            <?php foreach ($treasuryAccounts as $account): ?>
                                <option value="<?= (int)$account['id'] ?>"><?= e($account['account_code'] . ' - ' . $account['account_label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Créer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>