<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'pending_debits_edit');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    exit('Débit dû invalide.');
}

$stmt = $pdo->prepare("
    SELECT pd.*, c.client_code, c.full_name
    FROM pending_client_debits pd
    INNER JOIN clients c ON c.id = pd.client_id
    WHERE pd.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    exit('Débit dû introuvable.');
}

$error = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $remainingAmount = (float)str_replace(',', '.', (string)($_POST['remaining_amount'] ?? '0'));
        $priorityLevel = trim((string)($_POST['priority_level'] ?? 'normal'));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'pending'));

        $allowedStatuses = ['pending', 'partial', 'ready', 'resolved', 'cancelled'];
        $allowedPriorities = ['normal', 'high'];

        if ($remainingAmount < 0) {
            throw new RuntimeException('Le montant restant dû ne peut pas être négatif.');
        }

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Statut invalide.');
        }

        if (!in_array($priorityLevel, $allowedPriorities, true)) {
            throw new RuntimeException('Niveau de priorité invalide.');
        }

        $oldStatus = (string)($item['status'] ?? 'pending');
        $initialAmount = (float)($item['initial_amount'] ?? 0);
        $oldRemaining = (float)($item['remaining_amount'] ?? 0);
        $oldExecuted = (float)($item['executed_amount'] ?? 0);
        $clientCode = (string)($item['client_code'] ?? '');
        $clientName = (string)($item['full_name'] ?? '');
        $label = (string)($item['label'] ?? 'Débit dû');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $newExecuted = max(0, $initialAmount - $remainingAmount);
        $newStatus = $remainingAmount <= 0 ? 'resolved' : $status;
        $resolvedAtSql = $remainingAmount <= 0 ? 'NOW()' : 'NULL';

        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("
            UPDATE pending_client_debits
            SET executed_amount = ?,
                remaining_amount = ?,
                priority_level = ?,
                notes = ?,
                status = ?,
                resolved_at = {$resolvedAtSql},
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $newExecuted,
            $remainingAmount,
            $priorityLevel,
            $notes,
            $newStatus,
            $id
        ]);

        if (function_exists('sl_create_pending_client_debit_log')) {
            sl_create_pending_client_debit_log(
                $pdo,
                $id,
                'edit',
                $oldStatus,
                $newStatus,
                $remainingAmount,
                'Modification manuelle du débit dû',
                $userId
            );
        }

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'edit_pending_debit',
                'pending_debits',
                'pending_client_debit',
                $id,
                'Modification du débit dû #' . $id
                . ($clientCode !== '' ? ' | client: ' . $clientCode : '')
                . ' | ancien statut: ' . $oldStatus
                . ' | nouveau statut: ' . $newStatus
                . ' | ancien restant: ' . number_format($oldRemaining, 2, '.', '')
                . ' | nouveau restant: ' . number_format($remainingAmount, 2, '.', '')
                . ' | ancien exécuté: ' . number_format($oldExecuted, 2, '.', '')
                . ' | nouvel exécuté: ' . number_format($newExecuted, 2, '.', '')
            );
        }

        $notificationMessage = 'Débit dû modifié'
            . ($clientCode !== '' ? ' pour le client ' . $clientCode : '')
            . ($clientName !== '' ? ' - ' . $clientName : '')
            . ' | statut : ' . $newStatus
            . ' | restant : ' . number_format($remainingAmount, 2, ',', ' ')
            . ' | exécuté : ' . number_format($newExecuted, 2, ',', ' ')
            . ($label !== '' ? ' | ' . $label : '');

        sl_create_notification_if_possible(
            $pdo,
            $newStatus === 'resolved' ? 'pending_debit_resolved_manual' : 'pending_debit_updated',
            $notificationMessage,
            $newStatus === 'resolved' ? 'success' : 'info',
            APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id,
            'pending_client_debit',
            $id,
            $userId
        );

        $pdo->commit();

        $_SESSION['success_message'] = $newStatus === 'resolved'
            ? 'Le débit dû a été mis à jour et marqué comme résolu.'
            : 'Le débit dû a été mis à jour avec succès.';

        header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$pageTitle = 'Modifier débit dû 411';
$pageSubtitle = 'Ajustement manuel';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?= e($successMessage) ?></div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="dashboard-grid-2">
                    <div>
                        <label>Client</label>
                        <input
                            type="text"
                            class="form-control"
                            value="<?= e((string)$item['client_code'] . ' - ' . (string)$item['full_name']) ?>"
                            disabled
                        >
                    </div>

                    <div>
                        <label>Montant restant dû</label>
                        <input
                            type="number"
                            step="0.01"
                            name="remaining_amount"
                            class="form-control"
                            value="<?= e((string)$item['remaining_amount']) ?>"
                            required
                        >
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="status" class="form-control">
                            <option value="pending" <?= $item['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="partial" <?= $item['status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="ready" <?= $item['status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                            <option value="resolved" <?= $item['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="cancelled" <?= $item['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label>Priorité</label>
                        <select name="priority_level" class="form-control">
                            <option value="normal" <?= $item['priority_level'] === 'normal' ? 'selected' : '' ?>>Normale</option>
                            <option value="high" <?= $item['priority_level'] === 'high' ? 'selected' : '' ?>>Haute</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="5"><?= e((string)($item['notes'] ?? '')) ?></textarea>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$id ?>" class="btn btn-outline">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>