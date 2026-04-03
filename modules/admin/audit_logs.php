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

$pageTitle = 'Audit & Traçabilité';
$pageSubtitle = 'Journal consolidé des actions et des modifications détaillées';

$tab = trim((string)($_GET['tab'] ?? 'logs'));
$search = trim((string)($_GET['search'] ?? ''));
$entityType = trim((string)($_GET['entity_type'] ?? ''));
$userId = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim((string)($_GET['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? ''));

$users = [];
if (tableExists($pdo, 'users')) {
    $userLabel = columnExists($pdo, 'users', 'username') ? 'username' : (columnExists($pdo, 'users', 'email') ? 'email' : 'id');
    $users = $pdo->query("
        SELECT id, {$userLabel} AS display_name
        FROM users
        ORDER BY {$userLabel} ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$entityTypes = [];
if (tableExists($pdo, 'audit_trail')) {
    $entityTypes = $pdo->query("
        SELECT DISTINCT entity_type
        FROM audit_trail
        WHERE COALESCE(entity_type, '') <> ''
        ORDER BY entity_type ASC
    ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

$actionLogs = [];
$auditRows = [];

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
    $actionLogs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && columnExists($pdo, 'audit_trail', 'created_at')) {
        $where[] = "DATE(a.created_at) >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) && columnExists($pdo, 'audit_trail', 'created_at')) {
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

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            {$selectUser}
        FROM audit_trail a
        {$joinUser}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY " . (columnExists($pdo, 'audit_trail', 'created_at') ? 'a.created_at DESC' : 'a.id DESC') . "
        LIMIT 300
    ");
    $stmt->execute($params);
    $auditRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

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

        <section class="sl-card sl-stable-block" style="margin-bottom:20px;">
            <div class="btn-group" style="margin-bottom:16px;">
                <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=logs" class="btn <?= $tab === 'logs' ? 'btn-success' : 'btn-outline' ?>">Journal actions</a>
                <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php?tab=trail" class="btn <?= $tab === 'trail' ? 'btn-success' : 'btn-outline' ?>">Audit détaillé</a>
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
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($actionLogs): ?>
                                <?php foreach ($actionLogs as $log): ?>
                                    <tr>
                                        <td><?= e((string)($log['created_at'] ?? '')) ?></td>
                                        <td><?= e((string)($log['username'] ?? ($log['user_id'] ?? ''))) ?></td>
                                        <td><?= e((string)($log['action'] ?? '')) ?></td>
                                        <td><?= e((string)($log['module'] ?? '')) ?></td>
                                        <td><?= e((string)($log['entity_type'] ?? '')) ?></td>
                                        <td><?= e((string)($log['entity_id'] ?? '')) ?></td>
                                        <td><?= e((string)($log['details'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Aucune action trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                                <th>Champ</th>
                                <th>Ancienne valeur</th>
                                <th>Nouvelle valeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($auditRows): ?>
                                <?php foreach ($auditRows as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['created_at'] ?? '')) ?></td>
                                        <td><?= e((string)($row['username'] ?? ($row['user_id'] ?? ''))) ?></td>
                                        <td><?= e((string)($row['entity_type'] ?? '')) ?></td>
                                        <td><?= e((string)($row['entity_id'] ?? '')) ?></td>
                                        <td><?= e((string)($row['field_name'] ?? '')) ?></td>
                                        <td style="max-width:260px; white-space:pre-wrap;"><?= e((string)($row['old_value'] ?? '')) ?></td>
                                        <td style="max-width:260px; white-space:pre-wrap;"><?= e((string)($row['new_value'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Aucune modification trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>