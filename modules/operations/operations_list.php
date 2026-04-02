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
$pageSubtitle = 'Suivi, contrôle et audit des opérations';

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$service = trim($_GET['service'] ?? '');
$client = trim($_GET['client'] ?? '');

$where = ["1=1"];
$params = [];

/* ===============================
   FILTRES
================================ */

if ($search !== '') {
    $where[] = "(o.reference LIKE ? OR o.label LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
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
    $params[] = $client;
}

$whereSQL = implode(' AND ', $where);

/* ===============================
   QUERY PRINCIPALE
================================ */

$sql = "
SELECT
    o.*,
    c.client_code,
    c.full_name,
    rs.label AS service_label,
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

/* ===============================
   DATA POUR FILTRES
================================ */

$types = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("SELECT code, label FROM ref_operation_types ORDER BY label")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("SELECT code, label FROM ref_services ORDER BY label")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("SELECT id, client_code, full_name FROM clients ORDER BY client_code")->fetchAll(PDO::FETCH_ASSOC)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main">
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="page-hero">

    <div class="btn-group">
        <a href="operation_create.php" class="btn btn-success">+ Nouvelle opération</a>
    </div>
</div>

<!-- =========================
     FILTRES
========================= -->

<div class="card">
    <form method="GET" class="dashboard-grid-4">

        <input type="text" name="search" placeholder="Recherche..." value="<?= e($search) ?>">

        <select name="type">
            <option value="">Type</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= e($t['code']) ?>" <?= $type === $t['code'] ? 'selected' : '' ?>>
                    <?= e($t['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="service">
            <option value="">Service</option>
            <?php foreach ($services as $s): ?>
                <option value="<?= e($s['code']) ?>" <?= $service === $s['code'] ? 'selected' : '' ?>>
                    <?= e($s['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="client">
            <option value="">Client</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $client == $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['client_code'] . ' - ' . $c['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="btn-group">
            <button class="btn btn-primary">Filtrer</button>
            <a href="operations_list.php" class="btn btn-outline">Reset</a>
        </div>

    </form>
</div>

<!-- =========================
     TABLEAU
========================= -->

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
                        <td><?= e($op['operation_date']) ?></td>

                        <td>
                            <?= e(($op['client_code'] ?? '') . ' - ' . ($op['full_name'] ?? '')) ?>
                        </td>

                        <td><?= e($op['type_label'] ?? '') ?></td>
                        <td><?= e($op['service_label'] ?? '') ?></td>

                        <td class="amount">
                            <?= number_format((float)$op['amount'], 2, ',', ' ') ?>
                        </td>

                        <td><?= e($op['debit_account_code']) ?></td>
                        <td><?= e($op['credit_account_code']) ?></td>

                        <td class="actions">
                            <a href="operation_view.php?id=<?= $op['id'] ?>" class="btn btn-sm">Voir</a>
                            <a href="operation_edit.php?id=<?= $op['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                            <a href="operation_delete.php?id=<?= $op['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Confirmer ?')">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>