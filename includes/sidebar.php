<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/admin_functions.php';

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

$canUseAccessMap = isset($pdo) && $pdo instanceof PDO && function_exists('studelyCanAccess');

$can = function (string $accessKey) use ($canUseAccessMap, $pdo): bool {
    if (!$canUseAccessMap) {
        return true;
    }
    return studelyCanAccess($pdo, $accessKey);
};

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

$groupSupportOpen = sidebarGroupOpen([
    '/modules/support/'
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
            </div>
        </div>

        <nav class="sidebar-nav">

            <details class="sidebar-group" <?= $groupMainOpen ? 'open' : '' ?>>
                <summary>Navigation principale</summary>
                <div class="sidebar-group-links">
                    <?php if ($can('dashboard_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/dashboard/dashboard.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php">
                            <span>📊</span><span>Dashboard</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('clients_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/clients/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">
                            <span>👤</span><span>Clients</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('operations_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/operations/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/operations/operations_list.php">
                            <span>💰</span><span>Opérations</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('treasury_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/treasury/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/treasury/index.php">
                            <span>🏦</span><span>Comptes internes</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('analytics_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/analytics/', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php">
                            <span>📈</span><span>Analytics</span>
                        </a>
                    <?php endif; ?>
                </div>
            </details>

            <?php if ($can('imports_upload_page') || $can('imports_preview_page') || $can('imports_journal_page')): ?>
                <details class="sidebar-group" <?= $groupImportsOpen ? 'open' : '' ?>>
                    <summary>Imports</summary>
                    <div class="sidebar-group-links">
                        <?php if ($can('imports_preview_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/imports/import_preview.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/import_preview.php">
                                <span>📥</span><span>Import relevés</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('imports_journal_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/imports/import_journal.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/import_journal.php">
                                <span>🧾</span><span>Journal imports</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('clients_create_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/clients/import_clients_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">
                                <span>🧍</span><span>Import clients CSV</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('treasury_import_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/treasury/import_treasury_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php">
                                <span>🏛️</span><span>Import comptes internes CSV</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($can('statements_view_page')): ?>
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
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($can('support_view_page')): ?>
                <details class="sidebar-group" <?= $groupSupportOpen ? 'open' : '' ?>>
                    <summary>Support</summary>
                    <div class="sidebar-group-links">
                        <a class="sidebar-link <?= sidebarActive('/modules/support/support_requests.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/support_requests.php">
                            <span>🆘</span><span>Demandes support</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/support/ask_question.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/ask_question.php">
                            <span>❓</span><span>Poser une question</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/support/report_bug.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/report_bug.php">
                            <span>🐞</span><span>Signaler un bug</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/support/request_access.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/request_access.php">
                            <span>🔐</span><span>Demander un accès</span>
                        </a>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($can('admin_functional_page') || $can('services_manage_page') || $can('operation_types_manage_page')): ?>
                <details class="sidebar-group" <?= $groupAdminFunctionalOpen ? 'open' : '' ?>>
                    <summary>Administration fonctionnelle</summary>
                    <div class="sidebar-group-links">
                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/dashboard.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/dashboard.php">
                            <span>⚙️</span><span>Dashboard Admin Fonctionnelle</span>
                        </a>

                        <?php if ($can('services_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_services.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">
                                <span>🧩</span><span>Services</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('operation_types_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_operation_types.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">
                                <span>🧠</span><span>Types d’opérations</span>
                            </a>
                        <?php endif; ?>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_accounts.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">
                            <span>📚</span><span>Comptes</span>
                        </a>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($can('admin_dashboard_page') || $can('users_manage_page') || $can('settings_manage_page')): ?>
                <details class="sidebar-group" <?= $groupAdminTechnicalOpen ? 'open' : '' ?>>
                    <summary>Administration technique</summary>
                    <div class="sidebar-group-links">
                        <a class="sidebar-link <?= sidebarActive('/modules/admin/dashboard_admin.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php">
                            <span>🛠️</span><span>Dashboard Admin Technique</span>
                        </a>

                        <?php if ($can('user_logs_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/user_logs.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/user_logs.php">
                                <span>📜</span><span>Audit des logs</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('roles_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/roles.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/roles.php">
                                <span>🔐</span><span>Rôles</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('users_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/users.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/users.php">
                                <span>👥</span><span>Utilisateurs</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('permissions_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/access_matrix.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/access_matrix.php">
                                <span>🧮</span><span>Matrice d’accès</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('settings_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/settings.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/settings.php">
                                <span>⚙️</span><span>Paramètres</span>
                            </a>
                        <?php endif; ?>
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