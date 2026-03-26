<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

function sidebarActive(string $needle, string $currentUri): string
{
    return str_contains($currentUri, $needle) ? 'active' : '';
}
?>

<aside class="sidebar" style="background:#1d2549;">
    <div class="sidebar-inner" style="min-height:100vh;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:24px;">
        <div class="sidebar-brand" style="margin-bottom:26px;text-align:center;">
            <img src="<?= APP_URL ?>assets/img/logo-sidebar.png" alt="StudelyLedger" style="max-width:180px;width:100%;height:auto;">
        </div>

        <nav style="width:100%;max-width:260px;">
            <a class="sidebar-link <?= sidebarActive('/modules/dashboard/', $currentUri) ?>" href="<?= APP_URL ?>modules/dashboard/dashboard.php">Dashboard</a>
            <a class="sidebar-link <?= sidebarActive('/modules/clients/', $currentUri) ?>" href="<?= APP_URL ?>modules/clients/clients_list.php">Clients</a>
            <a class="sidebar-link <?= sidebarActive('/modules/operations/', $currentUri) ?>" href="<?= APP_URL ?>modules/operations/operations_list.php">Opérations</a>
            <a class="sidebar-link <?= sidebarActive('/modules/treasury/', $currentUri) ?>" href="<?= APP_URL ?>modules/treasury/index.php">Comptes internes</a>

            <div class="sidebar-group-title" style="margin-top:18px;">Imports</div>
            <a class="sidebar-link <?= sidebarActive('/modules/imports/import_preview.php', $currentUri) ?>" href="<?= APP_URL ?>modules/imports/import_preview.php">Import relevés</a>
            <a class="sidebar-link <?= sidebarActive('/modules/imports/import_journal.php', $currentUri) ?>" href="<?= APP_URL ?>modules/imports/import_journal.php">Journal imports</a>

            <div class="sidebar-group-title" style="margin-top:18px;">Exports</div>
            <a class="sidebar-link <?= sidebarActive('/modules/statements/account_statements.php', $currentUri) ?>" href="<?= APP_URL ?>modules/statements/account_statements.php">Relevés de comptes</a>
            <a class="sidebar-link <?= sidebarActive('/modules/statements/client_profiles.php', $currentUri) ?>" href="<?= APP_URL ?>modules/statements/client_profiles.php">Fiches clients</a>
            <a class="sidebar-link <?= sidebarActive('/modules/statements/index.php', $currentUri) ?>" href="<?= APP_URL ?>modules/statements/index.php">Hub exports</a>
        </nav>

        <div style="margin-top:28px;width:100%;max-width:260px;text-align:center;">
            <a href="<?= APP_URL ?>logout.php" class="btn btn-danger" style="width:100%;">Déconnexion</a>
        </div>
    </div>
</aside>