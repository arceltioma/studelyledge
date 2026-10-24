<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
$pdo = getPDO();

$pageTitle = 'Sitemap';
$pageSubtitle = 'Plan des principales pages de la plateforme.';
require_once __DIR__ . '/../includes/document_start.php';
?>

<div class="public-page-shell">
    <div class="public-page-container">
        <?php require_once __DIR__ . '/../includes/header.php'; ?>

        <style>
            .sl-sitemap-hero {
                position: relative;
                overflow: hidden;
                border-radius: 24px;
                padding: 28px;
                margin-bottom: 24px;
                background:
                    radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 30%),
                    radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.14), transparent 28%),
                    linear-gradient(135deg, #0f172a 0%, #1e3a8a 45%, #0f766e 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            }

            .sl-sitemap-hero__eyebrow {
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

            .sl-sitemap-hero h1 {
                margin: 0 0 10px;
                font-size: clamp(28px, 4vw, 40px);
                line-height: 1.1;
                font-weight: 800;
            }

            .sl-sitemap-hero p {
                margin: 0;
                max-width: 920px;
                font-size: 15px;
                line-height: 1.7;
                color: rgba(255,255,255,0.92);
            }

            .sl-sitemap-kpis {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
                margin-bottom: 24px;
            }

            @media (max-width: 980px) {
                .sl-sitemap-kpis {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .sl-sitemap-kpis {
                    grid-template-columns: 1fr;
                }
            }

            .sl-sitemap-kpi {
                border-radius: 18px;
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 32px rgba(15, 23, 42, 0.05);
            }

            .sl-sitemap-kpi__label {
                display: block;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                font-weight: 700;
                margin-bottom: 8px;
            }

            .sl-sitemap-kpi__value {
                display: block;
                font-size: 24px;
                line-height: 1;
                font-weight: 800;
                color: #0f172a;
                margin-bottom: 8px;
            }

            .sl-sitemap-kpi__meta {
                display: block;
                font-size: 12px;
                color: #64748b;
                line-height: 1.5;
            }

            .sl-sitemap-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 20px;
                margin-bottom: 24px;
            }

            @media (max-width: 980px) {
                .sl-sitemap-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sl-sitemap-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }

            .sl-sitemap-card__head {
                padding: 18px 22px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            }

            .sl-sitemap-card__head h3 {
                margin: 0 0 6px;
                font-size: 18px;
                font-weight: 800;
                color: #0f172a;
            }

            .sl-sitemap-card__head p {
                margin: 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.55;
            }

            .sl-sitemap-card__body {
                padding: 22px;
            }

            .sl-sitemap-list {
                list-style: none;
                padding: 0;
                margin: 0;
                display: grid;
                gap: 10px;
            }

            .sl-sitemap-list li {
                margin: 0;
            }

            .sl-sitemap-link {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 16px;
                text-decoration: none;
                color: #0f172a;
                border: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                transition: all .18s ease;
            }

            .sl-sitemap-link:hover {
                transform: translateY(-1px);
                border-color: rgba(59, 130, 246, 0.28);
                box-shadow: 0 10px 20px rgba(59, 130, 246, 0.08);
            }

            .sl-sitemap-link__left {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
            }

            .sl-sitemap-link__icon {
                width: 38px;
                height: 38px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(59, 130, 246, 0.10);
                font-size: 18px;
                flex: 0 0 38px;
            }

            .sl-sitemap-link__text {
                min-width: 0;
            }

            .sl-sitemap-link__title {
                display: block;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.35;
            }

            .sl-sitemap-link__meta {
                display: block;
                color: #64748b;
                font-size: 12px;
                margin-top: 2px;
            }

            .sl-sitemap-link__arrow {
                color: #94a3b8;
                font-weight: 700;
                flex: 0 0 auto;
            }

            .sl-sitemap-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-top: 10px;
            }
        </style>

        <section class="sl-sitemap-hero">
            <div class="sl-sitemap-hero__eyebrow">Navigation globale</div>
            <h1>Sitemap</h1>
            <p>
                Cette page centralise les principaux accès de StudelyLedger pour offrir
                une lecture claire de la plateforme, de ses modules métier et de ses espaces d’administration.
            </p>
        </section>

        <section class="sl-sitemap-kpis">
            <div class="sl-sitemap-kpi">
                <span class="sl-sitemap-kpi__label">Modules métier</span>
                <span class="sl-sitemap-kpi__value">6</span>
                <span class="sl-sitemap-kpi__meta">Accès opérationnels principaux</span>
            </div>

            <div class="sl-sitemap-kpi">
                <span class="sl-sitemap-kpi__label">Administration</span>
                <span class="sl-sitemap-kpi__value">7</span>
                <span class="sl-sitemap-kpi__meta">Pilotage technique et fonctionnel</span>
            </div>

            <div class="sl-sitemap-kpi">
                <span class="sl-sitemap-kpi__label">Pages publiques</span>
                <span class="sl-sitemap-kpi__value">4</span>
                <span class="sl-sitemap-kpi__meta">Contact, légales et navigation</span>
            </div>

            <div class="sl-sitemap-kpi">
                <span class="sl-sitemap-kpi__label">Point d’entrée</span>
                <span class="sl-sitemap-kpi__value">Login</span>
                <span class="sl-sitemap-kpi__meta">Connexion sécurisée à la plateforme</span>
            </div>
        </section>

        <div class="sl-sitemap-grid">
            <section class="sl-sitemap-card">
                <div class="sl-sitemap-card__head">
                    <h3>Modules métier</h3>
                    <p>Les accès principaux liés à l’exploitation quotidienne de la plateforme.</p>
                </div>

                <div class="sl-sitemap-card__body">
                    <ul class="sl-sitemap-list">
                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">📊</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Dashboard</span>
                                        <span class="sl-sitemap-link__meta">Vue d’ensemble de la plateforme</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">👤</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Clients</span>
                                        <span class="sl-sitemap-link__meta">Gestion des fiches clients et comptes 411</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/operations/operations_list.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">💰</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Opérations</span>
                                        <span class="sl-sitemap-link__meta">Suivi et gestion des flux comptables</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/treasury/index.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">🏛️</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Comptes internes</span>
                                        <span class="sl-sitemap-link__meta">Pilotage des comptes 512</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/imports/import_preview.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">📥</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Imports</span>
                                        <span class="sl-sitemap-link__meta">Prévisualisation, mapping et validation</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/statements/index.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">📤</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Exports</span>
                                        <span class="sl-sitemap-link__meta">Relevés, états et documents générés</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="sl-sitemap-card">
                <div class="sl-sitemap-card__head">
                    <h3>Administration</h3>
                    <p>Les espaces liés au paramétrage, à la sécurité et à la gouvernance applicative.</p>
                </div>

                <div class="sl-sitemap-card__body">
                    <ul class="sl-sitemap-list">
                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">🛠️</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Dashboard Admin Technique</span>
                                        <span class="sl-sitemap-link__meta">Vue de pilotage technique</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/admin_functional/dashboard.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">⚙️</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Dashboard Admin Fonctionnelle</span>
                                        <span class="sl-sitemap-link__meta">Pilotage des référentiels métier</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/admin/users.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">👥</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Utilisateurs</span>
                                        <span class="sl-sitemap-link__meta">Gestion des comptes internes</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/admin/roles.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">🔐</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Rôles</span>
                                        <span class="sl-sitemap-link__meta">Structure des rôles et accès</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/admin/access_matrix.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">🧮</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Matrice d’accès</span>
                                        <span class="sl-sitemap-link__meta">Affectation des permissions par rôle</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/admin/user_logs.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">📜</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Audit des logs</span>
                                        <span class="sl-sitemap-link__meta">Traçabilité et suivi des actions</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>

                        <li>
                            <a class="sl-sitemap-link" href="<?= e(APP_URL) ?>modules/support/support_requests.php">
                                <span class="sl-sitemap-link__left">
                                    <span class="sl-sitemap-link__icon">🆘</span>
                                    <span class="sl-sitemap-link__text">
                                        <span class="sl-sitemap-link__title">Demandes support</span>
                                        <span class="sl-sitemap-link__meta">Questions, bugs et demandes d’accès</span>
                                    </span>
                                </span>
                                <span class="sl-sitemap-link__arrow">→</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </section>
        </div>

        <section class="sl-sitemap-card" style="margin-bottom:24px;">
            <div class="sl-sitemap-card__head">
                <h3>Accès publics</h3>
                <p>Les principales pages publiques et institutionnelles de StudelyLedger.</p>
            </div>

            <div class="sl-sitemap-card__body">
                <div class="sl-sitemap-actions">
                    <a href="<?= e(APP_URL) ?>login.php" class="btn btn-primary">Connexion</a>
                    <a href="<?= e(APP_URL) ?>pages/contact.php" class="btn btn-outline">Contact</a>
                    <a href="<?= e(APP_URL) ?>pages/cookies_policy.php" class="btn btn-outline">Cookies</a>
                    <a href="<?= e(APP_URL) ?>pages/copyright.php" class="btn btn-outline">Copyright</a>
                </div>
            </div>
        </section>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/document_end.php'; ?>