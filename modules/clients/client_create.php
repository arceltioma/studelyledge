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

function clientCreateOld(string $key, mixed $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $postalAddress = trim((string)($_POST['postal_address'] ?? ''));
        $clientType = trim((string)($_POST['client_type'] ?? ''));
        $countryOrigin = trim((string)($_POST['country_origin'] ?? ''));
        $countryDestination = trim((string)($_POST['country_destination'] ?? ''));
        $countryCommercial = trim((string)($_POST['country_commercial'] ?? ''));
        $currency = trim((string)($_POST['currency'] ?? 'EUR'));
        $treasuryId = (int)($_POST['initial_treasury_account_id'] ?? 0);
        $initialBalance = (float)($_POST['initial_balance'] ?? 0);

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('Le prénom et le nom sont obligatoires.');
        }

        if ($clientType === '') {
            throw new RuntimeException('Le type de client est obligatoire.');
        }

        if ($countryCommercial === '') {
            throw new RuntimeException('Le pays commercial est obligatoire.');
        }

        $clientCode = generateClientCode($pdo);
        $clientAccount = '411' . $clientCode;

        $pdo->beginTransaction();

        $clientColumns = [
            'client_code',
            'first_name',
            'last_name',
            'full_name',
            'email',
            'phone',
            'client_type',
            'country_origin',
            'country_destination',
            'country_commercial',
            'currency',
            'generated_client_account',
            'initial_treasury_account_id',
            'is_active',
        ];

        $clientValues = [
            $clientCode,
            $firstName,
            $lastName,
            $fullName,
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $clientType,
            $countryOrigin !== '' ? $countryOrigin : null,
            $countryDestination !== '' ? $countryDestination : null,
            $countryCommercial,
            $currency !== '' ? $currency : 'EUR',
            $clientAccount,
            $treasuryId > 0 ? $treasuryId : null,
            1,
        ];

        if (columnExists($pdo, 'clients', 'postal_address')) {
            $clientColumns[] = 'postal_address';
            $clientValues[] = $postalAddress !== '' ? $postalAddress : null;
        }

        if (columnExists($pdo, 'clients', 'created_at')) {
            $clientColumns[] = 'created_at';
        }
        if (columnExists($pdo, 'clients', 'updated_at')) {
            $clientColumns[] = 'updated_at';
        }

        $placeholders = [];
        $params = [];
        $valueIndex = 0;

        foreach ($clientColumns as $column) {
            if (in_array($column, ['created_at', 'updated_at'], true)) {
                $placeholders[] = 'NOW()';
            } else {
                $placeholders[] = '?';
                $params[] = $clientValues[$valueIndex];
                $valueIndex++;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO clients (" . implode(', ', $clientColumns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);

        $clientId = (int)$pdo->lastInsertId();

        if (tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'client_bank_accounts')) {
            $bankColumns = ['account_number', 'account_name', 'initial_balance', 'balance'];
            $bankValues = [$clientAccount, 'Compte client ' . $fullName, $initialBalance, $initialBalance];

            if (columnExists($pdo, 'bank_accounts', 'created_at')) {
                $bankColumns[] = 'created_at';
            }
            if (columnExists($pdo, 'bank_accounts', 'updated_at')) {
                $bankColumns[] = 'updated_at';
            }

            $bankPlaceholders = [];
            $bankParams = [];
            $bankValueIndex = 0;

            foreach ($bankColumns as $column) {
                if (in_array($column, ['created_at', 'updated_at'], true)) {
                    $bankPlaceholders[] = 'NOW()';
                } else {
                    $bankPlaceholders[] = '?';
                    $bankParams[] = $bankValues[$bankValueIndex];
                    $bankValueIndex++;
                }
            }

            $stmtAccount = $pdo->prepare("
                INSERT INTO bank_accounts (" . implode(', ', $bankColumns) . ")
                VALUES (" . implode(', ', $bankPlaceholders) . ")
            ");
            $stmtAccount->execute($bankParams);

            $bankAccountId = (int)$pdo->lastInsertId();

            $stmtLink = $pdo->prepare("
                INSERT INTO client_bank_accounts (client_id, bank_account_id)
                VALUES (?, ?)
            ");
            $stmtLink->execute([$clientId, $bankAccountId]);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_client',
                'clients',
                'client',
                $clientId,
                'Création du client ' . $clientCode
            );
        }

        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/clients/client_view.php?id=' . $clientId);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Créer un client';
$pageSubtitle = 'Création du client, de son compte 411 et rattachement éventuel au compte 512.';
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
            <h1>Créer un client</h1>

            <form method="POST">
                <?= csrf_input() ?>

                <div class="dashboard-grid-2">
                    <div>
                        <label>Prénom</label>
                        <input type="text" name="first_name" value="<?= clientCreateOld('first_name') ?>" required>
                    </div>

                    <div>
                        <label>Nom</label>
                        <input type="text" name="last_name" value="<?= clientCreateOld('last_name') ?>" required>
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?= clientCreateOld('email') ?>">
                    </div>

                    <div>
                        <label>Téléphone</label>
                        <input type="text" name="phone" value="<?= clientCreateOld('phone') ?>">
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label>Adresse postale</label>
                        <textarea name="postal_address" rows="3"><?= clientCreateOld('postal_address') ?></textarea>
                    </div>

                    <div>
                        <label>Type de client</label>
                        <select name="client_type" required>
                            <option value="">Choisir</option>
                            <?php foreach ($clientTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= clientCreateOld('client_type') === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Devise</label>
                        <input type="text" name="currency" value="<?= clientCreateOld('currency', 'EUR') ?>">
                    </div>

                    <div>
                        <label>Pays d'origine</label>
                        <select name="country_origin">
                            <option value="">Choisir</option>
                            <?php foreach ($originCountries as $country): ?>
                                <option value="<?= e($country) ?>" <?= clientCreateOld('country_origin') === $country ? 'selected' : '' ?>>
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
                                <option value="<?= e($country) ?>" <?= clientCreateOld('country_destination') === $country ? 'selected' : '' ?>>
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
                                <option value="<?= e($country) ?>" <?= clientCreateOld('country_commercial') === $country ? 'selected' : '' ?>>
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
                                <option value="<?= (int)$account['id'] ?>" <?= clientCreateOld('initial_treasury_account_id') === (string)$account['id'] ? 'selected' : '' ?>>
                                    <?= e(($account['account_code'] ?? '') . ' - ' . ($account['account_label'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Solde initial</label>
                        <input type="number" step="0.01" name="initial_balance" value="<?= clientCreateOld('initial_balance', '0') ?>">
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Créer le client</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>