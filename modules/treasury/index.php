<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceAccess($pdo, 'treasury_view_page');

$rows = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT *
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Trésorerie';
$pageSubtitle = 'Consultation et pilotage des comptes internes.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <div class="page-title">

            <div class="btn-group">
                <?php if (studelyCanAccess($pdo, 'treasury_import_page')): ?>
                    <a href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php" class="btn btn-outline">Import trésorerie</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Pays</th>
                        <th>Zone</th>
                        <th>Filiale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['account_code'] ?? '') ?></td>
                            <td><?= e($row['account_label'] ?? '') ?></td>
                            <td><?= e($row['country_label'] ?? '') ?></td>
                            <td><?= e($row['zone_label'] ?? '') ?></td>
                            <td><?= e($row['entity_label'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="5">Aucun compte interne trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>