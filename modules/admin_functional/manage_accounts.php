<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

$pageTitle = 'Comptes fonctionnels';
$pageSubtitle = 'Lecture croisée des comptes 411, 512 et 706 avec recalcul des mouvements sur période.';

if (!function_exists('ma_money')) {
    function ma_money(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('ma_valid_date')) {
    function ma_valid_date(string $value): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}

$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-t')));

if (!ma_valid_date($from)) {
    $from = date('Y-m-01');
}
if (!ma_valid_date($to)) {
    $to = date('Y-m-t');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

/*
|--------------------------------------------------------------------------
| Comptes 706
|--------------------------------------------------------------------------
*/
$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT *
        FROM service_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

/*
|--------------------------------------------------------------------------
| Comptes 512
|--------------------------------------------------------------------------
*/
$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT *
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

/*
|--------------------------------------------------------------------------
| Comptes 411 (bank_accounts + clients)
|--------------------------------------------------------------------------
*/
$clientAccounts = [];
if (tableExists($pdo, 'bank_accounts')) {
    $sql411 = "
        SELECT
            ba.*,
            c.id AS client_id,
            c.client_code,
            c.full_name,
            c.country_commercial,
            c.client_type
        FROM bank_accounts ba
        LEFT JOIN clients c ON c.generated_client_account = ba.account_number
        WHERE ba.account_number LIKE '411%'
        ORDER BY ba.account_number ASC
    ";
    $clientAccounts = $pdo->query($sql411)->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Mouvements période 706 via operations
|--------------------------------------------------------------------------
*/
$serviceMovementMap = [];
if (tableExists($pdo, 'operations')) {
    $stmt706 = $pdo->prepare("
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
              AND COALESCE(credit_account_code, '') LIKE '706%'
            GROUP BY credit_account_code

            UNION ALL

            SELECT
                debit_account_code AS account_code,
                0 AS total_credit,
                SUM(amount) AS total_debit
            FROM operations
            WHERE operation_date BETWEEN ? AND ?
              AND COALESCE(debit_account_code, '') LIKE '706%'
            GROUP BY debit_account_code
        ) t
        GROUP BY account_code
    ");
    $stmt706->execute([$from, $to, $from, $to]);
    foreach ($stmt706->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $serviceMovementMap[(string)$row['account_code']] = [
            'credit' => (float)($row['total_credit'] ?? 0),
            'debit' => (float)($row['total_debit'] ?? 0),
            'net' => (float)($row['total_credit'] ?? 0) - (float)($row['total_debit'] ?? 0),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Mouvements période 512 via operations + treasury_movements
|--------------------------------------------------------------------------
*/
$treasuryMovementMap = [];
if (tableExists($pdo, 'operations') || tableExists($pdo, 'treasury_movements')) {
    foreach ($treasuryAccounts as $account) {
        $code = (string)($account['account_code'] ?? '');
        $id = (int)($account['id'] ?? 0);

        $opsCredit = 0.0;
        $opsDebit = 0.0;
        $tmIn = 0.0;
        $tmOut = 0.0;

        if (tableExists($pdo, 'operations') && $code !== '') {
            $stmtOps512 = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit,
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit
                FROM operations
                WHERE operation_date BETWEEN ? AND ?
            ");
            $stmtOps512->execute([$code, $code, $from, $to]);
            $ops = $stmtOps512->fetch(PDO::FETCH_ASSOC) ?: [];
            $opsCredit = (float)($ops['total_credit'] ?? 0);
            $opsDebit = (float)($ops['total_debit'] ?? 0);
        }

        if (tableExists($pdo, 'treasury_movements') && $id > 0) {
            $stmtTm = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN target_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_in,
                    COALESCE(SUM(CASE WHEN source_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_out
                FROM treasury_movements
                WHERE operation_date BETWEEN ? AND ?
            ");
            $stmtTm->execute([$id, $id, $from, $to]);
            $tm = $stmtTm->fetch(PDO::FETCH_ASSOC) ?: [];
            $tmIn = (float)($tm['total_in'] ?? 0);
            $tmOut = (float)($tm['total_out'] ?? 0);
        }

        $credit = $opsCredit + $tmIn;
        $debit = $opsDebit + $tmOut;

        $treasuryMovementMap[$code] = [
            'credit' => $credit,
            'debit' => $debit,
            'net' => $credit - $debit,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Mouvements période 411 via operations
|--------------------------------------------------------------------------
*/
$clientMovementMap = [];
if (tableExists($pdo, 'operations')) {
    $stmt411 = $pdo->prepare("
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
    $stmt411->execute([$from, $to, $from, $to]);
    foreach ($stmt411->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clientMovementMap[(string)$row['account_code']] = [
            'credit' => (float)($row['total_credit'] ?? 0),
            'debit' => (float)($row['total_debit'] ?? 0),
            'net' => (float)($row['total_credit'] ?? 0) - (float)($row['total_debit'] ?? 0),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Blocs de synthèse recalculés sur période
|--------------------------------------------------------------------------
*/
$summary = [
    '411' => ['count' => 0, 'current_balance' => 0.0, 'period_credit' => 0.0, 'period_debit' => 0.0, 'period_net' => 0.0],
    '512' => ['count' => 0, 'current_balance' => 0.0, 'period_credit' => 0.0, 'period_debit' => 0.0, 'period_net' => 0.0],
    '706' => ['count' => 0, 'current_balance' => 0.0, 'period_credit' => 0.0, 'period_debit' => 0.0, 'period_net' => 0.0],
];

foreach ($clientAccounts as $row) {
    $code = (string)($row['account_number'] ?? '');
    $mv = $clientMovementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

    $summary['411']['count']++;
    $summary['411']['current_balance'] += (float)($row['balance'] ?? 0);
    $summary['411']['period_credit'] += (float)$mv['credit'];
    $summary['411']['period_debit'] += (float)$mv['debit'];
    $summary['411']['period_net'] += (float)$mv['net'];
}

foreach ($treasuryAccounts as $row) {
    $code = (string)($row['account_code'] ?? '');
    $mv = $treasuryMovementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

    $summary['512']['count']++;
    $summary['512']['current_balance'] += (float)($row['current_balance'] ?? 0);
    $summary['512']['period_credit'] += (float)$mv['credit'];
    $summary['512']['period_debit'] += (float)$mv['debit'];
    $summary['512']['period_net'] += (float)$mv['net'];
}

foreach ($serviceAccounts as $row) {
    $code = (string)($row['account_code'] ?? '');
    $mv = $serviceMovementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];

    $summary['706']['count']++;
    $summary['706']['current_balance'] += (float)($row['current_balance'] ?? 0);
    $summary['706']['period_credit'] += (float)$mv['credit'];
    $summary['706']['period_debit'] += (float)$mv['debit'];
    $summary['706']['period_net'] += (float)$mv['net'];
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card" style="margin-bottom:20px;">
            <h3 class="section-title">Filtres période</h3>

            <form method="GET">
                <div class="dashboard-grid-2">
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
                    <button type="submit" class="btn btn-secondary">Actualiser</button>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="dashboard-grid-3" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Comptes 411</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Nombre</span><strong><?= (int)$summary['411']['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= ma_money((float)$summary['411']['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédits période</span><strong><?= ma_money((float)$summary['411']['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits période</span><strong><?= ma_money((float)$summary['411']['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= ma_money((float)$summary['411']['period_net']) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 512</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Nombre</span><strong><?= (int)$summary['512']['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= ma_money((float)$summary['512']['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédits période</span><strong><?= ma_money((float)$summary['512']['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits période</span><strong><?= ma_money((float)$summary['512']['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= ma_money((float)$summary['512']['period_net']) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 706</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Nombre</span><strong><?= (int)$summary['706']['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= ma_money((float)$summary['706']['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédits période</span><strong><?= ma_money((float)$summary['706']['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits période</span><strong><?= ma_money((float)$summary['706']['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= ma_money((float)$summary['706']['period_net']) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="page-title page-title-inline">
                <div>
                    <h3 class="section-title">Comptes clients 411</h3>
                    <p class="muted">Mouvements recalculés sur la période sélectionnée.</p>
                </div>
                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="btn btn-outline">Voir les comptes clients</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Compte 411</th>
                            <th>Client</th>
                            <th>Pays</th>
                            <th>Type</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Crédit période</th>
                            <th>Débit période</th>
                            <th>Net période</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientAccounts as $row): ?>
                            <?php
                            $code = (string)($row['account_number'] ?? '');
                            $mv = $clientMovementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];
                            ?>
                            <tr>
                                <td><?= e($code) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '—')) ?></td>
                                <td><?= e((string)($row['country_commercial'] ?? '—')) ?></td>
                                <td><?= e((string)($row['client_type'] ?? '—')) ?></td>
                                <td><?= ma_money((float)($row['initial_balance'] ?? 0)) ?></td>
                                <td><?= ma_money((float)($row['balance'] ?? 0)) ?></td>
                                <td><?= ma_money((float)$mv['credit']) ?></td>
                                <td><?= ma_money((float)$mv['debit']) ?></td>
                                <td><?= ma_money((float)$mv['net']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$clientAccounts): ?>
                            <tr><td colspan="9">Aucun compte 411.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Comptes 706</h3>
                    </div>
                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Gérer les 706</a>
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
                            <th>Solde courant</th>
                            <th>Crédit période</th>
                            <th>Débit période</th>
                            <th>Net période</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviceAccounts as $row): ?>
                            <?php
                            $code = (string)($row['account_code'] ?? '');
                            $mv = $serviceMovementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];
                            ?>
                            <tr>
                                <td><?= e($code) ?></td>
                                <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                <td><?= e((string)($row['operation_type_label'] ?? '')) ?></td>
                                <td><?= e((string)($row['destination_country_label'] ?? '')) ?></td>
                                <td><?= e((string)($row['commercial_country_label'] ?? '')) ?></td>
                                <td><?= ma_money((float)($row['current_balance'] ?? 0)) ?></td>
                                <td><?= ma_money((float)$mv['credit']) ?></td>
                                <td><?= ma_money((float)$mv['debit']) ?></td>
                                <td><?= ma_money((float)$mv['net']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$serviceAccounts): ?>
                            <tr><td colspan="9">Aucun compte 706.</td></tr>
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
                            <th>Solde courant</th>
                            <th>Crédit période</th>
                            <th>Débit période</th>
                            <th>Net période</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treasuryAccounts as $row): ?>
                            <?php
                            $code = (string)($row['account_code'] ?? '');
                            $mv = $treasuryMovementMap[$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];
                            ?>
                            <tr>
                                <td><?= e($code) ?></td>
                                <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                <td><?= e((string)($row['bank_name'] ?? '')) ?></td>
                                <td><?= e((string)($row['country_label'] ?? '')) ?></td>
                                <td><?= e((string)($row['currency_code'] ?? '')) ?></td>
                                <td><?= ma_money((float)($row['current_balance'] ?? 0)) ?></td>
                                <td><?= ma_money((float)$mv['credit']) ?></td>
                                <td><?= ma_money((float)$mv['debit']) ?></td>
                                <td><?= ma_money((float)$mv['net']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$treasuryAccounts): ?>
                            <tr><td colspan="9">Aucun compte 512.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>