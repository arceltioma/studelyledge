<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT *
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT rs.*, rot.label AS operation_type_label, sa.account_code AS service_account_code, ta.account_code AS treasury_account_code
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Catalogue fonctionnel';
$pageSubtitle = 'Lecture consolidée des types d’opérations et services.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="table-card">
                <h3 class="section-title">Types d’opérations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Direction</th>
                            <th>Actif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operationTypes as $row): ?>
                            <tr>
                                <td><?= e($row['code'] ?? '') ?></td>
                                <td><?= e($row['label'] ?? '') ?></td>
                                <td><?= e($row['direction'] ?? '') ?></td>
                                <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Oui' : 'Non' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$operationTypes): ?>
                            <tr><td colspan="4">Aucun type d’opération.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h3 class="section-title">Services</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Type</th>
                            <th>706</th>
                            <th>512</th>
                            <th>Actif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $row): ?>
                            <tr>
                                <td><?= e($row['code'] ?? '') ?></td>
                                <td><?= e($row['label'] ?? '') ?></td>
                                <td><?= e($row['operation_type_label'] ?? '') ?></td>
                                <td><?= e($row['service_account_code'] ?? '') ?></td>
                                <td><?= e($row['treasury_account_code'] ?? '') ?></td>
                                <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Oui' : 'Non' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$services): ?>
                            <tr><td colspan="6">Aucun service.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>