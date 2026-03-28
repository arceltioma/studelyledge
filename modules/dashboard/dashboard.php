<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'dashboard_view');

$totalClients = tableExists($pdo, 'clients')
    ? (int)$pdo->query("
        SELECT COUNT(*)
        FROM clients
        WHERE COALESCE(is_active, 1) = 1
    ")->fetchColumn()
    : 0;

$totalOperations = tableExists($pdo, 'operations')
    ? (int)$pdo->query("SELECT COUNT(*) FROM operations")->fetchColumn()
    : 0;

$totalTreasury = tableExists($pdo, 'treasury_accounts')
    ? (float)$pdo->query("
        SELECT COALESCE(SUM(current_balance), 0)
        FROM treasury_accounts
        WHERE COALESCE(is_active, 1) = 1
    ")->fetchColumn()
    : 0.0;

$totalService = tableExists($pdo, 'service_accounts')
    ? (float)$pdo->query("
        SELECT COALESCE(SUM(current_balance), 0)
        FROM service_accounts
        WHERE COALESCE(is_active, 1) = 1
    ")->fetchColumn()
    : 0.0;

$rejectedImports = tableExists($pdo, 'import_rows')
    ? (int)$pdo->query("
        SELECT COUNT(*)
        FROM import_rows
        WHERE status = 'rejected'
    ")->fetchColumn()
    : 0;

$activeClientsPositive = tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'client_bank_accounts')
    ? (int)$pdo->query("
        SELECT COUNT(DISTINCT c.id)
        FROM clients c
        INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        WHERE COALESCE(c.is_active,1) = 1
          AND COALESCE(ba.balance,0) > 0
    ")->fetchColumn()
    : 0;

$recentOperations = tableExists($pdo, 'operations')
    ? $pdo->query("
        SELECT
            o.*,
            c.client_code,
            c.full_name,
            rot.label AS operation_type_label
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        LEFT JOIN ref_operation_types rot ON rot.code = o.operation_type_code
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

$pageTitle = 'Dashboard';
$pageSubtitle = 'Vue consolidée des clients, flux, comptes internes et imports.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid">
            <div class="card">
                <h3>Clients actifs</h3>
                <div class="kpi"><?= $totalClients ?></div>
                <p class="muted">Base active exploitable</p>
            </div>

            <div class="card">
                <h3>Clients avec solde > 0</h3>
                <div class="kpi"><?= $activeClientsPositive ?></div>
                <p class="muted">Engagements positifs</p>
            </div>

            <div class="card">
                <h3>Opérations</h3>
                <div class="kpi"><?= $totalOperations ?></div>
                <p class="muted">Écritures enregistrées</p>
            </div>

            <div class="card">
                <h3>Soldes 512</h3>
                <div class="kpi"><?= number_format($totalTreasury, 2, ',', ' ') ?></div>
                <p class="muted">Trésorerie cumulée</p>
            </div>

            <div class="card">
                <h3>Soldes 706</h3>
                <div class="kpi"><?= number_format($totalService, 2, ',', ' ') ?></div>
                <p class="muted">Produits / services cumulés</p>
            </div>

            <div class="card">
                <h3>Rejets imports</h3>
                <div class="kpi"><?= $rejectedImports ?></div>
                <p class="muted">Lignes à corriger</p>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Dernières opérations</h3>
                        <p class="muted">Lecture rapide des flux récents.</p>
                    </div>

                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Voir tout</a>
                        <a href="<?= e(APP_URL) ?>modules/operations/operation_create.php" class="btn btn-secondary">Nouvelle opération</a>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
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
                                <td><?= e($op['operation_type_label'] ?? $op['operation_type_code'] ?? '') ?></td>
                                <td><?= e(trim((string)($op['client_code'] ?? '') . ' - ' . (string)($op['full_name'] ?? ''))) ?></td>
                                <td><?= e($op['label'] ?? '') ?></td>
                                <td><?= e($op['debit_account_code'] ?? '') ?></td>
                                <td><?= e($op['credit_account_code'] ?? '') ?></td>
                                <td><?= number_format((float)($op['amount'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$recentOperations): ?>
                            <tr>
                                <td colspan="7">Aucune opération récente.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Derniers imports</h3>
                        <p class="muted">Vue synthétique des derniers traitements.</p>
                    </div>

                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/imports/import_preview.php" class="btn btn-secondary">Nouvel import</a>
                        <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Journal</a>
                    </div>
                </div>

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
                                <td><?= (int)($import['id'] ?? 0) ?></td>
                                <td><?= e($import['file_name'] ?? '') ?></td>
                                <td><?= e($import['status'] ?? '') ?></td>
                                <td><?= e($import['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$recentImports): ?>
                            <tr>
                                <td colspan="4">Aucun import récent.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Gérer les clients</a>
                    <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Voir la trésorerie</a>
                    <a href="<?= e(APP_URL) ?>modules/statements/index.php" class="btn btn-outline">Exports & relevés</a>
                    <a href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php" class="btn btn-outline">Analytics</a>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/rebuild_balances.php" class="btn btn-outline">Recalculer les soldes</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture globale</h3>
                <div class="dashboard-note">
                    Ce dashboard rassemble les briques essentielles du pilotage :
                    base client, activité opérationnelle, équilibres 512/706 et suivi des imports.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>