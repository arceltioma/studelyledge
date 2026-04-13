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
$pageSubtitle = 'Suivi global des opérations avec aperçu comptable et compte bancaire 512';

$canCreate = currentUserCan($pdo, 'operations_create') || currentUserCan($pdo, 'operations_manage') || currentUserCan($pdo, 'admin_manage');
$canEdit = currentUserCan($pdo, 'operations_edit') || currentUserCan($pdo, 'operations_manage') || currentUserCan($pdo, 'admin_manage');
$canDelete = currentUserCan($pdo, 'operations_delete') || currentUserCan($pdo, 'operations_manage') || currentUserCan($pdo, 'admin_manage');

$search = trim((string)($_GET['search'] ?? ''));
$typeFilter = trim((string)($_GET['type'] ?? ''));
$serviceFilter = trim((string)($_GET['service'] ?? ''));
$clientFilter = trim((string)($_GET['client_id'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$stats = [
    'total_operations' => 0,
    'total_amount' => 0,
    'month_operations' => 0,
    'month_amount' => 0,
];

if (tableExists($pdo, 'operations')) {
    $statsSql = "
        SELECT
            COUNT(*) AS total_operations,
            COALESCE(SUM(amount), 0) AS total_amount,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(operation_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END), 0) AS month_operations,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(operation_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN amount ELSE 0 END), 0) AS month_amount
        FROM operations
    ";

    $statsStmt = $pdo->query($statsSql);
    $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($statsRow) {
        $stats = $statsRow;
    }
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_services
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        o.label LIKE ?
        OR o.reference LIKE ?
        OR o.notes LIKE ?
        OR o.operation_type_code LIKE ?
        OR c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR rot.label LIKE ?
        OR rs.label LIKE ?
        OR lta.account_code LIKE ?
        OR lta.account_label LIKE ?
    )";
    for ($i = 0; $i < 10; $i++) {
        $params[] = '%' . $search . '%';
    }
}

if ($typeFilter !== '') {
    $where[] = "o.operation_type_id = ?";
    $params[] = (int)$typeFilter;
}

if ($serviceFilter !== '') {
    $where[] = "o.service_id = ?";
    $params[] = (int)$serviceFilter;
}

if ($clientFilter !== '') {
    $where[] = "o.client_id = ?";
    $params[] = (int)$clientFilter;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "o.operation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "o.operation_date <= ?";
    $params[] = $dateTo;
}

$sql = "
    SELECT
        o.*,
        c.client_code,
        c.full_name AS client_full_name,
        c.generated_client_account,
        rot.code AS operation_type_code_ref,
        rot.label AS operation_type_label,
        rs.code AS service_code_ref,
        rs.label AS service_label,
        lta.id AS linked_treasury_account_id,
        lta.account_code AS linked_treasury_account_code,
        lta.account_label AS linked_treasury_account_label,
        lba.account_number AS linked_bank_account_number,
        lba.account_name AS linked_bank_account_name,
        mba.account_number AS main_bank_account_number,
        mba.account_name AS main_bank_account_name
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN ref_operation_types rot ON rot.id = o.operation_type_id
    LEFT JOIN ref_services rs ON rs.id = o.service_id
    LEFT JOIN treasury_accounts lta ON lta.id = o.linked_bank_account_id
    LEFT JOIN bank_accounts lba ON lba.id = o.linked_bank_account_id
    LEFT JOIN bank_accounts mba ON mba.id = o.bank_account_id
