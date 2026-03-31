<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceAccess($pdo, 'clients_archive_page');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($id <= 0) {
    exit('Client invalide.');
}

if (!in_array($action, ['archive', 'restore'], true)) {
    exit('Action invalide.');
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

        $newStatus = $action === 'archive' ? 0 : 1;

        $stmtUpdate = $pdo->prepare("
            UPDATE clients
            SET is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$newStatus, $id]);

        header('Location: ' . APP_URL . 'modules/clients/clients_list.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = $action === 'archive' ? 'Archiver un client' : 'Réactiver un client';
$pageSubtitle = $action === 'archive'
    ? 'Le client sera désactivé mais restera conservé en base.'
    : 'Le client redeviendra actif dans les flux de gestion.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Confirmation</h3>

                <div class="detail-grid">
                    <div class="detail-row"><span class="detail-label">Code client</span><span class="detail-value"><?= e($client['client_code'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Nom</span><span class="detail-value"><?= e($client['full_name'] ?? '') ?></span></div>
                    <div class="detail-row"><span class="detail-label">Compte client</span><span class="detail-value"><?= e($client['generated_client_account'] ?? '') ?></span></div>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <input type="hidden" name="action" value="<?= e($action) ?>">

                    <div class="btn-group">
                        <?php if ($action === 'archive'): ?>
                            <button type="submit" class="btn btn-danger">Confirmer l’archivage</button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">Confirmer la réactivation</button>
                        <?php endif; ?>

                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Effet de l’action</h3>
                <div class="dashboard-note">
                    <?php if ($action === 'archive'): ?>
                        Le client sera retiré des flux actifs mais restera disponible pour l’historique.
                    <?php else: ?>
                        Le client redeviendra actif et visible dans les listes standards.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>