<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Politique de cookies';
require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">
        <?php require_once __DIR__ . '/../includes/header.php'; ?>
        <?php render_app_header_bar('Politique de cookies', 'Informations sur l’usage des cookies essentiels et de confort sur StudelyLedger.'); ?>

        <div class="card">
            <p>
                StudelyLedger utilise des cookies techniques nécessaires au bon fonctionnement de la session utilisateur,
                ainsi que des cookies de confort liés à l’expérience de navigation lorsque tu les acceptes.
            </p>
            <p>
                Les cookies peuvent servir à mémoriser ton consentement, à maintenir ta session,
                et à fluidifier l’utilisation de l’interface.
            </p>
            <p>
                Tu peux accepter ou refuser les cookies de confort à tout moment depuis la bannière dédiée.
            </p>

            <div class="btn-group" style="margin-top:20px;">
                <a href="<?= e(APP_URL) ?>login.php" class="btn btn-outline">Retour</a>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>