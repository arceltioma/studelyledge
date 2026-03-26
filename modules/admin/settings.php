<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar('Paramètres', 'Page de paramètres prête pour accueillir les réglages fonctionnels du projet.'); ?>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>