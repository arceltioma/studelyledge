<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$pageTitle = 'Clients';
$pageSubtitle = 'Liste compacte des clients, comptes 411, trésorerie liée et actions rapides.';

if (!function_exists('cl_like')) {
    function cl_like(string $value): string
    {
        return '%' . trim($value) . '%';
    }
}

if (!function_exists('cl_money')) {
    function cl_money(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('cl_can_access')) {
    function cl_can_access(PDO $pdo, string $accessKey, string $fallbackPermission = ''): bool
    {
        if (function_exists('studelyCanAccess')) {
            return studelyCanAccess($pdo, $accessKey);
        }

        if ($fallbackPermission !== '' && function_exists('currentUserCan')) {
            return currentUserCan($pdo, $fallbackPermission);
        }

        return true;
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterCountry = trim((string)($_GET['filter_country'] ?? ''));
$filterType = trim((string)($_GET['filter_type'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? 'active'));

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ta.currency_code AS treasury_currency_code,
        ba.account_number AS bank_account_number,
        ba.account_name AS bank_account_name,
        ba.balance AS bank_balance,
        ba.initial_balance AS bank_initial_balance
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
    WHERE 1=1
";
$params = [];

if ($filterSearch !== '') {
    $sql .= "
        AND (
            c.client_code LIKE ?
            OR c.first_name LIKE ?
            OR c.last_name LIKE ?
            OR c.full_name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
            OR c.generated_client_account LIKE ?
        )
    ";
    for ($i = 0; $i < 7; $i++) {
        $params[] = cl_like($filterSearch);
    }
}

if ($filterCountry !== '') {
    $sql .= " AND COALESCE(c.country_commercial, '') = ? ";
    $params[] = $filterCountry;
}

if ($filterType !== '') {
    $sql .= " AND COALESCE(c.client_type, '') = ? ";
    $params[] = $filterType;
}

if ($filterStatus === 'active') {
    $sql .= " AND COALESCE(c.is_active, 1) = 1 ";
} elseif ($filterStatus === 'archived') {
    $sql .= " AND COALESCE(c.is_active, 1) = 0 ";
}

$sql .= " ORDER BY c.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countryOptions = [];
if (tableExists($pdo, 'clients') && columnExists($pdo, 'clients', 'country_commercial')) {
    $countryOptions = $pdo->query("
        SELECT DISTINCT country_commercial
        FROM clients
        WHERE COALESCE(country_commercial, '') <> ''
        ORDER BY country_commercial ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

$typeOptions = [];
if (tableExists($pdo, 'clients') && columnExists($pdo, 'clients', 'client_type')) {
    $typeOptions = $pdo->query("
        SELECT DISTINCT client_type
        FROM clients
        WHERE COALESCE(client_type, '') <> ''
        ORDER BY client_type ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

$totalClients = count($rows);
$totalActive = count(array_filter($rows, static fn(array $r): bool => (int)($r['is_active'] ?? 1) === 1));
$totalArchived = count(array_filter($rows, static fn(array $r): bool => (int)($r['is_active'] ?? 1) !== 1));
$total411Balance = array_sum(array_map(static fn(array $r): float => (float)($r['bank_balance'] ?? 0), $rows));
$totalInitial411Balance = array_sum(array_map(static fn(array $r): float => (float)($r['bank_initial_balance'] ?? 0), $rows));

$canCreate = cl_can_access($pdo, 'clients_create_page', 'clients_create');
$canEdit = cl_can_access($pdo, 'clients_edit_page', 'clients_edit');
$canArchive = cl_can_access($pdo, 'clients_archive_page', 'clients_archive');

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="success"><?= e((string)$_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="error"><?= e((string)$_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Synthèse clients</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Total affiché</span>
                        <strong><?= (int)$totalClients ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Clients actifs</span>
                        <strong><?= (int)$totalActive ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Clients archivés</span>
                        <strong><?= (int)$totalArchived ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Total soldes initiaux 411</span>
                        <strong><?= e(cl_money((float)$totalInitial411Balance)) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Total soldes courants 411</span>
                        <strong><?= e(cl_money((float)$total411Balance)) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Actions rapides</h3>

                <div class="btn-group" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <?php if ($canCreate): ?>
                        <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/clients/client_create.php">
                            Nouveau client
                        </a>

                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">
                            Import clients
                        </a>
                    <?php endif; ?>

                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_accounts.php">
                        Comptes clients 411
                    </a>
                </div>

                <div class="dashboard-note" style="margin-top:16px;">
                    Cette vue centralise les informations essentielles du client, son compte 411 généré et sa trésorerie liée.
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <h3 class="section-title">Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-2">
                    <div>
                        <label>Recherche</label>
                        <input
                            type="text"
                            name="filter_search"
                            value="<?= e($filterSearch) ?>"
                            placeholder="Code, nom, email, téléphone, compte 411..."
                        >
                    </div>

                    <div>
                        <label>Pays commercial</label>
                        <select name="filter_country">
                            <option value="">Tous</option>
                            <?php foreach ($countryOptions as $country): ?>
                                <option value="<?= e((string)$country) ?>" <?= $filterCountry === (string)$country ? 'selected' : '' ?>>
                                    <?= e((string)$country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type client</label>
                        <select name="filter_type">
                            <option value="">Tous</option>
                            <?php foreach ($typeOptions as $typeOption): ?>
                                <option value="<?= e((string)$typeOption) ?>" <?= $filterType === (string)$typeOption ? 'selected' : '' ?>>
                                    <?= e((string)$typeOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="filter_status">
                            <option value="">Tous</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archivés</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="page-title page-title-inline">
                <div>
                    <h3 class="section-title">Liste des clients</h3>
                    <p class="muted">Vue compacte et actionnable des comptes clients.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Pays</th>
                            <th>Type</th>
                            <th>Compte 411</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e((string)($row['client_code'] ?? '')) ?></strong>
                                </td>

                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <strong><?= e((string)($row['full_name'] ?? '')) ?></strong>
                                        <span class="muted"><?= e(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''))) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <span><?= e((string)($row['email'] ?? '—')) ?></span>
                                        <span class="muted"><?= e((string)($row['phone'] ?? '—')) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <span><?= e((string)($row['country_commercial'] ?? '—')) ?></span>
                                        <span class="muted"><?= e((string)($row['country_destination'] ?? '—')) ?></span>
                                    </div>
                                </td>

                                <td><?= e((string)($row['client_type'] ?? '—')) ?></td>

                                <td>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <strong><?= e((string)($row['generated_client_account'] ?? '—')) ?></strong>
                                        <span class="muted"><?= e((string)($row['bank_account_name'] ?? 'Compte client interne')) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <?= e(cl_money((float)($row['bank_initial_balance'] ?? 0))) ?>
                                </td>

                                <td>
                                    <strong><?= e(cl_money((float)($row['bank_balance'] ?? 0))) ?></strong>
                                </td>

                                <td>
                                    <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                        <span class="badge badge-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Archivé</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="btn-group" style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">
                                            Voir
                                        </a>

                                        <?php if ($canEdit): ?>
                                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>">
                                                Modifier
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($canArchive): ?>
                                            <?php if ((int)($row['is_active'] ?? 1) === 1): ?>
                                                <a
                                                    class="btn btn-danger"
                                                    href="<?= e(APP_URL) ?>modules/clients/archive_client.php?id=<?= (int)$row['id'] ?>"
                                                    onclick="return confirm('Archiver ce client ?');"
                                                >
                                                    Archiver
                                                </a>
                                            <?php else: ?>
                                                <a
                                                    class="btn btn-success"
                                                    href="<?= e(APP_URL) ?>modules/clients/archive_client.php?id=<?= (int)$row['id'] ?>&restore=1"
                                                    onclick="return confirm('Réactiver ce client ?');"
                                                >
                                                    Réactiver
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="11">Aucun client trouvé.</td>
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