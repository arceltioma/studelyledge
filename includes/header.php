<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$title = $pageTitle ?? APP_NAME;
$subtitle = $pageSubtitle ?? 'Pilotage financier, audit et gouvernance';
$currentUser = $_SESSION['username'] ?? 'Utilisateur';
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
        <div class="header-user">
            <span>Connecté en tant que</span>
            <strong><?= e($currentUser) ?></strong>
        </div>

        <div class="header-actions">
            <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="btn btn-secondary">❓ Question</a>
            <a href="<?= e(APP_URL) ?>modules/support/report_bug.php" class="btn btn-warning">🐞 Bug</a>
            <a href="<?= e(APP_URL) ?>modules/support/request_access.php" class="btn btn-outline">🔐 Accès</a>
            <a href="<?= e(APP_URL) ?>logout.php" class="btn btn-danger">🚪 Déconnexion</a>
        </div>
    </div>
</header>