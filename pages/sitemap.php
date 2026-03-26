<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <div class="main">
        <?php render_app_header_bar('Sitemap', 'Carte claire des grandes zones de StudelyLedger.'); ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Pilotage</h3>
                <div class="suggestion-list">
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/dashboard/dashboard.php">Dashboard</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/analytics/revenue_analysis.php">Analyse CA</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/treasury/bank_accounts.php">Trésorerie</a>
                </div>
            </div>

            <div class="card">
                <h3>Clients & opérations</h3>
                <div class="suggestion-list">
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/clients/clients_list.php">Clients</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/operations/operations_list.php">Opérations</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/manual_actions/manual_operation.php">Action manuelle</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/manual_actions/bulk_fees.php">Frais en masse</a>
                </div>
            </div>

            <div class="card">
                <h3>Imports & corrections</h3>
                <div class="suggestion-list">
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/imports/import_upload.php">Importer un fichier</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/imports/import_journal.php">Journal des imports</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/imports/rejected_rows.php">Lignes rejetées</a>
                </div>
            </div>

            <div class="card">
                <h3>Relevés</h3>
                <div class="suggestion-list">
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/statements/index.php">Module relevés</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/statements/client_statement.php">Relevé individuel</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/statements/bulk_statement_export.php">Export en masse</a>
                </div>
            </div>

            <div class="card">
                <h3>Administration</h3>
                <div class="suggestion-list">
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/admin/dashboard_admin.php">Dashboard admin</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/admin/users.php">Utilisateurs</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/admin/roles.php">Rôles</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/admin/access_matrix.php">Matrice des accès</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/admin/user_logs.php">Audit des logs</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/admin/support_requests.php">Demandes support</a>
                </div>
            </div>

            <div class="card">
                <h3>Support & informations</h3>
                <div class="suggestion-list">
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/support/request_access.php">Demander un accès</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/support/report_bug.php">Signaler un bug</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>modules/support/ask_question.php">Poser une question</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>pages/contact.php">Contacts</a>
                    <a class="suggestion-chip" href="<?= APP_URL ?>pages/cookies_policy.php">Politique de cookies</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>