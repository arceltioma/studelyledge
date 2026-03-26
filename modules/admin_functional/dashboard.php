<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

require_once __DIR__ . '/../../includes/header.php';

$totalServices = tableExists($pdo, 'ref_services')
    ? (int)$pdo->query("SELECT COUNT(*) FROM ref_services")->fetchColumn()
    : 0;

$totalOperationTypes = tableExists($pdo, 'ref_operation_types')
    ? (int)$pdo->query("SELECT COUNT(*) FROM ref_operation_types")->fetchColumn()
    : 0;

$totalTreasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM treasury_accounts")->fetchColumn()
    : 0;

$totalServiceAccounts = tableExists($pdo, 'service_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM service_accounts")->fetchColumn()
    : 0;

$totalClients = tableExists($pdo, 'clients')
    ? (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn()
    : 0;
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Dashboard Admin Fonctionnelle',
            'Le cockpit des objets métier : services, types d’opérations, comptes et clients.'
        ); ?>

        <div class="card-grid">
            <div class="card"><h3>Services</h3><div class="kpi"><?= $totalServices ?></div></div>
            <div class="card"><h3>Types d’opération</h3><div class="kpi"><?= $totalOperationTypes ?></div></div>
            <div class="card"><h3>Comptes 512</h3><div class="kpi"><?= $totalTreasuryAccounts ?></div></div>
            <div class="card"><h3>Comptes 706</h3><div class="kpi"><?= $totalServiceAccounts ?></div></div>
            <div class="card"><h3>Clients</h3><div class="kpi"><?= $totalClients ?></div></div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group" style="flex-direction:column;align-items:flex-start;">
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_services.php">Gérer les services</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php">Gérer les types d’opération</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_accounts.php">Gérer les comptes</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/clients_list.php">Gérer les clients</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture métier</h3>
                <div class="dashboard-note">
                    L’administration fonctionnelle agit sur le référentiel métier. Elle structure ce que le moteur comptable pourra ensuite interpréter et exécuter.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>