<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_view_page');
} else {
    enforcePagePermission($pdo, 'treasury_view');
}

if (!tableExists($pdo, 'treasury_accounts')) {
    exit('Table treasury_accounts introuvable.');
}

$pageTitle = 'Comptes internes (512)';
$pageSubtitle = 'Gestion compacte des comptes de trésorerie, suivi des soldes et accès rapide';

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$typeView = trim((string)($_GET['type_view'] ?? ''));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(
        COALESCE(account_code,'') LIKE ?
        OR COALESCE(account_label,'') LIKE ?
    )";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status === 'active' && columnExists($pdo, 'treasury_accounts', 'is_active')) {
    $where[] = "COALESCE(is_active,1) = 1";
}

if ($status === 'archived' && columnExists($pdo, 'treasury_accounts', 'is_active')) {
    $where[] = "COALESCE(is_active,1) = 0";
}

if ($typeView === 'postable' && columnExists($pdo, 'treasury_accounts', 'is_postable')) {
    $where[] = "COALESCE(is_postable,0) = 1";
}

if ($typeView === 'structure' && columnExists($pdo, 'treasury_accounts', 'is_postable')) {
    $where[] = "COALESCE(is_postable,0) = 0";
}

$stmt = $pdo->prepare("
    SELECT *
    FROM treasury_accounts
    WHERE " . implode(' AND ', $where) . "
    ORDER BY COALESCE(is_active,1) DESC, account_code ASC
");
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalAccounts = count($accounts);
$totalActive = 0;
$totalCurrentBalance = 0.0;
$totalPostable = 0;
$totalStructure = 0;

foreach ($accounts as $row) {
    if ((int)($row['is_active'] ?? 1) === 1) {
        $totalActive++;
    }

    if ((int)($row['is_postable'] ?? 0) === 1) {
        $totalPostable++;
    } else {
        $totalStructure++;
    }

    $totalCurrentBalance += (float)($row['current_balance'] ?? 0);
}

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
                    <span>Total affiché</span>
                    <strong>512</strong>
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
                    <span>Somme affichée</span>
                    <strong>Trésorerie</strong>
                </div>
            </div>
        </section>

        <section class="sl-card sl-stable-block" style="margin-bottom:20px;">
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
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/treasury/treasury_create.php" class="btn btn-secondary">Ajouter</a>
                </div>
            </form>
        </section>

        <section class="sl-card sl-stable-block">
            <div class="sl-card-head">
                <div>
                    <h3>Liste compacte des comptes internes</h3>
                    <p class="sl-card-head-subtitle">Vue synthétique, type comptable et accès direct</p>
                </div>
            </div>

            <div class="sl-table-wrap">
                <table class="sl-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Intitulé</th>
                            <th>Type</th>
                            <th>Solde ouverture</th>
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
                                    <td><?= e(number_format((float)($row['opening_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                    <td><?= e(number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                    <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-secondary" href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                                            <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>modules/treasury/treasury_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                            <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                                <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>modules/treasury/treasury_archive.php?id=<?= (int)$row['id'] ?>">Archiver</a>
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
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>