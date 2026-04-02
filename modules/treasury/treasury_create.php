<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_create_page');
} else {
    enforcePagePermission($pdo, 'treasury_create');
}

$pageTitle = 'Créer un compte de trésorerie';
$pageSubtitle = 'Ajout sécurisé d’un nouveau compte interne';

$successMessage = '';
$errorMessage = '';

$currencyOptions = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [
    ['code' => 'EUR', 'label' => 'Euro']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!tableExists($pdo, 'treasury_accounts')) {
            throw new RuntimeException('Table treasury_accounts introuvable.');
        }

        $accountCode = trim((string)($_POST['account_code'] ?? ''));
        $accountLabel = trim((string)($_POST['account_label'] ?? ''));
        $openingBalance = trim((string)($_POST['opening_balance'] ?? '0'));
        $currencyCode = trim((string)($_POST['currency_code'] ?? 'EUR'));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($accountCode === '') {
            throw new RuntimeException('Le code compte est obligatoire.');
        }

        if (columnExists($pdo, 'treasury_accounts', 'account_label') && $accountLabel === '') {
            throw new RuntimeException('L’intitulé du compte est obligatoire.');
        }

        if (!preg_match('/^[0-9A-Z_\-\.]+$/', $accountCode)) {
            throw new RuntimeException('Le code compte contient des caractères non autorisés.');
        }

        if (columnExists($pdo, 'treasury_accounts', 'account_code')) {
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM treasury_accounts
                WHERE account_code = ?
            ");
            $stmtCheck->execute([$accountCode]);

            if ((int)$stmtCheck->fetchColumn() > 0) {
                throw new RuntimeException('Un compte de trésorerie avec ce code existe déjà.');
            }
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'account_code' => $accountCode,
            'account_label' => $accountLabel !== '' ? $accountLabel : null,
            'opening_balance' => $openingBalance !== '' ? (float)$openingBalance : 0,
            'current_balance' => $openingBalance !== '' ? (float)$openingBalance : 0,
            'currency_code' => $currencyCode !== '' ? $currencyCode : 'EUR',
            'is_active' => $isActive,
        ];

        foreach ($map as $column => $value) {
            if ($value === null) {
                continue;
            }
            if (columnExists($pdo, 'treasury_accounts', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'treasury_accounts', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
            $columns[] = 'updated_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            throw new RuntimeException('Aucune colonne insérable disponible dans treasury_accounts.');
        }

        $sql = "
            INSERT INTO treasury_accounts (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $newId = (int)$pdo->lastInsertId();

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'create_treasury_account',
                'treasury',
                'treasury_account',
                $newId,
                'Création d’un compte de trésorerie'
            );
        }

        $successMessage = 'Compte de trésorerie créé avec succès.';
        $_POST = [];
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

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Nouveau compte interne</h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <div>
                        <label for="account_code">Code compte</label>
                        <input
                            type="text"
                            id="account_code"
                            name="account_code"
                            value="<?= e($_POST['account_code'] ?? '') ?>"
                            required
                        >
                    </div>

                    <?php if (tableExists($pdo, 'treasury_accounts') && columnExists($pdo, 'treasury_accounts', 'account_label')): ?>
                        <div style="margin-top:16px;">
                            <label for="account_label">Intitulé</label>
                            <input
                                type="text"
                                id="account_label"
                                name="account_label"
                                value="<?= e($_POST['account_label'] ?? '') ?>"
                                required
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (tableExists($pdo, 'treasury_accounts') && columnExists($pdo, 'treasury_accounts', 'opening_balance')): ?>
                        <div style="margin-top:16px;">
                            <label for="opening_balance">Solde d’ouverture</label>
                            <input
                                type="number"
                                step="0.01"
                                id="opening_balance"
                                name="opening_balance"
                                value="<?= e($_POST['opening_balance'] ?? '0') ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (tableExists($pdo, 'treasury_accounts') && columnExists($pdo, 'treasury_accounts', 'currency_code')): ?>
                        <div style="margin-top:16px;">
                            <label for="currency_code">Devise</label>
                            <?php $selectedCurrency = (string)($_POST['currency_code'] ?? 'EUR'); ?>
                            <select id="currency_code" name="currency_code">
                                <?php foreach ($currencyOptions as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= $selectedCurrency === $currency['code'] ? 'selected' : '' ?>>
                                        <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (tableExists($pdo, 'treasury_accounts') && columnExists($pdo, 'treasury_accounts', 'is_active')): ?>
                        <div style="margin-top:16px;">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="is_active" value="1" <?= isset($_POST['is_active']) || !$_POST ? 'checked' : '' ?>>
                                Compte actif
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Créer le compte</button>
                        <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Bonnes pratiques</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Code compte</span>
                        <strong>Unique et stable</strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Intitulé</span>
                        <strong>Clair et métier</strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Solde d’ouverture</span>
                        <strong>Valeur initiale fiable</strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Devise</span>
                        <strong>Alignée avec le compte</strong>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>