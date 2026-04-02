<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_delete_page');
} else {
    enforcePagePermission($pdo, 'treasury_delete');
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Compte de trésorerie invalide.';
    header('Location: ' . APP_URL . 'modules/treasury/index.php');
    exit;
}

if (!tableExists($pdo, 'treasury_accounts')) {
    $_SESSION['error_message'] = 'Table treasury_accounts introuvable.';
    header('Location: ' . APP_URL . 'modules/treasury/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $_SESSION['error_message'] = 'Compte de trésorerie introuvable.';
    header('Location: ' . APP_URL . 'modules/treasury/index.php');
    exit;
}

if (!columnExists($pdo, 'treasury_accounts', 'is_active')) {
    $_SESSION['error_message'] = 'La colonne is_active est absente.';
    header('Location: ' . APP_URL . 'modules/treasury/index.php');
    exit;
}

$newStatus = ((int)($row['is_active'] ?? 1) === 1) ? 0 : 1;

$sql = "UPDATE treasury_accounts SET is_active = ?";
$params = [$newStatus];

if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
    $sql .= ", updated_at = NOW()";
}

$sql .= " WHERE id = ?";
$params[] = $id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
    logUserAction(
        $pdo,
        (int)$_SESSION['user_id'],
        $newStatus === 1 ? 'reactivate_treasury_account' : 'archive_treasury_account',
        'treasury',
        'treasury_account',
        $id,
        'Changement de statut du compte de trésorerie'
    );
}

$_SESSION['success_message'] = $newStatus === 1
    ? 'Compte de trésorerie réactivé avec succès.'
    : 'Compte de trésorerie archivé avec succès.';

header('Location: ' . APP_URL . 'modules/treasury/index.php');
exit;