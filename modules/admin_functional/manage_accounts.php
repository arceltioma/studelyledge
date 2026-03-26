<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'treasury_view');

require_once __DIR__ . '/../../includes/header.php';

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("SELECT * FROM treasury_accounts ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("SELECT * FROM service_accounts ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clientAccounts = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT
            c.id AS client_id,
            c.client_code,
            c.full_name,
            c.generated_client_account,
            ba.account_number,
            ba.bank_name,
            ba.balance
        FROM clients c
        LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
        LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        ORDER BY c.client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Comptes',
        'Vue centralisée des comptes 512, 706 et clients.'
    ); ?>

    <div class="dashboard-grid-2">
        <div class="table-card">
            <h3 class="section-title">Comptes 512</h3>
            <div class="btn-group" style="margin-bottom:12px;">
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/index.php">Créer / modifier / archiver</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Solde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($treasuryAccounts as $row): ?>
                        <tr>
                            <td><?= e($row['account_code'] ?? '') ?></td>
                            <td><?= e($row['account_label'] ?? '') ?></td>
                            <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$treasuryAccounts): ?>
                        <tr><td colspan="3">Aucun compte 512.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <h3 class="section-title">Comptes 706</h3>
            <div class="btn-group" style="margin-bottom:12px;">
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/service_accounts.php">Créer / modifier / archiver</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Solde</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serviceAccounts as $row): ?>
                        <tr>
                            <td><?= e($row['account_code'] ?? '') ?></td>
                            <td><?= e($row['account_label'] ?? '') ?></td>
                            <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$serviceAccounts): ?>
                        <tr><td colspan="3">Aucun compte 706.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card" style="margin-top:20px;">
        <h3 class="section-title">Comptes clients</h3>
        <div class="btn-group" style="margin-bottom:12px;">
            <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/client_create.php">Créer</a>
            <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/clients_list.php">Modifier / archiver</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Code client</th>
                    <th>Nom</th>
                    <th>Compte généré</th>
                    <th>Compte bancaire</th>
                    <th>Banque</th>
                    <th>Solde</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientAccounts as $row): ?>
                    <tr>
                        <td><?= e($row['client_code'] ?? '') ?></td>
                        <td><?= e($row['full_name'] ?? '') ?></td>
                        <td><?= e($row['generated_client_account'] ?? '') ?></td>
                        <td><?= e($row['account_number'] ?? '') ?></td>
                        <td><?= e($row['bank_name'] ?? '') ?></td>
                        <td><?= number_format((float)($row['balance'] ?? 0), 2, ',', ' ') ?></td>
                        <td>
                            <a class="btn btn-secondary" href="<?= APP_URL ?>modules/clients/client_edit.php?id=<?= (int)$row['client_id'] ?>">Modifier</a>
                            <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin_functional/archive_client.php?id=<?= (int)$row['client_id'] ?>" onclick="return confirm('Archiver ce client ?');">Archiver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clientAccounts): ?>
                    <tr><td colspan="7">Aucun compte client.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>