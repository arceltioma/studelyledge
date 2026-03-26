<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'clients_create');

require_once __DIR__ . '/../../includes/header.php';

function clientOld(string $key, mixed $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
}

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
        $clientCode = trim((string)($_POST['client_code'] ?? ''));
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

        if (!preg_match('/^[0-9]{9}$/', $clientCode)) {
            throw new RuntimeException('Le code client doit contenir exactement 9 chiffres.');
        }

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($fullName === '') {
            $fullName = trim($firstName . ' ' . $lastName);
        }

        if ($email === '') {
            $email = strtolower(
                preg_replace('/\s+/', '', $firstName) . '.' . preg_replace('/\s+/', '', $lastName) . '@studelyledger.com'
            );
        }

        $stmtDup = $pdo->prepare("SELECT id FROM clients WHERE client_code = ? LIMIT 1");
        $stmtDup->execute([$clientCode]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code client existe déjà.');
        }

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
                currency,
                generated_client_account,
                initial_treasury_account_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtClient->execute([
            $clientCode,
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
            $generatedClientAccount,
            $treasuryId
        ]);

        $clientId = (int)$pdo->lastInsertId();

        $stmtBank = $pdo->prepare("
            INSERT INTO bank_accounts (
                account_number,
                bank_name,
                country,
                initial_balance,
                balance,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtBank->execute([
            $generatedClientAccount,
            'Compte Client Interne',
            'France',
            15000.00,
            15000.00
        ]);

        $bankAccountId = (int)$pdo->lastInsertId();

        $stmtLink = $pdo->prepare("
            INSERT INTO client_bank_accounts (
                client_id,
                bank_account_id
            ) VALUES (?, ?)
        ");
        $stmtLink->execute([$clientId, $bankAccountId]);

        $pdo->commit();

        $successMessage = 'Client créé avec succès.';
        $_POST = [];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Créer un client',
            'Création complète : identité, périmètre pays, rattachement financier, compte client généré.'
        ); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <h3 class="section-title">Fiche client</h3>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code client (9 chiffres)</label>
                            <input type="text" name="client_code" maxlength="9" value="<?= clientOld('client_code') ?>" required>
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency">
                                <?php foreach (['EUR', 'XAF', 'XOF', 'USD'] as $currency): ?>
                                    <option value="<?= e($currency) ?>" <?= clientOld('currency', 'EUR') === $currency ? 'selected' : '' ?>>
                                        <?= e($currency) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <input type="text" name="client_type" value="<?= clientOld('client_type') ?>">
                        </div>

                        <div>
                            <label>Statut client</label>
                            <input type="text" name="client_status" value="<?= clientOld('client_status') ?>">
                        </div>

                        <div>
                            <label>Pays origine</label>
                            <input type="text" name="country_origin" value="<?= clientOld('country_origin') ?>">
                        </div>

                        <div>
                            <label>Pays destination</label>
                            <input type="text" name="country_destination" value="<?= clientOld('country_destination') ?>">
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <input type="text" name="country_commercial" value="<?= clientOld('country_commercial') ?>">
                        </div>

                        <div>
                            <label>Compte interne lié</label>
                            <select name="initial_treasury_account_id">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $account): ?>
                                    <option value="<?= (int)$account['id'] ?>" <?= clientOld('initial_treasury_account_id') == $account['id'] ? 'selected' : '' ?>>
                                        <?= e($account['account_code'] . ' - ' . $account['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Créer le client</button>
                        <a href="<?= APP_URL ?>modules/clients/clients_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Ce que la création fait</h3>
                <div class="dashboard-note">
                    À la création, le client reçoit automatiquement :
                    <br>• un compte client généré en 411 + code client
                    <br>• un compte bancaire lié
                    <br>• un solde initial de 15 000
                    <br>• un rattachement possible à un compte interne 512
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>