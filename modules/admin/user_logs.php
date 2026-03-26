<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'admin_logs_view';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

function logsColumnExists(PDO $pdo, string $columnName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_logs'
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

$search = trim((string)($_GET['search'] ?? ''));

$logAction = logsColumnExists($pdo, 'action')
    ? 'action'
    : (logsColumnExists($pdo, 'event') ? 'event AS action' : "'—' AS action");

$logModule = logsColumnExists($pdo, 'module')
    ? 'module'
    : (logsColumnExists($pdo, 'section') ? 'section AS module' : "'—' AS module");

$logEntityType = logsColumnExists($pdo, 'entity_type')
    ? 'entity_type'
    : (logsColumnExists($pdo, 'target_type') ? 'target_type AS entity_type' : "'—' AS entity_type");

$logEntityId = logsColumnExists($pdo, 'entity_id')
    ? 'entity_id'
    : (logsColumnExists($pdo, 'target_id') ? 'target_id AS entity_id' : 'NULL AS entity_id');

$logCreatedAt = logsColumnExists($pdo, 'created_at')
    ? 'created_at'
    : (logsColumnExists($pdo, 'log_date') ? 'log_date AS created_at' : 'NULL AS created_at');

$sql = "
    SELECT
        id,
        {$logAction},
        {$logModule},
        {$logEntityType},
        {$logEntityId},
        {$logCreatedAt}
    FROM user_logs
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        " . (logsColumnExists($pdo, 'action') ? "action LIKE ?" : (logsColumnExists($pdo, 'event') ? "event LIKE ?" : "1=0")) . "
        OR " . (logsColumnExists($pdo, 'module') ? "module LIKE ?" : (logsColumnExists($pdo, 'section') ? "section LIKE ?" : "1=0")) . "
    )";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Audit des logs',
            'Lecture des traces système et des gestes techniques laissés dans le sillage des utilisateurs.'
        ); ?>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <div>
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Action ou module...">
                </div>

                <div class="btn-group" style="margin-top:26px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= APP_URL ?>modules/admin/user_logs.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <?php if (!$logs): ?>
                <div class="warning">Aucun log à afficher.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Cible</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e((string)($log['id'] ?? '—')) ?></td>
                                <td><?= e((string)($log['action'] ?? '—')) ?></td>
                                <td><?= e((string)($log['module'] ?? '—')) ?></td>
                                <td><?= e((string)($log['entity_type'] ?? '—')) ?></td>
                                <td><?= e((string)($log['entity_id'] ?? '—')) ?></td>
                                <td><?= e((string)($log['created_at'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>