<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'admin_functional_page');
} else {
    enforcePagePermission($pdo, 'admin_functional_view');
}

$pageTitle = 'Dashboard administration fonctionnelle';
$pageSubtitle = 'Vision consolidée des comptes 411 / 512 / 706, des référentiels et des règles comptables';

if (!function_exists('af_dashboard_money')) {
    function af_dashboard_money(float $value): string
    {
        return number_format($value, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('af_dashboard_fetch_one')) {
    function af_dashboard_fetch_one(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('af_dashboard_fetch_all')) {
    function af_dashboard_fetch_all(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$stats = [
    'clients_total' => 0,
    'clients_active' => 0,
    'accounts_411_total' => 0,
    'accounts_411_balance_total' => 0.0,
    'accounts_512_total' => 0,
    'accounts_512_balance_total' => 0.0,
    'accounts_706_total' => 0,
    'accounts_706_balance_total' => 0.0,
    'operation_types_total' => 0,
    'services_total' => 0,
    'rules_total' => 0,
    'rules_active' => 0,
    'rules_missing' => 0,
];

if (tableExists($pdo, 'clients')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT
            COUNT(*) AS clients_total,
            SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END) AS clients_active
        FROM clients
    ");
    $stats['clients_total'] = (int)($row['clients_total'] ?? 0);
    $stats['clients_active'] = (int)($row['clients_active'] ?? 0);
}

if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT
            COUNT(*) AS accounts_411_total,
            COALESCE(SUM(COALESCE(ba.balance, 0)), 0) AS accounts_411_balance_total
        FROM clients c
        LEFT JOIN bank_accounts ba
            ON ba.account_number = c.generated_client_account
        WHERE COALESCE(c.is_active,1)=1
    ");
    $stats['accounts_411_total'] = (int)($row['accounts_411_total'] ?? 0);
    $stats['accounts_411_balance_total'] = (float)($row['accounts_411_balance_total'] ?? 0);
}

if (tableExists($pdo, 'treasury_accounts')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT
            COUNT(*) AS accounts_512_total,
            COALESCE(SUM(COALESCE(current_balance,0)), 0) AS accounts_512_balance_total
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
    ");
    $stats['accounts_512_total'] = (int)($row['accounts_512_total'] ?? 0);
    $stats['accounts_512_balance_total'] = (float)($row['accounts_512_balance_total'] ?? 0);
}

if (tableExists($pdo, 'service_accounts')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT
            COUNT(*) AS accounts_706_total,
            COALESCE(SUM(COALESCE(current_balance,0)), 0) AS accounts_706_balance_total
        FROM service_accounts
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(is_postable,0)=1
    ");
    $stats['accounts_706_total'] = (int)($row['accounts_706_total'] ?? 0);
    $stats['accounts_706_balance_total'] = (float)($row['accounts_706_balance_total'] ?? 0);
}

if (tableExists($pdo, 'ref_operation_types')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM ref_operation_types
    ");
    $stats['operation_types_total'] = (int)($row['total'] ?? 0);
} elseif (tableExists($pdo, 'operation_types')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM operation_types
    ");
    $stats['operation_types_total'] = (int)($row['total'] ?? 0);
}

if (tableExists($pdo, 'ref_services')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM ref_services
    ");
    $stats['services_total'] = (int)($row['total'] ?? 0);
} elseif (tableExists($pdo, 'services')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM services
    ");
    $stats['services_total'] = (int)($row['total'] ?? 0);
}

if (tableExists($pdo, 'accounting_rules')) {
    $row = af_dashboard_fetch_one($pdo, "
        SELECT
            COUNT(*) AS rules_total,
            SUM(CASE WHEN COALESCE(is_active,1)=1 THEN 1 ELSE 0 END) AS rules_active
        FROM accounting_rules
    ");
    $stats['rules_total'] = (int)($row['rules_total'] ?? 0);
    $stats['rules_active'] = (int)($row['rules_active'] ?? 0);
}

$servicesWithoutRule = [];
if (tableExists($pdo, 'ref_services') && tableExists($pdo, 'accounting_rules')) {
    $servicesWithoutRule = af_dashboard_fetch_all($pdo, "
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rot.code AS operation_type_code,
            rot.label AS operation_type_label
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN accounting_rules ar
            ON ar.service_id = rs.id
           AND ar.operation_type_id = rs.operation_type_id
           AND COALESCE(ar.is_active,1)=1
        WHERE ar.id IS NULL
        ORDER BY rot.label ASC, rs.label ASC
        LIMIT 8
    ");
    $stats['rules_missing'] = count($servicesWithoutRule);
}

$top411 = [];
if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
    $top411 = af_dashboard_fetch_all($pdo, "
        SELECT
            c.id,
            c.client_code,
            c.full_name,
            c.generated_client_account,
            COALESCE(ba.balance, 0) AS balance,
            COALESCE(ba.initial_balance, 0) AS initial_balance,
            c.country_commercial
        FROM clients c
        LEFT JOIN bank_accounts ba
            ON ba.account_number = c.generated_client_account
        WHERE COALESCE(c.is_active,1)=1
        ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
        LIMIT 8
    ");
}

$top706 = [];
if (tableExists($pdo, 'service_accounts')) {
    $top706 = af_dashboard_fetch_all($pdo, "
        SELECT
            id,
            account_code,
            account_label,
            current_balance,
            commercial_country_label,
            destination_country_label
        FROM service_accounts
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(is_postable,0)=1
        ORDER BY COALESCE(current_balance,0) DESC, account_code ASC
        LIMIT 8
    ");
}

$recentRules = [];
if (tableExists($pdo, 'accounting_rules')) {
    $recentRules = af_dashboard_fetch_all($pdo, "
        SELECT
            ar.id,
            ar.rule_code,
            ar.debit_mode,
            ar.credit_mode,
            ar.is_active,
            rot.label AS operation_type_label,
            rs.label AS service_label
        FROM accounting_rules ar
        LEFT JOIN ref_operation_types rot ON rot.id = ar.operation_type_id
        LEFT JOIN ref_services rs ON rs.id = ar.service_id
        ORDER BY COALESCE(ar.updated_at, ar.created_at) DESC, ar.id DESC
        LIMIT 8
    ");
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Vue rapide</h3>
                <div class="btn-group" style="margin-top:14px;">
                    <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php">Règles comptables</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">Types d’opérations</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">Services</a>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">Comptes</a>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Contrôle fonctionnel</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Services sans règle active</span><strong><?= (int)$stats['rules_missing'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Règles actives</span><strong><?= (int)$stats['rules_active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Référentiel services</span><strong><?= (int)$stats['services_total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Référentiel types</span><strong><?= (int)$stats['operation_types_total'] ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-4">
            <div class="card">
                <h3 class="section-title">Clients</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total clients</span><strong><?= (int)$stats['clients_total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Clients actifs</span><strong><?= (int)$stats['clients_active'] ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 411</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$stats['accounts_411_total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde total</span><strong><?= e(af_dashboard_money((float)$stats['accounts_411_balance_total'])) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 512</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$stats['accounts_512_total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde total</span><strong><?= e(af_dashboard_money((float)$stats['accounts_512_balance_total'])) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 706</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total postables</span><strong><?= (int)$stats['accounts_706_total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde total</span><strong><?= e(af_dashboard_money((float)$stats['accounts_706_balance_total'])) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-3" style="margin-top:20px;">
            <div class="card">
                <h3 class="section-title">Top comptes clients 411</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Compte</th>
                                <th>Pays</th>
                                <th>Solde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top411 as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">
                                            <?= e(($row['client_code'] ?? '') . ' - ' . ($row['full_name'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string)($row['generated_client_account'] ?? '')) ?></td>
                                    <td><?= e((string)($row['country_commercial'] ?? '—')) ?></td>
                                    <td><?= e(af_dashboard_money((float)($row['balance'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$top411): ?>
                                <tr><td colspan="4">Aucun compte client trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Top comptes de service 706</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Libellé</th>
                                <th>Solde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top706 as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)$row['id'] ?>">
                                            <?= e((string)($row['account_code'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                    <td><?= e(af_dashboard_money((float)($row['current_balance'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$top706): ?>
                                <tr><td colspan="3">Aucun compte 706 trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Règles récentes</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type / Service</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRules as $row): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_view.php?id=<?= (int)$row['id'] ?>">
                                            <?= e((string)($row['rule_code'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td><?= e(trim((string)($row['operation_type_label'] ?? '') . ' / ' . (string)($row['service_label'] ?? ''))) ?></td>
                                    <td><?= !empty($row['is_active']) ? 'Actif' : 'Inactif' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$recentRules): ?>
                                <tr><td colspan="3">Aucune règle comptable.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3 class="section-title">Services sans règle active</h3>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Type</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicesWithoutRule as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['code'] ?? '') . ' - ' . (string)($row['label'] ?? '')) ?></td>
                                    <td><?= e((string)($row['operation_type_label'] ?? '')) ?></td>
                                    <td>
                                        <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_create.php?operation_type_id=<?= (int)($row['id'] ? ($row['operation_type_id'] ?? 0) : 0) ?>&service_id=<?= (int)$row['id'] ?>">
                                            Créer une règle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$servicesWithoutRule): ?>
                                <tr><td colspan="3">Tous les services affichés ont une règle active.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Liens utiles</h3>
                <div class="btn-group" style="margin-top:10px;">
                    <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">Voir tous les comptes</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_accounts.php">Historique comptes 411</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/treasury/index.php">Historique comptes 512</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/service_accounts/index.php">Historique comptes 706</a>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php">Pilotage des règles</a>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>