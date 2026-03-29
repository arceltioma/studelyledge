<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_edit');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmtClient = $pdo->prepare("
    SELECT *
    FROM clients
    WHERE id = ?
    LIMIT 1
");
$stmtClient->execute([$id]);
$client = $stmtClient->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$statuses = tableExists($pdo, 'statuses')
    ? $pdo->query("
        SELECT id, name
        FROM statuses
        ORDER BY sort_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$destinationCountries = studely_destination_countries();
$commercialCountries = studely_commercial_countries();
$originCountries = studely_origin_countries();
$clientTypes = studely_client_types();

$bankAccount = findPrimaryBankAccountForClient($pdo, $id);

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

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
        $initialBalance = (float)str_replace(',', '.', (string)($_POST['initial_balance'] ?? '0'));

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
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

        if ($initialBalance < 0) {
            throw new RuntimeException('Le solde initial ne peut pas être négatif.');
        }

        $treasury = null;
        if ($initialTreasuryAccountId !== null) {
            $treasury = findTreasuryAccountById($pdo, $initialTreasuryAccountId);
            if (!$treasury) {
                throw new RuntimeException('Le compte 512 sélectionné est introuvable.');
            }
        } else {
            $treasury = studely_resolve_default_treasury_account($pdo, $countryCommercial);
            if (!$treasury) {
                throw new RuntimeException('Aucun compte 512 actif n’a pu être résolu pour ce pays commercial.');
            }
            $initialTreasuryAccountId = (int)$treasury['id'];
        }

        $pdo->beginTransaction();

        $stmtUpdateClient = $pdo->prepare("
            UPDATE clients
            SET
                first_name = ?,
                last_name = ?,
                full_name = ?,
                email = ?,
                phone = ?,
                country_origin = ?,
                country_destination = ?,
                country_commercial = ?,
                client_type = ?,
                client_status = ?,
                status_id = ?,
                currency = ?,
                initial_treasury_account_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdateClient->execute([
            $firstName,
            $lastName,
            $fullName,
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $countryOrigin,
            $countryDestination,
            $countryCommercial,
            $clientType,
            $clientStatus !== '' ? $clientStatus : null,
            $statusId,
            $currency !== '' ? $currency : 'EUR',
            $initialTreasuryAccountId,
            $id
        ]);

        studely_create_or_link_client_bank_account(
    $pdo,
    $id,
    (string)$client['generated_client_account'],
    $countryCommercial,
    'Compte client ' . $client['generated_client_account'],
    $initialBalance
);

// 🔥 recalcul propre après modification
recomputeClientBalance($pdo, $id);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_client',
                'clients',
                'client',
                $id,
                'Modification client avec mise à jour possible du 512 et du solde initial'
            );
        }

        $pdo->commit();

        $successMessage = 'Client mis à jour.';
        $stmtClient->execute([$id]);
        $client = $stmtClient->fetch(PDO::FETCH_ASSOC);
        $bankAccount = findPrimaryBankAccountForClient($pdo, $id);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un client';
$pageSubtitle = 'Le 512 lié et le solde initial du compte 411 sont maintenant modifiables.';
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
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <h3 class="section-title">Fiche client</h3>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code client</label>
                            <input type="text" value="<?= e($client['client_code'] ?? '') ?>" readonly>
                        </div>

                        <div>
                            <label>Compte client généré</label>
                            <input type="text" value="<?= e($client['generated_client_account'] ?? '') ?>" readonly>
                        </div>

                        <div>
                            <label>Prénom</label>
                            <input type="text" name="first_name" value="<?= e($client['first_name'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Nom</label>
                            <input type="text" name="last_name" value="<?= e($client['last_name'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Nom complet</label>
                            <input type="text" name="full_name" value="<?= e($client['full_name'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Email</label>
                            <input type="email" name="email" value="<?= e($client['email'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Téléphone</label>
                            <input type="text" name="phone" value="<?= e($client['phone'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Type client</label>
                            <select name="client_type" required>
                                <option value="">Choisir</option>
                                <?php foreach ($clientTypes as $item): ?>
                                    <option value="<?= e($item) ?>" <?= ($client['client_type'] ?? '') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Statut client</label>
                            <input type="text" name="client_status" value="<?= e($client['client_status'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Statut paramétré</label>
                            <select name="status_id">
                                <option value="">Choisir</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= (int)$status['id'] ?>" <?= (string)($client['status_id'] ?? '') === (string)$status['id'] ? 'selected' : '' ?>>
                                        <?= e($status['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency">
                                <?php foreach (['EUR','XAF','XOF','USD','CAD','DZD','GNF','TND','MAD'] as $curr): ?>
                                    <option value="<?= e($curr) ?>" <?= ($client['currency'] ?? 'EUR') === $curr ? 'selected' : '' ?>>
                                        <?= e($curr) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays origine</label>
                            <select name="country_origin" required>
                                <option value="">Choisir</option>
                                <?php foreach ($originCountries as $item): ?>
                                    <option value="<?= e($item) ?>" <?= ($client['country_origin'] ?? '') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays destination</label>
                            <select name="country_destination" required>
                                <option value="">Choisir</option>
                                <?php foreach ($destinationCountries as $item): ?>
                                    <option value="<?= e($item) ?>" <?= ($client['country_destination'] ?? '') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <select name="country_commercial" required>
                                <option value="">Choisir</option>
                                <?php foreach ($commercialCountries as $item): ?>
                                    <option value="<?= e($item) ?>" <?= ($client['country_commercial'] ?? '') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512 lié</label>
                            <select name="initial_treasury_account_id">
                                <option value="">Choisir automatiquement selon pays commercial</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($client['initial_treasury_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Solde initial du compte 411</label>
                            <input type="number" step="0.01" min="0" name="initial_balance" value="<?= e((string)($bankAccount['initial_balance'] ?? 0)) ?>">
                        </div>

                        <div>
                            <label>Solde actuel du compte 411</label>
                            <input type="text" value="<?= number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ') ?>" readonly>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Voir la fiche</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Note</h3>
                <div class="dashboard-note">
                    Le compte 512 lié peut être changé manuellement ici. Le solde initial du compte 411 peut aussi être ajusté.
                    Le solde actuel reste affiché séparément pour contrôle.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>