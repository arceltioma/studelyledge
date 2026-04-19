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

if (!function_exists('sidebarActiveMulti')) {
    function sidebarActiveMulti(array $needles, string $currentUri): string
    {
        foreach ($needles as $needle) {
            if (str_contains($currentUri, $needle)) {
                return 'active';
            }
        }
        return '';
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
    '/modules/manual_actions/',
    '/modules/treasury/',
    '/modules/service_accounts/',
    '/modules/analytics/'
], $currentUri);

$groupImportsOpen = sidebarGroupOpen([
    '/modules/imports/',
    '/modules/monthly_payments/',
    '/modules/clients/import_clients_csv.php',
    '/modules/treasury/import_treasury_csv.php',
    '/modules/service_accounts/import_service_accounts_csv.php'
], $currentUri);

$groupExportsOpen = sidebarGroupOpen([
    '/modules/statements/'
], $currentUri);

$groupSupportOpen = sidebarGroupOpen([
    '/modules/support/',
    '/modules/notifications/'
], $currentUri);

$groupAdminFunctionalOpen = sidebarGroupOpen([
    '/modules/admin_functional/',
    '/modules/service_accounts/',
    '/modules/treasury/',
    '/modules/clients/client_accounts.php',
    '/modules/pending_debits/'
], $currentUri);

$groupAdminTechnicalOpen = sidebarGroupOpen([
    '/modules/admin/'
], $currentUri);
?>

