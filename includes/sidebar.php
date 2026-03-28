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

if (isset($pdo) && $pdo instanceof PDO && function_exists('currentUserCan')) {
    $canAdminFunctional = currentUserCan($pdo, 'operations_create') || currentUserCan($pdo, 'treasury_view');
    $canAdminTechnical = currentUserCan($pdo, 'admin_dashboard_view') || currentUserCan($pdo, 'admin_users_manage');
}

$groupMainOpen = sidebarGroupOpen([
    '/modules/dashboard/',
    '/modules/clients/',
    '/modules/operations/',
    '/modules/treasury/'
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
                    <strong>Studely Ledger</strong>
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
                    </div>
                </details>
            <?php endif; ?>
        </nav>

        <div class="sidebar-bottom">
            <a href="<?= e(APP_URL) ?>logout.php" class="btn btn-danger sidebar-logout-btn">Déconnexion</a>
        </div>
    </div>
</aside>

<style>
    .studely-sidebar{
        width:320px;
        min-width:320px;
        background:linear-gradient(180deg,#182042 0%,#1d2549 100%);
        color:#fff;
        position:relative;
        transition:width .25s ease,min-width .25s ease;
        box-shadow:8px 0 30px rgba(7,14,38,0.18);
        z-index:5;
    }

    .studely-sidebar-inner{
        min-height:100vh;
        display:flex;
        flex-direction:column;
        padding:18px 16px;
        gap:16px;
        overflow:hidden;
    }

    .sidebar-top{
        flex:0 0 auto;
    }

    .sidebar-brand-card{
        display:grid;
        grid-template-columns:44px 1fr;
        gap:12px;
        align-items:center;
        background:rgba(255,255,255,0.06);
        border:1px solid rgba(255,255,255,0.08);
        border-radius:22px;
        padding:14px;
        position:relative;
    }

    .sidebar-collapse-btn{
        grid-column:1;
        grid-row:1 / span 2;
        width:44px;
        height:44px;
        border:none;
        border-radius:14px;
        background:#fff;
        color:#1d2549;
        font-size:20px;
        cursor:pointer;
        box-shadow:0 8px 18px rgba(0,0,0,0.18);
    }

    .sidebar-brand-visual{
        grid-column:2;
        display:flex;
        align-items:center;
        justify-content:flex-start;
    }

    .sidebar-logo{
        max-width:150px;
        width:100%;
        height:auto;
        display:block;
    }

    .sidebar-brand-text{
        grid-column:2;
        display:flex;
        flex-direction:column;
        gap:2px;
    }

    .sidebar-brand-text strong{
        font-size:15px;
        color:#fff;
    }

    .sidebar-brand-text span{
        font-size:12px;
        color:rgba(255,255,255,0.72);
    }

    .sidebar-nav{
        flex:1 1 auto;
        overflow:auto;
        padding-right:4px;
        display:flex;
        flex-direction:column;
        gap:12px;
    }

    .sidebar-nav::-webkit-scrollbar{
        width:6px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb{
        background:rgba(255,255,255,0.18);
        border-radius:999px;
    }

    .sidebar-group{
        background:rgba(255,255,255,0.05);
        border:1px solid rgba(255,255,255,0.08);
        border-radius:18px;
        overflow:hidden;
    }

    .sidebar-group summary{
        list-style:none;
        cursor:pointer;
        padding:14px 16px;
        font-weight:700;
        font-size:14px;
        color:#fff;
        position:relative;
        user-select:none;
    }

    .sidebar-group summary::-webkit-details-marker{
        display:none;
    }

    .sidebar-group summary::after{
        content:'▾';
        position:absolute;
        right:16px;
        top:50%;
        transform:translateY(-50%);
        font-size:14px;
        color:rgba(255,255,255,0.8);
        transition:transform .2s ease;
    }

    .sidebar-group:not([open]) summary::after{
        transform:translateY(-50%) rotate(-90deg);
    }

    .sidebar-group-links{
        display:flex;
        flex-direction:column;
        gap:8px;
        padding:0 10px 12px 10px;
    }

    .sidebar-link{
        display:flex;
        align-items:center;
        gap:10px;
        text-decoration:none;
        color:#eef2ff;
        padding:11px 12px;
        border-radius:14px;
        transition:all .2s ease;
        font-size:14px;
        line-height:1.35;
    }

    .sidebar-link:hover{
        background:rgba(255,255,255,0.12);
        transform:translateX(2px);
    }

    .sidebar-link.active{
        background:#ffffff;
        color:#1d2549;
        font-weight:700;
        box-shadow:0 8px 20px rgba(0,0,0,0.18);
    }

    .sidebar-bottom{
        flex:0 0 auto;
        padding-top:4px;
    }

    .sidebar-logout-btn{
        width:100%;
        text-align:center;
    }

    body.sidebar-collapsed .studely-sidebar{
        width:96px;
        min-width:96px;
    }

    body.sidebar-collapsed .sidebar-brand-card{
        grid-template-columns:1fr;
        justify-items:center;
        padding:12px 10px;
    }

    body.sidebar-collapsed .sidebar-collapse-btn{
        grid-column:1;
        grid-row:auto;
    }

    body.sidebar-collapsed .sidebar-brand-visual{
        grid-column:1;
        justify-content:center;
    }

    body.sidebar-collapsed .sidebar-logo{
        max-width:48px;
    }

    body.sidebar-collapsed .sidebar-brand-text,
    body.sidebar-collapsed .sidebar-group summary,
    body.sidebar-collapsed .sidebar-link span:last-child{
        display:none;
    }

    body.sidebar-collapsed .sidebar-group{
        background:transparent;
        border:none;
    }

    body.sidebar-collapsed .sidebar-group[open] .sidebar-group-links{
        padding-top:0;
    }

    body.sidebar-collapsed .sidebar-link{
        justify-content:center;
        padding:12px;
        border-radius:16px;
    }

    body.sidebar-collapsed .sidebar-nav{
        overflow:visible;
    }

    @media (max-width: 1100px){
        .studely-sidebar{
            width:270px;
            min-width:270px;
        }
    }

    @media (max-width: 860px){
        .studely-sidebar{
            width:100%;
            min-width:100%;
            min-height:auto;
        }

        .studely-sidebar-inner{
            min-height:auto;
        }

        .sidebar-nav{
            max-height:420px;
        }
    }
</style>

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