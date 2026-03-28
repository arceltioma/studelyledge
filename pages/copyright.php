<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Copyright';
require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">
        <?php require_once __DIR__ . '/../includes/header.php'; ?>

        <div class="card">
            <p>
                Le contenu, la structure, le design, les textes, les bases de données
                et les éléments graphiques de StudelyLedger sont protégés par les règles applicables
                en matière de propriété intellectuelle.
            </p>
            <p>
                Toute reproduction, adaptation, diffusion ou réutilisation, totale ou partielle,
                sans autorisation préalable, est interdite.
            </p>
            <p>
                © <?= date('Y') ?> StudelyLedger. Tous droits réservés.
            </p>

            <div class="btn-group" style="margin-top:20px;">
                <a href="<?= e(APP_URL) ?>login.php" class="btn btn-outline">Retour</a>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>