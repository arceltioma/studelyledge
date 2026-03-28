<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_view');

$search = trim((string)($_GET['search'] ?? ''));
$operationTypeCode = trim((string)($_GET['operation_type_code'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$clientId = (int)($_GET['client_id'] ?? 0);

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
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

$sql = "
    SELECT
        o.*,
        c.client_code,
        c.full_name,
        rot.label AS operation_type_label
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN ref_operation_types rot ON rot.code = o.operation_type_code
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        o.label LIKE ?
        OR o.reference LIKE ?
        OR o.debit_account_code LIKE ?
        OR o.credit_account_code LIKE ?
        OR o.service_account_code LIKE ?
        OR c.client_code LIKE ?
        OR c.full_name LIKE ?
    )";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}

if ($operationTypeCode !== '') {
    $sql .= " AND o.operation_type_code = ?";
    $params[] = $operationTypeCode;
}

if ($dateFrom !== '') {
    $sql .= " AND o.operation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND o.operation_date <= ?";
    $params[] = $dateTo;
}

if ($clientId > 0) {
    $sql .= " AND o.client_id = ?";
    $params[] = $clientId;
}

$sql .= " ORDER BY o.operation_date DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Opérations';
$pageSubtitle = 'Consultation, recherche et navigation dans les écritures enregistrées.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h2>Liste des opérations</h2>
                <p class="muted">Toute la chaîne CRUD parle la même langue comptable.</p>
            </div>

            <div class="btn-group">
                <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/operations/operation_create.php">Nouvelle opération</a>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="client, libellé, compte, référence...">

                <select name="operation_type_code">
                    <option value="">Tous les types</option>
                    <?php foreach ($operationTypes as $type): ?>
                        <option value="<?= e($type['code']) ?>" <?= $operationTypeCode === $type['code'] ? 'selected' : '' ?>>
                            <?= e($type['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="client_id">
                    <option value="0">Tous les clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client['id'] ?>" <?= $clientId === (int)$client['id'] ? 'selected' : '' ?>>
                            <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">

                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Client</th>
                        <th>Libellé</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Analytique</th>
                        <th>Montant</th>
                        <th>Référence</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['operation_date'] ?? '') ?></td>
                            <td><?= e($row['operation_type_label'] ?? $row['operation_type_code'] ?? '') ?></td>
                            <td><?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($row['full_name'] ?? ''))) ?></td>
                            <td><?= e($row['label'] ?? '') ?></td>
                            <td><?= e($row['debit_account_code'] ?? '') ?></td>
                            <td><?= e($row['credit_account_code'] ?? '') ?></td>
                            <td><?= e($row['service_account_code'] ?? '') ?></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= e($row['reference'] ?? '') ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/operations/operation_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/operations/operation_delete.php?id=<?= (int)$row['id'] ?>">Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="10">Aucune opération trouvée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>