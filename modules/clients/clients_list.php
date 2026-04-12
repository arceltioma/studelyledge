<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$pageTitle = 'Liste des clients';
$pageSubtitle = 'Recherche, suivi et consultation des comptes 411, rattachements 512 et mensualités';

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$monthly = trim((string) ($_GET['monthly'] ?? ''));
$countryCommercial = trim((string) ($_GET['country_commercial'] ?? ''));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = "(
        c.client_code LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR c.full_name LIKE ?
        OR c.email LIKE ?
        OR c.phone LIKE ?
        OR c.generated_client_account LIKE ?
    )";
    for ($i = 0; $i < 7; $i++) {
        $params[] = '%' . $search . '%';
    }
}

if ($status === 'active') {
    $where[] = "COALESCE(c.is_active,1) = 1";
} elseif ($status === 'inactive') {
    $where[] = "COALESCE(c.is_active,1) = 0";
}

if ($monthly === 'enabled') {
    $where[] = "COALESCE(c.monthly_enabled,0) = 1";
} elseif ($monthly === 'disabled') {
    $where[] = "COALESCE(c.monthly_enabled,0) = 0";
}

if ($countryCommercial !== '') {
    $where[] = "COALESCE(c.country_commercial,'') = ?";
    $params[] = $countryCommercial;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ta2.account_code AS monthly_treasury_account_code,
        ta2.account_label AS monthly_treasury_account_label
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN treasury_accounts ta2 ON ta2.id = c.monthly_treasury_account_id
    WHERE {$whereSql}
    ORDER BY c.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$countries = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT country_commercial
        FROM clients
        WHERE country_commercial IS NOT NULL
          AND country_commercial <> ''
        ORDER BY country_commercial ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/clients/client_create.php" class="btn btn-success">+ Nouveau client</a>
            </div>
        </div>

        <div class="card">
            <form method="GET" class="dashboard-grid-4">
                <div>
                    <label>Recherche</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code, nom, email, téléphone, compte 411...">
                </div>

                <div>
                    <label>Statut client</label>
                    <select name="status">
                        <option value="">Tous</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actifs</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                    </select>
                </div>

                <div>
                    <label>Mensualité</label>
                    <select name="monthly">
                        <option value="">Toutes</option>
                        <option value="enabled" <?= $monthly === 'enabled' ? 'selected' : '' ?>>Actives</option>
                        <option value="disabled" <?= $monthly === 'disabled' ? 'selected' : '' ?>>Inactives</option>
                    </select>
                </div>

                <div>
                    <label>Pays commercial</label>
                    <select name="country_commercial">
                        <option value="">Tous</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= e((string) $country) ?>" <?= $countryCommercial === (string) $country ? 'selected' : '' ?>>
                                <?= e((string) $country) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="btn-group" style="margin-top:26px;">
                    <button class="btn btn-primary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Clients (<?= count($clients) ?>)</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom complet</th>
                            <th>411</th>
                            <th>512 principal</th>
                            <th>Mensualité</th>
                            <th>512 mensualité</th>
                            <th>Jour</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?= e((string) ($client['client_code'] ?? '')) ?></td>
                                <td><?= e((string) ($client['full_name'] ?? '')) ?></td>
                                <td><?= e((string) ($client['generated_client_account'] ?? '')) ?></td>
                                <td><?= e((string) ($client['treasury_account_code'] ?? '—')) ?></td>
                                <td><?= e(number_format((float) ($client['monthly_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e((string) ($client['monthly_treasury_account_code'] ?? '—')) ?></td>
                                <td><?= e((string) ($client['monthly_day'] ?? '26')) ?></td>
                                <td>
                                    <?php
                                        $labels = [];
                                        $labels[] = ((int) ($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Inactif';
                                        $labels[] = ((int) ($client['monthly_enabled'] ?? 0) === 1) ? 'Mensualité ON' : 'Mensualité OFF';
                                        echo e(implode(' / ', $labels));
                                    ?>
                                </td>
                                <td class="actions">
                                    <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int) $client['id'] ?>" class="btn btn-sm">Voir</a>
                                    <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int) $client['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$clients): ?>
                            <tr>
                                <td colspan="9">Aucun client trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>