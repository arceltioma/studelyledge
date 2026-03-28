<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Contact';
require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">
        <?php require_once __DIR__ . '/../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Coordonnées</h3>
                <p><strong>Email :</strong> support@studelyledger.com</p>
                <p><strong>Téléphone :</strong> +33 6 73 91 06 69</p>
                <p><strong>Disponibilité :</strong> du lundi au vendredi, 9h–18h</p>
            </div>

            <div class="card">
                <h3>Assistance</h3>
                <p>
                    Pour les demandes fonctionnelles, techniques ou liées aux imports / exports,
                    contacte l’équipe support via les coordonnées ci-contre.
                </p>
                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>login.php" class="btn btn-primary">Retour à la connexion</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>