<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_dashboard_view');

$totalUsers = tableExists($pdo, 'users') ? (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() : 0;
$totalRoles = tableExists($pdo, 'roles') ? (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn() : 0;
$totalPermissions = tableExists($pdo, 'permissions') ? (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn() : 0;
$totalSupportOpen = tableExists($pdo, 'support_requests') ? (int)$pdo->query("SELECT COUNT(*) FROM support_requests WHERE status IN ('open','in_progress')")->fetchColumn() : 0;

$pageTitle = 'Dashboard Admin Technique';
$pageSubtitle = 'Pilotage des rôles, droits, utilisateurs, logs et support.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid">
            <div class="card"><h3>Utilisateurs</h3><div class="kpi"><?= $totalUsers ?></div></div>
            <div class="card"><h3>Rôles</h3><div class="kpi"><?= $totalRoles ?></div></div>
            <div class="card"><h3>Permissions</h3><div class="kpi"><?= $totalPermissions ?></div></div>
            <div class="card"><h3>Support ouvert</h3><div class="kpi"><?= $totalSupportOpen ?></div></div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Utilisateurs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/roles.php" class="btn btn-outline">Rôles</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php" class="btn btn-outline">Matrice d’accès</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Audit des logs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/support_requests.php" class="btn btn-outline">Demandes support</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    L’administration technique pilote le socle : utilisateurs, rôles, permissions, support et traçabilité.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>