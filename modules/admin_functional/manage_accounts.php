<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT *
        FROM service_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT *
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Comptes fonctionnels';
$pageSubtitle = 'Lecture croisée des comptes 706 et 512 utilisés par le moteur métier.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Comptes 706</h3>
                    </div>
                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/treasury/service_accounts.php" class="btn btn-outline">Gérer les 706</a>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Type op</th>
                            <th>Destination</th>
                            <th>Commercial</th>
                            <th>Solde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviceAccounts as $row): ?>
                            <tr>
                                <td><?= e($row['account_code'] ?? '') ?></td>
                                <td><?= e($row['account_label'] ?? '') ?></td>
                                <td><?= e($row['operation_type_label'] ?? '') ?></td>
                                <td><?= e($row['destination_country_label'] ?? '') ?></td>
                                <td><?= e($row['commercial_country_label'] ?? '') ?></td>
                                <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$serviceAccounts): ?>
                            <tr><td colspan="6">Aucun compte 706.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Comptes 512</h3>
                    </div>
                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Gérer les 512</a>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Banque</th>
                            <th>Pays</th>
                            <th>Devise</th>
                            <th>Solde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treasuryAccounts as $row): ?>
                            <tr>
                                <td><?= e($row['account_code'] ?? '') ?></td>
                                <td><?= e($row['account_label'] ?? '') ?></td>
                                <td><?= e($row['bank_name'] ?? '') ?></td>
                                <td><?= e($row['country_label'] ?? '') ?></td>
                                <td><?= e($row['currency_code'] ?? '') ?></td>
                                <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$treasuryAccounts): ?>
                            <tr><td colspan="6">Aucun compte 512.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>