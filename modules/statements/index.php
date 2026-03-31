<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceAccess($pdo, 'statements_view_page');

$pageTitle = 'Hub des exports';
$pageSubtitle = 'Deux logiques séparées : les relevés de compte et les fiches clients.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php if (function_exists('render_app_header_bar')): ?>
            <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card clickable" onclick="location.href='<?= APP_URL ?>modules/statements/account_statements.php'">
                <h2>Relevés de comptes</h2>
                <p class="muted">
                    Exports centrés sur les flux financiers : débits, crédits, soldes, historique des opérations.
                </p>
                <div class="btn-group" style="margin-top:18px;">
                    <span class="btn btn-primary">Ouvrir le module</span>
                </div>
            </div>

            <div class="card clickable" onclick="location.href='<?= APP_URL ?>modules/statements/client_profiles.php'">
                <h2>Fiches clients</h2>
                <p class="muted">
                    Exports centrés sur l’identité et le profil client : coordonnées, pays, rattachement financier, comptes, historique.
                </p>
                <div class="btn-group" style="margin-top:18px;">
                    <span class="btn btn-primary">Ouvrir le module</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="dashboard-panel">
                <h3 class="section-title">Relevés</h3>
                <div class="dashboard-note">
                    À utiliser pour raconter le mouvement de l’argent : export unitaire ou masse, avec période.
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Fiches clients</h3>
                <div class="dashboard-note">
                    À utiliser pour raconter l’identité complète d’un client, en version configurable ou exhaustive.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>