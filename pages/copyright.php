<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';

$pageTitle = 'Copyright';
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
                    radial-gradient(circle at top right, rgba(168, 85, 247, 0.18), transparent 30%),
                    radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.14), transparent 28%),
                    linear-gradient(135deg, #111827 0%, #1f2937 45%, #312e81 100%);
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
                max-width: 920px;
                font-size: 15px;
                line-height: 1.7;
                color: rgba(255,255,255,0.92);
            }

            .sl-legal-grid {
                display: grid;
                grid-template-columns: 1.15fr 0.85fr;
                gap: 20px;
                margin-bottom: 24px;
            }

            @media (max-width: 980px) {
                .sl-legal-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sl-legal-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }

            .sl-legal-card__head {
                padding: 18px 22px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            }

            .sl-legal-card__head h3 {
                margin: 0 0 6px;
                font-size: 18px;
                font-weight: 800;
                color: #0f172a;
            }

            .sl-legal-card__head p {
                margin: 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.55;
            }

            .sl-legal-card__body {
                padding: 22px;
            }

            .sl-legal-kpis {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-bottom: 22px;
            }

            @media (max-width: 720px) {
                .sl-legal-kpis {
                    grid-template-columns: 1fr;
                }
            }

            .sl-legal-kpi {
                border-radius: 18px;
                padding: 16px;
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
                border: 1px solid rgba(148, 163, 184, 0.16);
            }

            .sl-legal-kpi__label {
                display: block;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                font-weight: 700;
                margin-bottom: 8px;
            }

            .sl-legal-kpi__value {
                display: block;
                font-size: 22px;
                line-height: 1;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 8px;
            }

            .sl-legal-kpi__meta {
                display: block;
                font-size: 12px;
                color: #64748b;
                line-height: 1.5;
            }

            .sl-legal-section {
                padding: 16px 18px;
                border-radius: 18px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.14);
            }

            .sl-legal-section + .sl-legal-section {
                margin-top: 14px;
            }

            .sl-legal-section h4 {
                margin: 0 0 10px;
                color: #0f172a;
                font-size: 15px;
                font-weight: 800;
            }

            .sl-legal-section p,
            .sl-legal-section li {
                color: #475569;
                line-height: 1.7;
                font-size: 14px;
            }

            .sl-legal-section ul {
                margin: 0;
                padding-left: 18px;
            }

            .sl-legal-status-list {
                display: grid;
                gap: 12px;
            }

            .sl-legal-status-item {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 16px;
                border: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .sl-legal-dot {
                width: 12px;
                height: 12px;
                border-radius: 999px;
                margin-top: 4px;
                flex: 0 0 12px;
            }

            .sl-legal-dot--violet {
                background: #8b5cf6;
                box-shadow: 0 0 0 5px rgba(139, 92, 246, 0.12);
            }

            .sl-legal-dot--blue {
                background: #3b82f6;
                box-shadow: 0 0 0 5px rgba(59, 130, 246, 0.14);
            }

            .sl-legal-dot--emerald {
                background: #10b981;
                box-shadow: 0 0 0 5px rgba(16, 185, 129, 0.12);
            }

            .sl-legal-status-item strong {
                display: block;
                color: #0f172a;
                margin-bottom: 4px;
                font-size: 14px;
            }

            .sl-legal-status-item span {
                display: block;
                color: #64748b;
                font-size: 13px;
                line-height: 1.55;
            }

            .sl-legal-note {
                margin-top: 18px;
                padding: 16px 18px;
                border-radius: 18px;
                background: rgba(139, 92, 246, 0.06);
                border: 1px solid rgba(139, 92, 246, 0.14);
                color: #6d28d9;
                line-height: 1.65;
                font-size: 14px;
            }

            .sl-legal-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 22px;
            }
        </style>

        <section class="sl-public-hero">
            <div class="sl-public-hero__eyebrow">Protection juridique</div>
            <h1>Copyright & propriété intellectuelle</h1>
            <p>
                Les contenus, la structure applicative, les bases de données, les éléments visuels,
                les textes et les composants de StudelyLedger sont protégés. Cette page présente
                le cadre général applicable à leur usage, leur diffusion et leur réutilisation.
            </p>
        </section>

        <div class="sl-legal-grid">
            <section class="sl-legal-card">
                <div class="sl-legal-card__head">
                    <h3>Cadre général</h3>
                    <p>
                        Une présentation claire et structurée des droits attachés au contenu
                        et à l’architecture fonctionnelle de la plateforme.
                    </p>
                </div>

                <div class="sl-legal-card__body">
                    <div class="sl-legal-kpis">
                        <div class="sl-legal-kpi">
                            <span class="sl-legal-kpi__label">Contenu</span>
                            <span class="sl-legal-kpi__value">Protégé</span>
                            <span class="sl-legal-kpi__meta">Textes, visuels, structure et composants internes.</span>
                        </div>

                        <div class="sl-legal-kpi">
                            <span class="sl-legal-kpi__label">Réutilisation</span>
                            <span class="sl-legal-kpi__value">Encadrée</span>
                            <span class="sl-legal-kpi__meta">Toute reprise nécessite une autorisation préalable.</span>
                        </div>

                        <div class="sl-legal-kpi">
                            <span class="sl-legal-kpi__label">Année</span>
                            <span class="sl-legal-kpi__value"><?= e((string)date('Y')) ?></span>
                            <span class="sl-legal-kpi__meta">StudelyLedger — tous droits réservés.</span>
                        </div>
                    </div>

                    <div class="sl-legal-section">
                        <h4>Éléments concernés</h4>
                        <p>
                            Sont notamment concernés le design de l’interface, la structure des pages,
                            les textes métier, les bases de données, les logiques d’organisation,
                            les composants graphiques, les contenus rédactionnels et la documentation associée.
                        </p>
                    </div>

                    <div class="sl-legal-section">
                        <h4>Usages interdits sans autorisation</h4>
                        <ul>
                            <li>reproduction totale ou partielle du contenu ;</li>
                            <li>adaptation, diffusion ou republication sans validation préalable ;</li>
                            <li>réutilisation commerciale ou technique des éléments protégés ;</li>
                            <li>extraction ou duplication de la structure applicative ou des données.</li>
                        </ul>
                    </div>

                    <div class="sl-legal-section">
                        <h4>Principe général</h4>
                        <p>
                            Toute utilisation non explicitement autorisée doit être considérée comme interdite.
                            En cas de doute, il convient de passer par un canal de contact formel avant toute reprise,
                            redistribution, intégration ou modification d’un élément provenant de StudelyLedger.
                        </p>
                    </div>
                </div>
            </section>

            <section class="sl-legal-card">
                <div class="sl-legal-card__head">
                    <h3>Résumé juridique</h3>
                    <p>
                        Un rappel rapide des grands principes applicables à la plateforme.
                    </p>
                </div>

                <div class="sl-legal-card__body">
                    <div class="sl-legal-status-list">
                        <div class="sl-legal-status-item">
                            <span class="sl-legal-dot sl-legal-dot--violet"></span>
                            <div>
                                <strong>Structure et design protégés</strong>
                                <span>Les choix de conception, l’apparence générale et l’organisation des contenus relèvent d’un cadre protégé.</span>
                            </div>
                        </div>

                        <div class="sl-legal-status-item">
                            <span class="sl-legal-dot sl-legal-dot--blue"></span>
                            <div>
                                <strong>Données et contenus encadrés</strong>
                                <span>Les données, textes, éléments métiers et composants rédactionnels ne peuvent pas être réutilisés librement.</span>
                            </div>
                        </div>

                        <div class="sl-legal-status-item">
                            <span class="sl-legal-dot sl-legal-dot--emerald"></span>
                            <div>
                                <strong>Autorisation préalable requise</strong>
                                <span>Toute diffusion, adaptation ou reproduction doit faire l’objet d’un accord explicite avant usage.</span>
                            </div>
                        </div>
                    </div>

                    <div class="sl-legal-note">
                        © <?= date('Y') ?> StudelyLedger. Tous droits réservés.
                        Toute reproduction, extraction, diffusion ou adaptation non autorisée est interdite.
                    </div>

                    <div class="sl-legal-actions">
                        <a href="<?= e(APP_URL) ?>login.php" class="btn btn-outline">Retour à la connexion</a>
                        <a href="<?= e(APP_URL) ?>pages/contact.php" class="btn btn-primary">Contacter</a>
                        <a href="<?= e(APP_URL) ?>pages/sitemap.php" class="btn btn-secondary">Sitemap</a>
                    </div>
                </div>
            </section>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>