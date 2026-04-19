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

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

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

        <section class="sl-kpi-grid sl-kpi-grid--compact" style="margin-bottom:20px;">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <span class="sl-kpi-card__label">Lignes journal</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['total_logs'] ?></strong>
                <span class="sl-kpi-card__meta">Historique global</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--emerald">
                <span class="sl-kpi-card__label">Actions imports</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['imports_count'] ?></strong>
                <span class="sl-kpi-card__meta">Module imports</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--violet">
                <span class="sl-kpi-card__label">Modules</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['distinct_modules'] ?></strong>
                <span class="sl-kpi-card__meta">Diversité suivie</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--amber">
                <span class="sl-kpi-card__label">Actions</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['distinct_actions'] ?></strong>
                <span class="sl-kpi-card__meta">Types journalisés</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--indigo">
                <span class="sl-kpi-card__label">Aujourd'hui</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['today_logs'] ?></strong>
                <span class="sl-kpi-card__meta">Activité du jour</span>
            </div>
        </section>

        <section class="card" style="margin-bottom:20px;">
            <h3>Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-5">
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

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-secondary">Nouvel import</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="sl-card-head">
                <div>
                    <h3>Historique des imports et actions liées</h3>
                    <p class="sl-card-head-subtitle"><?= (int)$total ?> ligne(s) trouvée(s)</p>
                </div>
            </div>

            <?php if (!$canUseLogs): ?>
                <div class="warning">La table <strong>user_logs</strong> est absente. Le journal ne peut pas être affiché.</div>
            <?php else: ?>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Type entité</th>
                                <th>ID entité</th>
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= e((string)($log['created_at'] ?? '')) ?></td>
                                        <td><?= e((string)($log['username'] ?? ($log['user_id'] ?? ''))) ?></td>
                                        <td><?= e((string)($log['action'] ?? '')) ?></td>
                                        <td><?= e((string)($log['module'] ?? '')) ?></td>
                                        <td><?= e((string)($log['entity_type'] ?? '')) ?></td>
                                        <td><?= e((string)($log['entity_id'] ?? '')) ?></td>
                                        <td><?= e((string)($log['details'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Aucune ligne trouvée pour les filtres actuels.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                    <div class="btn-group" style="margin-top:18px;">
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

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>