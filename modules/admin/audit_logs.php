<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if (!function_exists('auditBadgeClass')) {
    function auditBadgeClass(string $value): string
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

if (!function_exists('auditEntityBadgeClass')) {
    function auditEntityBadgeClass(string $value): string
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

if (!function_exists('auditShortValue')) {
    function auditShortValue(?string $value, int $max = 80): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '—';
        }

        if (mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max - 1) . '…';
        }

        return $value;
    }
}

if (!function_exists('auditEntityUrl')) {
    function auditEntityUrl(string $entityType, $entityId): ?string
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

$pageTitle = 'Audit & Traçabilité';
$pageSubtitle = 'Journal consolidé des actions et des modifications détaillées';

$tab = trim((string)($_GET['tab'] ?? 'logs'));
$search = trim((string)($_GET['search'] ?? ''));
$entityType = trim((string)($_GET['entity_type'] ?? ''));
$userId = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim((string)($_GET['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? ''));
$page = sl_safe_int($_GET['page'] ?? 1, 1);
$perPage = sl_normalize_per_page($_GET['per_page'] ?? 25);

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

if (tableExists($pdo, 'audit_trail')) {
    $entityTypes = array_merge(
        $entityTypes,
        $pdo->query("
            SELECT DISTINCT entity_type
            FROM audit_trail
            WHERE COALESCE(entity_type, '') <> ''
            ORDER BY entity_type ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: []
    );
}

if (tableExists($pdo, 'user_logs') && columnExists($pdo, 'user_logs', 'entity_type')) {
    $entityTypes = array_merge(
        $entityTypes,
        $pdo->query("
            SELECT DISTINCT entity_type
            FROM user_logs
            WHERE COALESCE(entity_type, '') <> ''
            ORDER BY entity_type ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: []
    );
}

$entityTypes = array_values(array_unique(array_filter(array_map('strval', $entityTypes))));
sort($entityTypes);

$totalActionLogs = tableExists($pdo, 'user_logs')
    ? (int)$pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn()
    : 0;

$totalAuditTrail = tableExists($pdo, 'audit_trail')
    ? (int)$pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn()
    : 0;

$totalUnreadNotifications = function_exists('countUnreadNotifications')
    ? countUnreadNotifications($pdo)
    : 0;

$totalEntitiesTracked = count($entityTypes);

$actionLogs = [];
$auditRows = [];
$totalResults = 0;
$pages = 1;

/* =========================
   ONGLET LOGS UTILISATEURS
========================= */
if ($tab === 'logs' && tableExists($pdo, 'user_logs')) {
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

    if ($userId > 0 && columnExists($pdo, 'user_logs', 'user_id')) {
        $where[] = "l.user_id = ?";
        $params[] = $userId;
    }

    if (sl_valid_date($dateFrom) && columnExists($pdo, 'user_logs', 'created_at')) {
        $where[] = "DATE(l.created_at) >= ?";
        $params[] = $dateFrom;
    }

    if (sl_valid_date($dateTo) && columnExists($pdo, 'user_logs', 'created_at')) {
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

    $countSql = "
        SELECT COUNT(*)
        FROM user_logs l
        {$joinUser}
        WHERE " . implode(' AND ', $where);

    $rowsSql = "
        SELECT
            l.*,
            {$selectUser}
        FROM user_logs l
        {$joinUser}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . (columnExists($pdo, 'user_logs', 'created_at') ? 'l.created_at DESC' : 'l.id DESC');

    $data = sl_paginated_query($pdo, $countSql, $params, $rowsSql, $params, $page, $perPage);

    $actionLogs = $data['rows'];
    $page = $data['page'];
    $pages = $data['pages'];
    $totalResults = $data['total'];
}

/* =========================
   ONGLET AUDIT DETAILLE
========================= */
if ($tab === 'trail' && tableExists($pdo, 'audit_trail')) {
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = "(
            COALESCE(a.field_name,'') LIKE ?
            OR COALESCE(a.old_value,'') LIKE ?
            OR COALESCE(a.new_value,'') LIKE ?
            OR COALESCE(a.entity_type,'') LIKE ?
        )";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($entityType !== '') {
        $where[] = "a.entity_type = ?";
        $params[] = $entityType;
    }

    if ($userId > 0 && columnExists($pdo, 'audit_trail', 'user_id')) {
        $where[] = "a.user_id = ?";
        $params[] = $userId;
    }

    if (sl_valid_date($dateFrom) && columnExists($pdo, 'audit_trail', 'created_at')) {
        $where[] = "DATE(a.created_at) >= ?";
        $params[] = $dateFrom;
    }

    if (sl_valid_date($dateTo) && columnExists($pdo, 'audit_trail', 'created_at')) {
        $where[] = "DATE(a.created_at) <= ?";
        $params[] = $dateTo;
    }

    $joinUser = '';
    $selectUser = "NULL AS username";

    if (tableExists($pdo, 'users') && columnExists($pdo, 'audit_trail', 'user_id')) {
        if (columnExists($pdo, 'users', 'username')) {
            $selectUser = "u.username AS username";
        } elseif (columnExists($pdo, 'users', 'email')) {
            $selectUser = "u.email AS username";
        } else {
            $selectUser = "CAST(u.id AS CHAR) AS username";
        }

        $joinUser = "LEFT JOIN users u ON u.id = a.user_id";
    }

    $countSql = "
        SELECT COUNT(*)
        FROM audit_trail a
        {$joinUser}
        WHERE " . implode(' AND ', $where);

    $rowsSql = "
        SELECT
            a.*,
            {$selectUser}
        FROM audit_trail a
        {$joinUser}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . (columnExists($pdo, 'audit_trail', 'created_at') ? 'a.created_at DESC' : 'a.id DESC');

    $data = sl_paginated_query($pdo, $countSql, $params, $rowsSql, $params, $page, $perPage);

    $auditRows = $data['rows'];
    $page = $data['page'];
    $pages = $data['pages'];
    $totalResults = $data['total'];
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
        .audit-diff-old {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.18);
            color: #991b1b;
            border-radius: 10px;
            padding: 8px 10px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .audit-diff-new {
            background: rgba(34, 197, 94, 0.08);
            border: 1px solid rgba(34, 197, 94, 0.18);
            color: #166534;
            border-radius: 10px;
            padding: 8px 10px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .audit-meta-cell {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .badge-outline {
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(148, 163, 184, 0.4);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            background: #fff;
        }

        .audit-link-inline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            font-size: 12px;
            text-decoration: none;
        }

        .audit-link-inline:hover {
            text-decoration: underline;
        }
        </style>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Logs utilisateurs</div>
                <div class="sl-kpi-card__value"><?= (int)$totalActionLogs ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Journal fonctionnel</span>
                    <strong>Actions</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Audit trail</div>
                <div class="sl-kpi-card__value"><?= (int)$totalAuditTrail ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Historique détaillé</span>
                    <strong>Changements</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Entités tracées</div>
                <div class="sl-kpi-card__value"><?= (int)$totalEntitiesTracked ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Types détectés</span>
                    <strong>Traçabilité</strong>
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
                <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=logs" class="btn <?= $tab === 'logs' ? 'btn-success' : 'btn-outline' ?>">Journal actions</a>
                <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=trail" class="btn <?= $tab === 'trail' ? 'btn-success' : 'btn-outline' ?>">Audit détaillé</a>
                <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Notifications</a>
                <a href="<?= e(APP_URL) ?>modules/admin/intelligence_center.php" class="btn btn-outline">Centre d’intelligence</a>
            </div>

            <form method="GET">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">

                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="action, champ, valeur...">
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

                    <div>
                        <label>Par page</label>
                        <select name="per_page">
                            <?php foreach ([10, 25, 50, 100] as $size): ?>
                                <option value="<?= (int)$size ?>" <?= $perPage === $size ? 'selected' : '' ?>>
                                    <?= (int)$size ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=<?= e($tab) ?>" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </section>

        <?php if ($tab === 'logs'): ?>
            <section class="sl-card sl-stable-block">
                <div class="sl-card-head">
                    <div>
                        <h3>Journal des actions utilisateurs</h3>
                        <p class="sl-card-head-subtitle">Actions fonctionnelles et techniques enregistrées</p>
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
                            <?php if ($actionLogs): ?>
                                <?php foreach ($actionLogs as $log): ?>
                                    <?php $directUrl = auditEntityUrl((string)($log['entity_type'] ?? ''), (int)($log['entity_id'] ?? 0)); ?>
                                    <tr>
                                        <td><?= e((string)($log['created_at'] ?? '')) ?></td>
                                        <td>
                                            <div class="audit-meta-cell">
                                                <strong><?= e((string)($log['username'] ?? ($log['user_id'] ?? ''))) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="<?= e(auditBadgeClass((string)($log['action'] ?? ''))) ?>">
                                                <?= e((string)($log['action'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline"><?= e((string)($log['module'] ?? '')) ?></span>
                                        </td>
                                        <td>
                                            <span class="<?= e(auditEntityBadgeClass((string)($log['entity_type'] ?? ''))) ?>">
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
                                    <td colspan="8">Aucune action trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?= sl_render_pagination(
                    APP_URL . 'modules/admin/audit_logs.php',
                    $_GET,
                    $page,
                    $pages,
                    $totalResults
                ) ?>
            </section>
        <?php else: ?>
            <section class="sl-card sl-stable-block">
                <div class="sl-card-head">
                    <div>
                        <h3>Audit détaillé des modifications</h3>
                        <p class="sl-card-head-subtitle">Différences avant / après par champ</p>
                    </div>
                </div>

                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Entité</th>
                                <th>ID</th>
                                <th>Accès direct</th>
                                <th>Champ</th>
                                <th>Ancienne valeur</th>
                                <th>Nouvelle valeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($auditRows): ?>
                                <?php foreach ($auditRows as $row): ?>
                                    <?php $directUrl = auditEntityUrl((string)($row['entity_type'] ?? ''), (int)($row['entity_id'] ?? 0)); ?>
                                    <tr>
                                        <td><?= e((string)($row['created_at'] ?? '')) ?></td>
                                        <td>
                                            <div class="audit-meta-cell">
                                                <strong><?= e((string)($row['username'] ?? ($row['user_id'] ?? ''))) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="audit-meta-cell">
                                                <span class="<?= e(auditEntityBadgeClass((string)($row['entity_type'] ?? ''))) ?>">
                                                    <?= e((string)($row['entity_type'] ?? '')) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><?= e((string)($row['entity_id'] ?? '')) ?></td>
                                        <td>
                                            <?php if ($directUrl !== null): ?>
                                                <a class="btn btn-sm btn-secondary" href="<?= e($directUrl) ?>">Ouvrir</a>
                                            <?php else: ?>
                                                <span class="muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline"><?= e((string)($row['field_name'] ?? '')) ?></span>
                                        </td>
                                        <td style="max-width:260px;">
                                            <div class="audit-diff-old"><?= e(auditShortValue((string)($row['old_value'] ?? ''), 140)) ?></div>
                                        </td>
                                        <td style="max-width:260px;">
                                            <div class="audit-diff-new"><?= e(auditShortValue((string)($row['new_value'] ?? ''), 140)) ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">Aucune modification trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?= sl_render_pagination(
                    APP_URL . 'modules/admin/audit_logs.php',
                    $_GET,
                    $page,
                    $pages,
                    $totalResults
                ) ?>
            </section>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>