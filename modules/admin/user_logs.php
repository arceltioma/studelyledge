<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'user_logs_view_page');
} else {
    enforcePagePermission($pdo, 'user_logs_view');
}

if (!function_exists('userLogsBadgeClass')) {
    function userLogsBadgeClass(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'create_client', 'create_operation', 'create_treasury_account', 'import_operation', 'client_import', 'treasury_import' => 'badge badge-success',
            'edit_client', 'edit_operation', 'edit_treasury_account', 'client_update', 'operation_update', 'treasury_update' => 'badge badge-warning',
            'delete_operation', 'archive_operation' => 'badge badge-danger',
            default => 'badge badge-secondary',
        };
    }
}

if (!function_exists('userLogsEntityBadgeClass')) {
    function userLogsEntityBadgeClass(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'client' => 'badge badge-info',
            'operation' => 'badge badge-warning',
            'treasury_account' => 'badge badge-success',
            'import', 'client_import', 'treasury_import' => 'badge badge-secondary',
            default => 'badge badge-outline',
        };
    }
}

if (!function_exists('userLogsEntityUrl')) {
    function userLogsEntityUrl(string $entityType, $entityId): ?string
    {
        if (function_exists('sl_build_notification_link_for_entity')) {
            $url = sl_build_notification_link_for_entity($entityType, (int)$entityId);
            if ($url !== null && $url !== '') {
                return $url;
            }
        }

        $entityType = strtolower(trim($entityType));
        $entityId = (int)$entityId;

        if ($entityId <= 0 || !defined('APP_URL')) {
            return null;
        }

        return match ($entityType) {
            'client' => APP_URL . 'modules/clients/client_view.php?id=' . $entityId,
            'operation' => APP_URL . 'modules/operations/operation_view.php?id=' . $entityId,
            'treasury_account' => APP_URL . 'modules/treasury/treasury_view.php?id=' . $entityId,
            default => null,
        };
    }
}

$pageTitle = 'Audit des logs';
$pageSubtitle = 'Historique des actions utilisateurs, filtrage avancé et accès direct aux objets';

