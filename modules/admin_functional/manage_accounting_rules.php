<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_functional_view');

$pageTitle = 'Règles comptables';
$pageSubtitle = 'Pilotage des règles Débit / Crédit par type d’opération et service';

$rules = tableExists($pdo, 'accounting_rules')
    ? $pdo->query("
        SELECT ar.*, ot.label AS operation_type_label, s.label AS service_label
        FROM accounting_rules ar
        LEFT JOIN operation_types ot ON ot.id = ar.operation_type_id
        LEFT JOIN services s ON s.id = ar.service_id
        ORDER BY ar.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main">
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="card">
    <div class="btn-group">
        <a href="<?= APP_URL ?>modules/admin_functional/accounting_rule_create.php" class="btn btn-success">
            ➕ Nouvelle règle
        </a>
    </div>
</div>

<div class="card">
    <table class="sl-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Type opération</th>
            <th>Service</th>
            <th>Code</th>
            <th>Débit</th>
            <th>Crédit</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rules as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= e($r['operation_type_label']) ?></td>
                <td><?= e($r['service_label']) ?></td>
                <td><strong><?= e($r['rule_code']) ?></strong></td>
                <td><?= e($r['debit_mode']) ?></td>
                <td><?= e($r['credit_mode']) ?></td>
                <td>
                    <span class="badge <?= $r['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                        <?= $r['is_active'] ? 'Actif' : 'Inactif' ?>
                    </span>
                </td>
                <td>
                    <a href="<?= APP_URL ?>modules/admin_functional/accounting_rule_edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>