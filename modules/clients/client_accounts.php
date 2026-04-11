<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$pageTitle = 'Comptes clients (411)';
$pageSubtitle = 'Historique, soldes, filtres période et accès rapides aux exports clients.';

if (!function_exists('ca_money')) {
    function ca_money(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('ca_like')) {
    function ca_like(string $value): string
    {
        return '%' . trim($value) . '%';
    }
}

if (!function_exists('ca_valid_date')) {
    function ca_valid_date(string $value): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterCountry = trim((string)($_GET['filter_country'] ?? ''));
$filterClientType = trim((string)($_GET['filter_client_type'] ?? ''));
$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-t')));

if (!ca_valid_date($from)) {
    $from = date('Y-m-01');
}
if (!ca_valid_date($to)) {
    $to = date('Y-m-t');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$sql = "
    SELECT
        c.id AS client_id,
        c.client_code,
        c.full_name,
        c.country_commercial,
        c.client_type,
        c.generated_client_account,
        c.currency,
        c.is_active,
        ba.id AS bank_account_id,
        ba.account_name,
        ba.account_number,
        ba.initial_balance,
        ba.balance
    FROM clients c
    LEFT JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
    WHERE COALESCE(c.is_active,1)=1
";
$params = [];

if ($filterSearch !== '') {
    $sql .= "
        AND (
            c.client_code LIKE ?
            OR c.full_name LIKE ?
            OR COALESCE(c.email,'') LIKE ?
            OR COALESCE(c.phone,'') LIKE ?
            OR COALESCE(c.generated_client_account,'') LIKE ?
        )
    ";
    $params[] = ca_like($filterSearch);
    $params[] = ca_like($filterSearch);
    $params[] = ca_like($filterSearch);
    $params[] = ca_like($filterSearch);
    $params[] = ca_like($filterSearch);
}

if ($filterCountry !== '') {
    $sql .= " AND COALESCE(c.country_commercial,'') = ? ";
    $params[] = $filterCountry;
}

if ($filterClientType !== '') {
    $sql .= " AND COALESCE(c.client_type,'') = ? ";
    $params[] = $filterClientType;
}

$sql .= " ORDER BY c.client_code ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countryOptions = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT country_commercial
        FROM clients
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(country_commercial,'') <> ''
        ORDER BY country_commercial ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$clientTypeOptions = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT client_type
        FROM clients
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(client_type,'') <> ''
        ORDER BY client_type ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$movementMap = [];
if (tableExists($pdo, 'operations')) {
    $stmtMovements = $pdo->prepare("
        SELECT
            account_code,
            SUM(total_credit) AS total_credit,
            SUM(total_debit) AS total_debit
        FROM (
            SELECT
                credit_account_code AS account_code,
                SUM(amount) AS total_credit,
                0 AS total_debit
            FROM operations
            WHERE operation_date BETWEEN ? AND ?
              AND COALESCE(credit_account_code, '') LIKE '411%'
            GROUP BY credit_account_code

            UNION ALL

            SELECT
                debit_account_code AS account_code,
                0 AS total_credit,
                SUM(amount) AS total_debit
            FROM operations
            WHERE operation_date BETWEEN ? AND ?
              AND COALESCE(debit_account_code, '') LIKE '411%'
            GROUP BY debit_account_code
        ) t
        GROUP BY account_code
    ");
    $stmtMovements->execute([$from, $to, $from, $to]);

    foreach ($stmtMovements->fetchAll(PDO::FETCH_ASSOC) as $movement) {
        $code = (string)($movement['account_code'] ?? '');
        $credit = (float)($movement['total_credit'] ?? 0);
        $debit = (float)($movement['total_debit'] ?? 0);

        $movementMap[$code] = [
            'credit' => $credit,
            'debit' => $debit,
            'net' => $credit - $debit,
        ];
    }
}

$summary = [
    'count' => 0,
    'initial_balance' => 0.0,
    'current_balance' => 0.0,
    'period_credit' => 0.0,
    'period_debit' => 0.0,
    'period_net' => 0.0,
];

foreach ($rows as $row) {
    $code = (string)($row['generated_client_account'] ?? '');
    $mv = $movementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

    $summary['count']++;
    $summary['initial_balance'] += (float)($row['initial_balance'] ?? 0);
    $summary['current_balance'] += (float)($row['balance'] ?? 0);
    $summary['period_credit'] += (float)$mv['credit'];
    $summary['period_debit'] += (float)$mv['debit'];
    $summary['period_net'] += (float)$mv['net'];
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Filtres</h3>

                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input
                                type="text"
                                name="filter_search"
                                value="<?= e($filterSearch) ?>"
                                placeholder="Code client, nom, email, téléphone, compte 411..."
                            >
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <select name="filter_country">
                                <option value="">Tous</option>
                                <?php foreach ($countryOptions as $country): ?>
                                    <option value="<?= e((string)$country) ?>" <?= $filterCountry === (string)$country ? 'selected' : '' ?>>
                                        <?= e((string)$country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type client</label>
                            <select name="filter_client_type">
                                <option value="">Tous</option>
                                <?php foreach ($clientTypeOptions as $clientType): ?>
                                    <option value="<?= e((string)$clientType) ?>" <?= $filterClientType === (string)$clientType ? 'selected' : '' ?>>
                                        <?= e((string)$clientType) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Du</label>
                            <input type="date" name="from" value="<?= e($from) ?>">
                        </div>

                        <div>
                            <label>Au</label>
                            <input type="date" name="to" value="<?= e($to) ?>">
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">Synthèse</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Comptes affichés</span><strong><?= (int)$summary['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde initial cumulé</span><strong><?= ca_money((float)$summary['initial_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= ca_money((float)$summary['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédit période</span><strong><?= ca_money((float)$summary['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débit période</span><strong><?= ca_money((float)$summary['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= ca_money((float)$summary['period_net']) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="page-title page-title-inline">
                <div>
                    <h3 class="section-title">Liste des comptes clients 411</h3>
                    <p class="muted">Mouvements recalculés sur la période sélectionnée.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Code client</th>
                            <th>Nom</th>
                            <th>Compte 411</th>
                            <th>Pays</th>
                            <th>Type</th>
                            <th>Devise</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Crédit période</th>
                            <th>Débit période</th>
                            <th>Net période</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $code411 = (string)($row['generated_client_account'] ?? '');
                            $mv = $movementMap[$code411] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

                            $statementUrl = APP_URL . 'modules/statements/account_statements.php'
                                . '?prefill_client_id=' . (int)$row['client_id']
                                . '&prefill_date_from=' . urlencode($from)
                                . '&prefill_date_to=' . urlencode($to);

                            $profileUrl = APP_URL . 'modules/statements/client_profiles.php'
                                . '?prefill_client_id=' . (int)$row['client_id'];

                            $clientViewUrl = APP_URL . 'modules/clients/client_view.php?id=' . (int)$row['client_id'];
                            ?>
                            <tr>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '')) ?></td>
                                <td><?= e($code411) ?></td>
                                <td><?= e((string)($row['country_commercial'] ?? '—')) ?></td>
                                <td><?= e((string)($row['client_type'] ?? '—')) ?></td>
                                <td><?= e((string)($row['currency'] ?? '—')) ?></td>
                                <td><?= ca_money((float)($row['initial_balance'] ?? 0)) ?></td>
                                <td><?= ca_money((float)($row['balance'] ?? 0)) ?></td>
                                <td><?= ca_money((float)$mv['credit']) ?></td>
                                <td><?= ca_money((float)$mv['debit']) ?></td>
                                <td><?= ca_money((float)$mv['net']) ?></td>
                                <td>
                                    <div class="btn-group" style="flex-wrap:wrap;">
                                        <a href="<?= e($statementUrl) ?>" class="btn btn-secondary">Exporter le relevé</a>
                                        <a href="<?= e($profileUrl) ?>" class="btn btn-outline">Exporter la fiche client</a>
                                        <a href="<?= e($clientViewUrl) ?>" class="btn btn-success">Voir le client</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="12">Aucun compte client trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>