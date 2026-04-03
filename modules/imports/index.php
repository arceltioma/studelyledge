<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_upload_page');
} else {
    enforcePagePermission($pdo, 'imports_upload');
}

$pageTitle = 'Hub Import';
$pageSubtitle = 'Point d’entrée centralisé des imports opérations, clients, comptes internes et comptes de service';

$totalImports = tableExists($pdo, 'imports')
    ? (int)$pdo->query("SELECT COUNT(*) FROM imports")->fetchColumn()
    : 0;

$totalNotificationsUnread = function_exists('countUnreadNotifications')
    ? countUnreadNotifications($pdo)
    : 0;

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Imports journalisés</div>
                <div class="sl-kpi-card__value"><?= (int)$totalImports ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Historique</span>
                    <strong>Imports</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Notifications non lues</div>
                <div class="sl-kpi-card__value"><?= (int)$totalNotificationsUnread ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Supervision</span>
                    <strong>Contrôle</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Référentiels</div>
                <div class="sl-kpi-card__value">3</div>
                <div class="sl-kpi-card__meta">
                    <span>Clients / 512 / 706</span>
                    <strong>Structurants</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Flux métiers</div>
                <div class="sl-kpi-card__value">1</div>
                <div class="sl-kpi-card__meta">
                    <span>Opérations</span>
                    <strong>Financier</strong>
                </div>
            </div>
        </section>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Imports financiers</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-outline">Import opérations</a>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_preview.php" class="btn btn-outline">Prévisualisation import</a>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Journal imports</a>
                </div>
            </div>

            <div class="card">
                <h3>Imports référentiels</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php" class="btn btn-outline">Import clients CSV</a>
                    <a href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php" class="btn btn-outline">Import comptes internes CSV</a>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/import_service_accounts_csv.php" class="btn btn-outline">Import comptes de service CSV</a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Lecture</h3>
                <div class="dashboard-note">
                    Le Hub Import centralise les imports opérationnels et référentiels, avec contrôle, historisation et supervision unifiée.
                </div>
            </div>

            <div class="card">
                <h3>Accès complémentaires</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/statements/index.php" class="btn btn-outline">Hub Export</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php" class="btn btn-outline">Audit & traçabilité</a>
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Notifications</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>