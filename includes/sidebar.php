<?php
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/app.php';
}

require_once __DIR__ . '/admin_functions.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$currentPath = str_replace('\\', '/', $currentPath);

function sidebar_is_active(array $paths, string $currentPath): bool
{
    foreach ($paths as $path) {
        if ($path !== '' && str_contains($currentPath, $path)) {
            return true;
        }
    }
    return false;
}

function sidebar_group_open(array $items, string $currentPath): bool
{
    foreach ($items as $item) {
        if (!empty($item['children'])) {
            if (sidebar_group_open($item['children'], $currentPath)) {
                return true;
            }
        }

        if (!empty($item['match']) && sidebar_is_active((array)$item['match'], $currentPath)) {
            return true;
        }
    }
    return false;
}

function sidebar_render_link(array $item, string $currentPath): void
{
    $isActive = !empty($item['match']) && sidebar_is_active((array)$item['match'], $currentPath);
    $label = $item['label'] ?? 'Lien';
    $href = $item['href'] ?? '#';
    $icon = $item['icon'] ?? '•';

    echo '<a href="' . e($href) . '" class="sidebar-link' . ($isActive ? ' active' : '') . '">';
    echo '<span>' . e($icon) . '</span>';
    echo '<span>' . e($label) . '</span>';
    echo '</a>';
}

$nav = [];

/* Dashboard */
if (currentUserCan($pdo, 'dashboard_view')) {
    $nav[] = [
        'type' => 'link',
        'label' => 'Dashboard',
        'icon' => '⌂',
        'href' => APP_URL . 'modules/dashboard/dashboard.php',
        'match' => [
            '/modules/dashboard/dashboard.php',
            '/modules/dashboard/accounting_control_dashboard.php'
        ],
    ];
}

/* Clients */
$clientsChildren = [];
if (currentUserCan($pdo, 'clients_view')) {
    $clientsChildren[] = [
        'label' => 'Liste des clients',
        'icon' => '◦',
        'href' => APP_URL . 'modules/clients/clients_list.php',
        'match' => [
            '/modules/clients/clients_list.php',
            '/modules/clients/client_view.php'
        ],
    ];
}
if (currentUserCan($pdo, 'clients_create')) {
    $clientsChildren[] = [
        'label' => 'Créer un client',
        'icon' => '◦',
        'href' => APP_URL . 'modules/clients/client_create.php',
        'match' => ['/modules/clients/client_create.php'],
    ];
    $clientsChildren[] = [
        'label' => 'Import clients CSV',
        'icon' => '◦',
        'href' => APP_URL . 'modules/clients/import_clients_csv.php',
        'match' => ['/modules/clients/import_clients_csv.php'],
    ];
}
if (!empty($clientsChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Clients',
        'children' => $clientsChildren,
    ];
}

/* Opérations */
$operationsChildren = [];
if (currentUserCan($pdo, 'operations_view') || currentUserCan($pdo, 'operations_create')) {
    $operationsChildren[] = [
        'label' => 'Créer une opération',
        'icon' => '◦',
        'href' => APP_URL . 'modules/operations/operation_create.php',
        'match' => ['/modules/operations/operation_create.php'],
    ];
}
if (currentUserCan($pdo, 'operations_view')) {
    $operationsChildren[] = [
        'label' => 'Liste des opérations',
        'icon' => '◦',
        'href' => APP_URL . 'modules/operations/operations_list.php',
        'match' => ['/modules/operations/operations_list.php'],
    ];
}
if (!empty($operationsChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Opérations',
        'children' => $operationsChildren,
    ];
}

/* Imports */
$importsChildren = [];
if (currentUserCan($pdo, 'imports_upload') || currentUserCan($pdo, 'imports_create')) {
    $importsChildren[] = [
        'label' => 'Uploader un import',
        'icon' => '◦',
        'href' => APP_URL . 'modules/imports/import_upload.php',
        'match' => ['/modules/imports/import_upload.php'],
    ];
}
if (currentUserCan($pdo, 'imports_journal')) {
    $importsChildren[] = [
        'label' => 'Journal des imports',
        'icon' => '◦',
        'href' => APP_URL . 'modules/imports/import_journal.php',
        'match' => [
            '/modules/imports/import_journal.php',
            '/modules/imports/import_preview.php',
            '/modules/imports/rejected_rows.php',
            '/modules/imports/correct_rejected_row.php'
        ],
    ];
}
if (!empty($importsChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Imports',
        'children' => $importsChildren,
    ];
}

