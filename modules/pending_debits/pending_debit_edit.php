<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'pending_debits_edit_page', 'pending_debits_edit');
} else {
    enforcePagePermission($pdo, 'pending_debits_edit');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remainingAmount = (float)str_replace(',', '.', (string)($_POST['remaining_amount'] ?? '0'));
    $priorityLevel = trim((string)($_POST['priority_level'] ?? 'normal'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'pending'));

    if ($remainingAmount < 0) {
        $error = 'Le montant restant dû ne peut pas être négatif.';
    } else {
        $oldStatus = (string)$item['status'];
        $newExecuted = max(0, (float)$item['initial_amount'] - $remainingAmount);
        $newStatus = $remainingAmount <= 0 ? 'resolved' : $status;

        $stmtUpdate = $pdo->prepare("
            UPDATE pending_client_debits
            SET executed_amount = ?,
                remaining_amount = ?,
                priority_level = ?,
                notes = ?,
                status = ?,
                resolved_at = " . ($remainingAmount <= 0 ? "NOW()" : "NULL") . ",
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

        sl_create_pending_client_debit_log(
            $pdo,
            $id,
            'edit',
            $oldStatus,
            $newStatus,
            $remainingAmount,
            'Modification manuelle du débit dû',
            (int)($_SESSION['user_id'] ?? 0)
        );

        header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $id);
        exit;
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

            <form method="post">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="dashboard-grid-2">
                    <div>
                        <label>Client</label>
                        <input type="text" class="form-control" value="<?= e((string)$item['client_code'] . ' - ' . (string)$item['full_name']) ?>" disabled>
                    </div>

                    <div>
                        <label>Montant restant dû</label>
                        <input type="number" step="0.01" name="remaining_amount" class="form-control" value="<?= e((string)$item['remaining_amount']) ?>" required>
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