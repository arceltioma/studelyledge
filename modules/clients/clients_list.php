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

$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';

    $searchParts = [
        "c.client_code LIKE ?",
        "c.full_name LIKE ?",
        "c.first_name LIKE ?",
        "c.last_name LIKE ?",
        "c.email LIKE ?",
        "c.phone LIKE ?",
        "c.generated_client_account LIKE ?"
    ];
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);

    if (columnExists($pdo, 'clients', 'postal_address')) {
        $searchParts[] = "c.postal_address LIKE ?";
        $params[] = $like;
    }

    if (tableExists($pdo, 'bank_accounts')) {
        $searchParts[] = "ba.account_number LIKE ?";
        $searchParts[] = "ba.account_name LIKE ?";
        $params[] = $like;
        $params[] = $like;
    }

    $where[] = '(' . implode(' OR ', $searchParts) . ')';
}

if ($statusFilter !== '') {
    if ($statusFilter === 'active') {
        $where[] = "COALESCE(c.is_active,1) = 1";
    } elseif ($statusFilter === 'archived') {
        $where[] = "COALESCE(c.is_active,1) = 0";
    }
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "DATE(c.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "DATE(c.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ba.id AS linked_bank_account_id,
        ba.account_name AS linked_bank_account_name,
        ba.account_number AS linked_bank_account_number,
        ba.initial_balance AS linked_bank_initial_balance,
        ba.balance AS linked_bank_balance
    FROM clients c
    LEFT JOIN treasury_accounts ta
        ON ta.id = c.initial_treasury_account_id
    LEFT JOIN client_bank_accounts cba
        ON cba.client_id = c.id
    LEFT JOIN bank_accounts ba
        ON ba.id = cba.bank_account_id
    WHERE {$whereSql}
    ORDER BY c.client_code ASC, c.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rowsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Déduplication par client
|--------------------------------------------------------------------------
*/
$rows = [];
foreach ($rowsRaw as $row) {
    $clientId = (int)($row['id'] ?? 0);
    if ($clientId <= 0) {
        continue;
    }

    if (!isset($rows[$clientId])) {
        $rows[$clientId] = $row;
    } else {
        $currentHasBank = !empty($rows[$clientId]['linked_bank_account_id']);
        $newHasBank = !empty($row['linked_bank_account_id']);

        if (!$currentHasBank && $newHasBank) {
            $rows[$clientId] = $row;
        }
    }
}
$rows = array_values($rows);

/*
|--------------------------------------------------------------------------
| Dashboard stats
|--------------------------------------------------------------------------
*/
$statsWhere = ['1=1'];
$statsParams = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $statsParts = [
        "c.client_code LIKE ?",
        "c.full_name LIKE ?",
        "c.first_name LIKE ?",
        "c.last_name LIKE ?",
        "c.email LIKE ?",
        "c.phone LIKE ?",
        "c.generated_client_account LIKE ?"
    ];
    $statsParams = array_merge($statsParams, [$like, $like, $like, $like, $like, $like, $like]);

    if (columnExists($pdo, 'clients', 'postal_address')) {
        $statsParts[] = "c.postal_address LIKE ?";
        $statsParams[] = $like;
    }

    $statsWhere[] = '(' . implode(' OR ', $statsParts) . ')';
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $statsWhere[] = "DATE(c.created_at) >= ?";
    $statsParams[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $statsWhere[] = "DATE(c.created_at) <= ?";
    $statsParams[] = $dateTo;
}

$statsSql = "
    SELECT
        COUNT(*) AS total_clients,
        SUM(CASE WHEN COALESCE(c.is_active,1) = 1 THEN 1 ELSE 0 END) AS active_clients,
        SUM(CASE WHEN COALESCE(c.is_active,1) = 0 THEN 1 ELSE 0 END) AS archived_clients
    FROM clients c
    WHERE " . implode(' AND ', $statsWhere);

$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$bankStatsSql = "
    SELECT
        COUNT(DISTINCT c.id) AS clients_with_411,
        COALESCE(SUM(ba.initial_balance), 0) AS total_initial_balance,
        COALESCE(SUM(ba.balance), 0) AS total_current_balance
    FROM clients c
    LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
    LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
    WHERE " . implode(' AND ', $statsWhere);

$bankStatsStmt = $pdo->prepare($bankStatsSql);
$bankStatsStmt->execute($statsParams);
$bankStats = $bankStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$pageTitle = 'Clients';
$pageSubtitle = 'Recherche, lecture rapide, dashboard et gestion homogène des clients.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_create_page') : currentUserCan($pdo, 'clients_create')): ?>
                    <a class="btn btn-primary" href="<?= APP_URL ?>modules/clients/client_create.php">Nouveau client</a>
                <?php endif; ?>

                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'imports_upload_page') : currentUserCan($pdo, 'clients_create')): ?>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/import_clients_csv.php">Import CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="card stat-card">
                <div class="stat-title">Clients</div>
                <div class="stat-value"><?= (int)($stats['total_clients'] ?? 0) ?></div>
                <div class="stat-subtitle">Résultats sur le périmètre filtré</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Actifs</div>
                <div class="stat-value"><?= (int)($stats['active_clients'] ?? 0) ?></div>
                <div class="stat-subtitle">Clients exploitables</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Archivés</div>
                <div class="stat-value"><?= (int)($stats['archived_clients'] ?? 0) ?></div>
                <div class="stat-subtitle">Clients inactifs</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Comptes 411 liés</div>
                <div class="stat-value"><?= (int)($bankStats['clients_with_411'] ?? 0) ?></div>
                <div class="stat-subtitle">Liaisons client_bank_accounts</div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card stat-card">
                <div class="stat-title">Total soldes initiaux 411</div>
                <div class="stat-value" style="font-size:1.5rem;">
                    <?= number_format((float)($bankStats['total_initial_balance'] ?? 0), 2, ',', ' ') ?>
                </div>
                <div class="stat-subtitle">Somme des initial_balance</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Total soldes courants 411</div>
                <div class="stat-value" style="font-size:1.5rem;">
                    <?= number_format((float)($bankStats['total_current_balance'] ?? 0), 2, ',', ' ') ?>
                </div>
                <div class="stat-subtitle">Somme des balance</div>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="dashboard-grid-4">
                <div>
                    <label>Recherche</label>
                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="code, nom, email, téléphone, adresse, compte 411..."
                    >
                </div>

                <div>
                    <label>Créé du</label>
                    <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div>
                    <label>Créé au</label>
                    <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                </div>

                <div>
                    <label>Statut</label>
                    <select name="status">
                        <option value="">Tous</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actifs</option>
                        <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archivés</option>
                    </select>
                </div>

                <div class="btn-group" style="margin-top:26px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= APP_URL ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code client</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Adresse postale</th>
                            <th>Compte 411</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Compte 512 lié</th>
                            <th>État</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $isActive = ((int)($row['is_active'] ?? 1) === 1); ?>
                            <tr>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '')) ?></td>
                                <td><?= e((string)($row['email'] ?? '')) ?></td>
                                <td><?= e((string)($row['phone'] ?? '')) ?></td>
                                <td><?= columnExists($pdo, 'clients', 'postal_address') ? e((string)($row['postal_address'] ?? '')) : '' ?></td>
                                <td><?= e((string)($row['generated_client_account'] ?? ($row['linked_bank_account_number'] ?? ''))) ?></td>
                                <td><?= number_format((float)($row['linked_bank_initial_balance'] ?? 0), 2, ',', ' ') ?></td>
                                <td><?= number_format((float)($row['linked_bank_balance'] ?? 0), 2, ',', ' ') ?></td>
                                <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                                <td>
                                    <span class="status-pill <?= $isActive ? 'status-success' : 'status-warning' ?>">
                                        <?= $isActive ? 'Actif' : 'Archivé' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-primary" href="<?= APP_URL ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">Voir</a>

                                        <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_edit_page') : currentUserCan($pdo, 'clients_edit')): ?>
                                            <a class="btn btn-success" href="<?= APP_URL ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                        <?php endif; ?>

                                        <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_archive_page') : currentUserCan($pdo, 'clients_edit')): ?>
                                            <?php if ($isActive): ?>
                                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=archive">Archiver</a>
                                            <?php else: ?>
                                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=restore">Réactiver</a>
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