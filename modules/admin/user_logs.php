<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_logs_view');

require_once __DIR__ . '/../../includes/header.php';

$rows = tableExists($pdo, 'user_logs')
    ? $pdo->query("
        SELECT ul.*, u.username
        FROM user_logs ul
        LEFT JOIN users u ON u.id = ul.user_id
        ORDER BY ul.id DESC
        LIMIT 300
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Audit des logs',
        'Traçabilité des actions réalisées par les utilisateurs.'
    ); ?>

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
                        <td><?= e($row['entity_id'] ?? '') ?></td>
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