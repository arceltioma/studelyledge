<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
$pdo = getPDO();

$pageTitle = 'Sitemap';
$pageSubtitle = 'Plan des principales pages de la plateforme.';
require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">
        <div class="studely-header">
            <div class="header-left">
                <div class="header-titles">
                    <span class="header-overline"><?= e(APP_NAME) ?></span>
                    <h1 class="header-title">Sitemap</h1>
                    <div class="header-subtitle">Plan des principales pages de la plateforme.</div>
                </div>
            </div>

            <div class="header-right">
                <div class="header-actions">
                    <a href="<?= e(APP_URL) ?>login.php" class="btn btn-primary">Connexion</a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Modules métier</h3>
                <ul>
                    <li><a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/clients/clients_list.php">Clients</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/operations/operations_list.php">Opérations</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/treasury/index.php">Comptes internes</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/imports/import_preview.php">Imports</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/statements/index.php">Exports</a></li>
                </ul>
            </div>

            <div class="card">
                <h3>Administration</h3>
                <ul>
                    <li><a href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php">Dashboard Admin Technique</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/admin_functional/dashboard.php">Dashboard Admin Fonctionnelle</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/admin/users.php">Utilisateurs</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/admin/roles.php">Rôles</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php">Matrice d’accès</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/admin/user_logs.php">Audit des logs</a></li>
                    <li><a href="<?= e(APP_URL) ?>modules/admin/support_requests.php">Demandes support</a></li>
                </ul>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>