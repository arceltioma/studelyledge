<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_manage_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$pageTitle = 'Comptes de service (706)';
$pageSubtitle = 'Gestion compacte des comptes de service, suivi des soldes et accès rapide';

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$typeView = trim((string)($_GET['type_view'] ?? ''));
$perPage = (int)($_GET['per_page'] ?? 20);
$page = max(1, (int)($_GET['page'] ?? 1));

$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(
        COALESCE(account_code,'') LIKE ?
        OR COALESCE(account_label,'') LIKE ?
        OR COALESCE(commercial_country_label,'') LIKE ?
        OR COALESCE(destination_country_label,'') LIKE ?
    )";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status === 'active' && columnExists($pdo, 'service_accounts', 'is_active')) {
    $where[] = "COALESCE(is_active,1) = 1";
}

if ($status === 'archived' && columnExists($pdo, 'service_accounts', 'is_active')) {
    $where[] = "COALESCE(is_active,1) = 0";
}

if ($typeView === 'postable' && columnExists($pdo, 'service_accounts', 'is_postable')) {
    $where[] = "COALESCE(is_postable,0) = 1";
}

if ($typeView === 'structure' && columnExists($pdo, 'service_accounts', 'is_postable')) {
    $where[] = "COALESCE(is_postable,0) = 0";
}

$whereSql = implode(' AND ', $where);

/* =========================
   Totaux sur jeu filtré complet
========================= */
$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS total_count
    FROM service_accounts
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalAccounts = (int)($countStmt->fetchColumn() ?: 0);

$statsStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN COALESCE(is_active,1) = 1 THEN 1 ELSE 0 END), 0) AS total_active,
        COALESCE(SUM(CASE WHEN COALESCE(is_postable,0) = 1 THEN 1 ELSE 0 END), 0) AS total_postable,
        COALESCE(SUM(COALESCE(current_balance,0)), 0) AS total_current_balance
    FROM service_accounts
    WHERE {$whereSql}
");
$statsStmt->execute($params);
$statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalActive = (int)($statsRow['total_active'] ?? 0);
$totalPostable = (int)($statsRow['total_postable'] ?? 0);
$totalCurrentBalance = (float)($statsRow['total_current_balance'] ?? 0);

/* =========================
   Pagination
========================= */
$totalPages = max(1, (int)ceil($totalAccounts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$listSql = "
    SELECT *
    FROM service_accounts
    WHERE {$whereSql}
    ORDER BY COALESCE(is_active,1) DESC, account_code ASC
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$paginationQuery = [
    'search' => $search,
    'status' => $status,
    'type_view' => $typeView,
    'per_page' => $perPage,
];

$buildPageUrl = static function (int $targetPage) use ($paginationQuery): string {
    $query = $paginationQuery;
    $query['page'] = $targetPage;
    return APP_URL . 'modules/service_accounts/index.php?' . http_build_query($query);
};

$fromItem = $totalAccounts > 0 ? ($offset + 1) : 0;
$toItem = min($offset + $perPage, $totalAccounts);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Comptes</div>
                <div class="sl-kpi-card__value"><?= (int)$totalAccounts ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Total filtré</span>
                    <strong>706</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Actifs</div>
                <div class="sl-kpi-card__value"><?= (int)$totalActive ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Disponibles</span>
                    <strong>Suivi</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Postables</div>
                <div class="sl-kpi-card__value"><?= (int)$totalPostable ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Écritures autorisées</span>
                    <strong>Opérationnels</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Solde courant</div>
                <div class="sl-kpi-card__value"><?= e(number_format($totalCurrentBalance, 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Somme filtrée</span>
                    <strong>Produits</strong>
                </div>
            </div>
        </section>

        <section class="sl-card sl-stable-block" style="margin-bottom:20px;">
            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Code, intitulé, pays..."
                        >
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
                        <label>Par page</label>
                        <select name="per_page">
                            <?php foreach ($allowedPerPage as $size): ?>
                                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>>
                                    <?= $size ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/create.php" class="btn btn-secondary">Créer</a>
                    <a href="<?= e(APP_URL) ?>modules/service_accounts/import_service_accounts_csv.php" class="btn btn-secondary">Importer CSV</a>
                </div>
            </form>
        </section>

        <section class="sl-card sl-stable-block">
            <div class="sl-card-head">
                <div>
                    <h3>Liste compacte des comptes de service</h3>
                    <p class="sl-card-head-subtitle">
                        Vue synthétique, type comptable et accès direct
                    </p>
                </div>
                <div class="muted">
                    <?= e((string)$fromItem) ?> à <?= e((string)$toItem) ?> sur <?= e((string)$totalAccounts) ?>
                </div>
            </div>

            <div class="sl-table-wrap">
                <table class="sl-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Intitulé</th>
                            <th>Type</th>
                            <th>Pays commercial</th>
                            <th>Pays destination</th>
                            <th>Solde courant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($accounts): ?>
                            <?php foreach ($accounts as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['account_code'] ?? '')) ?></td>
                                    <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                    <td><?= renderPostableBadge($row['is_postable'] ?? 0) ?></td>
                                    <td><?= e((string)($row['commercial_country_label'] ?? '—')) ?></td>
                                    <td><?= e((string)($row['destination_country_label'] ?? '—')) ?></td>
                                    <td><?= e(number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                    <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                                            <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>modules/service_accounts/edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                            <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                                <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>modules/service_accounts/archive.php?id=<?= (int)$row['id'] ?>">Archiver</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">Aucun compte de service trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="btn-group" style="margin-top:18px; justify-content:space-between; align-items:center;">
                    <div class="muted">
                        Page <?= (int)$page ?> / <?= (int)$totalPages ?>
                    </div>

                    <div class="btn-group">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-outline btn-sm" href="<?= e($buildPageUrl(1)) ?>">« Première</a>
                            <a class="btn btn-outline btn-sm" href="<?= e($buildPageUrl($page - 1)) ?>">‹ Précédente</a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a
                                class="btn btn-sm <?= $i === $page ? 'btn-success' : 'btn-outline' ?>"
                                href="<?= e($buildPageUrl($i)) ?>"
                            >
                                <?= (int)$i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-outline btn-sm" href="<?= e($buildPageUrl($page + 1)) ?>">Suivante ›</a>
                            <a class="btn btn-outline btn-sm" href="<?= e($buildPageUrl($totalPages)) ?>">Dernière »</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>