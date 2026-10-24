<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Journal des imports';
$pageSubtitle = 'Suivi des imports, erreurs, doublons et opérations créées';

$filters = function_exists('sl_imports_journal_parse_filters')
    ? sl_imports_journal_parse_filters($_GET)
    : [
        'search' => trim((string)($_GET['search'] ?? '')),
        'module' => trim((string)($_GET['module'] ?? '')),
        'action' => trim((string)($_GET['action'] ?? '')),
        'from' => trim((string)($_GET['from'] ?? '')),
        'to' => trim((string)($_GET['to'] ?? '')),
        'page' => 1,
        'per_page' => (int)($_GET['per_page'] ?? 50),
    ];

$listData = function_exists('sl_imports_journal_get_rows')
    ? sl_imports_journal_get_rows($pdo, $filters)
    : [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 50,
        'pages' => 1,
        'can_use_logs' => false,
    ];

$kpis = function_exists('sl_imports_journal_get_kpis')
    ? sl_imports_journal_get_kpis($pdo, $filters)
    : [
        'total_logs' => 0,
        'imports_count' => 0,
        'distinct_modules' => 0,
        'distinct_actions' => 0,
        'today_logs' => 0,
    ];

$options = function_exists('sl_imports_journal_get_filter_options')
    ? sl_imports_journal_get_filter_options($pdo)
    : [
        'modules' => [],
        'actions' => [],
    ];

$flashSuccess = $_SESSION['flash_success'] ?? ($_SESSION['success_message'] ?? '');
$flashError = $_SESSION['flash_error'] ?? ($_SESSION['error_message'] ?? '');
$flashDetails = $_SESSION['flash_details'] ?? [];

unset(
    $_SESSION['flash_success'],
    $_SESSION['flash_error'],
    $_SESSION['flash_details'],
    $_SESSION['success_message'],
    $_SESSION['error_message']
);

$logs = $listData['rows'] ?? [];
$total = (int)($listData['total'] ?? 0);
$page = (int)($listData['page'] ?? 1);
$perPage = (int)($listData['per_page'] ?? 50);
$pages = (int)($listData['pages'] ?? 1);
$canUseLogs = (bool)($listData['can_use_logs'] ?? false);

$availableModules = $options['modules'] ?? [];
$availableActions = $options['actions'] ?? [];

$search = (string)($filters['search'] ?? '');
$module = (string)($filters['module'] ?? '');
$action = (string)($filters['action'] ?? '');
$dateFrom = (string)($filters['from'] ?? '');
$dateTo = (string)($filters['to'] ?? '');

