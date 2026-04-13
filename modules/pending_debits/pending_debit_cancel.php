<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'pending_debits_cancel_page', 'pending_debits_cancel');
} else {
    enforcePagePermission($pdo, 'pending_debits_cancel');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Débit dû invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM pending_client_debits WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    exit('Débit dû introuvable.');
}

if (!in_array((string)$item['status'], ['resolved', 'cancelled'], true)) {
    $stmtUpdate = $pdo->prepare("
        UPDATE pending_client_debits
        SET status = 'cancelled',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmtUpdate->execute([$id]);

    sl_create_pending_client_debit_log(
        $pdo,
        $id,
        'cancel',
        (string)$item['status'],
        'cancelled',
        (float)($item['remaining_amount'] ?? 0),
        'Annulation manuelle du débit dû',
        (int)($_SESSION['user_id'] ?? 0)
    );
}

header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id);
exit;