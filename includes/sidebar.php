<?php
require_once __DIR__ . '/../config/app.php';

$currentUri = $_SERVER['REQUEST_URI'] ?? '';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

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

/*
|--------------------------------------------------------------------------
| Permissions (robuste mais souple)
|--------------------------------------------------------------------------
*/
$canAdminFunctional = true;
$canAdminTechnical = true;
$canAnalytics = true;
$canSupport = true;

if (isset($pdo) && $pdo instanceof PDO && function_exists('currentUserCan')) {

    $canAdminFunctional =
        currentUserCan($pdo, 'operations_create') ||
        currentUserCan($pdo, 'treasury_view') ||
        currentUserCan($pdo, 'admin_functional_view');

    $canAdminTechnical =
        currentUserCan($pdo, 'admin_dashboard_view') ||
        currentUserCan($pdo, 'admin_users_manage') ||
        currentUserCan($pdo, 'support_admin_manage') ||
        currentUserCan($pdo, 'admin_manage') ||
        currentUserCan($pdo, 'settings_manage');

    $canAnalytics = currentUserCan($pdo, 'analytics_view');

    $canSupport =
        currentUserCan($pdo, 'support_requests_view') ||
        currentUserCan($pdo, 'support_view') ||
        currentUserCan($pdo, 'support_create') ||
        currentUserCan($pdo, 'dashboard_view');
}

/*
|--------------------------------------------------------------------------
| Group open state
|--------------------------------------------------------------------------
*/
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

                <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn">
                    ☰
                </button>

                <div class="sidebar-brand-visual">
                    <img src="<?= e(app_asset('assets/img/logo-sidebar.png')) ?>" class="sidebar-logo" alt="Studely Ledger">
                </div>

                <div class="sidebar-brand-text">
                    <strong>Studely Ledger</strong>
                    <span>Console financière</span>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">

            <details class="sidebar-group" <?= $groupMainOpen ? 'open' : '' ?>>
                <summary>Navigation principale</summary>

                <div class="sidebar-group-links">

                    <a class="sidebar-link <?= sidebarActive('/modules/dashboard/dashboard.php', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php">
                        📊 <span>Dashboard</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/clients/', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/clients/clients_list.php">
                        👤 <span>Clients</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/operations/', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/operations/operations_list.php">
                        💰 <span>Opérations</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/treasury/', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/treasury/index.php">
                        🏦 <span>Comptes internes</span>
                    </a>

                    <?php if ($canAnalytics): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/analytics/', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php">
                            📈 <span>Analytics</span>
                        </a>
                    <?php endif; ?>

                </div>
            </details>

            <details class="sidebar-group" <?= $groupImportsOpen ? 'open' : '' ?>>
                <summary>Imports</summary>

                <div class="sidebar-group-links">

                    <a class="sidebar-link <?= sidebarActive('/modules/imports/import_preview.php', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/imports/import_preview.php">
                        📥 <span>Import relevés</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/imports/import_journal.php', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/imports/import_journal.php">
                        🧾 <span>Journal imports</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/clients/import_clients_csv.php', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">
                        🧍 <span>Import clients CSV</span>
                    </a>

                    <a class="sidebar-link <?= sidebarActive('/modules/treasury/import_treasury_csv.php', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php">
                        🏛️ <span>Import comptes internes</span>
                    </a>

                </div>
            </details>

            <details class="sidebar-group" <?= $groupExportsOpen ? 'open' : '' ?>>
                <summary>Exports</summary>

                <div class="sidebar-group-links">
                    <a class="sidebar-link <?= sidebarActive('/modules/statements/', $currentUri) ?>"
                       href="<?= e(APP_URL) ?>modules/statements/index.php">
                        📤 <span>Hub exports</span>
                    </a>
                </div>
            </details>

            <?php if ($canSupport): ?>
                <details class="sidebar-group" <?= $groupSupportOpen ? 'open' : '' ?>>
                    <summary>Support</summary>

                    <div class="sidebar-group-links">

                        <a class="sidebar-link <?= sidebarActive('/modules/support/support_requests.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/support/support_requests.php">
                            🆘 <span>Demandes support</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/support/ask_question.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/support/ask_question.php">
                            ❓ <span>Poser une question</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/support/report_bug.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/support/report_bug.php">
                            🐞 <span>Signaler un bug</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/support/request_access.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/support/request_access.php">
                            🔐 <span>Demander un accès</span>
                        </a>

                    </div>
                </details>
            <?php endif; ?>

            <?php if ($canAdminFunctional): ?>
                <details class="sidebar-group" <?= $groupAdminFunctionalOpen ? 'open' : '' ?>>
                    <summary>Administration fonctionnelle</summary>

                    <div class="sidebar-group-links">

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_services.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">
                            🧩 <span>Services</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_operation_types.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">
                            🧠 <span>Types d’opérations</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_accounts.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">
                            📚 <span>Comptes</span>
                        </a>

                    </div>
                </details>
            <?php endif; ?>

            <?php if ($canAdminTechnical): ?>
                <details class="sidebar-group" <?= $groupAdminTechnicalOpen ? 'open' : '' ?>>
                    <summary>Administration technique</summary>

                    <div class="sidebar-group-links">

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/user_logs.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/admin/user_logs.php">
                            📜 <span>Logs</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/users.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/admin/users.php">
                            👥 <span>Utilisateurs</span>
                        </a>

                        <a class="sidebar-link <?= sidebarActive('/modules/admin/settings.php', $currentUri) ?>"
                           href="<?= e(APP_URL) ?>modules/admin/settings.php">
                            ⚙️ <span>Paramètres</span>
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
    const btn = document.getElementById('sidebarCollapseBtn');
    const key = 'studely_sidebar';

    if (localStorage.getItem(key) === '1') {
        document.body.classList.add('sidebar-collapsed');
    }

    if (btn) {
        btn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(key, document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
    }
});
</script>