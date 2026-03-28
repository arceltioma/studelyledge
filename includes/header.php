<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitleValue = $pageTitle ?? APP_NAME;
$pageSubtitleValue = $pageSubtitle ?? 'Pilotage financier, audit et gouvernance';
$username = $_SESSION['username'] ?? 'Utilisateur';
?>

<header class="studely-header">

    <div class="header-left">
        <div class="header-titles">
            <div class="header-overline">Studely Ledger</div>
            <h1 class="header-title"><?= e($pageTitleValue) ?></h1>
            <div class="header-subtitle"><?= e($pageSubtitleValue) ?></div>
        </div>
    </div>

    <div class="header-right">

        <div class="header-user">
            <span class="header-user-label">Connecté</span>
            <strong><?= e($username) ?></strong>
        </div>

        <div class="header-actions">
            <a href="<?= e(APP_URL) ?>modules/support/request_access.php" class="btn btn-secondary">Accès</a>
            <a href="<?= e(APP_URL) ?>modules/support/report_bug.php" class="btn btn-danger">Bug</a>
            <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="btn btn-outline">Question</a>
        </div>

    </div>

</header>