<aside class="sidebar studely-sidebar">
    <div class="sidebar-inner studely-sidebar-inner">
        <div class="sidebar-top">
            <div class="sidebar-brand-card">
                <button
                    type="button"
                    class="sidebar-collapse-btn"
                    id="sidebarCollapseBtn"
                    aria-label="Réduire ou ouvrir le menu"
                    title="Réduire / ouvrir le menu"
                >
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
                        <a class="sidebar-link <?= sidebarActive('/modules/clients/clients_list.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">
                            <span>👤</span><span>Clients</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('operations_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActiveMulti([
                            '/modules/operations/operations_list.php',
                            '/modules/operations/operation_create.php',
                            '/modules/operations/operation_edit.php',
                            '/modules/operations/operation_view.php'
                        ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/operations/operations_list.php">
                            <span>💰</span><span>Opérations</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('manual_actions_create_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/manual_actions/manual_operation.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/manual_actions/manual_operation.php">
                            <span>✍️</span><span>Opération manuelle</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('client_accounts_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActive('/modules/clients/client_accounts.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/client_accounts.php">
                            <span>🏦</span><span>Comptes Clients (411)</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('treasury_view_page')): ?>
                        <a class="sidebar-link <?= sidebarActiveMulti([
                            '/modules/treasury/index.php',
                            '/modules/treasury/treasury_view.php',
                            '/modules/treasury/treasury_edit.php',
                            '/modules/treasury/import_treasury_csv.php'
                        ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/treasury/index.php">
                            <span>🏛️</span><span>Comptes internes (512)</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($can('service_accounts_manage_page')): ?>
                        <a class="sidebar-link <?= sidebarActiveMulti([
                            '/modules/service_accounts/index.php',
                            '/modules/service_accounts/view.php',
                            '/modules/service_accounts/edit.php',
                            '/modules/service_accounts/import_service_accounts_csv.php'
                        ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/service_accounts/index.php">
                            <span>📘</span><span>Comptes de service (706)</span>
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
                        <?php if ($can('imports_upload_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/imports/index.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/index.php">
                                <span>📦</span><span>Hub Import</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('imports_preview_page')): ?>
                            <a class="sidebar-link <?= sidebarActiveMulti([
                                '/modules/imports/import_preview.php',
                                '/modules/imports/import_upload.php',
                                '/modules/imports/import_mapping.php'
                            ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/import_preview.php">
                                <span>📥</span><span>Import Opérations</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('imports_journal_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/imports/import_journal.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/imports/import_journal.php">
                                <span>🧾</span><span>Journal imports</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('monthly_payments_import_page')): ?>
                            <a class="sidebar-link <?= sidebarActiveMulti([
                                '/modules/monthly_payments/monthly_payments_import.php',
                                '/modules/monthly_payments/monthly_import_create.php',
                                '/modules/monthly_payments/monthly_import_preview.php',
                                '/modules/monthly_payments/monthly_import_validate.php',
                                '/modules/monthly_payments/import_monthly_payments.php',
                                '/modules/monthly_payments/monthly_payments_preview.php',
                                '/modules/monthly_payments/monthly_payments_validate.php'
                            ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_payments_import.php">
                                <span>📅</span><span>Import mensualités</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('monthly_runs_list_page')): ?>
                            <a class="sidebar-link <?= sidebarActiveMulti([
                                '/modules/monthly_payments/monthly_runs_list.php',
                                '/modules/monthly_payments/monthly_run_view.php',
                                '/modules/monthly_payments/monthly_run_execute.php',
                                '/modules/monthly_payments/monthly_run_cancel.php',
                                '/modules/monthly_payments/monthly_payments_run.php'
                            ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_runs_list.php">
                                <span>🔁</span><span>Runs mensualités</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('clients_import_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/clients/import_clients_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">
                                <span>🧍</span><span>Import clients CSV</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('treasury_import_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/treasury/import_treasury_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php">
                                <span>🏛️</span><span>Import comptes internes CSV</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('service_accounts_import_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/service_accounts/import_service_accounts_csv.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/service_accounts/import_service_accounts_csv.php">
                                <span>📘</span><span>Import comptes de service CSV</span>
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
                            <span>📤</span><span>Hub Export</span>
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

            <?php if ($can('support_requests_view_page') || $can('support_request_create_page') || $can('notifications_view_page')): ?>
                <details class="sidebar-group" <?= $groupSupportOpen ? 'open' : '' ?>>
                    <summary>Support</summary>
                    <div class="sidebar-group-links">
                        <?php if ($can('notifications_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/notifications/notifications.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/notifications/notifications.php">
                                <span>🔔</span><span>Notifications</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('support_requests_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/support/support_requests.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/support_requests.php">
                                <span>🆘</span><span>Demandes support</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('support_request_create_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/support/ask_question.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/ask_question.php">
                                <span>❓</span><span>Poser une question</span>
                            </a>

                            <a class="sidebar-link <?= sidebarActive('/modules/support/report_bug.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/report_bug.php">
                                <span>🐞</span><span>Signaler un bug</span>
                            </a>

                            <a class="sidebar-link <?= sidebarActive('/modules/support/request_access.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/support/request_access.php">
                                <span>🔐</span><span>Demander un accès</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($can('admin_functional_dashboard_view_page') || $can('manage_services_page') || $can('manage_operation_types_page')): ?>
                <details class="sidebar-group" <?= $groupAdminFunctionalOpen ? 'open' : '' ?>>
                    <summary>Administration fonctionnelle</summary>
                    <div class="sidebar-group-links">
                        <?php if ($can('admin_functional_dashboard_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/dashboard.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/dashboard.php">
                                <span>⚙️</span><span>Dashboard Admin Fonctionnelle</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('pending_debits_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActiveMulti([
                                '/modules/pending_debits/pending_debits_list.php',
                                '/modules/pending_debits/pending_debit_view.php',
                                '/modules/pending_debits/pending_debit_edit.php',
                                '/modules/pending_debits/pending_debit_execute.php',
                                '/modules/pending_debits/pending_debit_cancel.php'
                            ], $currentUri) ?>" href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php">
                                <span>⛔</span><span>Débits dus 411</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('manage_services_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_services.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">
                                <span>🧩</span><span>Services</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('manage_operation_types_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_operation_types.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">
                                <span>🧠</span><span>Types d’opérations</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('manage_accounting_rules_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_accounting_rules.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php">
                                <span>📐</span><span>Règles comptables</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('manage_accounts_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/manage_accounts.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">
                                <span>📚</span><span>Comptes</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('accounting_balance_audit_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin_functional/accounting_balance_audit.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin_functional/accounting_balance_audit.php">
                                <span>🧾</span><span>Audit soldes comptables</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($can('admin_dashboard_view_page') || $can('admin_users_manage_page') || $can('settings_manage_page') || $can('access_matrix_manage_page')): ?>
                <details class="sidebar-group" <?= $groupAdminTechnicalOpen ? 'open' : '' ?>>
                    <summary>Administration technique</summary>
                    <div class="sidebar-group-links">
                        <?php if ($can('admin_dashboard_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/dashboard_admin.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php">
                                <span>🛠️</span><span>Dashboard Admin Technique</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('audit_logs_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/audit_logs.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/audit_logs.php">
                                <span>🧭</span><span>Audit &amp; Traçabilité</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('user_logs_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/user_logs.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/user_logs.php">
                                <span>📜</span><span>Audit des logs</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('roles_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/roles.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/roles.php">
                                <span>🔐</span><span>Rôles</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('admin_users_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/users.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/users.php">
                                <span>👥</span><span>Utilisateurs</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('access_matrix_manage_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/access_matrix.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/access_matrix.php">
                                <span>🧮</span><span>Matrice d’accès</span>
                            </a>

                            <a class="sidebar-link <?= sidebarActive('/modules/admin/seed_permissions.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/seed_permissions.php">
                                <span>🌱</span><span>Seed permissions</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($can('intelligence_center_view_page')): ?>
                            <a class="sidebar-link <?= sidebarActive('/modules/admin/intelligence_center.php', $currentUri) ?>" href="<?= e(APP_URL) ?>modules/admin/intelligence_center.php">
                                <span>🧠</span><span>Centre d’intelligence</span>
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
            localStorage.setItem(
                storageKey,
                body.classList.contains('sidebar-collapsed') ? '1' : '0'
            );
        });
    }
});
</script>