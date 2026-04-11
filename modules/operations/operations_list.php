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
$pageSubtitle = 'Suivi, contrôle, dashboard et audit des opérations';

$search   = trim((string)($_GET['search'] ?? ''));
$type     = trim((string)($_GET['type'] ?? ''));
$service  = trim((string)($_GET['service'] ?? ''));
$client   = trim((string)($_GET['client'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(o.reference LIKE ? OR o.label LIKE ? OR o.debit_account_code LIKE ? OR o.credit_account_code LIKE ? OR c.client_code LIKE ? OR c.full_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($type !== '') {
    $where[] = "o.operation_type_code = ?";
    $params[] = $type;
}

if ($service !== '') {
    $where[] = "rs.code = ?";
    $params[] = $service;
}

if ($client !== '') {
    $where[] = "c.id = ?";
    $params[] = (int)$client;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "o.operation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "o.operation_date <= ?";
    $params[] = $dateTo;
}

$whereSQL = implode(' AND ', $where);

$sql = "
    SELECT
        o.*,
        c.client_code,
        c.full_name,
        rs.label AS service_label,
        rs.code AS service_code,
        rot.label AS type_label
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN ref_services rs ON rs.id = o.service_id
    LEFT JOIN ref_operation_types rot ON rot.id = o.operation_type_id
    WHERE {$whereSQL}
    ORDER BY o.operation_date DESC, o.id DESC
    LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsSql = "
    SELECT
        COUNT(*) AS total_operations,
        COALESCE(SUM(o.amount), 0) AS total_amount,
        COALESCE(SUM(CASE WHEN COALESCE(o.is_manual_accounting,0) = 1 THEN 1 ELSE 0 END), 0) AS manual_operations,
        COALESCE(SUM(CASE WHEN COALESCE(o.source_type,'') = 'manual' THEN 1 ELSE 0 END), 0) AS source_manual_operations
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN ref_services rs ON rs.id = o.service_id
    WHERE {$whereSQL}
";
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$creditStatsSql = "
    SELECT
        COALESCE(SUM(CASE WHEN o.amount > 0 THEN o.amount ELSE 0 END), 0) AS total_positive_amount,
        COUNT(DISTINCT o.client_id) AS impacted_clients
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN ref_services rs ON rs.id = o.service_id
    WHERE {$whereSQL}
";
$creditStatsStmt = $pdo->prepare($creditStatsSql);
$creditStatsStmt->execute($params);
$creditStats = $creditStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$types = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("SELECT code, label FROM ref_operation_types WHERE COALESCE(is_active,1)=1 ORDER BY label")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("SELECT code, label FROM ref_services WHERE COALESCE(is_active,1)=1 ORDER BY label")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("SELECT id, client_code, full_name FROM clients WHERE COALESCE(is_active,1)=1 ORDER BY client_code")->fetchAll(PDO::FETCH_ASSOC)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/operations/operation_create.php" class="btn btn-success">+ Nouvelle opération</a>
            </div>
        </div>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="card stat-card">
                <div class="stat-title">Opérations</div>
                <div class="stat-value"><?= (int)($stats['total_operations'] ?? 0) ?></div>
                <div class="stat-subtitle">Résultats filtrés</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Montant total</div>
                <div class="stat-value" style="font-size:1.4rem;"><?= number_format((float)($stats['total_amount'] ?? 0), 2, ',', ' ') ?></div>
                <div class="stat-subtitle">Somme des montants</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Écritures manuelles</div>
                <div class="stat-value"><?= (int)($stats['manual_operations'] ?? 0) ?></div>
                <div class="stat-subtitle">is_manual_accounting = 1</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Source manuelle</div>
                <div class="stat-value"><?= (int)($stats['source_manual_operations'] ?? 0) ?></div>
                <div class="stat-subtitle">source_type = manual</div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card stat-card">
                <div class="stat-title">Clients impactés</div>
                <div class="stat-value"><?= (int)($creditStats['impacted_clients'] ?? 0) ?></div>
                <div class="stat-subtitle">Clients distincts concernés</div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Montants positifs</div>
                <div class="stat-value" style="font-size:1.4rem;"><?= number_format((float)($creditStats['total_positive_amount'] ?? 0), 2, ',', ' ') ?></div>
                <div class="stat-subtitle">Cumul des montants > 0</div>
            </div>
        </div>

        <div class="card">
            <form method="GET" class="dashboard-grid-4">
                <div>
                    <label>Recherche</label>
                    <input type="text" name="search" placeholder="Référence, libellé, comptes..." value="<?= e($search) ?>">
                </div>

                <div>
                    <label>Type</label>
                    <select name="type">
                        <option value="">Tous</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t['code']) ?>" <?= $type === $t['code'] ? 'selected' : '' ?>>
                                <?= e($t['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Service</label>
                    <select name="service">
                        <option value="">Tous</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= e($s['code']) ?>" <?= $service === $s['code'] ? 'selected' : '' ?>>
                                <?= e($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Client</label>
                    <select name="client">
                        <option value="">Tous</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $client == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['client_code'] . ' - ' . $c['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Date du</label>
                    <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div>
                    <label>Date au</label>
                    <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                </div>

                <div class="btn-group" style="margin-top:26px;">
                    <button class="btn btn-primary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Résultats (<?= count($operations) ?>)</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Service</th>
                            <th>Montant</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operations as $op): ?>
                            <tr>
                                <td><?= e((string)$op['operation_date']) ?></td>
                                <td><?= e(trim((string)($op['client_code'] ?? '') . ' - ' . (string)($op['full_name'] ?? ''))) ?></td>
                                <td><?= e((string)($op['type_label'] ?? $op['operation_type_code'] ?? '')) ?></td>
                                <td><?= e((string)($op['service_label'] ?? $op['service_code'] ?? '')) ?></td>
                                <td class="amount"><?= number_format((float)$op['amount'], 2, ',', ' ') ?></td>
                                <td><?= e((string)($op['debit_account_code'] ?? '')) ?></td>
                                <td><?= e((string)($op['credit_account_code'] ?? '')) ?></td>
                                <td class="actions">
                                    <a href="<?= e(APP_URL) ?>modules/operations/operation_view.php?id=<?= (int)$op['id'] ?>" class="btn btn-sm">Voir</a>
                                    <a href="<?= e(APP_URL) ?>modules/operations/operation_edit.php?id=<?= (int)$op['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                    <a href="<?= e(APP_URL) ?>modules/operations/operation_delete.php?id=<?= (int)$op['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Confirmer ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$operations): ?>
                            <tr>
                                <td colspan="8">Aucune opération trouvée.</td>
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