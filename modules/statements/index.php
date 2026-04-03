<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'statements_view_page');
} else {
    enforcePagePermission($pdo, 'statements_view');
}

$pageTitle = 'Hub Export';
$pageSubtitle = 'Point d’entrée centralisé des exports financiers, clients et états';

$totalClients = tableExists($pdo, 'clients')
    ? (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn()
    : 0;

$totalOperations = tableExists($pdo, 'operations')
    ? (int)$pdo->query("SELECT COUNT(*) FROM operations")->fetchColumn()
    : 0;

$totalTreasury = tableExists($pdo, 'treasury_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM treasury_accounts")->fetchColumn()
    : 0;

$totalServiceAccounts = tableExists($pdo, 'service_accounts')
    ? (int)$pdo->query("SELECT COUNT(*) FROM service_accounts")->fetchColumn()
    : 0;

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Clients</div>
                <div class="sl-kpi-card__value"><?= (int)$totalClients ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Exportables</span>
                    <strong>Profils</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Opérations</div>
                <div class="sl-kpi-card__value"><?= (int)$totalOperations ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Exportables</span>
                    <strong>Flux</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Comptes internes</div>
                <div class="sl-kpi-card__value"><?= (int)$totalTreasury ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Exportables</span>
                    <strong>512</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Comptes service</div>
                <div class="sl-kpi-card__value"><?= (int)$totalServiceAccounts ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Exportables</span>
                    <strong>706</strong>
                </div>
            </div>
        </section>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Exports disponibles</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/statements/account_statements.php" class="btn btn-outline">Relevés de comptes</a>
                    <a href="<?= e(APP_URL) ?>modules/statements/client_profiles.php" class="btn btn-outline">Fiches clients</a>
                    <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Liste opérations</a>
                    <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Comptes internes</a>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Comptes de service</a>
                </div>
            </div>

            <div class="card">
                <h3>Lecture</h3>
                <div class="dashboard-note">
                    Le Hub Export regroupe les principaux points de sortie de la donnée pour le pilotage, le contrôle et la restitution métier.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>