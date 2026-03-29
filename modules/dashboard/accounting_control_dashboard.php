<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'dashboard_view');

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$whereOps = '1=1';
$paramsOps = [];

if ($dateFrom !== '') {
    $whereOps .= ' AND o.operation_date >= ?';
    $paramsOps[] = $dateFrom;
}
if ($dateTo !== '') {
    $whereOps .= ' AND o.operation_date <= ?';
    $paramsOps[] = $dateTo;
}

$totalClients = tableExists($pdo, 'clients')
    ? (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE COALESCE(is_active,1)=1")->fetchColumn()
    : 0;

$totalOperations = tableExists($pdo, 'operations')
    ? (int)$pdo->query("SELECT COUNT(*) FROM operations")->fetchColumn()
    : 0;

$totalTreasuryBalance = tableExists($pdo, 'treasury_accounts')
    ? (float)$pdo->query("SELECT COALESCE(SUM(current_balance),0) FROM treasury_accounts WHERE COALESCE(is_active,1)=1")->fetchColumn()
    : 0.0;

$totalServiceBalance = tableExists($pdo, 'service_accounts')
    ? (float)$pdo->query("SELECT COALESCE(SUM(current_balance),0) FROM service_accounts WHERE COALESCE(is_active,1)=1")->fetchColumn()
    : 0.0;

$totalClientEngagements = tableExists($pdo, 'bank_accounts')
    ? (float)$pdo->query("
        SELECT COALESCE(SUM(balance),0)
        FROM bank_accounts
        WHERE account_number LIKE '411%'
          AND COALESCE(is_active,1)=1
    ")->fetchColumn()
    : 0.0;

$clientsPositiveByDestination = [];
if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'client_bank_accounts')) {
    $stmt = $pdo->query("
        SELECT
            c.country_destination,
            COUNT(*) AS total_clients
        FROM clients c
        INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        WHERE COALESCE(c.is_active,1)=1
          AND ba.account_number LIKE '411%'
          AND COALESCE(ba.balance,0) > 0
        GROUP BY c.country_destination
        ORDER BY total_clients DESC, c.country_destination ASC
    ");
    $clientsPositiveByDestination = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$dormantClients = 0;
if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'client_bank_accounts')) {
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM clients c
        INNER JOIN client_bank_accounts cba ON cba.client_id = c.id
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        WHERE COALESCE(c.is_active,1)=1
          AND ba.account_number LIKE '411%'
          AND COALESCE(ba.balance,0) <= 0
    ");
    $dormantClients = (int)$stmt->fetchColumn();
}

$treasuryVsEngagementGap = $totalTreasuryBalance - $totalClientEngagements;

