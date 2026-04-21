<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'pending_debits_cancel');
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

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error_message'] = 'Débit dû invalide.';
    header('Location: ' . APP_URL . 'modules/pending_debits/pending_debits_list.php');
    exit;
}

if (!tableExists($pdo, 'pending_client_debits')) {
    $_SESSION['error_message'] = 'Table pending_client_debits introuvable.';
    header('Location: ' . APP_URL . 'modules/pending_debits/pending_debits_list.php');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Méthode invalide pour cette action.');
    }

    if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
        throw new RuntimeException('Jeton CSRF invalide.');
    }

    $stmt = $pdo->prepare("SELECT * FROM pending_client_debits WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new RuntimeException('Débit dû introuvable.');
    }

    $currentStatus = (string)($item['status'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $clientCode = (string)($item['client_code'] ?? '');
    $clientAccountCode = (string)($item['client_account_code'] ?? '');
    $remainingAmount = (float)($item['remaining_amount'] ?? 0);
    $label = trim((string)($item['label'] ?? 'Débit dû'));
    $redirectUrl = APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id;

    if (in_array($currentStatus, ['resolved', 'cancelled', 'settled'], true)) {
        $_SESSION['error_message'] = 'Ce débit dû ne peut plus être annulé car il est déjà ' . $currentStatus . '.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $pdo->beginTransaction();

    $updateFields = ["status = 'cancelled'"];
    if (columnExists($pdo, 'pending_client_debits', 'updated_at')) {
        $updateFields[] = "updated_at = NOW()";
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE pending_client_debits
        SET " . implode(', ', $updateFields) . "
        WHERE id = ?
    ");
    $stmtUpdate->execute([$id]);

    if (function_exists('sl_create_pending_client_debit_log')) {
        sl_create_pending_client_debit_log(
            $pdo,
            $id,
            'cancel',
            $currentStatus,
            'cancelled',
            $remainingAmount,
            'Annulation manuelle du débit dû',
            $userId
        );
    }

    if (function_exists('logUserAction') && $userId > 0) {
        logUserAction(
            $pdo,
            $userId,
            'cancel_pending_debit',
            'pending_debits',
            'pending_client_debit',
            $id,
            'Annulation du débit dû #' . $id
            . ($clientCode !== '' ? ' | client: ' . $clientCode : '')
            . ($clientAccountCode !== '' ? ' | compte: ' . $clientAccountCode : '')
            . ' | montant restant: ' . number_format($remainingAmount, 2, '.', '')
        );
    }

    sl_create_notification_if_possible(
        $pdo,
        'pending_debit_cancelled',
        'Débit dû annulé'
            . ($clientCode !== '' ? ' pour le client ' . $clientCode : '')
            . ' | restant : ' . number_format($remainingAmount, 2, ',', ' ')
            . ($label !== '' ? ' | ' . $label : ''),
        'warning',
        $redirectUrl,
        'pending_client_debit',
        $id,
        $userId
    );

    $pdo->commit();

    $_SESSION['success_message'] = 'Le débit dû a été annulé avec succès.';
    header('Location: ' . $redirectUrl);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id);
    exit;
}