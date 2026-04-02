<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_delete_page');
} else {
    enforcePagePermission($pdo, 'operations_delete');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error_message'] = 'Opération invalide.';
    header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
    exit;
}

if (!tableExists($pdo, 'operations')) {
    $_SESSION['error_message'] = 'Table operations introuvable.';
    header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    $_SESSION['error_message'] = 'Opération introuvable.';
    header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
    exit;
}

try {
    $pdo->beginTransaction();

    if (columnExists($pdo, 'operations', 'is_active')) {
        $sql = "UPDATE operations SET is_active = 0";
        if (columnExists($pdo, 'operations', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmtDelete = $pdo->prepare($sql);
        $stmtDelete->execute([$id]);

        $action = 'archive_operation';
        $message = 'Opération archivée avec succès.';
    } else {
        $stmtDelete = $pdo->prepare("DELETE FROM operations WHERE id = ?");
        $stmtDelete->execute([$id]);

        $action = 'delete_operation';
        $message = 'Opération supprimée avec succès.';
    }

    if (function_exists('recomputeAllBalances')) {
        recomputeAllBalances($pdo);
    }

    if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
        logUserAction(
            $pdo,
            (int)$_SESSION['user_id'],
            $action,
            'operations',
            'operation',
            $id,
            'Suppression / archivage d’une opération'
        );
    }

    $pdo->commit();

    $_SESSION['success_message'] = $message;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = $e->getMessage();
}

header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
exit;