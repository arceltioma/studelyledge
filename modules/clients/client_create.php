<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_create');

function clientOld(string $key, mixed $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
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
        SELECT id, account_code, account_label, country_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$destinationCountries = studely_destination_countries();
$commercialCountries = studely_commercial_countries();
$originCountries = studely_origin_countries();
$clientTypes = studely_client_types();

$previewClientCode = studely_generate_next_client_code($pdo);
$previewClientAccount = '411' . $previewClientCode;

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

        if ($email === '') {
            $email = strtolower(
                preg_replace('/\s+/', '', $firstName) . '.' . preg_replace('/\s+/', '', $lastName) . '@studelyledger.com'
            );
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

        $clientCode = studely_generate_next_client_code($pdo);
        $generatedClientAccount = '411' . $clientCode;

        $pdo->beginTransaction();

        $stmtClient = $pdo->prepare("
            INSERT INTO clients (
                client_code,
                first_name,
                last_name,
                full_name,
                email,
                phone,
                country_origin,
                country_destination,
                country_commercial,
                client_type,
                client_status,
                status_id,
                currency,
                generated_client_account,
                initial_treasury_account_id,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmtClient->execute([
            $clientCode,
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
            $generatedClientAccount,
            $initialTreasuryAccountId,
        ]);

        $clientId = (int)$pdo->lastInsertId();

        studely_create_or_link_client_bank_account(
            $pdo,
            $clientId,
            $generatedClientAccount,
            $countryCommercial,
            'Compte client ' . $generatedClientAccount,
            $initialBalance
        );

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_client',
                'clients',
                'client',
                $clientId,
                'Création client avec code auto, compte 411 auto, 512 sélectionnable et solde initial paramétrable'
            );
        }

        $pdo->commit();

        $successMessage = 'Client créé avec succès. Code client : ' . $clientCode . ' — Compte client : ' . $generatedClientAccount;
        $_POST = [];

        $previewClientCode = studely_generate_next_client_code($pdo);
        $previewClientAccount = '411' . $previewClientCode;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Créer un client';
$pageSubtitle = 'Code client généré automatiquement, choix manuel possible du 512 et définition du solde initial du compte 411.';
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

                    <h3 class="section-title">Fiche client</h3>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code client généré</label>
                            <input type="text" value="<?= e($previewClientCode) ?>" readonly>
                        </div>

                        <div>
                            <label>Compte client généré</label>
                            <input type="text" value="<?= e($previewClientAccount) ?>" readonly>
                        </div>

                        <div>
                            <label>Prénom</label>
                            <input type="text" name="first_name" value="<?= clientOld('first_name') ?>" required>
                        </div>

                        <div>
                            <label>Nom</label>
                            <input type="text" name="last_name" value="<?= clientOld('last_name') ?>" required>
                        </div>

                        <div>
                            <label>Nom complet</label>
                            <input type="text" name="full_name" value="<?= clientOld('full_name') ?>">
                        </div>

                        <div>
                            <label>Email</label>
                            <input type="email" name="email" value="<?= clientOld('email') ?>">
                        </div>

                        <div>
                            <label>Téléphone</label>
                            <input type="text" name="phone" value="<?= clientOld('phone') ?>">
                        </div>

                        <div>
                            <label>Type client</label>
                            <select name="client_type" required>
                                <option value="">Choisir</option>
                                <?php foreach ($clientTypes as $item): ?>
                                    <option value="<?= e($item) ?>" <?= clientOld('client_type') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Statut client</label>
                            <input type="text" name="client_status" value="<?= clientOld('client_status') ?>">
                        </div>

                        <div>
                            <label>Statut paramétré</label>
                            <select name="status_id">
                                <option value="">Choisir</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= (int)$status['id'] ?>" <?= clientOld('status_id') == $status['id'] ? 'selected' : '' ?>>
                                        <?= e($status['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency">
                                <?php foreach (['EUR','XAF','XOF','USD','CAD','DZD','GNF','TND','MAD'] as $curr): ?>
                                    <option value="<?= e($curr) ?>" <?= clientOld('currency', 'EUR') === $curr ? 'selected' : '' ?>>
                                        <?= e($curr) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays d’origine</label>
                            <select name="country_origin" required>
                                <option value="">Choisir</option>
                                <?php foreach ($originCountries as $item): ?>
                                    <option value="<?= e($item) ?>" <?= clientOld('country_origin') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Pays de destination</label>
                            <select name="country_destination" required>
                                <option value="">Choisir</option>
                                <?php foreach ($destinationCountries as $item): ?>
                                    <option value="<?= e($item) ?>" <?= clientOld('country_destination') === $item ? 'selected' : '' ?>>
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
                                    <option value="<?= e($item) ?>" <?= clientOld('country_commercial') === $item ? 'selected' : '' ?>>
                                        <?= e($item) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512 à lier</label>
                            <select name="initial_treasury_account_id">
                                <option value="">Choisir automatiquement selon pays commercial</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= clientOld('initial_treasury_account_id') == $account['id'] ? 'selected' : '' ?>>
                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Solde initial du compte 411</label>
                            <input type="number" step="0.01" min="0" name="initial_balance" value="<?= clientOld('initial_balance', '0') ?>">
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Créer le client</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Règles appliquées</h3>
                <div class="dashboard-note">
                    Le code client reste généré automatiquement sur 9 chiffres et le compte client reste généré en 411 + code client.
                    En revanche, tu peux maintenant choisir manuellement le compte 512 à lier et définir le solde initial du compte client 411.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>