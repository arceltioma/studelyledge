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

$pageTitle = 'Comptes clients';
$pageSubtitle = 'Vue consolidée des comptes 411 liés aux clients, des soldes et des rattachements';

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];

$stats = [
    'total_accounts' => 0,
    'active_clients' => 0,
    'inactive_clients' => 0,
    'total_initial_balance' => 0,
    'total_current_balance' => 0,
];

$typeStats = [];

$baseSql = "
    FROM clients c
    LEFT JOIN (
        SELECT
            cba.client_id,
            ba.id AS bank_account_id,
            ba.account_number,
            ba.account_name,
            COALESCE(ba.initial_balance, 0) AS initial_balance,
            COALESCE(ba.balance, 0) AS balance,
            COALESCE(ba.is_active, 1) AS bank_is_active
        FROM client_bank_accounts cba
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        INNER JOIN (
            SELECT client_id, MIN(id) AS first_link_id
            FROM client_bank_accounts
            GROUP BY client_id
        ) x ON x.client_id = cba.client_id AND x.first_link_id = cba.id
    ) ba ON ba.client_id = c.id
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
";

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR c.generated_client_account LIKE ?
        OR ba.account_number LIKE ?
        OR ba.account_name LIKE ?
    )";
    for ($i = 0; $i < 7; $i++) {
        $params[] = '%' . $search . '%';
    }
}

if ($statusFilter === 'active') {
    $where[] = "COALESCE(c.is_active,1) = 1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "COALESCE(c.is_active,1) = 0";
}

if ($typeFilter !== '') {
    $where[] = "c.client_type = ?";
    $params[] = $typeFilter;
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$statsSql = "
    SELECT
        COUNT(*) AS total_accounts,
        COALESCE(SUM(CASE WHEN COALESCE(c.is_active,1)=1 THEN 1 ELSE 0 END), 0) AS active_clients,
        COALESCE(SUM(CASE WHEN COALESCE(c.is_active,1)=0 THEN 1 ELSE 0 END), 0) AS inactive_clients,
        COALESCE(SUM(COALESCE(ba.initial_balance,0)), 0) AS total_initial_balance,
        COALESCE(SUM(COALESCE(ba.balance,0)), 0) AS total_current_balance
    {$baseSql}
";

$stmtStats = $pdo->prepare($statsSql);
$stmtStats->execute();
$statsRow = $stmtStats->fetch(PDO::FETCH_ASSOC);
if ($statsRow) {
    $stats = $statsRow;
}

if (tableExists($pdo, 'clients') && columnExists($pdo, 'clients', 'client_type')) {
    $stmtTypeStats = $pdo->query("
        SELECT
            COALESCE(client_type, 'Non renseigné') AS client_type,
            COUNT(*) AS total_count
        FROM clients
        GROUP BY client_type
        ORDER BY total_count DESC, client_type ASC
    ");
    $typeStats = $stmtTypeStats->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$listSql = "
    SELECT
        c.id AS client_id,
        c.client_code,
        c.full_name,
        c.first_name,
        c.last_name,
        c.client_type,
        c.generated_client_account,
        c.currency,
        c.is_active,
        c.monthly_amount,
        c.monthly_enabled,
        ba.bank_account_id,
        ba.account_number,
        ba.account_name,
        ba.initial_balance,
        ba.balance,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
    {$baseSql}
    {$whereSql}
    ORDER BY c.id DESC
";

$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="card">
                <div class="metric-label">Comptes clients</div>
                <div class="metric-value"><?= (int)($stats['total_accounts'] ?? 0) ?></div>
            </div>
            <div class="card">
                <div class="metric-label">Clients actifs</div>
                <div class="metric-value"><?= (int)($stats['active_clients'] ?? 0) ?></div>
            </div>
            <div class="card">
                <div class="metric-label">Soldes initiaux</div>
                <div class="metric-value"><?= e(number_format((float)($stats['total_initial_balance'] ?? 0), 2, ',', ' ')) ?></div>
            </div>
            <div class="card">
                <div class="metric-label">Soldes courants</div>
                <div class="metric-value"><?= e(number_format((float)($stats['total_current_balance'] ?? 0), 2, ',', ' ')) ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3>Répartition statutaire</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Clients actifs</span>
                        <strong><?= (int)($stats['active_clients'] ?? 0) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Clients archivés</span>
                        <strong><?= (int)($stats['inactive_clients'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Types de clients</h3>
                <?php if ($typeStats): ?>
                    <div class="sl-data-list">
                        <?php foreach ($typeStats as $item): ?>
                            <div class="sl-data-list__row">
                                <span><?= e((string)($item['client_type'] ?? 'Non renseigné')) ?></span>
                                <strong><?= (int)($item['total_count'] ?? 0) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">Aucune donnée disponible.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <h3>Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code client, nom, compte 411...">
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Archivés</option>
                        </select>
                    </div>

                    <div>
                        <label>Type client</label>
                        <select name="type">
                            <option value="">Tous</option>
                            <?php foreach ($clientTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Liste des comptes clients</h3>

            <div class="table-responsive">
                <table class="modern-table sl-compact-table">
                    <thead>
                        <tr>
                            <th>Code client</th>
                            <th>Nom complet</th>
                            <th>Type</th>
                            <th>Compte 411</th>
                            <th>Compte bancaire lié</th>
                            <th>512 principal</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Mensualité</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $isActive = (int)($row['is_active'] ?? 1) === 1;
                            $statusClass = $isActive ? 'badge badge-success' : 'badge badge-danger';
                            $statusLabel = $isActive ? 'Actif' : 'Archivé';

                            $treasuryDisplay = trim((string)(
                                (($row['treasury_account_code'] ?? '') !== '' ? ($row['treasury_account_code'] . ' - ') : '') .
                                ($row['treasury_account_label'] ?? '')
                            ));

                            $bankDisplay = trim((string)(
                                (($row['account_number'] ?? '') !== '' ? ($row['account_number'] . ' - ') : '') .
                                ($row['account_name'] ?? '')
                            ));
                            ?>
                            <tr>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '')) ?></td>
                                <td><?= e((string)($row['client_type'] ?? '—')) ?></td>
                                <td><?= e((string)($row['generated_client_account'] ?? '')) ?></td>
                                <td><?= e($bankDisplay !== '' ? $bankDisplay : '—') ?></td>
                                <td><?= e($treasuryDisplay !== '' ? $treasuryDisplay : '—') ?></td>
                                <td><?= e(number_format((float)($row['initial_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($row['balance'] ?? 0), 2, ',', ' ')) ?></td>
                                <td>
                                    <?= e(number_format((float)($row['monthly_amount'] ?? 0), 2, ',', ' ')) ?>
                                    <?php if ((int)($row['monthly_enabled'] ?? 0) === 1): ?>
                                        <span class="badge badge-success" style="margin-left:6px;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                <td>
                                    <div class="btn-group sl-btn-group-nowrap">
                                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['client_id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                                        <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$row['client_id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="11">Aucun compte client trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            .sl-btn-group-nowrap {
                display: flex;
                flex-wrap: nowrap;
                gap: 6px;
                align-items: center;
            }
            .sl-btn-group-nowrap .btn {
                white-space: nowrap;
            }
            .sl-compact-table th,
            .sl-compact-table td {
                padding-top: 8px;
                padding-bottom: 8px;
                vertical-align: middle;
            }
            .badge-danger {
                background: #dc2626;
                color: #fff;
                padding: 4px 8px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
                display: inline-block;
            }
        </style>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>