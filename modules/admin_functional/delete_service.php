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
    exit('Service invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM ref_services WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Service introuvable.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE ref_services
            SET is_active = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$id]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction($pdo, (int)$_SESSION['user_id'], 'archive_service', 'admin_functional', 'service', $id, 'Archivage d’un service');
        }

        header('Location: ' . APP_URL . 'modules/admin_functional/manage_services.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Archiver un service';
$pageSubtitle = 'On désactive le service, on ne supprime pas l’historique.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="card">
            <h3>Confirmation</h3>
            <p>Tu vas archiver le service : <strong><?= e($row['label'] ?? '') ?></strong> (<?= e($row['code'] ?? '') ?>).</p>

            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">Confirmer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>