/* Trésorerie */
$treasuryChildren = [];
if (currentUserCan($pdo, 'treasury_view')) {
    $treasuryChildren[] = [
        'label' => 'Comptes internes',
        'icon' => '◦',
        'href' => APP_URL . 'modules/treasury/treasury_accounts.php',
        'match' => ['/modules/treasury/treasury_accounts.php'],
    ];
}
if (currentUserCan($pdo, 'treasury_import')) {
    $treasuryChildren[] = [
        'label' => 'Import trésorerie',
        'icon' => '◦',
        'href' => APP_URL . 'modules/treasury/import_treasury_csv.php',
        'match' => ['/modules/treasury/import_treasury_csv.php'],
    ];
}
if (!empty($treasuryChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Trésorerie',
        'children' => $treasuryChildren,
    ];
}

/* Admin fonctionnel */
$adminFunctionalChildren = [];
if (currentUserCan($pdo, 'admin_functional_view')) {
    $adminFunctionalChildren[] = [
        'label' => 'Types d’opérations',
        'icon' => '◦',
        'href' => APP_URL . 'modules/admin_functional/manage_operation_types.php',
        'match' => [
            '/modules/admin_functional/manage_operation_types.php',
            '/modules/admin_functional/edit_operation_type.php'
        ],
    ];
    $adminFunctionalChildren[] = [
        'label' => 'Services',
        'icon' => '◦',
        'href' => APP_URL . 'modules/admin_functional/manage_services.php',
        'match' => [
            '/modules/admin_functional/manage_services.php',
            '/modules/admin_functional/edit_service.php'
        ],
    ];
}
if (!empty($adminFunctionalChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Référentiels',
        'children' => $adminFunctionalChildren,
    ];
}

/* Support */
$supportChildren = [];
if (currentUserCan($pdo, 'support_requests_view')) {
    $supportChildren[] = [
        'label' => 'Demandes support',
        'icon' => '◦',
        'href' => APP_URL . 'modules/support/support_requests.php',
        'match' => [
            '/modules/support/support_requests.php',
            '/modules/support/ask_question.php',
            '/modules/support/report_bug.php',
            '/modules/support/request_access.php'
        ],
    ];
}
if (!empty($supportChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Support',
        'children' => $supportChildren,
    ];
}

/* Administration */
$adminChildren = [];
if (currentUserCan($pdo, 'users_manage')) {
    $adminChildren[] = [
        'label' => 'Utilisateurs',
        'icon' => '◦',
        'href' => APP_URL . 'modules/admin/manage_users.php',
        'match' => [
            '/modules/admin/manage_users.php',
            '/modules/admin/user_create.php',
            '/modules/admin/user_edit.php',
            '/modules/admin/user_delete.php',
            '/modules/admin/user_logs.php'
        ],
    ];
}
if (currentUserCan($pdo, 'settings_manage')) {
    $adminChildren[] = [
        'label' => 'Paramètres',
        'icon' => '◦',
        'href' => APP_URL . 'modules/admin/settings.php',
        'match' => ['/modules/admin/settings.php'],
    ];
}
if (!empty($adminChildren)) {
    $nav[] = [
        'type' => 'group',
        'label' => 'Administration',
        'children' => $adminChildren,
    ];
}
?>

<aside class="studely-sidebar" id="studelySidebar">
    <div class="studely-sidebar-inner">
        <div class="sidebar-top">
            <div class="sidebar-brand-card">
                <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Réduire le menu">
                    ☰
                </button>

                <div class="sidebar-brand-visual">
                    <img src="<?= e(APP_URL) ?>assets/img/logo.png" alt="Studely Ledger" class="sidebar-logo">
                </div>

                <div class="sidebar-brand-text">
                    <strong>Studely Ledger</strong>
                    <span>Pilotage & contrôle</span>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($nav as $item): ?>
                <?php if (($item['type'] ?? '') === 'link'): ?>
                    <?php sidebar_render_link($item, $currentPath); ?>
                <?php elseif (($item['type'] ?? '') === 'group'): ?>
                    <?php $isOpen = sidebar_group_open($item['children'] ?? [], $currentPath); ?>
                    <details class="sidebar-group" <?= $isOpen ? 'open' : '' ?>>
                        <summary><?= e($item['label'] ?? 'Groupe') ?></summary>
                        <div class="sidebar-group-links">
                            <?php foreach (($item['children'] ?? []) as $child): ?>
                                <?php sidebar_render_link($child, $currentPath); ?>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-bottom">
            <a href="<?= e(APP_URL) ?>logout.php" class="btn btn-danger sidebar-logout-btn">Déconnexion</a>
        </div>
    </div>
</aside>

<script>
(function () {
    const body = document.body;
    const btn = document.getElementById('sidebarCollapseBtn');
    const storageKey = 'studely_sidebar_collapsed';

    if (!btn) return;

    const saved = localStorage.getItem(storageKey);
    if (saved === '1') {
        body.classList.add('sidebar-collapsed');
    }

    btn.addEventListener('click', function () {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(storageKey, body.classList.contains('sidebar-collapsed') ? '1' : '0');
    });
})();
</script>