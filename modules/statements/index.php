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

$pageTitle = 'Hub Export';
$pageSubtitle = 'Prévisualisation, validation et génération des documents';

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
            <div class="sl-card sl-stable-block" style="position:relative; overflow:hidden;">
                <div style="position:absolute; inset:0; background:linear-gradient(135deg, rgba(29,78,216,0.07), rgba(15,118,110,0.03)); pointer-events:none;"></div>
                <div style="position:relative; z-index:1;">
                    <div class="sl-card-head">
                        <div>
                            <h3>Relevés de comptes</h3>
                            <p class="sl-card-head-subtitle">Exports centrés sur les flux financiers, soldes, périodes et historiques.</p>
                        </div>
                        <span class="sl-pill sl-pill-soft">PDF</span>
                    </div>

                    <div class="sl-data-list" style="margin-top:12px;">
                        <div class="sl-data-list__row">
                            <span>Prévisualisation</span>
                            <strong><?= $previewAccountStatements !== '' ? 'Disponible' : 'À générer' ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Mode</span>
                            <strong>Unitaire et masse</strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Rendu</span>
                            <strong>Iframe scrollable + export final</strong>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:18px;">
                        <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/statements/account_statements.php">Ouvrir le module</a>
                    </div>
                </div>
            </div>

            <div class="sl-card sl-stable-block" style="position:relative; overflow:hidden;">
                <div style="position:absolute; inset:0; background:linear-gradient(135deg, rgba(15,118,110,0.08), rgba(29,78,216,0.03)); pointer-events:none;"></div>
                <div style="position:relative; z-index:1;">
                    <div class="sl-card-head">
                        <div>
                            <h3>Fiches clients</h3>
                            <p class="sl-card-head-subtitle">Exports centrés sur l’identité, le rattachement financier, l’adresse et les données passeport.</p>
                        </div>
                        <span class="sl-pill sl-pill-soft">PDF</span>
                    </div>

                    <div class="sl-data-list" style="margin-top:12px;">
                        <div class="sl-data-list__row">
                            <span>Prévisualisation</span>
                            <strong><?= $previewClientProfiles !== '' ? 'Disponible' : 'À générer' ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Mode</span>
                            <strong>Unitaire et masse</strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Rendu</span>
                            <strong>Iframe scrollable + export final</strong>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:18px;">
                        <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/statements/client_profiles.php">Ouvrir le module</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="sl-card sl-stable-block">
                <div class="sl-card-head">
                    <div>
                        <h3>Parcours harmonisé</h3>
                        <p class="sl-card-head-subtitle">Même logique sur tout le Hub Export</p>
                    </div>
                </div>

                <div class="dashboard-note">
                    1. Paramétrer l’export dans le bloc de gauche.<br>
                    2. Cliquer sur <strong>Prévisualiser l’opération demandée</strong>.<br>
                    3. Contrôler le rendu dans le bloc de droite.<br>
                    4. Cliquer sur <strong>Générer le/les PDF</strong> ou annuler.
                </div>
            </div>

            <div class="sl-card sl-stable-block">
                <div class="sl-card-head">
                    <div>
                        <h3>Contrôle de masse</h3>
                        <p class="sl-card-head-subtitle">Prévisualisation multi-pages avant validation</p>
                    </div>
                </div>

                <div class="dashboard-note">
                    En mode masse, l’aperçu produit un PDF unique de contrôle, scrollable dans l’iframe, puis la génération finale conserve l’export individuel ou ZIP selon le volume.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
