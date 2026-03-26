<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'operations_create';
enforcePagePermission($pdo, $pagePermission);

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE ref_operation_types SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php');
exit;