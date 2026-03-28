<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_logs_view');

$search = trim((string)($_GET['search'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$moduleOptions = tableExists($pdo, 'user_logs')
    ? $pdo->query("
        SELECT DISTINCT module
        FROM user_logs
        WHERE module IS NOT NULL
          AND module <> ''
        ORDER BY module ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$actionOptions = tableExists($pdo, 'user_logs')
    ? $pdo->query("
        SELECT DISTINCT action
        FROM user_logs
        WHERE action IS NOT NULL
          AND action <> ''
        ORDER BY action ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$sql = "
    SELECT
        ul.*,
        u.username
    FROM user_logs ul
    LEFT JOIN users u ON u.id = ul.user_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        u.username LIKE ?
        OR ul.action LIKE ?
        OR ul.module LIKE ?
        OR ul.entity_type LIKE ?
        OR ul.details LIKE ?
    )";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($module !== '') {
    $sql .= " AND ul.module = ?";
    $params[] = $module;
}

if ($action !== '') {
    $sql .= " AND ul.action = ?";
    $params[] = $action;
}

if ($dateFrom !== '') {
    $sql .= " AND DATE(ul.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND DATE(ul.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY ul.id DESC LIMIT 500";

$rows = [];
if (tableExists($pdo, 'user_logs')) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Audit des logs';
$pageSubtitle = 'Traçabilité des actions réalisées par les utilisateurs.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <input
                    type="text"
                    name="search"
                    placeholder="Utilisateur, action, module, détail..."
                    value="<?= e($search) ?>"
                >

                <select name="module">
                    <option value="">Tous les modules</option>
                    <?php foreach ($moduleOptions as $item): ?>
                        <option value="<?= e($item) ?>" <?= $module === $item ? 'selected' : '' ?>>
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="action">
                    <option value="">Toutes les actions</option>
                    <?php foreach ($actionOptions as $item): ?>
                        <option value="<?= e($item) ?>" <?= $action === $item ? 'selected' : '' ?>>
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">

                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Type</th>
                        <th>ID cible</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['created_at'] ?? '') ?></td>
                            <td><?= e($row['username'] ?? '') ?></td>
                            <td><?= e($row['action'] ?? '') ?></td>
                            <td><?= e($row['module'] ?? '') ?></td>
                            <td><?= e($row['entity_type'] ?? '') ?></td>
                            <td><?= e((string)($row['entity_id'] ?? '')) ?></td>
                            <td><?= e($row['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="7">Aucun log disponible.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>