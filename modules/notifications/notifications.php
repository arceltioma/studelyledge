<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'dashboard_view_page');
} else {
    enforcePagePermission($pdo, 'dashboard_view');
}

if (isset($_GET['mark_all']) && $_GET['mark_all'] === '1') {
    markAllNotificationsRead($pdo);
    $_SESSION['success_message'] = 'Toutes les notifications ont été marquées comme lues.';
    header('Location: ' . APP_URL . 'modules/notifications/notifications.php');
    exit;
}

if (isset($_GET['read'])) {
    markNotificationRead($pdo, (int)$_GET['read']);
    header('Location: ' . APP_URL . 'modules/notifications/notifications.php');
    exit;
}

$pageTitle = 'Notifications';
$pageSubtitle = 'Centre d’alertes, événements et informations système';

$items = tableExists($pdo, 'notifications')
    ? $pdo->query("
        SELECT *
        FROM notifications
        ORDER BY created_at DESC, id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-page-hero sl-stable-block">
            <div>
                <h1><?= e($pageTitle) ?></h1>
                <p><?= e($pageSubtitle) ?></p>
            </div>
        </section>

        <section class="sl-card">
            <div class="btn-group" style="margin-bottom:16px;">
                <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php?mark_all=1" class="btn btn-outline">Tout marquer comme lu</a>
            </div>

            <?php if ($items): ?>
                <?php foreach ($items as $item): ?>
                    <div class="sl-anomaly-list__item" style="margin-bottom:10px; <?= (int)($item['is_read'] ?? 0) === 0 ? 'border-left:4px solid #22c55e;' : '' ?>">
                        <div style="flex:1;">
                            <strong><?= e((string)($item['message'] ?? '')) ?></strong>
                            <div class="muted" style="margin-top:6px;">
                                Type : <?= e((string)($item['type'] ?? '')) ?> |
                                Niveau : <?= e((string)($item['level'] ?? 'info')) ?> |
                                Date : <?= e((string)($item['created_at'] ?? '')) ?>
                            </div>
                        </div>

                        <div class="btn-group">
                            <?php if (!empty($item['link_url'])): ?>
                                <a class="btn btn-sm btn-secondary" href="<?= e((string)$item['link_url']) ?>">Ouvrir</a>
                            <?php endif; ?>

                            <?php if ((int)($item['is_read'] ?? 0) === 0): ?>
                                <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>modules/notifications/notifications.php?read=<?= (int)$item['id'] ?>">Marquer lu</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">Aucune notification.</p>
            <?php endif; ?>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>