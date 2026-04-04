<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'statements_view_page');
} else {
    enforcePagePermission($pdo, 'statements_export');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Hub des exports';
$pageSubtitle = 'Prévisualisation, validation et génération des relevés et fiches clients';

$previewClientProfiles = (string)($_SESSION['client_profiles_preview_pdf'] ?? '');
$previewAccountStatements = (string)($_SESSION['account_statements_preview_pdf'] ?? '');

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="success"><?= e((string)$_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="error"><?= e((string)$_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card clickable" onclick="location.href='<?= e(APP_URL) ?>modules/statements/account_statements.php'">
                <h2>Relevés de comptes</h2>
                <p class="muted">
                    Exports centrés sur les flux financiers : période, mouvements, débits, crédits, soldes, historique.
                </p>

                <div class="detail-grid" style="margin-top:18px;">
                    <div class="detail-row">
                        <div class="detail-label">Prévisualisation</div>
                        <div class="detail-value"><?= $previewAccountStatements !== '' ? 'Disponible' : 'À générer' ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Mode</div>
                        <div class="detail-value">Unitaire et masse</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Rendu</div>
                        <div class="detail-value">Iframe scrollable + PDF final</div>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <span class="btn btn-primary">Ouvrir le module</span>
                </div>
            </div>

            <div class="card clickable" onclick="location.href='<?= e(APP_URL) ?>modules/statements/client_profiles.php'">
                <h2>Fiches clients</h2>
                <p class="muted">
                    Exports centrés sur l’identité et le profil client : coordonnées, pays, rattachement financier, passeport, comptes et données utiles.
                </p>

                <div class="detail-grid" style="margin-top:18px;">
                    <div class="detail-row">
                        <div class="detail-label">Prévisualisation</div>
                        <div class="detail-value"><?= $previewClientProfiles !== '' ? 'Disponible' : 'À générer' ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Mode</div>
                        <div class="detail-value">Unitaire et masse</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Rendu</div>
                        <div class="detail-value">Iframe scrollable + PDF final</div>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <span class="btn btn-primary">Ouvrir le module</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Parcours harmonisé</h3>
                <div class="dashboard-note">
                    1. Tu paramètres l’export dans le bloc de gauche. <br>
                    2. Tu cliques sur <strong>Prévisualiser l’opération demandée</strong>. <br>
                    3. Le rendu PDF s’affiche dans le bloc de droite. <br>
                    4. Tu confirmes avec <strong>Générer le/les PDF</strong> ou tu annules.
                </div>
            </div>

            <div class="card">
                <h3>Comportement de masse</h3>
                <div class="dashboard-note">
                    La prévisualisation de masse produit un PDF unique de contrôle, scrollable dans l’iframe.  
                    La génération finale conserve l’export par client, avec PDF unitaire ou ZIP si plusieurs documents sont produits.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>