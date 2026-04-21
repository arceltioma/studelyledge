<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'treasury_archive');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

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

if (!columnExists($pdo, 'treasury_accounts', 'is_active')) {
    $_SESSION['error_message'] = 'La colonne is_active est absente.';
    header('Location: ' . APP_URL . 'modules/treasury/index.php');
    exit;
}

if (!function_exists('sl_create_notification_if_possible')) {
    function sl_create_notification_if_possible(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM treasury_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['error_message'] = 'Compte de trésorerie introuvable.';
        header('Location: ' . APP_URL . 'modules/treasury/index.php');
        exit;
    }

    $currentStatus = (int)($row['is_active'] ?? 1);
    $newStatus = $currentStatus === 1 ? 0 : 1;

    $accountCode = (string)($row['account_code'] ?? '');
    $accountLabel = (string)($row['account_label'] ?? '');
    $accountDisplay = trim($accountCode . ' - ' . $accountLabel, ' -');

    $pdo->beginTransaction();

    $sql = "UPDATE treasury_accounts SET is_active = ?";
    $params = [$newStatus];

    if (columnExists($pdo, 'treasury_accounts', 'updated_at')) {
        $sql .= ", updated_at = NOW()";
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if (function_exists('logUserAction') && $userId > 0) {
        logUserAction(
            $pdo,
            $userId,
            $newStatus === 1 ? 'reactivate_treasury_account' : 'archive_treasury_account',
            'treasury',
            'treasury_account',
            $id,
            ($newStatus === 1 ? 'Réactivation' : 'Archivage') . ' du compte de trésorerie ' . $accountDisplay
        );
    }

    $notificationMessage = $newStatus === 1
        ? 'Compte de trésorerie réactivé : ' . $accountDisplay
        : 'Compte de trésorerie archivé : ' . $accountDisplay;

    sl_create_notification_if_possible(
        $pdo,
        $newStatus === 1 ? 'treasury_reactivate' : 'treasury_archive',
        $notificationMessage,
        $newStatus === 1 ? 'success' : 'warning',
        APP_URL . 'modules/treasury/treasury_view.php?id=' . $id,
        'treasury_account',
        $id,
        $userId
    );

    $pdo->commit();

    $_SESSION['success_message'] = $newStatus === 1
        ? 'Compte de trésorerie réactivé avec succès.'
        : 'Compte de trésorerie archivé avec succès.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = $e->getMessage();
}

header('Location: ' . APP_URL . 'modules/treasury/index.php');
exit;