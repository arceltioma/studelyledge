<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Opération invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $label = trim((string)($_POST['label'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($label === '') {
            throw new RuntimeException('Le libellé est obligatoire.');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE operations
            SET
                label = ?,
                reference = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $label,
            $reference !== '' ? $reference : null,
            $notes !== '' ? $notes : null,
            $id
        ]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_operation',
                'operations',
                'operation',
                $id,
                'Modification descriptive d’une opération'
            );
        }

        $successMessage = 'Opération mise à jour.';
        $stmt->execute([$id]);
        $operation = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier une opération';
$pageSubtitle = 'Les comptes restent intacts, le descriptif peut être ajusté.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="dashboard-grid-2">
                    <div><label>Date</label><input type="text" value="<?= e($operation['operation_date'] ?? '') ?>" readonly></div>
                    <div><label>Montant</label><input type="text" value="<?= e((string)($operation['amount'] ?? '')) ?>" readonly></div>
                    <div><label>Débit</label><input type="text" value="<?= e($operation['debit_account_code'] ?? '') ?>" readonly></div>
                    <div><label>Crédit</label><input type="text" value="<?= e($operation['credit_account_code'] ?? '') ?>" readonly></div>
                </div>

                <div>
                    <label>Libellé</label>
                    <input type="text" name="label" value="<?= e($operation['label'] ?? '') ?>" required>
                </div>

                <div>
                    <label>Référence</label>
                    <input type="text" name="reference" value="<?= e($operation['reference'] ?? '') ?>">
                </div>

                <div>
                    <label>Notes</label>
                    <textarea name="notes" rows="4"><?= e($operation['notes'] ?? '') ?></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/operations/operations_list.php">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>