$search = trim((string)($_GET['search'] ?? ''));
$entityType = trim((string)($_GET['entity_type'] ?? ''));
$userId = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim((string)($_GET['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));

$users = [];
if (tableExists($pdo, 'users')) {
    $userLabel = columnExists($pdo, 'users', 'username')
        ? 'username'
        : (columnExists($pdo, 'users', 'email') ? 'email' : 'id');

    $users = $pdo->query("
        SELECT id, {$userLabel} AS display_name
        FROM users
        ORDER BY {$userLabel} ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$entityTypes = [];
if (tableExists($pdo, 'user_logs') && columnExists($pdo, 'user_logs', 'entity_type')) {
    $entityTypes = $pdo->query("
        SELECT DISTINCT entity_type
        FROM user_logs
        WHERE COALESCE(entity_type,'') <> ''
        ORDER BY entity_type ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$modules = [];
if (tableExists($pdo, 'user_logs') && columnExists($pdo, 'user_logs', 'module')) {
    $modules = $pdo->query("
        SELECT DISTINCT module
        FROM user_logs
        WHERE COALESCE(module,'') <> ''
        ORDER BY module ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(
        COALESCE(l.action,'') LIKE ?
        OR COALESCE(l.module,'') LIKE ?
        OR COALESCE(l.entity_type,'') LIKE ?
        OR COALESCE(l.details,'') LIKE ?
    )";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($entityType !== '' && columnExists($pdo, 'user_logs', 'entity_type')) {
    $where[] = "l.entity_type = ?";
    $params[] = $entityType;
}

if ($module !== '' && columnExists($pdo, 'user_logs', 'module')) {
    $where[] = "l.module = ?";
    $params[] = $module;
}

if ($userId > 0 && columnExists($pdo, 'user_logs', 'user_id')) {
    $where[] = "l.user_id = ?";
    $params[] = $userId;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && columnExists($pdo, 'user_logs', 'created_at')) {
    $where[] = "DATE(l.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) && columnExists($pdo, 'user_logs', 'created_at')) {
    $where[] = "DATE(l.created_at) <= ?";
    $params[] = $dateTo;
}

$joinUser = '';
$selectUser = "NULL AS username";
if (tableExists($pdo, 'users') && columnExists($pdo, 'user_logs', 'user_id')) {
    if (columnExists($pdo, 'users', 'username')) {
        $selectUser = "u.username AS username";
    } elseif (columnExists($pdo, 'users', 'email')) {
        $selectUser = "u.email AS username";
    } else {
        $selectUser = "CAST(u.id AS CHAR) AS username";
    }
    $joinUser = "LEFT JOIN users u ON u.id = l.user_id";
}

$logs = [];
$totalLogs = 0;

if (tableExists($pdo, 'user_logs')) {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_logs l
        {$joinUser}
        WHERE " . implode(' AND ', $where)
    );
    $countStmt->execute($params);
    $totalLogs = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            {$selectUser}
        FROM user_logs l
        {$joinUser}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . (columnExists($pdo, 'user_logs', 'created_at') ? 'l.created_at DESC' : 'l.id DESC') . "
        LIMIT 300
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$totalUnreadNotifications = function_exists('countUnreadNotifications')
    ? countUnreadNotifications($pdo)
    : 0;

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Logs trouvés</div>
                <div class="sl-kpi-card__value"><?= (int)$totalLogs ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Après filtrage</span>
                    <strong>Journal</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Types entité</div>
                <div class="sl-kpi-card__value"><?= count($entityTypes) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Traçabilité</span>
                    <strong>Références</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Modules</div>
                <div class="sl-kpi-card__value"><?= count($modules) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Périmètre</span>
                    <strong>Application</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Notifications non lues</div>
                <div class="sl-kpi-card__value"><?= (int)$totalUnreadNotifications ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Supervision</span>
                    <strong>Temps réel</strong>
                </div>
            </div>
        </section>

        <section class="sl-card sl-stable-block" style="margin-bottom:20px;">
            <div class="btn-group" style="margin-bottom:16px;">
                <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=logs" class="btn btn-outline">Audit & traçabilité</a>
                <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Notifications</a>
                <a href="<?= e(APP_URL) ?>modules/admin/intelligence_center.php" class="btn btn-outline">Centre d’intelligence</a>
            </div>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="action, module, détail...">
                    </div>

                    <div>
                        <label>Type entité</label>
                        <select name="entity_type">
                            <option value="">Tous</option>
                            <?php foreach ($entityTypes as $type): ?>
                                <option value="<?= e((string)$type) ?>" <?= $entityType === (string)$type ? 'selected' : '' ?>>
                                    <?= e((string)$type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Module</label>
                        <select name="module">
                            <option value="">Tous</option>
                            <?php foreach ($modules as $moduleName): ?>
                                <option value="<?= e((string)$moduleName) ?>" <?= $module === (string)$moduleName ? 'selected' : '' ?>>
                                    <?= e((string)$moduleName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Utilisateur</label>
                        <select name="user_id">
                            <option value="">Tous</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int)$user['id'] ?>" <?= $userId === (int)$user['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$user['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Du</label>
                        <input type="date" name="from" value="<?= e($dateFrom) ?>">
                    </div>

                    <div>
                        <label>Au</label>
                        <input type="date" name="to" value="<?= e($dateTo) ?>">
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </section>

        <section class="sl-card sl-stable-block">
            <div class="sl-card-head">
                <div>
                    <h3>Journal des actions utilisateurs</h3>
                    <p class="sl-card-head-subtitle">Vue alignée avec l’audit détaillé et accès direct aux objets</p>
                </div>
            </div>

            <div class="sl-table-wrap">
                <table class="sl-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Type entité</th>
                            <th>ID entité</th>
                            <th>Accès direct</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php $directUrl = userLogsEntityUrl((string)($log['entity_type'] ?? ''), (int)($log['entity_id'] ?? 0)); ?>
                                <tr>
                                    <td><?= e((string)($log['created_at'] ?? '')) ?></td>
                                    <td><strong><?= e((string)($log['username'] ?? ($log['user_id'] ?? ''))) ?></strong></td>
                                    <td>
                                        <span class="<?= e(userLogsBadgeClass((string)($log['action'] ?? ''))) ?>">
                                            <?= e((string)($log['action'] ?? '')) ?>
                                        </span>
                                    </td>
                                    <td><span class="badge badge-outline"><?= e((string)($log['module'] ?? '')) ?></span></td>
                                    <td>
                                        <span class="<?= e(userLogsEntityBadgeClass((string)($log['entity_type'] ?? ''))) ?>">
                                            <?= e((string)($log['entity_type'] ?? '')) ?>
                                        </span>
                                    </td>
                                    <td><?= e((string)($log['entity_id'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($directUrl !== null): ?>
                                            <a class="btn btn-sm btn-secondary" href="<?= e($directUrl) ?>">Ouvrir</a>
                                        <?php else: ?>
                                            <span class="muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e((string)($log['details'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">Aucun log trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>