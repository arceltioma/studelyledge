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

$totalUsers = tableExists($pdo, 'users')
    ? (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
    : 0;

$totalRoles = tableExists($pdo, 'roles')
    ? (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn()
    : 0;

$totalPermissions = tableExists($pdo, 'permissions')
    ? (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn()
    : 0;

$totalSupportOpen = tableExists($pdo, 'support_requests')
    ? (int)$pdo->query("SELECT COUNT(*) FROM support_requests WHERE status IN ('open','in_progress')")->fetchColumn()
    : 0;

/* LOT 2 - indicateurs additifs */
$totalUnreadNotifications = function_exists('countUnreadNotifications')
    ? countUnreadNotifications($pdo)
    : 0;

$totalAuditTrail = tableExists($pdo, 'audit_trail')
    ? (int)$pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn()
    : 0;

$totalUserLogs = tableExists($pdo, 'user_logs')
    ? (int)$pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn()
    : 0;

$totalImportsJournal = tableExists($pdo, 'imports')
    ? (int)$pdo->query("SELECT COUNT(*) FROM imports")->fetchColumn()
    : 0;

$recentNotifications = function_exists('getUnreadNotifications')
    ? getUnreadNotifications($pdo, 6)
    : [];

$pageTitle = 'Dashboard Admin Technique';
$pageSubtitle = 'Pilotage des rôles, droits, utilisateurs, logs, notifications, intelligence et support.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid">
            <div class="card">
                <h3>Utilisateurs</h3>
                <div class="kpi"><?= (int)$totalUsers ?></div>
            </div>

            <div class="card">
                <h3>Rôles</h3>
                <div class="kpi"><?= (int)$totalRoles ?></div>
            </div>

            <div class="card">
                <h3>Permissions</h3>
                <div class="kpi"><?= (int)$totalPermissions ?></div>
            </div>

            <div class="card">
                <h3>Support ouvert</h3>
                <div class="kpi"><?= (int)$totalSupportOpen ?></div>
            </div>
        </div>

        <!-- LOT 2 : supervision technique -->
        <div class="card-grid dashboard-section-spacing">
            <div class="card">
                <h3>Notifications non lues</h3>
                <div class="kpi"><?= (int)$totalUnreadNotifications ?></div>
            </div>

            <div class="card">
                <h3>Audit trail</h3>
                <div class="kpi"><?= (int)$totalAuditTrail ?></div>
            </div>

            <div class="card">
                <h3>Logs utilisateurs</h3>
                <div class="kpi"><?= (int)$totalUserLogs ?></div>
            </div>

            <div class="card">
                <h3>Imports journalisés</h3>
                <div class="kpi"><?= (int)$totalImportsJournal ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Utilisateurs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/roles.php" class="btn btn-outline">Rôles</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php" class="btn btn-outline">Matrice d’accès</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Audit des logs</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php" class="btn btn-outline">Audit & traçabilité</a>
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Notifications</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/intelligence_center.php" class="btn btn-outline">Centre d’intelligence</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/support_requests.php" class="btn btn-outline">Demandes support</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    L’administration technique pilote le socle : utilisateurs, rôles, permissions, support, notifications, intelligence et traçabilité.
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Centre d’intelligence</h3>
                <div class="dashboard-note" style="margin-bottom:16px;">
                    Accède au moteur intelligent du projet pour consulter les règles métier, les anomalies et les briques d’analyse avancée.
                </div>
                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/admin/intelligence_center.php" class="btn btn-success">Ouvrir le centre d’intelligence</a>
                </div>
            </div>

            <div class="card">
                <h3>Traçabilité & supervision</h3>
                <div class="dashboard-note" style="margin-bottom:16px;">
                    La supervision technique regroupe les notifications, les traces d’audit, les journaux utilisateurs et les contrôles transverses.
                </div>
                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php" class="btn btn-outline">Voir l’audit</a>
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Voir les notifications</a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Notifications récentes non lues</h3>

                <?php if ($recentNotifications): ?>
                    <div class="sl-anomaly-list">
                        <?php foreach ($recentNotifications as $item): ?>
                            <div class="sl-anomaly-list__item">
                                <span class="sl-anomaly-list__label">
                                    <?= e((string)($item['message'] ?? '')) ?>
                                </span>
                                <strong class="sl-anomaly-list__value">
                                    <?= e((string)($item['level'] ?? 'info')) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Voir tout</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Aucune notification non lue.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Résumé technique</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Comptes utilisateurs</span>
                        <strong><?= (int)$totalUsers ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Structure des rôles</span>
                        <strong><?= (int)$totalRoles ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Règles d’accès</span>
                        <strong><?= (int)$totalPermissions ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Notifications non lues</span>
                        <strong><?= (int)$totalUnreadNotifications ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Éléments d’audit</span>
                        <strong><?= (int)$totalAuditTrail ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Logs applicatifs</span>
                        <strong><?= (int)$totalUserLogs ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>