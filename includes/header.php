<?php
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/app.php';
}

require_once __DIR__ . '/admin_functions.php';

$currentUserName = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Utilisateur';
$currentUserRole = $_SESSION['role_name'] ?? $_SESSION['role'] ?? 'Accès sécurisé';

$pageTitle = $pageTitle ?? 'Studely Ledger';
$pageSubtitle = $pageSubtitle ?? 'Pilotage financier et contrôle comptable';
?>

<header class="studely-header">
    <div class="header-left">
        <div class="header-titles">
            <span class="header-overline">Studely Ledger</span>
            <h1 class="header-title"><?= e($pageTitle) ?></h1>
            <?php if ($pageSubtitle !== ''): ?>
                <div class="header-subtitle"><?= e($pageSubtitle) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="header-right">
        <div class="header-user">
            <strong><?= e($currentUserName) ?></strong>
            <span><?= e($currentUserRole) ?></span>
        </div>

        <div class="header-actions">
            <?php if (currentUserCan($pdo, 'dashboard_view')): ?>
                <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-secondary">Dashboard</a>
            <?php endif; ?>

            <?php if (currentUserCan($pdo, 'support_requests_view')): ?>
                <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-outline">Support</a>
            <?php endif; ?>

            <a href="<?= e(APP_URL) ?>logout.php" class="btn btn-danger">Déconnexion</a>
        </div>
    </div>
</header>