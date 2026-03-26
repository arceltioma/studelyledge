<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_view');

require_once __DIR__ . '/../../includes/header.php';

$search = trim((string)($_GET['search'] ?? ''));

$sql = "
    SELECT
        o.*,
        c.client_code,
        c.full_name
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        o.label LIKE ?
        OR o.reference LIKE ?
        OR o.debit_account_code LIKE ?
        OR o.credit_account_code LIKE ?
        OR c.client_code LIKE ?
        OR c.full_name LIKE ?
    )";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like, $like];
}

$sql .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Opérations',
            'La chaîne CRUD doit parler la même langue comptable du début à la fin.'
        ); ?>

        <div class="page-title">
            <div>
                <h1>Liste des opérations</h1>
                <p class="muted">Consultation, recherche et navigation dans les écritures enregistrées.</p>
            </div>
            <div class="btn-group">
                <a class="btn btn-primary" href="<?= APP_URL ?>modules/operations/operation_create.php">Nouvelle opération</a>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <div>
                    <label>Recherche</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="client, libellé, compte, référence...">
                </div>
                <div class="btn-group" style="margin-top:26px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= APP_URL ?>modules/operations/operations_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Libellé</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Montant</th>
                        <th>Référence</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['operation_date'] ?? '') ?></td>
                            <td><?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($row['full_name'] ?? ''))) ?></td>
                            <td><?= e($row['label'] ?? '') ?></td>
                            <td><?= e($row['debit_account_code'] ?? '') ?></td>
                            <td><?= e($row['credit_account_code'] ?? '') ?></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= e($row['reference'] ?? '') ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= APP_URL ?>modules/operations/operation_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    <a class="btn btn-danger" href="<?= APP_URL ?>modules/operations/operation_delete.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('Supprimer cette opération ?');">Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8">Aucune opération trouvée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>