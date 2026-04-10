<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/admin_functions.php';

$currentUri = $_SERVER['REQUEST_URI'] ?? '';

if (!function_exists('sidebarNeedleMatch')) {
    function sidebarNeedleMatch(array $needles, string $currentUri): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($currentUri, $needle)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sidebarActive')) {
    function sidebarActive(array $needles, string $currentUri): string
    {
        return sidebarNeedleMatch($needles, $currentUri) ? 'active' : '';
    }
}

if (!function_exists('sidebarGroupOpen')) {
    function sidebarGroupOpen(array $items, string $currentUri): bool
    {
        foreach ($items as $item) {
            if (!empty($item['patterns']) && sidebarNeedleMatch((array)$item['patterns'], $currentUri)) {
                return true;
            }
        }
        return false;
    }
}

$canUseAccessMap = isset($pdo) && $pdo instanceof PDO && function_exists('studelyCanAccess');

$can = function (?string $accessKey) use ($canUseAccessMap, $pdo): bool {
    if ($accessKey === null || $accessKey === '') {
        return true;
    }
    if (!$canUseAccessMap) {
        return true;
    }
    return studelyCanAccess($pdo, $accessKey);
};

$groups = [
    [
        'title' => 'Navigation principale',
        'items' => [
            [
                'label' => 'Dashboard',
                'icon' => '📊',
                'href' => APP_URL . 'modules/dashboard/dashboard.php',
                'patterns' => ['/modules/dashboard/dashboard.php'],
                'access' => 'dashboard_view_page',
            ],
            [
                'label' => 'Clients',
                'icon' => '👤',
                'href' => APP_URL . 'modules/clients/clients_list.php',
                'patterns' => ['/modules/clients/clients_list.php', '/modules/clients/client_view.php', '/modules/clients/client_create.php', '/modules/clients/client_edit.php'],
                'access' => 'clients_view_page',
            ],
            [
                'label' => 'Opérations',
                'icon' => '💰',
                'href' => APP_URL . 'modules/operations/operations_list.php',
                'patterns' => ['/modules/operations/'],
                'access' => 'operations_view_page',
            ],
            [
                'label' => 'Comptes Clients (411)',
                'icon' => '🏦',
                'href' => APP_URL . 'modules/clients/client_accounts.php',
                'patterns' => ['/modules/clients/client_accounts.php'],
                'access' => 'clients_view_page',
            ],
            [
                'label' => 'Comptes internes (512)',
                'icon' => '🏛️',
                'href' => APP_URL . 'modules/treasury/index.php',
                'patterns' => ['/modules/treasury/'],
                'access' => 'treasury_view_page',
            ],
            [
                'label' => 'Comptes de service (706)',
                'icon' => '📘',
                'href' => APP_URL . 'modules/service_accounts/index.php',
                'patterns' => ['/modules/service_accounts/'],
                'access' => 'service_accounts_manage_page',
            ],
            [
                'label' => 'Analytics',
                'icon' => '📈',
                'href' => APP_URL . 'modules/analytics/revenue_analysis.php',
                'patterns' => ['/modules/analytics/'],
                'access' => 'analytics_view_page',
            ],
        ],
    ],
    [
        'title' => 'Imports',
        'items' => [
            [
                'label' => 'Hub Import',
                'icon' => '📦',
                'href' => APP_URL . 'modules/imports/index.php',
                'patterns' => ['/modules/imports/index.php'],
                'access' => 'imports_upload_page',
            ],
            [
                'label' => 'Import Opérations',
                'icon' => '📥',
                'href' => APP_URL . 'modules/imports/import_preview.php',
                'patterns' => ['/modules/imports/import_preview.php', '/modules/imports/import_upload.php', '/modules/imports/import_mapping.php', '/modules/imports/import_validate.php'],
                'access' => 'imports_preview_page',
            ],
            [
                'label' => 'Journal imports',
                'icon' => '🧾',
                'href' => APP_URL . 'modules/imports/import_journal.php',
                'patterns' => ['/modules/imports/import_journal.php'],
                'access' => 'imports_journal_page',
            ],
            [
                'label' => 'Import clients CSV',
                'icon' => '🧍',
                'href' => APP_URL . 'modules/clients/import_clients_csv.php',
                'patterns' => ['/modules/clients/import_clients_csv.php'],
                'access' => 'clients_create_page',
            ],
            [
                'label' => 'Import comptes internes CSV',
                'icon' => '🏦',
                'href' => APP_URL . 'modules/treasury/import_treasury_csv.php',
                'patterns' => ['/modules/treasury/import_treasury_csv.php'],
                'access' => 'treasury_import_page',
            ],
            [
                'label' => 'Import comptes de service CSV',
                'icon' => '📘',
                'href' => APP_URL . 'modules/service_accounts/import_service_accounts_csv.php',
                'patterns' => ['/modules/service_accounts/import_service_accounts_csv.php'],
                'access' => 'service_accounts_import_page',
            ],
        ],
    ],
    [
        'title' => 'Exports',
        'items' => [
            [
                'label' => 'Hub Export',
                'icon' => '📤',
                'href' => APP_URL . 'modules/statements/index.php',
                'patterns' => ['/modules/statements/index.php'],
                'access' => 'statements_view_page',
            ],
            [
                'label' => 'Relevés de comptes',
                'icon' => '📄',
                'href' => APP_URL . 'modules/statements/account_statements.php',
                'patterns' => ['/modules/statements/account_statements.php'],
                'access' => 'statements_view_page',
            ],
            [
                'label' => 'Fiches clients',
                'icon' => '🗂️',
                'href' => APP_URL . 'modules/statements/client_profiles.php',
                'patterns' => ['/modules/statements/client_profiles.php'],
                'access' => 'statements_view_page',
            ],
        ],
    ],
    [
        'title' => 'Support',
        'items' => [
            [
                'label' => 'Demandes support',
                'icon' => '🆘',
                'href' => APP_URL . 'modules/support/support_requests.php',
                'patterns' => ['/modules/support/support_requests.php'],
                'access' => 'support_view_page',
            ],
            [
                'label' => 'Poser une question',
                'icon' => '❓',
                'href' => APP_URL . 'modules/support/ask_question.php',
                'patterns' => ['/modules/support/ask_question.php'],
                'access' => 'support_view_page',
            ],
            [
                'label' => 'Signaler un bug',
                'icon' => '🐞',
                'href' => APP_URL . 'modules/support/report_bug.php',
                'patterns' => ['/modules/support/report_bug.php'],
                'access' => 'support_view_page',
            ],
            [
                'label' => 'Demander un accès',
                'icon' => '🔐',
                'href' => APP_URL . 'modules/support/request_access.php',
                'patterns' => ['/modules/support/request_access.php'],
                'access' => 'support_view_page',
            ],
        ],
    ],
    [
        'title' => 'Administration fonctionnelle',
        'items' => [
            [
                'label' => 'Dashboard Admin Fonctionnelle',
                'icon' => '⚙️',
                'href' => APP_URL . 'modules/admin_functional/dashboard.php',
                'patterns' => ['/modules/admin_functional/dashboard.php'],
                'access' => 'admin_functional_page',
            ],
            [
                'label' => 'Services',
                'icon' => '🧩',
                'href' => APP_URL . 'modules/admin_functional/manage_services.php',
                'patterns' => ['/modules/admin_functional/manage_services.php', '/modules/admin_functional/edit_service.php'],
                'access' => 'services_manage_page',
            ],
            [
                'label' => 'Types d’opérations',
                'icon' => '🧠',
                'href' => APP_URL . 'modules/admin_functional/manage_operation_types.php',
                'patterns' => ['/modules/admin_functional/manage_operation_types.php', '/modules/admin_functional/edit_operation_type.php'],
                'access' => 'operation_types_manage_page',
            ],
            [
                'label' => 'Règles comptables',
                'icon' => '🧾',
                'href' => APP_URL . 'modules/admin_functional/manage_accounting_rules.php',
                'patterns' => [
                    '/modules/admin_functional/manage_accounting_rules.php',
                    '/modules/admin_functional/accounting_rule_create.php',
                    '/modules/admin_functional/accounting_rule_edit.php'
                ],
                'access' => 'admin_functional_page',
            ],
            [
                'label' => 'Comptes',
                'icon' => '📚',
                'href' => APP_URL . 'modules/admin_functional/manage_accounts.php',
                'patterns' => ['/modules/admin_functional/manage_accounts.php'],
                'access' => 'admin_functional_page',
            ],
        ],
    ],
    [
        'title' => 'Administration technique',
        'items' => [
            [
                'label' => 'Dashboard Admin Technique',
                'icon' => '🛠️',
                'href' => APP_URL . 'modules/admin/dashboard_admin.php',
                'patterns' => ['/modules/admin/dashboard_admin.php'],
                'access' => 'admin_dashboard_page',
            ],
            [
                'label' => 'Audit & Traçabilité',
                'icon' => '🧭',
                'href' => APP_URL . 'modules/admin/audit_logs.php',
                'patterns' => ['/modules/admin/audit_logs.php'],
                'access' => 'admin_dashboard_page',
            ],
            [
                'label' => 'Audit des logs',
                'icon' => '📜',
                'href' => APP_URL . 'modules/admin/user_logs.php',
                'patterns' => ['/modules/admin/user_logs.php'],
                'access' => 'user_logs_view_page',
            ],
            [
                'label' => 'Rôles',
                'icon' => '🔐',
                'href' => APP_URL . 'modules/admin/roles.php',
                'patterns' => ['/modules/admin/roles.php'],
                'access' => 'roles_manage_page',
            ],
            [
                'label' => 'Utilisateurs',
                'icon' => '👥',
                'href' => APP_URL . 'modules/admin/users.php',
                'patterns' => ['/modules/admin/users.php', '/modules/admin/user_create.php', '/modules/admin/user_edit.php'],
                'access' => 'users_manage_page',
            ],
            [
                'label' => 'Matrice d’accès',
                'icon' => '🧮',
                'href' => APP_URL . 'modules/admin/access_matrix.php',
                'patterns' => ['/modules/admin/access_matrix.php'],
                'access' => 'permissions_manage_page',
            ],
            [
                'label' => 'Centre d’intelligence',
                'icon' => '🧠',
                'href' => APP_URL . 'modules/admin/intelligence_center.php',
                'patterns' => ['/modules/admin/intelligence_center.php'],
                'access' => 'admin_dashboard_page',
            ],
            [
                'label' => 'Paramètres',
                'icon' => '⚙️',
                'href' => APP_URL . 'modules/admin/settings.php',
                'patterns' => ['/modules/admin/settings.php'],
                'access' => 'settings_manage_page',
            ],
        ],
    ],
];

$visibleGroups = [];
foreach ($groups as $group) {
    $visibleItems = [];
    foreach ($group['items'] as $item) {
        if ($can($item['access'] ?? null)) {
            $visibleItems[] = $item;
        }
    }
    if ($visibleItems) {
        $group['items'] = $visibleItems;
        $visibleGroups[] = $group;
    }
}
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
            <?php foreach ($visibleGroups as $group): ?>
                <details class="sidebar-group" <?= sidebarGroupOpen($group['items'], $currentUri) ? 'open' : '' ?>>
                    <summary><?= e($group['title']) ?></summary>
                    <div class="sidebar-group-links">
                        <?php foreach ($group['items'] as $item): ?>
                            <a
                                class="sidebar-link <?= sidebarActive((array)$item['patterns'], $currentUri) ?>"
                                href="<?= e($item['href']) ?>"
                            >
                                <span><?= e($item['icon']) ?></span>
                                <span><?= e($item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
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