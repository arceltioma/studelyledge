<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if (!tableExists($pdo, 'treasury_accounts')) {
    exit('Table treasury_accounts introuvable.');
}

$pageTitle = 'Comptes internes (512)';
$pageSubtitle = 'Gestion compacte des comptes de trésorerie, suivi des soldes et accès rapide';

$filters = function_exists('sl_treasury_list_parse_filters')
    ? sl_treasury_list_parse_filters($_GET)
    : [
        'search' => trim((string)($_GET['search'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'type_view' => trim((string)($_GET['type_view'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => 25,
    ];

$listData = function_exists('sl_treasury_list_get_rows')
    ? sl_treasury_list_get_rows($pdo, $filters)
    : [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 25,
        'pages' => 1,
    ];

$kpis = function_exists('sl_treasury_list_get_kpis')
    ? sl_treasury_list_get_kpis($pdo, $filters)
    : [
        'total_accounts' => 0,
        'active_accounts' => 0,
        'archived_accounts' => 0,
        'postable_accounts' => 0,
        'structure_accounts' => 0,
        'opening_balance_total' => 0.0,
        'current_balance_total' => 0.0,
    ];

$accounts = $listData['rows'] ?? [];
$total = (int)($listData['total'] ?? 0);
$page = (int)($listData['page'] ?? 1);
$perPage = (int)($listData['per_page'] ?? 25);
$pages = (int)($listData['pages'] ?? 1);

$search = (string)($filters['search'] ?? '');
$status = (string)($filters['status'] ?? '');
$typeView = (string)($filters['type_view'] ?? '');

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-kpi-grid sl-kpi-grid--compact" style="margin-bottom:20px;">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <span class="sl-kpi-card__label">Comptes 512</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['total_accounts'] ?></strong>
                <span class="sl-kpi-card__meta">Total référencé</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--emerald">
                <span class="sl-kpi-card__label">Actifs</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['active_accounts'] ?></strong>
                <span class="sl-kpi-card__meta">Comptes exploitables</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--rose">
                <span class="sl-kpi-card__label">Archivés</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['archived_accounts'] ?></strong>
                <span class="sl-kpi-card__meta">Non utilisés</span>
            </div>
            <div class="sl-kpi-card sl-kpi-card--violet">
                <span class="sl-kpi-card__label">Solde d'ouverture</span>
                <strong class="sl-kpi-card__value"><?= e(number_format((float)$kpis['opening_balance_total'], 2, ',', ' ')) ?></strong>
                <span class="sl-kpi-card__meta">Base initiale</span>
            </div>

            <div class="sl-kpi-card sl-kpi-card--indigo">
                <span class="sl-kpi-card__label">Solde courant</span>
                <strong class="sl-kpi-card__value"><?= e(number_format((float)$kpis['current_balance_total'], 2, ',', ' ')) ?></strong>
                <span class="sl-kpi-card__meta">Trésorerie affichée</span>
            </div>
        </section>

        <section class="card" style="margin-bottom:20px;">
            <h3>Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code ou intitulé">
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archivés</option>
                        </select>
                    </div>

                    <div>
                        <label>Type</label>
                        <select name="type_view">
                            <option value="">Tous</option>
                            <option value="postable" <?= $typeView === 'postable' ? 'selected' : '' ?>>Postables</option>
                            <option value="structure" <?= $typeView === 'structure' ? 'selected' : '' ?>>Structures</option>
                        </select>
                    </div>

                    <div>
                        <label>Résultats par page</label>
                        <select name="per_page">
                            <?php foreach ([10, 25, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/treasury/treasury_create.php" class="btn btn-secondary">Ajouter</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="sl-card-head">
                <div>
                    <h3>Liste compacte des comptes internes</h3>
                    <p class="sl-card-head-subtitle"><?= (int)$total ?> résultat(s) — vue synthétique et actions rapides</p>
                </div>
            </div>

            <div class="sl-table-wrap">
                <table class="sl-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Intitulé</th>
                            <th>Solde ouverture</th>
                            <th>Solde courant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($accounts): ?>
                            <?php foreach ($accounts as $row): ?>
                                <?php
                                $isActive = (int)($row['is_active'] ?? 1) === 1;
                                $openingBalance = (float)($row['opening_balance'] ?? 0);
                                $currentBalance = (float)($row['current_balance'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= e((string)($row['account_code'] ?? '')) ?></td>
                                    <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                    <td><?= e(number_format($openingBalance, 2, ',', ' ')) ?></td>
                                    <td><?= e(number_format($currentBalance, 2, ',', ' ')) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $isActive ? 'success' : 'danger' ?>">
                                            <?= $isActive ? 'Actif' : 'Archivé' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group--compact">
                                            <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                                            <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>modules/treasury/treasury_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>

                                            <?php if ($isActive): ?>
                                                <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>modules/treasury/treasury_archive.php?id=<?= (int)$row['id'] ?>">Archiver</a>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-success" href="<?= e(APP_URL) ?>modules/treasury/treasury_archive.php?id=<?= (int)$row['id'] ?>&action=reactivate">Réactiver</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">Aucun compte de trésorerie trouvé.</td>
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
                            href="<?= e(APP_URL) ?>modules/treasury/index.php?<?= http_build_query([
                                'search' => $search,
                                'status' => $status,
                                'type_view' => $typeView,
                                'per_page' => $perPage,
                                'page' => $p,
                            ]) ?>"
                        >
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>