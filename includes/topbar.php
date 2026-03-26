<header class="topbar">
    <div class="topbar-left">
        <strong><?= e(APP_NAME) ?></strong>
    </div>

    <div class="topbar-right">
        <span>Bonjour <?= e($_SESSION['username'] ?? 'Utilisateur') ?></span>
    </div>
</header>