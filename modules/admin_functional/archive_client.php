<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'clients_create');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0 && tableExists($pdo, 'clients')) {
    $stmt = $pdo->prepare("
        UPDATE clients
        SET is_active = 0, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$id]);
}

header('Location: ' . APP_URL . 'modules/admin_functional/manage_accounts.php');
exit;