<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'dashboard_view');

require_once __DIR__ . '/../../includes/header.php';

$totalClients = tableExists($pdo, 'clients')
    ? (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE COALESCE(is_active,1) = 1")->fetchColumn()
    : 0;

$totalOperations = tableExists($pdo, 'operations')
    ? (int)$pdo->query("SELECT COUNT(*) FROM operations")->fetchColumn()
    : 0;

$totalTreasury = tableExists($pdo, 'treasury_accounts')
    ? (float)$pdo->query("SELECT COALESCE(SUM(current_balance),0) FROM treasury_accounts WHERE COALESCE(is_active,1) = 1")->fetchColumn()
    : 0.0;

$totalService = tableExists($pdo, 'service_accounts')
    ? (float)$pdo->query("SELECT COALESCE(SUM(current_balance),0) FROM service_accounts WHERE COALESCE(is_active,1) = 1")->fetchColumn()
    : 0.0;

$rejectedImports = tableExists($pdo, 'import_rows')
    ? (int)$pdo->query("SELECT COUNT(*) FROM import_rows WHERE status = 'rejected'")->fetchColumn()
    : 0;

$recentOperations = tableExists($pdo, 'operations')
    ? $pdo->query("
        SELECT
            o.*,
            c.client_code,
            c.full_name
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        ORDER BY o.id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$recentImports = tableExists($pdo, 'imports')
    ? $pdo->query("
        SELECT *
        FROM imports
        ORDER BY id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Dashboard',
            'Vue consolidée des clients, des flux, des comptes internes et des imports.'
        ); ?>

        <div class="card-grid">
            <div class="card">
                <h3>Clients actifs</h3>
                <div class="kpi"><?= $totalClients ?></div>
            </div>

            <div class="card">
                <h3>Opérations</h3>
                <div class="kpi"><?= $totalOperations ?></div>
            </div>

            <div class="card">
                <h3>Soldes 512</h3>
                <div class="kpi"><?= number_format($totalTreasury, 2, ',', ' ') ?></div>
            </div>

            <div class="card">
                <h3>Soldes 706</h3>
                <div class="kpi"><?= number_format($totalService, 2, ',', ' ') ?></div>
            </div>

            <div class="card">
                <h3>Rejets ouverts</h3>
                <div class="kpi"><?= $rejectedImports ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="table-card">
                <h3 class="section-title">Dernières opérations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Libellé</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOperations as $op): ?>
                            <tr>
                                <td><?= e($op['operation_date'] ?? '') ?></td>
                                <td><?= e(trim((string)($op['client_code'] ?? '') . ' - ' . (string)($op['full_name'] ?? ''))) ?></td>
                                <td><?= e($op['label'] ?? '') ?></td>
                                <td><?= e($op['debit_account_code'] ?? '') ?></td>
                                <td><?= e($op['credit_account_code'] ?? '') ?></td>
                                <td><?= number_format((float)($op['amount'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentOperations): ?>
                            <tr><td colspan="6">Aucune opération récente.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h3 class="section-title">Derniers imports</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fichier</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentImports as $import): ?>
                            <tr>
                                <td><?= (int)$import['id'] ?></td>
                                <td><?= e($import['file_name'] ?? '') ?></td>
                                <td><?= e($import['status'] ?? '') ?></td>
                                <td><?= e($import['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentImports): ?>
                            <tr><td colspan="4">Aucun import récent.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>