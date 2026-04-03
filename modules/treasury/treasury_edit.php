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

/* 🔥 LOT 1B : snapshot AVANT */
$beforeAccount = $account;

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

        /* 🔥 recharge AFTER */
        $stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: $account;

        /* 🔥 LOT 1B : AUDIT */
        if (function_exists('auditEntityChanges')) {
            auditEntityChanges(
                $pdo,
                'treasury_account',
                (int)$id,
                $beforeAccount,
                $account,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );
        }

        /* 🔥 LOT 1B : NOTIFICATION */
        if (function_exists('createNotification')) {
            createNotification(
                $pdo,
                'treasury_update',
                'Le compte de trésorerie ' . ($account['account_code'] ?? '') . ' a été modifié.',
                'info',
                APP_URL . 'modules/treasury/treasury_view.php?id=' . (int)$id,
                'treasury_account',
                (int)$id,
                isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
            );
        }

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