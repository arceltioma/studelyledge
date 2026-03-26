<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0 && tableExists($pdo, 'operations')) {
    $stmt = $pdo->prepare("DELETE FROM operations WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
exit;