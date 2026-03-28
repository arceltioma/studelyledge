<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_create');

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

$bankAccount = findPrimaryBankAccountForClient($pdo, $id);

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label, currency_code
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

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
        $countryOrigin = trim((string)($_POST['country_origin'] ?? ''));
        $countryDestination = trim((string)($_POST['country_destination'] ?? ''));
        $countryCommercial = trim((string)($_POST['country_commercial'] ?? ''));
        $clientType = trim((string)($_POST['client_type'] ?? ''));
        $clientStatus = trim((string)($_POST['client_status'] ?? ''));
        $currency = trim((string)($_POST['currency'] ?? 'EUR'));
        $treasuryId = ($_POST['initial_treasury_account_id'] ?? '') !== '' ? (int)$_POST['initial_treasury_account_id'] : null;

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($fullName === '') {
            $fullName = trim($firstName . ' ' . $lastName);
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
            $countryOrigin !== '' ? $countryOrigin : null,
            $countryDestination !== '' ? $countryDestination : null,
            $countryCommercial !== '' ? $countryCommercial : null,
            $clientType !== '' ? $clientType : null,
            $clientStatus !== '' ? $clientStatus : null,
            $currency !== '' ? $currency : 'EUR',
            $treasuryId,
            $id
        ]);

        if ($bankAccount) {
            $stmtUpdateBank = $pdo->prepare("
                UPDATE bank_accounts
                SET
                    bank_name = ?,
                    country = ?
                WHERE id = ?
            ");
            $stmtUpdateBank->execute([
                'Compte Client Interne',
                'France',
                (int)$bankAccount['id']
            ]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_client',
                'clients',
                'client',
                $id,
                'Mise à jour du client ' . ($client['client_code'] ?? '') . ' - ' . $fullName
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
$pageSubtitle = 'La fiche reste éditable, mais les mécanismes bancaires restent cohérents.';
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
                            <input type="text" name="client_type" value="<?= e($client['client_type'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Statut client</label>
                            <input type="text" name="client_status" value="<?= e($client['client_status'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency">
                                <?php foreach (['EUR', 'XAF', 'XOF', 'USD'] as $currency): ?>
                                    <option value="<?= e($currency) ?>" <?= ($client['currency'] ?? 'EUR') === $currency ? 'selected' : '' ?>>
                                        <?= e($currency) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays origine</label>
                            <input type="text" name="country_origin" value="<?= e($client['country_origin'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Pays destination</label>
                            <input type="text" name="country_destination" value="<?= e($client['country_destination'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <input type="text" name="country_commercial" value="<?= e($client['country_commercial'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Compte interne lié</label>
                            <select name="initial_treasury_account_id">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= (string)($client['initial_treasury_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Solde compte client</label>
                            <input type="text" value="<?= number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ') ?>" readonly>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Voir la fiche</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Note</h3>
                <div class="dashboard-note">
                    Le solde du compte client est affiché ici en lecture seule. Il évolue via les opérations et le moteur comptable.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>