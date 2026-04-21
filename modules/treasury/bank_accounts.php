<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$search = trim($_GET['search'] ?? '');
$country = trim($_GET['country'] ?? '');
$accountTypeId = (int)($_GET['account_type_id'] ?? 0);
$accountCategoryId = (int)($_GET['account_category_id'] ?? 0);
$isActive = $_GET['is_active'] ?? '';

$accountTypes = $pdo->query("
    SELECT id, name
    FROM account_types
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$accountCategories = $pdo->query("
    SELECT id, name
    FROM account_categories
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$countries = $pdo->query("
    SELECT DISTINCT country
    FROM bank_accounts
    WHERE country IS NOT NULL AND country <> ''
    ORDER BY country ASC
")->fetchAll(PDO::FETCH_COLUMN);

$sql = "
    SELECT
        ba.*,
        at.name AS type_name,
        ac.name AS category_name
    FROM bank_accounts ba
    LEFT JOIN account_types at ON at.id = ba.account_type_id
    LEFT JOIN account_categories ac ON ac.id = ba.account_category_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        ba.account_name LIKE ?
        OR ba.account_number LIKE ?
        OR ba.bank_name LIKE ?
    )";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

if ($country !== '') {
    $sql .= " AND ba.country = ?";
    $params[] = $country;
}

if ($accountTypeId > 0) {
    $sql .= " AND ba.account_type_id = ?";
    $params[] = $accountTypeId;
}

if ($accountCategoryId > 0) {
    $sql .= " AND ba.account_category_id = ?";
    $params[] = $accountCategoryId;
}

if ($isActive === '1' || $isActive === '0') {
    $sql .= " AND ba.is_active = ?";
    $params[] = (int)$isActive;
}

$sql .= " ORDER BY ba.account_name ASC, ba.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAccounts = count($accounts);
$totalBalance = 0.0;
foreach ($accounts as $account) {
    $totalBalance += (float)$account['balance'];
}

$pageTitle = 'Comptes bancaires';
$pageSubtitle = 'Lecture structurée des comptes bancaires rattachés aux clients.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid">
            <div class="card">
                <h3>Comptes</h3>
                <div class="kpi"><?= $totalAccounts ?></div>
                <p class="muted">Résultats filtrés</p>
            </div>

            <div class="card">
                <h3>Solde cumulé</h3>
                <div class="kpi"><?= number_format($totalBalance, 2, ',', ' ') ?> €</div>
                <p class="muted">Total visible</p>
            </div>
        </div>

        <div class="form-card">
            <h3 class="section-title">Filtres</h3>

            <form method="GET" class="inline-form">
                <input type="text" name="search" placeholder="Nom, numéro, banque..." value="<?= e($search) ?>">

                <select name="country">
                    <option value="">Tous les pays</option>
                    <?php foreach ($countries as $countryItem): ?>
                        <option value="<?= e($countryItem) ?>" <?= $country === $countryItem ? 'selected' : '' ?>>
                            <?= e($countryItem) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="account_type_id">
                    <option value="0">Tous les types</option>
                    <?php foreach ($accountTypes as $type): ?>
                        <option value="<?= (int)$type['id'] ?>" <?= $accountTypeId === (int)$type['id'] ? 'selected' : '' ?>>
                            <?= e($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="account_category_id">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($accountCategories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" <?= $accountCategoryId === (int)$category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="is_active">
                    <option value="">Tous les états</option>
                    <option value="1" <?= $isActive === '1' ? 'selected' : '' ?>>Actifs</option>
                    <option value="0" <?= $isActive === '0' ? 'selected' : '' ?>>Inactifs</option>
                </select>

                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <a href="<?= e(APP_URL) ?>modules/treasury/bank_accounts.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <div class="table-card">
            <h3 class="section-title">Liste des comptes</h3>

            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Numéro</th>
                        <th>Pays</th>
                        <th>Type</th>
                        <th>Catégorie</th>
                        <th>Banque</th>
                        <th>Solde</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$accounts): ?>
                        <tr>
                            <td colspan="8">Aucun compte trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?= e($account['account_name'] ?? '—') ?></td>
                                <td><?= e($account['account_number'] ?? '—') ?></td>
                                <td><?= e($account['country'] ?? '—') ?></td>
                                <td><?= e($account['type_name'] ?? '—') ?></td>
                                <td><?= e($account['category_name'] ?? '—') ?></td>
                                <td><?= e($account['bank_name'] ?? '—') ?></td>
                                <td><?= number_format((float)$account['balance'], 2, ',', ' ') ?> €</td>
                                <td>
                                    <?php if ((int)$account['is_active'] === 1): ?>
                                        <span class="status-pill status-success">Actif</span>
                                    <?php else: ?>
                                        <span class="status-pill status-danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>