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
$pageSubtitle = 'Vue d’ensemble des clients, comptes 411, mensualités et statuts';

$canCreate = currentUserCan($pdo, 'clients_create') || currentUserCan($pdo, 'clients_manage') || currentUserCan($pdo, 'admin_manage');
$canEdit = currentUserCan($pdo, 'clients_edit') || currentUserCan($pdo, 'clients_manage') || currentUserCan($pdo, 'admin_manage');
$canDelete = currentUserCan($pdo, 'clients_delete') || currentUserCan($pdo, 'clients_manage') || currentUserCan($pdo, 'admin_manage');
$canArchive = currentUserCan($pdo, 'clients_archive') || currentUserCan($pdo, 'clients_edit') || currentUserCan($pdo, 'clients_manage') || currentUserCan($pdo, 'admin_manage');

$search = trim((string)($_GET['search'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$countryFilter = trim((string)($_GET['country'] ?? ''));

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];

$stats = [
    'total_clients' => 0,
    'active_clients' => 0,
    'inactive_clients' => 0,
    'total_initial_balance' => 0,
    'total_current_balance' => 0,
    'total_monthly_amount' => 0,
];

$typeStats = [];

if (tableExists($pdo, 'clients')) {
    $statsSql = "
        SELECT
            COUNT(*) AS total_clients,
            COALESCE(SUM(CASE WHEN COALESCE(c.is_active,1) = 1 THEN 1 ELSE 0 END), 0) AS active_clients,
            COALESCE(SUM(CASE WHEN COALESCE(c.is_active,1) = 0 THEN 1 ELSE 0 END), 0) AS inactive_clients,
            COALESCE(SUM(COALESCE(ba.initial_balance, 0)), 0) AS total_initial_balance,
            COALESCE(SUM(COALESCE(ba.balance, 0)), 0) AS total_current_balance,
            COALESCE(SUM(CASE WHEN COALESCE(c.monthly_enabled,0) = 1 THEN COALESCE(c.monthly_amount,0) ELSE 0 END), 0) AS total_monthly_amount
        FROM clients c
        LEFT JOIN (
            SELECT cba.client_id, ba.initial_balance, ba.balance
            FROM client_bank_accounts cba
            INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            INNER JOIN (
                SELECT client_id, MIN(id) AS first_link_id
                FROM client_bank_accounts
                GROUP BY client_id
            ) x ON x.client_id = cba.client_id AND x.first_link_id = cba.id
        ) ba ON ba.client_id = c.id
    ";

    $statsStmt = $pdo->query($statsSql);
    $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($statsRow) {
        $stats = $statsRow;
    }

    if (columnExists($pdo, 'clients', 'client_type')) {
        $typeStatsStmt = $pdo->query("
            SELECT
                COALESCE(client_type, 'Non renseigné') AS client_type,
                COUNT(*) AS total_count
            FROM clients
            GROUP BY client_type
            ORDER BY total_count DESC, client_type ASC
        ");
        $typeStats = $typeStatsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR c.email LIKE ?
        OR c.phone LIKE ?
        OR c.generated_client_account LIKE ?
    )";
    for ($i = 0; $i < 7; $i++) {
        $params[] = '%' . $search . '%';
    }
}

if ($typeFilter !== '') {
    $where[] = "c.client_type = ?";
    $params[] = $typeFilter;
}

if ($statusFilter === 'active') {
    $where[] = "COALESCE(c.is_active,1) = 1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "COALESCE(c.is_active,1) = 0";
}

if ($countryFilter !== '') {
    $where[] = "c.country_commercial = ?";
    $params[] = $countryFilter;
}

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ta2.account_code AS monthly_treasury_account_code,
        ta2.account_label AS monthly_treasury_account_label,
        ba.account_number AS bank_account_number,
        ba.account_name AS bank_account_name,
        COALESCE(ba.initial_balance, 0) AS bank_initial_balance,
        COALESCE(ba.balance, 0) AS bank_balance
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN treasury_accounts ta2 ON ta2.id = c.monthly_treasury_account_id
    LEFT JOIN (
        SELECT
            cba.client_id,
            ba.account_number,
            ba.account_name,
            ba.initial_balance,
            ba.balance
        FROM client_bank_accounts cba
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        INNER JOIN (
            SELECT client_id, MIN(id) AS first_link_id
            FROM client_bank_accounts
            GROUP BY client_id
        ) x ON x.client_id = cba.client_id AND x.first_link_id = cba.id
    ) ba ON ba.client_id = c.id
";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY c.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <?php if ($canCreate): ?>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_create.php" class="btn btn-success">Nouveau client</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="card">
                <div class="metric-label">Clients total</div>
                <div class="metric-value"><?= (int)($stats['total_clients'] ?? 0) ?></div>
            </div>

            <div class="card">
                <div class="metric-label">Clients actifs</div>
                <div class="metric-value"><?= (int)($stats['active_clients'] ?? 0) ?></div>
            </div>

            <div class="card">
                <div class="metric-label">Clients archivés</div>
                <div class="metric-value"><?= (int)($stats['inactive_clients'] ?? 0) ?></div>
            </div>

            <div class="card">
                <div class="metric-label">Mensualités actives</div>
                <div class="metric-value"><?= e(number_format((float)($stats['total_monthly_amount'] ?? 0), 2, ',', ' ')) ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3>Soldes clients</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Total soldes initiaux</span>
                        <strong><?= e(number_format((float)($stats['total_initial_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Total soldes courants</span>
                        <strong><?= e(number_format((float)($stats['total_current_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Types de clients</h3>
                <?php if ($typeStats): ?>
                    <div class="sl-data-list">
                        <?php foreach ($typeStats as $row): ?>
                            <div class="sl-data-list__row">
                                <span><?= e((string)($row['client_type'] ?? 'Non renseigné')) ?></span>
                                <strong><?= (int)($row['total_count'] ?? 0) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">Aucune donnée de typologie disponible.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <h3>Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code, nom, email, 411...">
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

                    <div>
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Archivé</option>
                        </select>
                    </div>

                    <div>
                        <label>Pays commercial</label>
                        <select name="country">
                            <option value="">Tous</option>
                            <?php foreach ($commercialCountries as $country): ?>
                                <option value="<?= e($country) ?>" <?= $countryFilter === $country ? 'selected' : '' ?>>
                                    <?= e($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Liste des clients</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom complet</th>
                            <th>Type</th>
                            <th>Compte 411</th>
                            <th>512 principal</th>
                            <th>Mensualité</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <?php
                            $isActive = (int)($client['is_active'] ?? 1) === 1;
                            $statusClass = $isActive ? 'badge badge-success' : 'badge badge-danger';
                            $statusLabel = $isActive ? 'Actif' : 'Archivé';

                            $treasuryDisplay = trim((string)(
                                (($client['treasury_account_code'] ?? '') !== '' ? ($client['treasury_account_code'] . ' - ') : '') .
                                ($client['treasury_account_label'] ?? '')
                            ));

                            $archiveUrl = e(APP_URL) . 'modules/clients/client_archive.php?id=' . (int)$client['id'];
                            ?>
                            <tr>
                                <td><?= e((string)($client['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($client['full_name'] ?? '')) ?></td>
                                <td><?= e((string)($client['client_type'] ?? '—')) ?></td>
                                <td><?= e((string)($client['generated_client_account'] ?? '')) ?></td>
                                <td><?= e($treasuryDisplay !== '' ? $treasuryDisplay : '—') ?></td>
                                <td>
                                    <?= e(number_format((float)($client['monthly_amount'] ?? 0), 2, ',', ' ')) ?>
                                    <?php if ((int)($client['monthly_enabled'] ?? 0) === 1): ?>
                                        <span class="badge badge-success" style="margin-left:6px;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(number_format((float)($client['bank_initial_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($client['bank_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                <td>
                                    <span class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group sl-btn-group-nowrap">
                                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline btn-sm">Voir</a>

                                        <?php if ($canEdit): ?>
                                            <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                        <?php endif; ?>

                                        <?php if ($canArchive): ?>
                                            <a
                                                href="<?= $archiveUrl ?>"
                                                class="btn <?= $isActive ? 'btn-warning' : 'btn-success' ?> btn-sm"
                                                onclick="return confirm('Confirmer cette action sur ce client ?');"
                                            >
                                                <?= $isActive ? 'Archiver' : 'Réactiver' ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($canDelete): ?>
                                            <a
                                                href="<?= e(APP_URL) ?>modules/clients/client_delete.php?id=<?= (int)$client['id'] ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Confirmer la suppression de ce client ?');"
                                            >
                                                Supprimer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$clients): ?>
                            <tr>
                                <td colspan="10">Aucun client trouvé.</td>
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