$monthlyTransfers = [];
if (tableExists($pdo, 'operations')) {
    $sql = "
        SELECT
            DATE_FORMAT(o.operation_date, '%Y-%m') AS month_label,
            COALESCE(SUM(o.amount),0) AS total_amount
        FROM operations o
        WHERE o.operation_type_code IN ('VIREMENT_MENSUEL','VIREMENT_REGULIER','VIREMENT_EXCEPTIONEL')
          AND o.operation_date >= CURDATE()
          AND o.operation_date < DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY DATE_FORMAT(o.operation_date, '%Y-%m')
        ORDER BY month_label ASC
    ";
    $monthlyTransfers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$anomalies = [];

/**
 * 1. Clients sans 512
 */
if (tableExists($pdo, 'clients')) {
    $stmt = $pdo->query("
        SELECT c.id, c.client_code, c.full_name, c.country_commercial
        FROM clients c
        WHERE COALESCE(c.is_active,1)=1
          AND c.initial_treasury_account_id IS NULL
        ORDER BY c.client_code ASC
        LIMIT 50
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $anomalies[] = [
            'severity' => 'danger',
            'family' => 'Clients',
            'label' => 'Client sans compte 512',
            'detail' => ($row['client_code'] ?? '') . ' - ' . ($row['full_name'] ?? '') . ' [' . ($row['country_commercial'] ?? '') . ']',
        ];
    }
}

/**
 * 2. Clients sans compte 411
 */
if (tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
    $stmt = $pdo->query("
        SELECT c.id, c.client_code, c.full_name, c.generated_client_account
        FROM clients c
        LEFT JOIN bank_accounts ba ON ba.account_number = c.generated_client_account
        WHERE COALESCE(c.is_active,1)=1
          AND (c.generated_client_account IS NULL OR ba.id IS NULL)
        ORDER BY c.client_code ASC
        LIMIT 50
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $anomalies[] = [
            'severity' => 'danger',
            'family' => 'Clients',
            'label' => 'Client sans compte 411 valide',
            'detail' => ($row['client_code'] ?? '') . ' - ' . ($row['full_name'] ?? '') . ' [' . ($row['generated_client_account'] ?? 'aucun') . ']',
        ];
    }
}

/**
 * 3. Services sans 706 postable
 */
if (tableExists($pdo, 'ref_services') && tableExists($pdo, 'service_accounts')) {
    $stmt = $pdo->query("
        SELECT
            rs.code,
            rs.label,
            sa.account_code,
            sa.account_label,
            sa.is_postable,
            sa.is_active
        FROM ref_services rs
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        WHERE COALESCE(rs.is_active,1)=1
          AND (
            rs.service_account_id IS NULL
            OR sa.id IS NULL
            OR COALESCE(sa.is_active,1) <> 1
            OR COALESCE(sa.is_postable,0) <> 1
          )
        ORDER BY rs.label ASC
        LIMIT 50
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $anomalies[] = [
            'severity' => 'danger',
            'family' => 'Services',
            'label' => 'Service sans 706 final postable',
            'detail' => ($row['code'] ?? '') . ' - ' . ($row['label'] ?? ''),
        ];
    }
}

/**
 * 4. Opérations avec comptes vides
 */
if (tableExists($pdo, 'operations')) {
    $stmt = $pdo->prepare("
        SELECT o.id, o.operation_date, o.operation_type_code, o.label, o.amount
        FROM operations o
        WHERE {$whereOps}
          AND (
            o.debit_account_code IS NULL OR o.debit_account_code = ''
            OR o.credit_account_code IS NULL OR o.credit_account_code = ''
          )
        ORDER BY o.operation_date DESC, o.id DESC
        LIMIT 100
    ");
    $stmt->execute($paramsOps);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $anomalies[] = [
            'severity' => 'danger',
            'family' => 'Opérations',
            'label' => 'Écriture incomplète',
            'detail' => '#' . (int)$row['id'] . ' - ' . ($row['operation_type_code'] ?? '') . ' - ' . ($row['label'] ?? ''),
        ];
    }
}

/**
 * 5. Opérations incohérentes avec les règles métier
 */
if (tableExists($pdo, 'operations') && tableExists($pdo, 'clients')) {
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            c.client_code,
            c.generated_client_account,
            ta.account_code AS client_treasury_code
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
        WHERE {$whereOps}
        ORDER BY o.operation_date DESC, o.id DESC
        LIMIT 300
    ");
    $stmt->execute($paramsOps);
    $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ops as $op) {
        $type = (string)($op['operation_type_code'] ?? '');
        $debit = (string)($op['debit_account_code'] ?? '');
        $credit = (string)($op['credit_account_code'] ?? '');
        $client411 = (string)($op['generated_client_account'] ?? '');
        $client512 = (string)($op['client_treasury_code'] ?? '');

        $ok = true;

        if ($type === 'VERSEMENT') {
            $ok = ($debit === $client512 && $credit === $client411);
        } elseif (in_array($type, ['VIREMENT_REGULIER','VIREMENT_MENSUEL','VIREMENT_EXCEPTIONEL'], true)) {
            $ok = ($debit === $client411 && $credit === $client512);
        } elseif ($type === 'REGULARISATION_POSITIVE') {
            $ok = (str_starts_with($debit, '706') && $credit === $client411);
        } elseif (in_array($type, ['REGULARISATION_NEGATIVE','FRAIS_DE_SERVICE','FRAIS_BANCAIRES','AUTRES_FRAIS','CA_PLACEMENT','CA_DIVERS','CA_DEBOURS_LOGEMENT','CA_DEBOURS_ASSURANCE','CA_COURTAGE_PRET','FRAIS_DEBOURS_MICROFINANCE'], true)) {
            $ok = ($debit === $client411 && str_starts_with($credit, '706'));
        } elseif ($type === 'VIREMENT_INTERNE') {
            $ok = (str_starts_with($debit, '512') && str_starts_with($credit, '512') && $debit !== $credit);
        }

        if (!$ok) {
            $anomalies[] = [
                'severity' => 'danger',
                'family' => 'Opérations',
                'label' => 'Écriture incohérente avec les règles métier',
                'detail' => '#' . (int)$op['id'] . ' - ' . $type . ' - D:' . $debit . ' / C:' . $credit,
            ];
        }
    }
}

/**
 * 6. Imports rejetés / bloqués
 */
$openRejectedImports = 0;
if (tableExists($pdo, 'import_rows')) {
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM import_rows
        WHERE status = 'rejected'
    ");
    $openRejectedImports = (int)$stmt->fetchColumn();

    if ($openRejectedImports > 0) {
        $anomalies[] = [
            'severity' => 'warning',
            'family' => 'Imports',
            'label' => 'Lignes rejetées ouvertes',
            'detail' => $openRejectedImports . ' ligne(s) rejetée(s) à reprendre',
        ];
    }
}

