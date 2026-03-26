<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_dashboard_view');

require_once __DIR__ . '/../../includes/header.php';

$totalUsers = tableExists($pdo, 'users') ? (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() : 0;
$totalRoles = tableExists($pdo, 'roles') ? (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn() : 0;
$totalLogs = tableExists($pdo, 'user_logs') ? (int)$pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn() : 0;
$totalPermissions = tableExists($pdo, 'permissions') ? (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn() : 0;
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Dashboard Admin Technique',
        'Le poste de pilotage des accès, rôles, permissions et traces d’usage.'
    ); ?>

    <div class="card-grid">
        <div class="card"><h3>Utilisateurs</h3><div class="kpi"><?= $totalUsers ?></div></div>
        <div class="card"><h3>Rôles</h3><div class="kpi"><?= $totalRoles ?></div></div>
        <div class="card"><h3>Logs</h3><div class="kpi"><?= $totalLogs ?></div></div>
        <div class="card"><h3>Permissions</h3><div class="kpi"><?= $totalPermissions ?></div></div>
    </div>

    <div class="dashboard-grid-2" style="margin-top:20px;">
        <div class="card">
            <h3>Accès rapides</h3>
            <div class="btn-group" style="flex-direction:column;align-items:flex-start;">
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/users.php">Utilisateurs</a>
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/roles.php">Rôles</a>
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/access_matrix.php">Matrice d’accès</a>
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/user_logs.php">Audit des logs</a>
            </div>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Vision</h3>
            <div class="dashboard-note">
                L’administration technique ne gère pas le métier. Elle gère qui peut voir, modifier, créer ou supprimer.
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>