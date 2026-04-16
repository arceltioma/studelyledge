<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_view_page');
} else {
    enforcePagePermission($pdo, 'operations_view');
}

$pageTitle = 'Liste des opérations';
$pageSubtitle = 'Suivi consolidé des opérations comptables';

$filters = sl_parse_common_list_filters($_GET);

if ($filters['date_from'] === '') {
    $filters['date_from'] = date('Y-m-01');
}
if ($filters['date_to'] === '') {
    $filters['date_to'] = date('Y-m-d');
}

$allowedPerPage = [10, 20, 50, 100, 200];
$perPage = (int)($_GET['per_page'] ?? ($filters['per_page'] ?? 20));
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}
$filters['per_page'] = $perPage;

$requestedPage = (int)($_GET['page'] ?? ($filters['page'] ?? 1));
if ($requestedPage < 1) {
    $requestedPage = 1;
}
$filters['page'] = $requestedPage;

$kpis = sl_operations_list_get_kpis($pdo, $filters);
$data = sl_operations_list_get_rows($pdo, $filters);

$rows = $data['rows'] ?? [];
$page = max(1, (int)($data['page'] ?? 1));
$pages = max(1, (int)($data['pages'] ?? 1));
$totalRows = (int)($data['total'] ?? count($rows));

$serviceRows = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, label
        FROM ref_services
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$paginationBaseQuery = $_GET;
unset($paginationBaseQuery['page']);

$startItem = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$endItem = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;

$window = 2;
$pageStart = max(1, $page - $window);
$pageEnd = min($pages, $page + $window);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="sl-kpi-grid">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Total opérations</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['total_operations'] ?></div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Montant total</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['total_amount'], 2, ',', ' ')) ?></div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Opérations du mois</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['month_operations'] ?></div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Montant du mois</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['month_amount'], 2, ',', ' ')) ?></div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Écritures manuelles</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['manual_count'] ?></div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Types distincts</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['types_count'] ?></div>
            </div>
        </div>

        <div class="sl-filter-card" style="margin-top:20px;">
            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input
                            type="text"
                            name="q"
                            value="<?= e($filters['q']) ?>"
                            placeholder="Référence, libellé, client"
                        >
                    </div>

                    <div>
                        <label>Date du</label>
                        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                    </div>

                    <div>
                        <label>au</label>
                        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                    </div>

                    <div>
                        <label>Type d’opération</label>
                        <input
                            type="text"
                            name="operation_type_code"
                            value="<?= e($filters['operation_type_code']) ?>"
                            placeholder="Ex: VIREMENT"
                        >
                    </div>

                    <div>
                        <label>Service</label>
                        <select name="service_id">
                            <option value="">Tous</option>
                            <?php foreach ($serviceRows as $service): ?>
                                <option
                                    value="<?= (int)$service['id'] ?>"
                                    <?= (string)$filters['service_id'] === (string)$service['id'] ? 'selected' : '' ?>
                                >
                                    <?= e((string)$service['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Résultats par page</label>
                        <select name="per_page">
                            <?php foreach ($allowedPerPage as $size): ?>
                                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>>
                                    <?= $size ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="page" value="1">

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/operations/operation_create.php" class="btn btn-primary">Nouvelle opération</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:12px;
                    flex-wrap:wrap;
                    margin-bottom:16px;
                "
            >
                <div class="muted">
                    <?php if ($totalRows > 0): ?>
                        Affichage de <strong><?= $startItem ?></strong> à <strong><?= $endItem ?></strong>
                        sur <strong><?= $totalRows ?></strong> opération(s)
                    <?php else: ?>
                        Aucune opération à afficher
                    <?php endif; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <div class="muted">
                        Page <strong><?= $page ?></strong> / <strong><?= $pages ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="modern-table sl-modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Service</th>
                            <th>Montant</th>
                            <th>Compte débité</th>
                            <th>Compte crédité</th>
                            <th>Compte Bancaire 512</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e((string)($row['operation_date'] ?? '')) ?></td>
                                <td><?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($row['client_full_name'] ?? '')) ?: '—') ?></td>
                                <td><?= e((string)($row['operation_type_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['service_label'] ?? '')) ?></td>
                                <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?> <?= e((string)($row['currency_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['debit_account_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['credit_account_code'] ?? '')) ?></td>
                                <td><?= e(trim((string)($row['linked_treasury_account_code'] ?? '') . ' - ' . (string)($row['linked_treasury_account_label'] ?? '')) ?: '—') ?></td>
                                <td>
                                    <div class="btn-group btn-group--compact" style="flex-wrap:nowrap;">
                                        <a
                                            href="<?= e(APP_URL) ?>modules/operations/operation_view.php?id=<?= (int)$row['id'] ?>"
                                            class="btn btn-outline btn-sm"
                                        >
                                            Voir
                                        </a>
                                        <a
                                            href="<?= e(APP_URL) ?>modules/operations/operation_edit.php?id=<?= (int)$row['id'] ?>"
                                            class="btn btn-success btn-sm"
                                        >
                                            Modifier
                                        </a>
                                        <a
                                            href="<?= e(APP_URL) ?>modules/operations/operation_delete.php?id=<?= (int)$row['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Confirmer la suppression ?');"
                                        >
                                            Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="9">Aucune opération trouvée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div
                    class="btn-group"
                    style="
                        margin-top:18px;
                        justify-content:space-between;
                        align-items:center;
                        width:100%;
                    "
                >
                    <div class="btn-group">
                        <?php
                        $prevQuery = $paginationBaseQuery;
                        $prevQuery['page'] = max(1, $page - 1);

                        $nextQuery = $paginationBaseQuery;
                        $nextQuery['page'] = min($pages, $page + 1);
                        ?>

                        <a
                            href="?<?= e(http_build_query($prevQuery)) ?>"
                            class="btn <?= $page <= 1 ? 'btn-outline' : 'btn-secondary' ?>"
                            <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>
                        >
                            Précédent
                        </a>

                        <?php if ($pageStart > 1): ?>
                            <?php
                            $firstQuery = $paginationBaseQuery;
                            $firstQuery['page'] = 1;
                            ?>
                            <a href="?<?= e(http_build_query($firstQuery)) ?>" class="btn btn-outline">1</a>

                            <?php if ($pageStart > 2): ?>
                                <span class="btn btn-outline" style="pointer-events:none;">…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $pageStart; $i <= $pageEnd; $i++): ?>
                            <?php
                            $query = $paginationBaseQuery;
                            $query['page'] = $i;
                            ?>
                            <a
                                href="?<?= e(http_build_query($query)) ?>"
                                class="btn <?= $i === $page ? 'btn-success' : 'btn-outline' ?>"
                            >
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pageEnd < $pages): ?>
                            <?php if ($pageEnd < ($pages - 1)): ?>
                                <span class="btn btn-outline" style="pointer-events:none;">…</span>
                            <?php endif; ?>

                            <?php
                            $lastQuery = $paginationBaseQuery;
                            $lastQuery['page'] = $pages;
                            ?>
                            <a href="?<?= e(http_build_query($lastQuery)) ?>" class="btn btn-outline"><?= $pages ?></a>
                        <?php endif; ?>

                        <a
                            href="?<?= e(http_build_query($nextQuery)) ?>"
                            class="btn <?= $page >= $pages ? 'btn-outline' : 'btn-secondary' ?>"
                            <?= $page >= $pages ? 'aria-disabled="true"' : '' ?>
                        >
                            Suivant
                        </a>
                    </div>

                    <div class="muted">
                        <?= $perPage ?> / page
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>