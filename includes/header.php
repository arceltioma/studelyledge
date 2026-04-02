<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$title = $pageTitle ?? APP_NAME;
$subtitle = $pageSubtitle ?? 'Pilotage financier, audit et gouvernance';
$currentUser = $_SESSION['username'] ?? 'Utilisateur';

$notificationCount = 0;
$notificationItems = [];

if (isset($pdo) && $pdo instanceof PDO && function_exists('countUnreadNotifications')) {
    $notificationCount = countUnreadNotifications($pdo);
    $notificationItems = function_exists('getUnreadNotifications') ? getUnreadNotifications($pdo, 5) : [];
}
?>

<header class="studely-header">
    <div class="header-left">
        <div class="header-titles">
            <span class="header-overline"><?= e(APP_NAME) ?></span>
            <h1 class="header-title"><?= e($title) ?></h1>

            <?php if ($subtitle !== ''): ?>
                <div class="header-subtitle"><?= e($subtitle) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="header-right">
        <form method="GET" action="<?= e(APP_URL) ?>modules/search/global_search.php" class="header-search-form" style="display:flex; gap:8px; align-items:center;">
            <input
                type="text"
                name="q"
                placeholder="Recherche globale..."
                value="<?= e((string)($_GET['q'] ?? '')) ?>"
                style="min-width:220px;"
            >
            <button type="submit" class="btn btn-secondary">🔎</button>
        </form>

        <div class="header-user">
            <span>Connecté en tant que</span>
            <strong><?= e($currentUser) ?></strong>
        </div>

        <div class="header-actions" style="display:flex; align-items:center; gap:10px;">
            <div class="header-notification-box" style="position:relative;">
                <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-secondary">
                    🔔<?= $notificationCount > 0 ? ' (' . (int)$notificationCount . ')' : '' ?>
                </a>

                <?php if ($notificationItems): ?>
                    <div class="header-notification-dropdown" style="position:absolute; right:0; top:100%; width:320px; background:#fff; border:1px solid rgba(148,163,184,0.16); border-radius:14px; padding:12px; box-shadow:0 12px 24px rgba(15,23,42,0.08); z-index:999; display:none;">
                        <?php foreach ($notificationItems as $item): ?>
                            <div style="padding:8px 0; border-bottom:1px solid rgba(148,163,184,0.12);">
                                <strong><?= e((string)($item['message'] ?? '')) ?></strong>
                                <div class="muted" style="margin-top:4px;"><?= e((string)($item['created_at'] ?? '')) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="btn btn-secondary">❓ Question</a>
            <a href="<?= e(APP_URL) ?>modules/support/report_bug.php" class="btn btn-warning">🐞 Bug</a>
            <a href="<?= e(APP_URL) ?>modules/support/request_access.php" class="btn btn-outline">🔐 Accès</a>
            <a href="<?= e(APP_URL) ?>logout.php" class="btn btn-danger">🚪 Déconnexion</a>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.querySelector('.header-notification-box');
    const dropdown = document.querySelector('.header-notification-dropdown');

    if (box && dropdown) {
        box.addEventListener('mouseenter', function () {
            dropdown.style.display = 'block';
        });

        box.addEventListener('mouseleave', function () {
            dropdown.style.display = 'none';
        });
    }
});
</script>