<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'monthly_run_cancel');
}

$runId = (int)($_GET['id'] ?? 0);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = sl_monthly_payment_cancel_run(
            $pdo,
            $runId,
            $_SESSION['user_id'] ?? null
        );

        $message = "Run annulé. Opérations supprimées : " . $result['deleted_operations'];
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Annulation run';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<?php if ($message): ?><div class="success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <h3>Confirmer l'annulation du run #<?= $runId ?></h3>

    <form method="POST">
        <p>⚠️ Cette action va supprimer toutes les opérations générées.</p>

        <div class="btn-group">
            <button type="submit" class="btn btn-danger">Confirmer l'annulation</button>
            <a href="monthly_runs_list.php" class="btn btn-outline">Annuler</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>