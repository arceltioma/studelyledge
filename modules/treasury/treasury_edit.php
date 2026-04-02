<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_edit_page');
} else {
    enforcePagePermission($pdo, 'treasury_edit');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de trésorerie invalide.');
}

if (!tableExists($pdo, 'treasury_accounts')) {
    exit('Table treasury_accounts introuvable.');
}

$stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de trésorerie introuvable.');
}

$pageTitle = 'Modifier un compte de trésorerie';
$pageSubtitle = 'Mise à jour sécurisée du compte interne';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $accountCode = trim((string)($_POST['account_code'] ?? ''));
        $accountLabel = trim((string)($_POST['account_label'] ?? ''));
        $openingBalance = (string)($_POST['opening_balance'] ?? '');
        $currencyCode = trim((string)($_POST['currency_code'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($accountCode === '') {
            throw new RuntimeException('Le code compte est obligatoire.');
        }

        if ($accountLabel === '' && columnExists($pdo, 'treasury_accounts', 'account_label')) {
            throw new RuntimeException('L’intitulé du compte est obligatoire.');
        }

        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM treasury_accounts
            WHERE account_code = ?
              AND id <> ?
        ");
        $stmtCheck->execute([$accountCode, $id]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Un autre compte de trésorerie utilise déjà ce code.');
        }

        $fields = [];
        $params = [];

        if (columnExists($pdo, 'treasury_accounts', 'account_code')) {
            $fields[] = 'account_code = ?';
            $params[] = $accountCode;
        }

        if (columnExists($pdo, 'treasury_accounts', 'account_label')) {
            $fields[] = 'account_label = ?';
            $params[] = $accountLabel;
        }

        if (columnExists($pdo, 'treasury_accounts', 'opening_balance') && $openingBalance !== '') {
            $fields[] = 'opening_balance = ?';
            $params[] = (float)$openingBalance;
        }

        if (columnExists($pdo, 'treasury_accounts', 'currency_code')) {
            $fields[] = 'currency_code = ?';
            $params[] = $currencyCode !== '' ? $currencyCode : ($account['currency_code'] ?? 'EUR');
        }

        if (columnExists($pdo, 'treasury_accounts', 'is_active')) {
            $fields[] = 'is_active = ?';
            $params[] = $isActive;
        }

        if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (!$fields) {
            throw new RuntimeException('Aucun champ modifiable disponible.');
        }

        $params[] = $id;

        $stmtUpdate = $pdo->prepare("
            UPDATE treasury_accounts
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmtUpdate->execute($params);

        if (function_exists('recomputeAllBalances')) {
            recomputeAllBalances($pdo);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_treasury_account',
                'treasury',
                'treasury_account',
                $id,
                'Modification d’un compte de trésorerie'
            );
        }

        $stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: $account;

        $successMessage = 'Compte de trésorerie mis à jour avec succès.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$currencyOptions = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [
    ['code' => 'EUR', 'label' => 'Euro']
];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div>
                        <label for="account_code">Code compte</label>
                        <input
                            type="text"
                            id="account_code"
                            name="account_code"
                            value="<?= e($_POST['account_code'] ?? ($account['account_code'] ?? '')) ?>"
                            required
                        >
                    </div>

                    <?php if (columnExists($pdo, 'treasury_accounts', 'account_label')): ?>
                        <div style="margin-top:16px;">
                            <label for="account_label">Intitulé</label>
                            <input
                                type="text"
                                id="account_label"
                                name="account_label"
                                value="<?= e($_POST['account_label'] ?? ($account['account_label'] ?? '')) ?>"
                                required
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (columnExists($pdo, 'treasury_accounts', 'opening_balance')): ?>
                        <div style="margin-top:16px;">
                            <label for="opening_balance">Solde ouverture</label>
                            <input
                                type="number"
                                step="0.01"
                                id="opening_balance"
                                name="opening_balance"
                                value="<?= e($_POST['opening_balance'] ?? ($account['opening_balance'] ?? '0')) ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (columnExists($pdo, 'treasury_accounts', 'currency_code')): ?>
                        <div style="margin-top:16px;">
                            <label for="currency_code">Devise</label>
                            <?php $selectedCurrency = (string)($_POST['currency_code'] ?? ($account['currency_code'] ?? 'EUR')); ?>
                            <select id="currency_code" name="currency_code">
                                <?php foreach ($currencyOptions as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= $selectedCurrency === $currency['code'] ? 'selected' : '' ?>>
                                        <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (columnExists($pdo, 'treasury_accounts', 'is_active')): ?>
                        <?php $isChecked = isset($_POST['is_active']) ? true : ((int)($account['is_active'] ?? 1) === 1); ?>
                        <div style="margin-top:16px;">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="is_active" value="1" <?= $isChecked ? 'checked' : '' ?>>
                                Compte actif
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Voir</a>
                        <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>État actuel</h3>

                <div class="stat-row">
                    <span class="metric-label">Code compte</span>
                    <span class="metric-value"><?= e((string)($account['account_code'] ?? '')) ?></span>
                </div>

                <?php if (array_key_exists('account_label', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Intitulé</span>
                        <span class="metric-value"><?= e((string)($account['account_label'] ?? '')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('opening_balance', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Solde ouverture</span>
                        <span class="metric-value"><?= e(number_format((float)($account['opening_balance'] ?? 0), 2, ',', ' ')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('current_balance', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Solde courant</span>
                        <span class="metric-value"><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (array_key_exists('is_active', $account)): ?>
                    <div class="stat-row">
                        <span class="metric-label">Statut</span>
                        <span class="metric-value"><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>