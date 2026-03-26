<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'operations_create';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

function functionalCount(PDO $pdo, string $table): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);

    if ((int)$stmt->fetchColumn() === 0) {
        return 0;
    }

    return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

$totalOperationTypes = functionalCount($pdo, 'ref_operation_types');
$totalServices = functionalCount($pdo, 'ref_services');
$totalClients = functionalCount($pdo, 'clients');
$totalTreasuryAccounts = functionalCount($pdo, 'treasury_accounts');
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Administration Fonctionnelle',
            'Les réglages métier : types d’opération, services, clients et comptes bancaires internes.'
        ); ?>

        <div class="card-grid">
            <div class="card">
                <h3>Types d’opération</h3>
                <div class="kpi"><?= $totalOperationTypes ?></div>
                <p class="muted">Référentiel disponible</p>
                <div class="btn-group" style="margin-top:12px;">
                    <a class="btn btn-primary" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php">Gérer</a>
                </div>
            </div>

            <div class="card">
                <h3>Types de service</h3>
                <div class="kpi"><?= $totalServices ?></div>
                <p class="muted">Services métier actifs</p>
                <div class="btn-group" style="margin-top:12px;">
                    <a class="btn btn-primary" href="<?= APP_URL ?>modules/admin_functional/manage_services.php">Gérer</a>
                </div>
            </div>

            <div class="card">
                <h3>Clients</h3>
                <div class="kpi"><?= $totalClients ?></div>
                <p class="muted">Fiches existantes</p>
                <div class="btn-group" style="margin-top:12px;">
                    <a class="btn btn-success" href="<?= APP_URL ?>modules/clients/client_create.php">Créer</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/clients_list.php">Voir</a>
                </div>
            </div>

            <div class="card">
                <h3>Comptes internes</h3>
                <div class="kpi"><?= $totalTreasuryAccounts ?></div>
                <p class="muted">Trésorerie disponible</p>
                <div class="btn-group" style="margin-top:12px;">
                    <a class="btn btn-secondary" href="<?= APP_URL ?>modules/treasury/index.php">Gérer</a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="dashboard-panel">
                <h3 class="section-title">Ce que tu peux faire ici</h3>

                <div class="stat-row">
                    <span class="metric-label">Types d’opération</span>
                    <span class="metric-value">Créer / modifier / supprimer</span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Types de service</span>
                    <span class="metric-value">Créer / modifier / supprimer</span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Clients</span>
                    <span class="metric-value">Créer / modifier / archiver</span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Comptes bancaires internes</span>
                    <span class="metric-value">Créer / modifier / archiver</span>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Vision métier</h3>
                <div class="dashboard-note">
                    L’administration fonctionnelle règle la musique. Les types d’opération s’attachent aux services, les clients aux comptes internes, et la comptabilité suit ensuite la piste sans improviser n’importe quoi.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>