$recentOperations = [];
if (tableExists($pdo, 'operations')) {
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            c.client_code,
            c.full_name
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        WHERE {$whereOps}
        ORDER BY o.id DESC
        LIMIT 20
    ");
    $stmt->execute($paramsOps);
    $recentOperations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Contrôle comptable';
$pageSubtitle = 'Tableau de bord des écarts 411 / 512 / 706, anomalies réelles et cohérence globale.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date début</label>
                            <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                        </div>
                        <div>
                            <label>Date fin</label>
                            <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                        </div>
                    </div>
                    <div class="btn-group" style="margin-top:16px;">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/accounting_control_dashboard.php" class="btn btn-outline">Réinitialiser</a>
                        <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-secondary">Journal des imports</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture</h3>
                <div class="dashboard-note">
                    Ce tableau met face à face les engagements clients, la trésorerie, les produits 706 et les anomalies d’écriture pour sécuriser l’exploitation.
                </div>
            </div>
        </div>

        <div class="card-grid" style="margin-top:20px;">
            <div class="card">
                <h3>Clients actifs</h3>
                <div class="kpi"><?= (int)$totalClients ?></div>
            </div>
            <div class="card">
                <h3>Opérations</h3>
                <div class="kpi"><?= (int)$totalOperations ?></div>
            </div>
            <div class="card">
                <h3>Engagements 411</h3>
                <div class="kpi"><?= number_format($totalClientEngagements, 2, ',', ' ') ?></div>
            </div>
            <div class="card">
                <h3>Trésorerie 512</h3>
                <div class="kpi"><?= number_format($totalTreasuryBalance, 2, ',', ' ') ?></div>
            </div>
            <div class="card">
                <h3>Produits 706</h3>
                <div class="kpi"><?= number_format($totalServiceBalance, 2, ',', ' ') ?></div>
            </div>
            <div class="card">
                <h3>Écart 512 - 411</h3>
                <div class="kpi"><?= number_format($treasuryVsEngagementGap, 2, ',', ' ') ?></div>
            </div>
            <div class="card">
                <h3>Comptes dormants</h3>
                <div class="kpi"><?= (int)$dormantClients ?></div>
            </div>
            <div class="card">
                <h3>Rejets imports</h3>
                <div class="kpi"><?= (int)$openRejectedImports ?></div>
            </div>
            <div class="card">
                <h3>Anomalies détectées</h3>
                <div class="kpi"><?= count($anomalies) ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="table-card">
                <h3 class="section-title">Clients avec solde positif par destination</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Destination</th>
                            <th>Clients</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientsPositiveByDestination as $row): ?>
                            <tr>
                                <td><?= e($row['country_destination'] ?? '') ?></td>
                                <td><?= (int)($row['total_clients'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$clientsPositiveByDestination): ?>
                            <tr><td colspan="2">Aucune donnée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h3 class="section-title">Montants à virer sur 3 mois</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyTransfers as $row): ?>
                            <tr>
                                <td><?= e($row['month_label'] ?? '') ?></td>
                                <td><?= number_format((float)($row['total_amount'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$monthlyTransfers): ?>
                            <tr><td colspan="2">Aucun montant à virer détecté.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Anomalies détectées</h3>
            <table>
                <thead>
                    <tr>
                        <th>Niveau</th>
                        <th>Famille</th>
                        <th>Anomalie</th>
                        <th>Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anomalies as $anomaly): ?>
                        <tr>
                            <td><?= e($anomaly['severity'] ?? '') ?></td>
                            <td><?= e($anomaly['family'] ?? '') ?></td>
                            <td><?= e($anomaly['label'] ?? '') ?></td>
                            <td><?= e($anomaly['detail'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$anomalies): ?>
                        <tr><td colspan="4">Aucune anomalie détectée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Dernières opérations contrôlées</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Libellé</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOperations as $op): ?>
                        <tr>
                            <td><?= e($op['operation_date'] ?? '') ?></td>
                            <td><?= e(trim((string)($op['client_code'] ?? '') . ' - ' . (string)($op['full_name'] ?? ''))) ?></td>
                            <td><?= e($op['operation_type_code'] ?? '') ?></td>
                            <td><?= e($op['label'] ?? '') ?></td>
                            <td><?= e($op['debit_account_code'] ?? '') ?></td>
                            <td><?= e($op['credit_account_code'] ?? '') ?></td>
                            <td><?= number_format((float)($op['amount'] ?? 0), 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentOperations): ?>
                        <tr><td colspan="7">Aucune opération trouvée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>