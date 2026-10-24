<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Politique de cookies';
require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">
        <?php require_once __DIR__ . '/../includes/header.php'; ?>

        <style>
            .sl-public-hero {
                position: relative;
                overflow: hidden;
                border-radius: 24px;
                padding: 28px;
                margin-bottom: 24px;
                background:
                    radial-gradient(circle at top right, rgba(59, 130, 246, 0.18), transparent 30%),
                    radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.14), transparent 28%),
                    linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #0f766e 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            }

            .sl-public-hero__eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 12px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.16);
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .04em;
                text-transform: uppercase;
                margin-bottom: 14px;
            }

            .sl-public-hero h1 {
                margin: 0 0 10px;
                font-size: clamp(28px, 4vw, 40px);
                line-height: 1.1;
                font-weight: 800;
            }

            .sl-public-hero p {
                margin: 0;
                max-width: 900px;
                font-size: 15px;
                line-height: 1.7;
                color: rgba(255,255,255,0.92);
            }

            .sl-cookie-grid {
                display: grid;
                grid-template-columns: 1.2fr 0.8fr;
                gap: 20px;
                margin-bottom: 24px;
            }

            @media (max-width: 980px) {
                .sl-cookie-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sl-cookie-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }

            .sl-cookie-card__head {
                padding: 18px 22px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            }

            .sl-cookie-card__head h3 {
                margin: 0 0 6px;
                font-size: 18px;
                font-weight: 800;
                color: #0f172a;
            }

            .sl-cookie-card__head p {
                margin: 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.55;
            }

            .sl-cookie-card__body {
                padding: 22px;
            }

            .sl-cookie-kpis {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-bottom: 22px;
            }

            @media (max-width: 720px) {
                .sl-cookie-kpis {
                    grid-template-columns: 1fr;
                }
            }

            .sl-cookie-kpi {
                border-radius: 18px;
                padding: 16px;
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
                border: 1px solid rgba(148, 163, 184, 0.16);
            }

            .sl-cookie-kpi__label {
                display: block;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                font-weight: 700;
                margin-bottom: 8px;
            }

            .sl-cookie-kpi__value {
                display: block;
                font-size: 22px;
                line-height: 1;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 8px;
            }

            .sl-cookie-kpi__meta {
                display: block;
                font-size: 12px;
                color: #64748b;
                line-height: 1.5;
            }

            .sl-cookie-section {
                padding: 16px 18px;
                border-radius: 18px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.14);
            }

            .sl-cookie-section + .sl-cookie-section {
                margin-top: 14px;
            }

            .sl-cookie-section h4 {
                margin: 0 0 10px;
                color: #0f172a;
                font-size: 15px;
                font-weight: 800;
            }

            .sl-cookie-section p,
            .sl-cookie-section li {
                color: #475569;
                line-height: 1.7;
                font-size: 14px;
            }

            .sl-cookie-section ul {
                margin: 0;
                padding-left: 18px;
            }

            .sl-cookie-status-list {
                display: grid;
                gap: 12px;
            }

            .sl-cookie-status-item {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 16px;
                border: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .sl-cookie-dot {
                width: 12px;
                height: 12px;
                border-radius: 999px;
                margin-top: 4px;
                flex: 0 0 12px;
            }

            .sl-cookie-dot--green {
                background: #10b981;
                box-shadow: 0 0 0 5px rgba(16, 185, 129, 0.12);
            }

            .sl-cookie-dot--amber {
                background: #f59e0b;
                box-shadow: 0 0 0 5px rgba(245, 158, 11, 0.14);
            }

            .sl-cookie-dot--blue {
                background: #3b82f6;
                box-shadow: 0 0 0 5px rgba(59, 130, 246, 0.14);
            }

            .sl-cookie-status-item strong {
                display: block;
                color: #0f172a;
                margin-bottom: 4px;
                font-size: 14px;
            }

            .sl-cookie-status-item span {
                display: block;
                color: #64748b;
                font-size: 13px;
                line-height: 1.55;
            }

            .sl-cookie-banner-note {
                margin-top: 18px;
                padding: 16px 18px;
                border-radius: 18px;
                background: rgba(59, 130, 246, 0.06);
                border: 1px solid rgba(59, 130, 246, 0.14);
                color: #1d4ed8;
                line-height: 1.65;
                font-size: 14px;
            }

            .sl-cookie-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 22px;
            }
        </style>

        <section class="sl-public-hero">
            <div class="sl-public-hero__eyebrow">Confidentialité & conformité</div>
            <h1>Politique de cookies</h1>
            <p>
                StudelyLedger utilise des cookies strictement nécessaires au bon fonctionnement de la plateforme,
                ainsi que des cookies de confort destinés à améliorer l’expérience utilisateur lorsque leur usage
                est accepté. Cette page te présente de manière claire, lisible et transparente la logique appliquée.
            </p>
        </section>

        <div class="sl-cookie-grid">
            <section class="sl-cookie-card">
                <div class="sl-cookie-card__head">
                    <h3>Vue d’ensemble</h3>
                    <p>
                        Une lecture synthétique de l’usage des cookies sur la plateforme,
                        dans un format cohérent avec l’interface Studely Ledger.
                    </p>
                </div>

                <div class="sl-cookie-card__body">
                    <div class="sl-cookie-kpis">
                        <div class="sl-cookie-kpi">
                            <span class="sl-cookie-kpi__label">Cookies essentiels</span>
                            <span class="sl-cookie-kpi__value">Actifs</span>
                            <span class="sl-cookie-kpi__meta">Nécessaires à la connexion, à la session et à la sécurité.</span>
                        </div>

                        <div class="sl-cookie-kpi">
                            <span class="sl-cookie-kpi__label">Cookies de confort</span>
                            <span class="sl-cookie-kpi__value">Optionnels</span>
                            <span class="sl-cookie-kpi__meta">Activés uniquement selon ton consentement.</span>
                        </div>

                        <div class="sl-cookie-kpi">
                            <span class="sl-cookie-kpi__label">Gestion du consentement</span>
                            <span class="sl-cookie-kpi__value">Dynamique</span>
                            <span class="sl-cookie-kpi__meta">Modifiable à tout moment selon le dispositif prévu.</span>
                        </div>
                    </div>

                    <div class="sl-cookie-section">
                        <h4>Pourquoi des cookies sont utilisés</h4>
                        <p>
                            Les cookies permettent à StudelyLedger de maintenir une session utilisateur fiable,
                            de protéger certains accès, de mémoriser des préférences de navigation et de fluidifier
                            l’usage général de l’interface.
                        </p>
                    </div>

                    <div class="sl-cookie-section">
                        <h4>Ce qui est strictement nécessaire</h4>
                        <ul>
                            <li>maintien de la session authentifiée ;</li>
                            <li>sécurisation de certaines actions sensibles ;</li>
                            <li>mémorisation technique indispensable au bon fonctionnement de l’application.</li>
                        </ul>
                    </div>

                    <div class="sl-cookie-section">
                        <h4>Ce qui reste facultatif</h4>
                        <p>
                            Les cookies de confort servent principalement à améliorer l’expérience utilisateur.
                            Ils ne sont pas indispensables au fonctionnement fondamental de la plateforme
                            et peuvent être acceptés ou refusés selon les mécanismes prévus dans l’interface.
                        </p>
                    </div>
                </div>
            </section>

            <section class="sl-cookie-card">
                <div class="sl-cookie-card__head">
                    <h3>Statut & gestion</h3>
                    <p>
                        Un résumé rapide des catégories de cookies et de leur rôle dans la plateforme.
                    </p>
                </div>

                <div class="sl-cookie-card__body">
                    <div class="sl-cookie-status-list">
                        <div class="sl-cookie-status-item">
                            <span class="sl-cookie-dot sl-cookie-dot--green"></span>
                            <div>
                                <strong>Cookies techniques</strong>
                                <span>Indispensables à l’authentification, à la stabilité de session et à la sécurité applicative.</span>
                            </div>
                        </div>

                        <div class="sl-cookie-status-item">
                            <span class="sl-cookie-dot sl-cookie-dot--amber"></span>
                            <div>
                                <strong>Cookies de confort</strong>
                                <span>Destinés à améliorer l’expérience de navigation lorsque leur usage est accepté.</span>
                            </div>
                        </div>

                        <div class="sl-cookie-status-item">
                            <span class="sl-cookie-dot sl-cookie-dot--blue"></span>
                            <div>
                                <strong>Consentement utilisateur</strong>
                                <span>Le choix d’acceptation ou de refus peut être pris en compte et mémorisé via le mécanisme prévu par la plateforme.</span>
                            </div>
                        </div>
                    </div>

                    <div class="sl-cookie-banner-note">
                        Tu peux accepter ou refuser les cookies non essentiels lorsque l’interface de consentement est proposée,
                        puis ajuster ce choix ultérieurement selon les paramètres ou mécanismes disponibles sur StudelyLedger.
                    </div>

                    <div class="sl-cookie-actions">
                        <a href="<?= e(APP_URL) ?>login.php" class="btn btn-outline">Retour à la connexion</a>
                        <a href="<?= e(APP_URL) ?>pages/contact.php" class="btn btn-primary">Contacter le support</a>
                        <a href="<?= e(APP_URL) ?>pages/copyright.php" class="btn btn-secondary">Copyright</a>
                    </div>
                </div>
            </section>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>