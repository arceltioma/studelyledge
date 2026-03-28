<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

$totalOperationTypes = tableExists($pdo, 'ref_operation_types')
    ? (int)$pdo->query("SELECT COUNT(*) FROM ref_operation_types")->fetchColumn()
    : 0;

$totalServices = tableExists($pdo, 'ref_services')
    ? (int)$pdo->query("SELECT COUNT(*) FROM ref_services")->fetchColumn()
    : 0;

$totalServiceAccounts = tableExists($pdo, 'service_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM service_accounts")->fetchColumn()
    : 0;

$totalTreasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM treasury_accounts")->fetchColumn()
    : 0;

$pageTitle = 'Dashboard Admin Fonctionnelle';
$pageSubtitle = 'Pilotage des référentiels métier, comptes et rattachements.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid">
            <div class="card">
                <h3>Types d’opérations</h3>
                <div class="kpi"><?= $totalOperationTypes ?></div>
            </div>

            <div class="card">
                <h3>Services</h3>
                <div class="kpi"><?= $totalServices ?></div>
            </div>

            <div class="card">
                <h3>Comptes 706</h3>
                <div class="kpi"><?= $totalServiceAccounts ?></div>
            </div>

            <div class="card">
                <h3>Comptes 512</h3>
                <div class="kpi"><?= $totalTreasuryAccounts ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-outline">Gérer les types d’opérations</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-outline">Gérer les services</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php" class="btn btn-outline">Voir les comptes liés</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/catalogs.php" class="btn btn-outline">Vue catalogue</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    L’administration fonctionnelle te permet de piloter la logique métier
                    sans toucher au socle technique : types, services, comptes et rattachements.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>