if (!function_exists('sl_import_journal_badge_class')) {
    function sl_import_journal_badge_class(string $value): string
    {
        $normalized = strtolower(trim($value));

        if (
            str_contains($normalized, 'error') ||
            str_contains($normalized, 'failed') ||
            str_contains($normalized, 'danger')
        ) {
            return 'sij-chip--danger';
        }

        if (
            str_contains($normalized, 'warning') ||
            str_contains($normalized, 'duplicate') ||
            str_contains($normalized, 'doublon')
        ) {
            return 'sij-chip--warning';
        }

        if (
            str_contains($normalized, 'success') ||
            str_contains($normalized, 'validated') ||
            str_contains($normalized, 'import') ||
            str_contains($normalized, 'preview')
        ) {
            return 'sij-chip--success';
        }

        return 'sij-chip--neutral';
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sij-shell {
                display: grid;
                gap: 20px;
            }

            .sij-toolbar,
            .sij-panel,
            .sij-kpi-card,
            .sij-log-card {
                border-radius: 20px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
            }

            .sij-toolbar,
            .sij-panel {
                padding: 18px;
            }

            .sij-toolbar h3,
            .sij-panel h3 {
                margin: 0 0 8px;
            }

            .sij-subtitle {
                margin: 0;
                color: #64748b;
                line-height: 1.55;
            }

            .sij-kpi-grid {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1200px) {
                .sij-kpi-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 760px) {
                .sij-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sij-kpi-card {
                padding: 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .sij-kpi-label {
                display: block;
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 8px;
            }

            .sij-kpi-value {
                display: block;
                font-size: 28px;
                font-weight: 900;
                line-height: 1;
                color: #0f172a;
            }

            .sij-kpi-meta {
                display: block;
                margin-top: 8px;
                color: #64748b;
                font-size: 12px;
            }

            .sij-filter-grid {
                display: grid;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 14px;
            }

            @media (max-width: 1200px) {
                .sij-filter-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 760px) {
                .sij-filter-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sij-help-box {
                margin-top: 14px;
                padding: 14px 16px;
                border-radius: 14px;
                background: rgba(99, 102, 241, 0.07);
                border: 1px solid rgba(99, 102, 241, 0.16);
                color: #3730a3;
                line-height: 1.55;
                font-size: 13px;
            }

            .sij-log-list {
                display: grid;
                gap: 12px;
                margin-top: 14px;
            }

            .sij-log-card {
                padding: 14px;
                background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            }

            .sij-log-top {
                display: grid;
                grid-template-columns: minmax(0, 1.1fr) minmax(220px, 0.9fr);
                gap: 14px;
                align-items: start;
            }

            @media (max-width: 860px) {
                .sij-log-top {
                    grid-template-columns: 1fr;
                }
            }

            .sij-log-title {
                font-weight: 900;
                color: #0f172a;
                word-break: break-word;
            }

            .sij-log-sub {
                display: block;
                margin-top: 4px;
                font-size: 12px;
                color: #64748b;
                word-break: break-word;
            }

            .sij-chip-row {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 10px;
            }

            .sij-chip {
                display: inline-flex;
                align-items: center;
                padding: 5px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                white-space: nowrap;
            }

            .sij-chip--success {
                background: rgba(16, 185, 129, 0.12);
                color: #047857;
            }

            .sij-chip--warning {
                background: rgba(245, 158, 11, 0.12);
                color: #92400e;
            }

            .sij-chip--danger {
                background: rgba(239, 68, 68, 0.10);
                color: #b91c1c;
            }

            .sij-chip--neutral {
                background: rgba(148, 163, 184, 0.14);
                color: #475569;
            }

            .sij-log-details {
                margin-top: 12px;
                padding: 12px 14px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.95);
                border: 1px solid rgba(226, 232, 240, 0.9);
                color: #334155;
                font-size: 13px;
                line-height: 1.6;
                word-break: break-word;
            }

            .sij-meta-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
            }

            @media (max-width: 860px) {
                .sij-meta-grid {
                    grid-template-columns: 1fr;
                }
            }

            .sij-meta-item {
                padding: 10px 12px;
                border-radius: 14px;
                background: rgba(248, 250, 252, 0.95);
                border: 1px solid rgba(226, 232, 240, 0.9);
            }

            .sij-meta-item span {
                display: block;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 6px;
            }

            .sij-meta-item strong {
                color: #0f172a;
                word-break: break-word;
            }

            .sij-pagination {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 18px;
            }

            .sij-empty {
                padding: 18px;
                border-radius: 16px;
                background: rgba(248, 250, 252, 0.95);
                border: 1px dashed rgba(148, 163, 184, 0.35);
                color: #475569;
            }
        </style>

        <?php if ($flashSuccess !== ''): ?>
            <div class="success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError !== ''): ?>
            <div class="error"><?= e($flashError) ?></div>
        <?php endif; ?>

        <?php if (!empty($flashDetails) && is_array($flashDetails)): ?>
            <div class="warning" style="margin-bottom:20px;">
                <strong>Détails :</strong>
                <ul style="margin:10px 0 0 18px;">
                    <?php foreach ($flashDetails as $detail): ?>
                        <li><?= e((string)$detail) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="sij-shell">
            <div class="sij-kpi-grid">
                <div class="sij-kpi-card">
                    <span class="sij-kpi-label">Lignes journal</span>
                    <span class="sij-kpi-value"><?= (int)$kpis['total_logs'] ?></span>
                    <span class="sij-kpi-meta">Historique global</span>
                </div>

                <div class="sij-kpi-card">
                    <span class="sij-kpi-label">Actions imports</span>
                    <span class="sij-kpi-value"><?= (int)$kpis['imports_count'] ?></span>
                    <span class="sij-kpi-meta">Module imports</span>
                </div>

                <div class="sij-kpi-card">
                    <span class="sij-kpi-label">Modules</span>
                    <span class="sij-kpi-value"><?= (int)$kpis['distinct_modules'] ?></span>
                    <span class="sij-kpi-meta">Diversité suivie</span>
                </div>

                <div class="sij-kpi-card">
                    <span class="sij-kpi-label">Actions</span>
                    <span class="sij-kpi-value"><?= (int)$kpis['distinct_actions'] ?></span>
                    <span class="sij-kpi-meta">Types journalisés</span>
                </div>

                <div class="sij-kpi-card">
                    <span class="sij-kpi-label">Aujourd'hui</span>
                    <span class="sij-kpi-value"><?= (int)$kpis['today_logs'] ?></span>
                    <span class="sij-kpi-meta">Activité du jour</span>
                </div>
            </div>

            <section class="sij-toolbar">
                <h3>Filtres</h3>
                <p class="sij-subtitle">
                    Affine l’historique par mot-clé, module, action, plage de dates et volume affiché.
                </p>

                <form method="GET">
                    <div class="sij-filter-grid">
                        <div>
                            <label for="search">Recherche</label>
                            <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Mot-clé, détail, action...">
                        </div>

                        <div>
                            <label for="module">Module</label>
                            <select id="module" name="module">
                                <option value="">Tous</option>
                                <?php foreach ($availableModules as $moduleOption): ?>
                                    <option value="<?= e((string)$moduleOption) ?>" <?= $module === (string)$moduleOption ? 'selected' : '' ?>>
                                        <?= e((string)$moduleOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="action">Action</label>
                            <select id="action" name="action">
                                <option value="">Toutes</option>
                                <?php foreach ($availableActions as $actionOption): ?>
                                    <option value="<?= e((string)$actionOption) ?>" <?= $action === (string)$actionOption ? 'selected' : '' ?>>
                                        <?= e((string)$actionOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="from">Du</label>
                            <input type="date" id="from" name="from" value="<?= e($dateFrom) ?>">
                        </div>

                        <div>
                            <label for="to">Au</label>
                            <input type="date" id="to" name="to" value="<?= e($dateTo) ?>">
                        </div>

                        <div>
                            <label for="per_page">Résultats</label>
                            <select id="per_page" name="per_page">
                                <?php foreach ([25, 50, 100, 200] as $size): ?>
                                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="sij-help-box">
                        Utilise le journal pour suivre les uploads, validations de mapping, prévisualisations, erreurs d’import et créations d’opérations.
                    </div>

                    <div class="btn-group" style="margin-top:18px;">
                        <button type="submit" class="btn btn-success">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Réinitialiser</a>
                        <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-secondary">Nouvel import</a>
                    </div>
                </form>
            </section>

            <section class="sij-panel">
                <div class="sl-card-head">
                    <div>
                        <h3>Historique des imports et actions liées</h3>
                        <p class="sij-subtitle"><?= (int)$total ?> ligne(s) trouvée(s)</p>
                    </div>
                </div>

                <?php if (!$canUseLogs): ?>
                    <div class="warning">La table <strong>user_logs</strong> est absente. Le journal ne peut pas être affiché.</div>
                <?php else: ?>
                    <?php if ($logs): ?>
                        <div class="sij-log-list">
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $actionValue = (string)($log['action'] ?? '');
                                $moduleValue = (string)($log['module'] ?? '');
                                $entityTypeValue = (string)($log['entity_type'] ?? '');
                                $detailsValue = (string)($log['details'] ?? '');
                                $usernameValue = (string)($log['username'] ?? ($log['user_id'] ?? ''));
                                $entityIdValue = (string)($log['entity_id'] ?? '');
                                $createdAtValue = (string)($log['created_at'] ?? '');
                                ?>
                                <article class="sij-log-card">
                                    <div class="sij-log-top">
                                        <div>
                                            <div class="sij-log-title"><?= e($actionValue !== '' ? $actionValue : 'Action non renseignée') ?></div>
                                            <span class="sij-log-sub">
                                                <?= e($createdAtValue !== '' ? $createdAtValue : 'Date inconnue') ?>
                                                •
                                                <?= e($usernameValue !== '' ? $usernameValue : 'Utilisateur inconnu') ?>
                                            </span>

                                            <div class="sij-chip-row">
                                                <span class="sij-chip <?= e(sl_import_journal_badge_class($actionValue)) ?>">
                                                    <?= e($actionValue !== '' ? $actionValue : 'action') ?>
                                                </span>

                                                <span class="sij-chip <?= e(sl_import_journal_badge_class($moduleValue)) ?>">
                                                    <?= e($moduleValue !== '' ? $moduleValue : 'module') ?>
                                                </span>

                                                <span class="sij-chip sij-chip--neutral">
                                                    <?= e($entityTypeValue !== '' ? $entityTypeValue : 'entity') ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="sij-meta-grid">
                                            <div class="sij-meta-item">
                                                <span>Utilisateur</span>
                                                <strong><?= e($usernameValue !== '' ? $usernameValue : '—') ?></strong>
                                            </div>

                                            <div class="sij-meta-item">
                                                <span>ID entité</span>
                                                <strong><?= e($entityIdValue !== '' ? $entityIdValue : '—') ?></strong>
                                            </div>

                                            <div class="sij-meta-item">
                                                <span>Type entité</span>
                                                <strong><?= e($entityTypeValue !== '' ? $entityTypeValue : '—') ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sij-log-details">
                                        <?= e($detailsValue !== '' ? $detailsValue : 'Aucun détail disponible.') ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sij-empty">Aucune ligne trouvée pour les filtres actuels.</div>
                    <?php endif; ?>

                    <?php if ($pages > 1): ?>
                        <div class="sij-pagination">
                            <?php for ($p = 1; $p <= $pages; $p++): ?>
                                <a
                                    class="btn <?= $p === $page ? 'btn-success' : 'btn-outline' ?>"
                                    href="<?= e(APP_URL) ?>modules/imports/import_journal.php?<?= http_build_query([
                                        'search' => $search,
                                        'module' => $module,
                                        'action' => $action,
                                        'from' => $dateFrom,
                                        'to' => $dateTo,
                                        'per_page' => $perPage,
                                        'page' => $p,
                                    ]) ?>"
                                >
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>