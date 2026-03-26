<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_dashboard_view');

require_once __DIR__ . '/../../includes/header.php';

$totalUsers = tableExists($pdo, 'users')
    ? (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
    : 0;

$totalTreasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM treasury_accounts")->fetchColumn()
    : 0;

$totalServiceAccounts = tableExists($pdo, 'service_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM service_accounts")->fetchColumn()
    : 0;

$totalServices = tableExists($pdo, 'ref_services')
    ? (int)$pdo->query("SELECT COUNT(*) FROM ref_services")->fetchColumn()
    : 0;

$totalOperationTypes = tableExists($pdo, 'ref_operation_types')
    ? (int)$pdo->query("SELECT COUNT(*) FROM ref_operation_types")->fetchColumn()
    : 0;

$recentLogs = tableExists($pdo, 'user_logs')
    ? $pdo->query("
        SELECT ul.*, u.username
        FROM user_logs ul
        LEFT JOIN users u ON u.id = ul.user_id
        ORDER BY ul.id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Dashboard administration',
            'Pilotage technique et structurel de la plateforme.'
        ); ?>

        <div class="card-grid">
            <div class="card">
                <h3>Utilisateurs</h3>
                <div class="kpi"><?= $totalUsers ?></div>
            </div>

            <div class="card">
                <h3>Comptes 512</h3>
                <div class="kpi"><?= $totalTreasuryAccounts ?></div>
            </div>

            <div class="card">
                <h3>Comptes 706</h3>
                <div class="kpi"><?= $totalServiceAccounts ?></div>
            </div>

            <div class="card">
                <h3>Services</h3>
                <div class="kpi"><?= $totalServices ?></div>
            </div>

            <div class="card">
                <h3>Types d’opération</h3>
                <div class="kpi"><?= $totalOperationTypes ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group" style="flex-direction:column;align-items:flex-start;">
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin/users.php">Gérer les utilisateurs</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/index.php">Comptes 512</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/service_accounts.php">Comptes 706</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_services.php">Services</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/operations/operations_list.php">Opérations</a>
                </div>
            </div>

            <div class="table-card">
                <h3 class="section-title">Derniers logs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?= e($log['created_at'] ?? '') ?></td>
                                <td><?= e($log['username'] ?? '') ?></td>
                                <td><?= e($log['action'] ?? '') ?></td>
                                <td><?= e($log['module'] ?? '') ?></td>
                                <td><?= e($log['details'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentLogs): ?>
                            <tr><td colspan="5">Aucun log récent.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>