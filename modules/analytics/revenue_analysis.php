<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'analytics_view_page';
studelyEnforceCurrentPageAccess($pdo);

$xAxis = $_GET['x_axis'] ?? 'month';
$chartType = $_GET['chart_type'] ?? 'line';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$allowedX = ['day', 'month', 'country', 'client', 'operation_type', 'source_type'];
$allowedChartTypes = ['bar', 'line'];

if (!in_array($xAxis, $allowedX, true)) {
    $xAxis = 'month';
}

if (!in_array($chartType, $allowedChartTypes, true)) {
    $chartType = 'line';
}

$groupSql = '';
$labelSql = '';

switch ($xAxis) {
    case 'day':
        $groupSql = "DATE(o.operation_date)";
        $labelSql = "DATE(o.operation_date)";
        break;

    case 'month':
        $groupSql = "DATE_FORMAT(o.operation_date, '%Y-%m')";
        $labelSql = "DATE_FORMAT(o.operation_date, '%Y-%m')";
        break;

    case 'country':
        $groupSql = "c.country_destination";
        $labelSql = "c.country_destination";
        break;

    case 'client':
        $groupSql = "c.client_code";
        $labelSql = "CONCAT(c.client_code, ' - ', c.full_name)";
        break;

    case 'operation_type':
        $groupSql = "o.operation_type_code";
        $labelSql = "COALESCE(rot.label, o.operation_type_code)";
        break;

    case 'source_type':
        $groupSql = "o.source_type";
        $labelSql = "o.source_type";
        break;
}

$sql = "
    SELECT
        {$labelSql} AS chart_label,
        COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '411%' OR o.credit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0) AS total_credit,
        COALESCE(SUM(CASE WHEN o.debit_account_code LIKE '411%' OR o.debit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0) AS total_debit,
        COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '411%' OR o.credit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0)
        - COALESCE(SUM(CASE WHEN o.debit_account_code LIKE '411%' OR o.debit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0) AS total_net
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    LEFT JOIN ref_operation_types rot ON rot.code = o.operation_type_code
    WHERE 1=1
";
$params = [];

if ($dateFrom !== '') {
    $sql .= " AND o.operation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND o.operation_date <= ?";
    $params[] = $dateTo;
}

$sql .= "
    GROUP BY {$groupSql}
    ORDER BY {$groupSql} ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$creditValues = [];
$debitValues = [];
$netValues = [];

foreach ($rows as $row) {
    $chartLabels[] = $row['chart_label'] ?? 'N/A';
    $creditValues[] = (float)$row['total_credit'];
    $debitValues[] = (float)$row['total_debit'];
    $netValues[] = (float)$row['total_net'];
}

$xAxisLabels = [
    'day' => 'Jour',
    'month' => 'Mois',
    'country' => 'Pays',
    'client' => 'Client',
    'operation_type' => 'Type d’opération',
    'source_type' => 'Source',
];

$chartTypeLabels = [
    'bar' => 'Histogramme',
    'line' => 'Courbe',
];

$totalCredits = array_sum($creditValues);
$totalDebits = array_sum($debitValues);
$totalNet = array_sum($netValues);
$totalBuckets = count($chartLabels);

$pageTitle = 'Analytics';
$pageSubtitle = 'Lecture consolidée des flux financiers et de leur respiration dans le temps.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if (currentUserCan($pdo, 'dashboard_view')): ?>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour dashboard</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <h3>Crédits analysés</h3>
                <div class="kpi"><?= number_format($totalCredits, 2, ',', ' ') ?> €</div>
                <p class="muted">Somme agrégée sur la période</p>
            </div>

            <div class="card">
                <h3>Débits analysés</h3>
                <div class="kpi"><?= number_format($totalDebits, 2, ',', ' ') ?> €</div>
                <p class="muted">Sorties agrégées sur la période</p>
            </div>

            <div class="card">
                <h3>Net analysé</h3>
                <div class="kpi"><?= number_format($totalNet, 2, ',', ' ') ?> €</div>
                <p class="muted">Balance consolidée</p>
            </div>

            <div class="card">
                <h3>Points de lecture</h3>
                <div class="kpi"><?= $totalBuckets ?></div>
                <p class="muted">Groupes affichés dans le graphe</p>
            </div>
        </div>

        <div class="form-card">
            <h3 class="section-title">Pilotage du graphe analytique</h3>

            <form method="GET" class="inline-form">
                <div>
                    <label for="x_axis">Axe X</label>
                    <select name="x_axis" id="x_axis">
                        <?php foreach ($xAxisLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $xAxis === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="chart_type">Type de graphe</label>
                    <select name="chart_type" id="chart_type">
                        <?php foreach ($chartTypeLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $chartType === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="date_from">Du</label>
                    <input type="date" name="date_from" id="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div>
                    <label for="date_to">Au</label>
                    <input type="date" name="date_to" id="date_to" value="<?= e($dateTo) ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="dashboard-chart-shell">
                <div class="dashboard-chart-header">
                    <div>
                        <h3 class="dashboard-chart-title">Graphe analytique</h3>
                        <div class="dashboard-chart-subtitle">
                            Axe X : <strong><?= e($xAxisLabels[$xAxis]) ?></strong> —
                            Type : <strong><?= e($chartTypeLabels[$chartType]) ?></strong>
                        </div>
                    </div>
                </div>

                <div id="analyticsChartErrorBox" class="dashboard-chart-error" style="display:none;"></div>

                <?php if (empty($chartLabels)): ?>
                    <div class="dashboard-chart-empty">
                        Aucune donnée analytique à afficher pour les filtres sélectionnés.
                    </div>
                <?php else: ?>
                    <div class="dashboard-chart-canvas-wrap">
                        <canvas id="analyticsChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            <h3 class="section-title">Synthèse tabulaire</h3>

            <table>
                <thead>
                    <tr>
                        <th><?= e($xAxisLabels[$xAxis]) ?></th>
                        <th>Crédits</th>
                        <th>Débits</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="4">Aucune donnée trouvée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['chart_label'] ?? 'N/A') ?></td>
                                <td><?= number_format((float)$row['total_credit'], 2, ',', ' ') ?> €</td>
                                <td><?= number_format((float)$row['total_debit'], 2, ',', ' ') ?> €</td>
                                <td><?= number_format((float)$row['total_net'], 2, ',', ' ') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php if (!empty($chartLabels)): ?>
<script>
window.analyticsChartData = {
    labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
    creditValues: <?= json_encode($creditValues, JSON_UNESCAPED_UNICODE) ?>,
    debitValues: <?= json_encode($debitValues, JSON_UNESCAPED_UNICODE) ?>,
    netValues: <?= json_encode($netValues, JSON_UNESCAPED_UNICODE) ?>,
    xAxisLabel: <?= json_encode($xAxisLabels[$xAxis], JSON_UNESCAPED_UNICODE) ?>,
    chartType: <?= json_encode($chartType, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>