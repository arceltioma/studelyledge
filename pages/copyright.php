<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="layout">
    <div class="main">
        <?php render_app_header_bar('Copyright', 'Protection du produit, du contenu et des éléments graphiques.'); ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Application</h3>
                <p class="muted">
                    StudelyLedger, son interface, sa logique métier, ses structures fonctionnelles et ses composants visuels
                    sont protégés par les règles applicables en matière de propriété intellectuelle.
                </p>
            </div>

            <div class="card">
                <h3>Éléments concernés</h3>
                <div class="suggestion-list">
                    <span class="suggestion-chip">Nom du produit</span>
                    <span class="suggestion-chip">Logo</span>
                    <span class="suggestion-chip">Design UI</span>
                    <span class="suggestion-chip">Base fonctionnelle</span>
                    <span class="suggestion-chip">Code source</span>
                    <span class="suggestion-chip">Documents générés</span>
                </div>
            </div>
        </div>

        <div class="table-card">
            <h3 class="section-title">Cadre général</h3>
            <table>
                <tbody>
                    <tr>
                        <th>Produit</th>
                        <td><?= e(APP_NAME) ?></td>
                    </tr>
                    <tr>
                        <th>Nature</th>
                        <td>Plateforme logicielle de pilotage financier, d’import, d’audit et de relevés</td>
                    </tr>
                    <tr>
                        <th>Protection</th>
                        <td>Les contenus, graphismes, structures, textes, documents et logiques fonctionnelles sont protégés.</td>
                    </tr>
                    <tr>
                        <th>Usage non autorisé</th>
                        <td>Toute reproduction, diffusion, extraction, adaptation ou réutilisation non autorisée est interdite.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="warning">
            En clair : on peut admirer l’ouvrage, l’utiliser dans son cadre légitime, mais pas le piller comme un buffet sans surveillant.
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>