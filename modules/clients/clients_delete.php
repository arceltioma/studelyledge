<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceAccess($pdo, 'clients_delete_page');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $stmtDelete = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmtDelete->execute([$id]);

        header('Location: ' . APP_URL . 'modules/clients/clients_list.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer un client';
$pageSubtitle = 'Suppression définitive du client.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="form-card">
            <h1>Supprimer le client</h1>
            <p>Tu es sur le point de supprimer définitivement le client <strong><?= e($client['full_name'] ?? '') ?></strong>.</p>

            <form method="POST" style="margin-top:20px;">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>