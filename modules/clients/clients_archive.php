<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_edit');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($id <= 0) {
    exit('Client invalide.');
}

if (!in_array($action, ['archive', 'restore'], true)) {
    exit('Action invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM clients
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$successMessage = '';
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

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                $action === 'archive' ? 'archive_client' : 'restore_client',
                'clients',
                'client',
                $id,
                ($action === 'archive' ? 'Archivage' : 'Réactivation') . ' du client ' . ($client['client_code'] ?? '')
            );
        }

        header('Location: ' . APP_URL . 'modules/clients/clients_list.php');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = $action === 'archive' ? 'Archiver un client' : 'Réactiver un client';
$pageSubtitle = $action === 'archive'
    ? 'Le client sera désactivé mais conservé en base.'
    : 'Le client sera réactivé et redeviendra exploitable.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Confirmation</h3>

                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">Code client</span>
                        <span class="detail-value"><?= e($client['client_code'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Nom</span>
                        <span class="detail-value"><?= e($client['full_name'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Compte 411</span>
                        <span class="detail-value"><?= e($client['generated_client_account'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">État actuel</span>
                        <span class="detail-value"><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Inactif' ?></span>
                    </div>
                </div>

                <form method="POST" style="margin-top:20px;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <input type="hidden" name="action" value="<?= e($action) ?>">

                    <div class="btn-group">
                        <button type="submit" class="btn <?= $action === 'archive' ? 'btn-danger' : 'btn-success' ?>">
                            <?= $action === 'archive' ? 'Confirmer l’archivage' : 'Confirmer la réactivation' ?>
                        </button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Effet</h3>
                <div class="dashboard-note">
                    <?= $action === 'archive'
                        ? 'Le client ne sera plus proposé dans les flux actifs, mais son historique, son compte 411 et ses opérations seront conservés.'
                        : 'Le client redeviendra visible et exploitable dans les flux actifs.' ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>