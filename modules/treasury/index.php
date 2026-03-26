<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'treasury_view');

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$archiveId = (int)($_GET['archive'] ?? 0);
$restoreId = (int)($_GET['restore'] ?? 0);

if ($archiveId > 0) {
    $stmt = $pdo->prepare("
        UPDATE treasury_accounts
        SET is_active = 0
        WHERE id = ?
    ");
    $stmt->execute([$archiveId]);
    header('Location: ' . APP_URL . 'modules/treasury/index.php?ok=archived');
    exit;
}

if ($restoreId > 0) {
    $stmt = $pdo->prepare("
        UPDATE treasury_accounts
        SET is_active = 1
        WHERE id = ?
    ");
    $stmt->execute([$restoreId]);
    header('Location: ' . APP_URL . 'modules/treasury/index.php?ok=restored');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_treasury_account'])) {
    try {
        $accountCode = trim((string)($_POST['account_code'] ?? ''));
        $accountLabel = trim((string)($_POST['account_label'] ?? ''));
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $subsidiaryName = trim((string)($_POST['subsidiary_name'] ?? ''));
        $zoneCode = trim((string)($_POST['zone_code'] ?? ''));
        $countryLabel = trim((string)($_POST['country_label'] ?? ''));
        $countryType = trim((string)($_POST['country_type'] ?? ''));
        $paymentPlace = trim((string)($_POST['payment_place'] ?? ''));
        $currencyCode = trim((string)($_POST['currency_code'] ?? 'EUR'));
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);
        $currentBalance = (float)($_POST['current_balance'] ?? $openingBalance);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($accountCode === '' || $accountLabel === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $sqlDup = "SELECT id FROM treasury_accounts WHERE account_code = ?";
        $paramsDup = [$accountCode];
        if ($editId > 0) {
            $sqlDup .= " AND id <> ?";
            $paramsDup[] = $editId;
        }
        $sqlDup .= " LIMIT 1";

        $stmtDup = $pdo->prepare($sqlDup);
        $stmtDup->execute($paramsDup);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce compte interne existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE treasury_accounts
                SET
                    account_code = ?,
                    account_label = ?,
                    bank_name = ?,
                    subsidiary_name = ?,
                    zone_code = ?,
                    country_label = ?,
                    country_type = ?,
                    payment_place = ?,
                    currency_code = ?,
                    opening_balance = ?,
                    current_balance = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $accountCode, $accountLabel, $bankName, $subsidiaryName, $zoneCode, $countryLabel,
                $countryType, $paymentPlace, $currencyCode, $openingBalance, $currentBalance, $isActive, $editId
            ]);
            $successMessage = 'Compte 512 mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO treasury_accounts (
                    account_code, account_label, bank_name, subsidiary_name, zone_code,
                    country_label, country_type, payment_place, currency_code,
                    opening_balance, current_balance, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $accountCode, $accountLabel, $bankName, $subsidiaryName, $zoneCode,
                $countryLabel, $countryType, $paymentPlace, $currencyCode,
                $openingBalance, $currentBalance, $isActive
            ]);
            $successMessage = 'Compte 512 créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editAccount = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("SELECT * FROM treasury_accounts ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Comptes bancaires internes 512',
            'Pilotage des comptes de trésorerie.'
        ); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title"><?= $editAccount ? 'Modifier un compte 512' : 'Créer un compte 512' ?></h3>

                <form method="POST">
                    <?php if ($editAccount): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editAccount['id'] ?>">
                    <?php endif; ?>

                    <div class="dashboard-grid-2">
                        <div><label>Code</label><input type="text" name="account_code" value="<?= e($editAccount['account_code'] ?? '') ?>" required></div>
                        <div><label>Libellé</label><input type="text" name="account_label" value="<?= e($editAccount['account_label'] ?? '') ?>" required></div>
                        <div><label>Banque</label><input type="text" name="bank_name" value="<?= e($editAccount['bank_name'] ?? '') ?>"></div>
                        <div><label>Filiale</label><input type="text" name="subsidiary_name" value="<?= e($editAccount['subsidiary_name'] ?? '') ?>"></div>
                        <div><label>Zone</label><input type="text" name="zone_code" value="<?= e($editAccount['zone_code'] ?? '') ?>"></div>
                        <div><label>Pays</label><input type="text" name="country_label" value="<?= e($editAccount['country_label'] ?? '') ?>"></div>
                        <div><label>Type pays</label><input type="text" name="country_type" value="<?= e($editAccount['country_type'] ?? '') ?>"></div>
                        <div><label>Lieu paiement</label><input type="text" name="payment_place" value="<?= e($editAccount['payment_place'] ?? '') ?>"></div>
                        <div><label>Devise</label><input type="text" name="currency_code" value="<?= e($editAccount['currency_code'] ?? 'EUR') ?>"></div>
                        <div><label>Solde initial</label><input type="number" step="0.01" name="opening_balance" value="<?= e((string)($editAccount['opening_balance'] ?? '0')) ?>"></div>
                        <div><label>Solde courant</label><input type="number" step="0.01" name="current_balance" value="<?= e((string)($editAccount['current_balance'] ?? '0')) ?>"></div>
                        <div style="display:flex;align-items:end;">
                            <label><input type="checkbox" name="is_active" <?= ((int)($editAccount['is_active'] ?? 1) === 1) ? 'checked' : '' ?>> Actif</label>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="save_treasury_account" value="1" class="btn btn-success"><?= $editAccount ? 'Enregistrer' : 'Créer' ?></button>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/service_accounts.php">Gérer les 706</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Les comptes 512 suivent la trésorerie réelle. Ils bougent avec les opérations clients et les virements internes.
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Banque</th>
                        <th>Pays</th>
                        <th>Devise</th>
                        <th>Solde initial</th>
                        <th>Solde courant</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['account_code'] ?? '') ?></td>
                            <td><?= e($row['account_label'] ?? '') ?></td>
                            <td><?= e($row['bank_name'] ?? '') ?></td>
                            <td><?= e($row['country_label'] ?? '') ?></td>
                            <td><?= e($row['currency_code'] ?? '') ?></td>
                            <td><?= number_format((float)($row['opening_balance'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td>
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/treasury/index.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                    <a class="btn btn-danger" href="<?= APP_URL ?>modules/treasury/index.php?archive=<?= (int)$row['id'] ?>">Archiver</a>
                                <?php else: ?>
                                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/index.php?restore=<?= (int)$row['id'] ?>">Réactiver</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>