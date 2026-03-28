<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'statements_export');

$pageTitle = 'Hub des exports';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

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