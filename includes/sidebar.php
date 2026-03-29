<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

if (!function_exists('sidebarActive')) {
    function sidebarActive(string $needle, string $currentUri): string
    {
        return str_contains($currentUri, $needle) ? 'active' : '';
    }
}

if (!function_exists('sidebarGroupOpen')) {
    function sidebarGroupOpen(array $needles, string $currentUri): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($currentUri, $needle)) {
                return true;
            }
        }
        return false;
    }
}

$canAdminFunctional = true;
$canAdminTechnical = true;
$canAnalytics = true;

if (isset($pdo) && $pdo instanceof PDO && function_exists('currentUserCan')) {
    $canAdminFunctional = currentUserCan($pdo, 'operations_create') || currentUserCan($pdo, 'treasury_view');
    $canAdminTechnical = currentUserCan($pdo, 'admin_dashboard_view') || currentUserCan($pdo, 'admin_users_manage') || currentUserCan($pdo, 'support_admin_manage');
    $canAnalytics = currentUserCan($pdo, 'analytics_view');
}

$groupMainOpen = sidebarGroupOpen([
    '/modules/dashboard/',
    '/modules/clients/',
    '/modules/operations/',
    '/modules/treasury/',
    '/modules/analytics/'
], $currentUri);

$groupImportsOpen = sidebarGroupOpen([
    '/modules/imports/',
    '/modules/clients/import_clients_csv.php',
    '/modules/treasury/import_treasury_csv.php'
], $currentUri);

$groupExportsOpen = sidebarGroupOpen([
    '/modules/statements/'
], $currentUri);

$groupAdminFunctionalOpen = sidebarGroupOpen([
    '/modules/admin_functional/'
], $currentUri);

$groupAdminTechnicalOpen = sidebarGroupOpen([
    '/modules/admin/'
], $currentUri);
?>

<aside class="sidebar studely-sidebar">
    <div class="sidebar-inner studely-sidebar-inner">
        <div class="sidebar-top">
            <div class="sidebar-brand-card">
                <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Réduire ou ouvrir le menu">
                    ☰
                </button>

                <div class="sidebar-brand-visual">
                    <img
                        src="<?= e(app_asset('assets/img/logo-sidebar.png')) ?>"
                        alt="StudelyLedger"
                        class="sidebar-logo"
                    >
                </div>

                <div class="sidebar-brand-text">
                    <span>Console financière</span>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <details class="sidebar-group" <?= $groupMainOpen ? 'open' : '' ?>>
                <summary>Navigation principale</summary>
                <div class="sidebar-group-links">
                    <a class="sidebar-link <?= sidebarActive('/modules/dashboard/dashboard.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php">
                        <span>📊</span><span>Dashboard</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/clients/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">
                        <span>👤</span><span>Clients</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/operations/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/operations/operations_list.php">
                        <span>💰</span><span>Opérations</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/treasury/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/treasury/index.php">
                        <span>🏦</span><span>Comptes internes</span>
                    </a>

                    <?php if ($canAnalytics): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/analytics/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php">
                            <span>📈</span><span>Analytics</span>
                        </a>
                    <?php endif; ?>
                </div>
            </details>

            <details class="sidebar-group" <?= $groupImportsOpen ? 'open' : '' ?>>
                <summary>Imports</summary>
                <div class="sidebar-group-links">
                    <a class="sidebar-link <?= sidebarActive('/modules/imports/import_preview.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/import_preview.php">
                        <span>📥</span><span>Import relevés</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/imports/import_journal.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/import_journal.php">
                        <span>🧾</span><span>Journal imports</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/clients/import_clients_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">
                        <span>🧍</span><span>Import clients CSV</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/treasury/import_treasury_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php">
                        <span>🏛️</span><span>Import comptes internes CSV</span>
                    </a>
                </div>
            </details>

            <details class="sidebar-group" <?= $groupExportsOpen ? 'open' : '' ?>>
                <summary>Exports</summary>
                <div class="sidebar-group-links">
                    <a class="sidebar-link <?= sidebarActive('/modules/statements/index.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/statements/index.php">
                        <span>📤</span><span>Hub exports</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/statements/account_statements.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/statements/account_statements.php">
                        <span>📄</span><span>Relevés de comptes</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/statements/client_profiles.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/statements/client_profiles.php">
                        <span>🗂️</span><span>Fiches clients</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/statements/client_statement.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/statements/client_statement.php">
                        <span>📘</span><span>Consultation relevé</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/statements/bulk_statement_export.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/statements/bulk_statement_export.php">
                        <span>📚</span><span>Export masse</span>
                    </a>
                </div>
            </details>

            <?php if ($canAdminFunctional): ?>
                <details class="sidebar-group" <?= $groupAdminFunctionalOpen ? 'open' : '' ?>>
                    <summary>Administration fonctionnelle</summary>
                    <div class="sidebar-group-links">
                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/dashboard.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/dashboard.php">
                            <span>⚙️</span><span>Dashboard Admin Fonctionnelle</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_services.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">
                            <span>🧩</span><span>Services</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_operation_types.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">
                            <span>🧠</span><span>Types d’opérations</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_accounts.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">
                            <span>📚</span><span>Comptes</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/catalogs.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/catalogs.php">
                            <span>🗃️</span><span>Catalogue</span>
                        </a>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($canAdminTechnical): ?>
                <details class="sidebar-group" <?= $groupAdminTechnicalOpen ? 'open' : '' ?>>
                    <summary>Administration technique</summary>
                    <div class="sidebar-group-links">
                        <a class="sidebar-link <?= sidebarActive('/modules/admin/dashboard_admin.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php">
                            <span>🛠️</span><span>Dashboard Admin Technique</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/user_logs.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/user_logs.php">
                            <span>📜</span><span>Audit des logs</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/roles.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/roles.php">
                            <span>🔐</span><span>Rôles</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/users.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/users.php">
                            <span>👥</span><span>Utilisateurs</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/access_matrix.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/access_matrix.php">
                            <span>🧮</span><span>Matrice d’accès</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/support_requests.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/support_requests.php">
                            <span>🆘</span><span>Demandes support</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/settings.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/settings.php">
                            <span>🛠</span><span>Paramètres</span>
                        </a>
                    </div>
                </details>
            <?php endif; ?>
        </nav>

        <div class="sidebar-bottom">
            <a href="<?= e(APP_URL) ?>logout.php" class="btn btn-danger sidebar-logout-btn">Déconnexion</a>
        </div>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    const body = document.body;
    const storageKey = 'studelyledger_sidebar_collapsed';

    if (localStorage.getItem(storageKey) === '1') {
        body.classList.add('sidebar-collapsed');
    }

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(storageKey, body.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
    }
});
</script>