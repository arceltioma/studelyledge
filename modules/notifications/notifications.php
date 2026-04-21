<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

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
    markNotificationRead($pdo, (int) $_GET['read']);

    $query = $_GET;
    unset($query['read']);
    $redirect = APP_URL . 'modules/notifications/notifications.php';
    if ($query) {
        $redirect .= '?' . http_build_query($query);
    }

    header('Location: ' . $redirect);
    exit;
}

$pageTitle = 'Notifications';
$pageSubtitle = 'Centre d’alertes, événements et informations système';

$filters = function_exists('sl_notifications_parse_filters')
    ? sl_notifications_parse_filters($_GET)
    : [
        'q' => '',
        'type' => '',
        'level' => '',
        'entity_type' => '',
        'client_id' => '',
        'operation_type_code' => '',
        'service_id' => '',
        'status' => '',
        'date_from' => '',
        'date_to' => '',
        'page' => 1,
        'per_page' => 25,
    ];

$options = function_exists('sl_notifications_get_filter_options')
    ? sl_notifications_get_filter_options($pdo)
    : [
        'types' => [],
        'levels' => [],
        'entity_types' => [],
        'clients' => [],
        'operation_types' => [],
        'services' => [],
        'statuses' => [],
    ];

$stats = function_exists('sl_notifications_get_kpis')
    ? sl_notifications_get_kpis($pdo)
    : [
        'total' => 0,
        'unread' => 0,
        'warning' => 0,
        'danger' => 0,
        'success' => 0,
    ];

$list = function_exists('sl_notifications_get_rows')
    ? sl_notifications_get_rows($pdo, $filters)
    : [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 25,
        'pages' => 1,
    ];

$items = $list['rows'] ?? [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Notifications</div>
                <div class="sl-kpi-card__value"><?= (int)($stats['total'] ?? 0) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Total</span>
                    <strong>Système</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Non lues</div>
                <div class="sl-kpi-card__value"><?= (int)($stats['unread'] ?? 0) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>À traiter</span>
                    <strong>Priorité</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Warnings</div>
                <div class="sl-kpi-card__value"><?= (int)($stats['warning'] ?? 0) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Attention</span>
                    <strong>Suivi</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Succès</div>
                <div class="sl-kpi-card__value"><?= (int)($stats['success'] ?? 0) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Actions OK</span>
                    <strong>Info</strong>
                </div>
            </div>
        </section>

        <section class="sl-card sl-stable-block sl-notifications-toolbar" style="margin-bottom:20px;">
            <form method="GET" class="sl-toolbar-form">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Message, client, type, service...">
                    </div>

                    <div>
                        <label>Type</label>
                        <select name="type">
                            <option value="">Tous</option>
                            <?php foreach (($options['types'] ?? []) as $value): ?>
                                <option value="<?= e((string)$value) ?>" <?= $filters['type'] === (string)$value ? 'selected' : '' ?>>
                                    <?= e((string)$value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Niveau</label>
                        <select name="level">
                            <option value="">Tous</option>
                            <?php foreach (($options['levels'] ?? []) as $value): ?>
                                <option value="<?= e((string)$value) ?>" <?= $filters['level'] === (string)$value ? 'selected' : '' ?>>
                                    <?= e((string)$value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Statut lecture</label>
                        <select name="status">
                            <option value="">Tous</option>
                            <?php foreach (($options['statuses'] ?? []) as $row): ?>
                                <option value="<?= e((string)($row['value'] ?? '')) ?>" <?= $filters['status'] === (string)($row['value'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string)($row['label'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type entité</label>
                        <select name="entity_type">
                            <option value="">Tous</option>
                            <?php foreach (($options['entity_types'] ?? []) as $value): ?>
                                <option value="<?= e((string)$value) ?>" <?= $filters['entity_type'] === (string)$value ? 'selected' : '' ?>>
                                    <?= e((string)$value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Client</label>
                        <select name="client_id">
                            <option value="">Tous</option>
                            <?php foreach (($options['clients'] ?? []) as $client): ?>
                                <option value="<?= (int)($client['id'] ?? 0) ?>" <?= $filters['client_id'] === (string)($client['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string)($client['client_code'] ?? '') . ' - ' . (string)($client['full_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type opération</label>
                        <select name="operation_type_code">
                            <option value="">Tous</option>
                            <?php foreach (($options['operation_types'] ?? []) as $value): ?>
                                <option value="<?= e((string)$value) ?>" <?= $filters['operation_type_code'] === (string)$value ? 'selected' : '' ?>>
                                    <?= e((string)$value) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Service</label>
                        <select name="service_id">
                            <option value="">Tous</option>
                            <?php foreach (($options['services'] ?? []) as $service): ?>
                                <option value="<?= (int)($service['id'] ?? 0) ?>" <?= $filters['service_id'] === (string)($service['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string)($service['code'] ?? '') . ' - ' . (string)($service['label'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Du</label>
                        <input type="date" name="date_from" value="<?= e((string)$filters['date_from']) ?>">
                    </div>

                    <div>
                        <label>Au</label>
                        <input type="date" name="date_to" value="<?= e((string)$filters['date_to']) ?>">
                    </div>

                    <div>
                        <label>Par page</label>
                        <select name="per_page">
                            <?php foreach ([10, 25, 50, 100] as $pp): ?>
                                <option value="<?= $pp ?>" <?= (int)$filters['per_page'] === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php?mark_all=1" class="btn btn-secondary">Tout marquer comme lu</a>
                </div>
            </form>
        </section>

        <section class="sl-card">
            <div class="sl-card-head">
                <div>
                    <h3>Flux des notifications</h3>
                    <p class="sl-card-head-subtitle">
                        <?= (int)($list['total'] ?? 0) ?> résultat(s) • page <?= (int)($list['page'] ?? 1) ?>/<?= (int)($list['pages'] ?? 1) ?>
                    </p>
                </div>
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

                                <?php if (!empty($item['client_code']) || !empty($item['full_name'])): ?>
                                    <span class="badge badge-outline">
                                        Client : <?= e(trim((string)($item['client_code'] ?? '') . ' - ' . (string)($item['full_name'] ?? ''))) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($item['operation_type_code'])): ?>
                                    <span class="badge badge-outline">
                                        Opération : <?= e((string)$item['operation_type_code']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($item['service_label'])): ?>
                                    <span class="badge badge-outline">
                                        Service : <?= e((string)$item['service_label']) ?>
                                    </span>
                                <?php endif; ?>

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
                                <?php
                                $query = $_GET;
                                $query['read'] = (int)$item['id'];
                                ?>
                                <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>modules/notifications/notifications.php?<?= e(http_build_query($query)) ?>">
                                    Marquer lu
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (($list['pages'] ?? 1) > 1): ?>
                    <div class="btn-group sl-pagination-inline" style="margin-top:18px;">
                        <?php for ($p = 1; $p <= (int)$list['pages']; $p++): ?>
                            <?php
                            $query = $_GET;
                            $query['page'] = $p;
                            ?>
                            <a
                                class="btn <?= ((int)($list['page'] ?? 1) === $p) ? 'btn-success' : 'btn-outline' ?>"
                                href="<?= e(APP_URL) ?>modules/notifications/notifications.php?<?= e(http_build_query($query)) ?>"
                            >
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">Aucune notification.</p>
            <?php endif; ?>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>