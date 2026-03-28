<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_dashboard_view');

$pageTitle = 'Paramètres';
$pageSubtitle = 'Point d’entrée pour les réglages techniques globaux.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Paramètres techniques</h3>
                <p class="muted">
                    Cette page sert de hub de configuration pour les réglages applicatifs,
                    imports, exports, sécurité et maintenance.
                </p>

                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Gérer les utilisateurs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/roles.php" class="btn btn-outline">Gérer les rôles</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php" class="btn btn-outline">Matrice d’accès</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Voir les logs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/support_requests.php" class="btn btn-outline">Demandes support</a>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/rebuild_balances.php" class="btn btn-outline">Recalculer les soldes</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Les réglages métiers sont répartis dans les modules fonctionnels dédiés.
                    Cette page reste le point d’accès central pour l’administration technique.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>