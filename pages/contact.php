<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <div class="main">
        <?php render_app_header_bar('Contacts', 'Trouver le bon canal sans tirer l’alarme incendie.'); ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Support applicatif</h3>
                <p><strong>Email :</strong> contact@studelyledger.com</p>
                <p><strong>Téléphone :</strong> +33 6 73 91 06 69</p>
                <p class="muted">Pour les incidents, bugs, questions d’utilisation et accompagnement fonctionnel.</p>

                <div class="btn-group">
                    <a href="<?= APP_URL ?>modules/support/report_bug.php" class="btn btn-danger">Signaler un bug</a>
                    <a href="<?= APP_URL ?>modules/support/ask_question.php" class="btn btn-outline">Poser une question</a>
                </div>
            </div>

            <div class="card">
                <h3>Gestion des accès</h3>
                <p><strong>Canal recommandé :</strong> demande d’accès intégrée</p>
                <p class="muted">
                    Pour toute ouverture de droit, évolution de périmètre ou demande de rôle supplémentaire.
                </p>

                <div class="btn-group">
                    <a href="<?= APP_URL ?>modules/support/request_access.php" class="btn btn-secondary">Demander un accès</a>
                </div>
            </div>
        </div>

        <div class="table-card">
            <h3 class="section-title">Raccourcis utiles</h3>
            <table>
                <tbody>
                    <tr>
                        <th>Demande d’accès</th>
                        <td><a href="<?= APP_URL ?>modules/support/request_access.php">Ouvrir le formulaire</a></td>
                    </tr>
                    <tr>
                        <th>Bug</th>
                        <td><a href="<?= APP_URL ?>modules/support/report_bug.php">Déclarer un incident</a></td>
                    </tr>
                    <tr>
                        <th>Question</th>
                        <td><a href="<?= APP_URL ?>modules/support/ask_question.php">Envoyer une question</a></td>
                    </tr>
                    <tr>
                        <th>Sitemap</th>
                        <td><a href="<?= APP_URL ?>pages/sitemap.php">Voir la carte du site</a></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>