<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'admin_dashboard_page');
} else {
    enforcePagePermission($pdo, 'admin_dashboard_view');
}

$totalUsers = tableExists($pdo, 'users') ? (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() : 0;
$totalRoles = tableExists($pdo, 'roles') ? (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn() : 0;
$totalPermissions = tableExists($pdo, 'permissions') ? (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn() : 0;
$totalSupportOpen = tableExists($pdo, 'support_requests') ? (int)$pdo->query("SELECT COUNT(*) FROM support_requests WHERE status IN ('open','in_progress')")->fetchColumn() : 0;
$totalUnreadNotifications = function_exists('countUnreadNotifications') ? countUnreadNotifications($pdo) : 0;
$totalAuditTrail = tableExists($pdo, 'audit_trail') ? (int)$pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn() : 0;
$totalLogs = tableExists($pdo, 'user_logs') ? (int)$pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn() : 0;

$pageTitle = 'Dashboard Admin Technique';
$pageSubtitle = 'Pilotage des rôles, droits, utilisateurs, logs, notifications, audit et support.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Utilisateurs</div>
                <div class="sl-kpi-card__value"><?= (int)$totalUsers ?></div>
            </div>
            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Rôles</div>
                <div class="sl-kpi-card__value"><?= (int)$totalRoles ?></div>
            </div>
            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Permissions</div>
                <div class="sl-kpi-card__value"><?= (int)$totalPermissions ?></div>
            </div>
            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Support ouvert</div>
                <div class="sl-kpi-card__value"><?= (int)$totalSupportOpen ?></div>
            </div>
        </section>

        <section class="sl-grid sl-grid-3 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card">
                <h3>Notifications</h3>
                <div class="kpi"><?= (int)$totalUnreadNotifications ?></div>
                <div class="btn-group" style="margin-top:14px;">
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Ouvrir</a>
                </div>
            </div>

            <div class="sl-card">
                <h3>Audit Trail</h3>
                <div class="kpi"><?= (int)$totalAuditTrail ?></div>
                <div class="btn-group" style="margin-top:14px;">
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=trail" class="btn btn-outline">Voir</a>
                </div>
            </div>

            <div class="sl-card">
                <h3>Logs utilisateurs</h3>
                <div class="kpi"><?= (int)$totalLogs ?></div>
                <div class="btn-group" style="margin-top:14px;">
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Voir</a>
                </div>
            </div>
        </section>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Utilisateurs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/roles.php" class="btn btn-outline">Rôles</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php" class="btn btn-outline">Matrice d’accès</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Audit des logs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php" class="btn btn-outline">Audit & traçabilité</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/intelligence_center.php" class="btn btn-outline">Centre d’intelligence</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/support_requests.php" class="btn btn-outline">Demandes support</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    L’administration technique pilote les accès, la supervision, l’audit, les notifications et la stabilité du socle applicatif.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>