";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY o.operation_date DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">
            <div class="btn-group">
                <?php if ($canCreate): ?>
                    <a href="<?= e(APP_URL) ?>modules/operations/operation_create.php" class="btn btn-success">Nouvelle opération</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="card">
                <div class="metric-label">Total opérations</div>
                <div class="metric-value"><?= (int)($stats['total_operations'] ?? 0) ?></div>
            </div>

            <div class="card">
                <div class="metric-label">Montant cumulé</div>
                <div class="metric-value"><?= e(number_format((float)($stats['total_amount'] ?? 0), 2, ',', ' ')) ?></div>
            </div>

            <div class="card">
                <div class="metric-label">Opérations du mois</div>
                <div class="metric-value"><?= (int)($stats['month_operations'] ?? 0) ?></div>
            </div>

            <div class="card">
                <div class="metric-label">Montant du mois</div>
                <div class="metric-value"><?= e(number_format((float)($stats['month_amount'] ?? 0), 2, ',', ' ')) ?></div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <h3>Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Libellé, référence, client, 512...">
                    </div>

                    <div>
                        <label>Type d’opération</label>
                        <select name="type">
                            <option value="">Tous</option>
                            <?php foreach ($operationTypes as $type): ?>
                                <option value="<?= (int)$type['id'] ?>" <?= $typeFilter === (string)$type['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$type['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Service</label>
                        <select name="service">
                            <option value="">Tous</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int)$service['id'] ?>" <?= $serviceFilter === (string)$service['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$service['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Client</label>
                        <select name="client_id">
                            <option value="">Tous</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int)$client['id'] ?>" <?= $clientFilter === (string)$client['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$client['client_code'] . ' - ' . (string)$client['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Date du</label>
                        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                    </div>

                    <div>
                        <label>au</label>
                        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                    </div>
                </div>

                <div class="btn-group" style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Liste des opérations</h3>

            <div class="table-responsive">
                <table class="modern-table sl-compact-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Type / Service</th>
                            <th>Compte Bancaire 512</th>
                            <th>Débit / Crédit</th>
                            <th>Montant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operations as $row): ?>
                            <?php
                            $clientDisplay = trim((string)(
                                (($row['client_code'] ?? '') !== '' ? ($row['client_code'] . ' - ') : '') .
                                ($row['client_full_name'] ?? '')
                            ));

                            $typeDisplay = trim((string)($row['operation_type_label'] ?? $row['operation_type_code_ref'] ?? $row['operation_type_code'] ?? ''));
                            $serviceDisplay = trim((string)($row['service_label'] ?? $row['service_code_ref'] ?? $row['service_code'] ?? ''));

                            $treasury512Display = trim((string)(
                                (($row['linked_treasury_account_code'] ?? '') !== '' ? ($row['linked_treasury_account_code'] . ' - ') : '') .
                                ($row['linked_treasury_account_label'] ?? '')
                            ));

                            $legacyBankDisplay = trim((string)(
                                (($row['linked_bank_account_number'] ?? '') !== '' ? ($row['linked_bank_account_number'] . ' - ') : '') .
                                ($row['linked_bank_account_name'] ?? '')
                            ));

                            $final512Display = $treasury512Display !== '' ? $treasury512Display : ($legacyBankDisplay !== '' ? $legacyBankDisplay : '—');
                            ?>
                            <tr>
                                <td><?= e((string)($row['operation_date'] ?? '')) ?></td>
                                <td>
                                    <div><strong><?= e((string)($row['reference'] ?? '')) ?></strong></div>
                                    <div class="muted" style="font-size:12px;"><?= e((string)($row['label'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div><?= e($clientDisplay !== '' ? $clientDisplay : '—') ?></div>
                                    <div class="muted" style="font-size:12px;"><?= e((string)($row['generated_client_account'] ?? '—')) ?></div>
                                </td>
                                <td>
                                    <div><strong><?= e($typeDisplay !== '' ? $typeDisplay : '—') ?></strong></div>
                                    <div class="muted" style="font-size:12px;"><?= e($serviceDisplay !== '' ? $serviceDisplay : '—') ?></div>
                                </td>
                                <td><?= e($final512Display) ?></td>
                                <td>
                                    <div><strong>D:</strong> <?= e((string)($row['debit_account_code'] ?? '')) ?></div>
                                    <div><strong>C:</strong> <?= e((string)($row['credit_account_code'] ?? '')) ?></div>
                                </td>
                                <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?> <?= e((string)($row['currency_code'] ?? '')) ?></td>
                                <td>
                                    <div class="btn-group sl-btn-group-nowrap">
                                        <a href="<?= e(APP_URL) ?>modules/operations/operation_view.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline btn-sm">Voir</a>

                                        <?php if ($canEdit): ?>
                                            <a href="<?= e(APP_URL) ?>modules/operations/operation_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                        <?php endif; ?>

                                        <?php if ($canDelete): ?>
                                            <a
                                                href="<?= e(APP_URL) ?>modules/operations/operation_delete.php?id=<?= (int)$row['id'] ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Confirmer la suppression ou l’archivage de cette opération ?');"
                                            >
                                                Supprimer
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
        </style>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>