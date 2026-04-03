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

if (!function_exists('notificationBadgeClass')) {
    function notificationBadgeClass(string $level): string
    {
        $level = strtolower(trim($level));

        return match ($level) {
            'success' => 'badge badge-success',
            'warning' => 'badge badge-warning',
            'danger' => 'badge badge-danger',
            'info' => 'badge badge-info',
            default => 'badge badge-secondary',
        };
    }
}

if (!function_exists('notificationTypeIcon')) {
    function notificationTypeIcon(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'import_success', 'client_import', 'treasury_import', 'client_import_summary', 'treasury_import_summary' => '📥',
            'import_error' => '⚠️',
            'operation_create', 'operation_update' => '💰',
            'client_update', 'client_import' => '👤',
            'treasury_update', 'treasury_create' => '🏦',
            'manual_accounting' => '🧮',
            default => '🔔',
        };
    }
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

$stats = [
    'total' => count($items),
    'unread' => 0,
    'warning' => 0,
    'danger' => 0,
    'success' => 0,
];

foreach ($items as $item) {
    if ((int)($item['is_read'] ?? 0) === 0) {
        $stats['unread']++;
    }

    $level = strtolower((string)($item['level'] ?? 'info'));
    if (isset($stats[$level])) {
        $stats[$level]++;
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
        .sl-notification-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 16px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 14px;
            background: #fff;
        }

        .sl-notification-item + .sl-notification-item {
            margin-top: 12px;
        }

        .sl-notification-item--unread {
            border-left: 4px solid #22c55e;
            background: rgba(248, 250, 252, 0.9);
        }

        .sl-notification-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.04);
            font-size: 20px;
        }

        .sl-notification-content {
            flex: 1;
            min-width: 0;
        }

        .sl-notification-title {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .sl-notification-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .sl-notification-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        </style>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Notifications</div>
                <div class="sl-kpi-card__value"><?= (int)$stats['total'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Total affiché</span>
                    <strong>200 max</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Non lues</div>
                <div class="sl-kpi-card__value"><?= (int)$stats['unread'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>À traiter</span>
                    <strong>Priorité</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Warnings</div>
                <div class="sl-kpi-card__value"><?= (int)$stats['warning'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Points d’attention</span>
                    <strong>Suivi</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Succès</div>
                <div class="sl-kpi-card__value"><?= (int)$stats['success'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Actions terminées</span>
                    <strong>OK</strong>
                </div>
            </div>
        </section>

        <section class="sl-card">
            <div class="btn-group" style="margin-bottom:16px;">
                <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php?mark_all=1" class="btn btn-outline">Tout marquer comme lu</a>
            </div>

            <?php if ($items): ?>
                <?php foreach ($items as $item): ?>
                    <div class="sl-notification-item <?= (int)($item['is_read'] ?? 0) === 0 ? 'sl-notification-item--unread' : '' ?>">
                        <div class="sl-notification-icon">
                            <?= e(notificationTypeIcon((string)($item['type'] ?? ''))) ?>
                        </div>

                        <div class="sl-notification-content">
                            <div class="sl-notification-title"><?= e((string)($item['message'] ?? '')) ?></div>

                            <div class="sl-notification-meta">
                                <span class="badge badge-outline">Type : <?= e((string)($item['type'] ?? '')) ?></span>
                                <span class="<?= e(notificationBadgeClass((string)($item['level'] ?? 'info'))) ?>">
                                    <?= e((string)($item['level'] ?? 'info')) ?>
                                </span>
                                <span class="badge badge-outline"><?= e((string)($item['created_at'] ?? '')) ?></span>
                                <?php if ((int)($item['is_read'] ?? 0) === 0): ?>
                                    <span class="badge badge-success">Non lue</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Lue</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sl-notification-actions">
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