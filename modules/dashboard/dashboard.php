<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

/* LOT 2 - moteurs additifs */
require_once __DIR__ . '/../../includes/rules_engine.php';
require_once __DIR__ . '/../../includes/anomaly_engine.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'dashboard_view_page');
} else {
    enforcePagePermission($pdo, 'dashboard_view');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Dashboard financier';
$pageSubtitle = 'Pilotage global, revenus, trésorerie, activité et contrôle avancé';

$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-t')));
$country = trim((string)($_GET['country'] ?? ''));
$service = trim((string)($_GET['service'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-t');
}

function dashboard_table_exists(PDO $pdo, string $table): bool
{
    return function_exists('tableExists') ? tableExists($pdo, $table) : false;
}

function dashboard_column_exists(PDO $pdo, string $table, string $column): bool
{
    return function_exists('columnExists') ? columnExists($pdo, $table, $column) : false;
}

function dashboard_money(float $value): string
{
    return number_format($value, 2, ',', ' ') . ' €';
}

function dashboard_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dashboard_fetch_one(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function dashboard_attention_link(string $key): string
{
    return match ($key) {
        'missing_706' => APP_URL . 'modules/admin_functional/manage_services.php',
        'missing_512' => APP_URL . 'modules/treasury/index.php',
        'missing_client' => APP_URL . 'modules/clients/clients_list.php',
        'same_account' => APP_URL . 'modules/operations/operations_list.php',
        'manual_ops' => APP_URL . 'modules/admin_functional/manage_accounting_rules.php',
        'missing_service' => APP_URL . 'modules/admin_functional/manage_services.php',
        'missing_type' => APP_URL . 'modules/admin_functional/manage_operation_types.php',
        'negative_or_zero' => APP_URL . 'modules/operations/operations_list.php',
        default => APP_URL . 'modules/dashboard/dashboard.php',
    };
}

function dashboard_card_link(string $url, string $label, string $value, string $meta, string $class = ''): string
{
    ob_start(); ?>
    <a class="sl-card sl-kpi-card <?= e($class) ?>" href="<?= e($url) ?>" style="text-decoration:none;">
        <div class="sl-kpi-card__label"><?= e($label) ?></div>
        <div class="sl-kpi-card__value"><?= $value ?></div>
        <div class="sl-kpi-card__meta"><strong><?= e($meta) ?></strong></div>
    </a>
    <?php
    return (string)ob_get_clean();
}

$hasOperations = dashboard_table_exists($pdo, 'operations');
$hasClients = dashboard_table_exists($pdo, 'clients');
$hasTreasury = dashboard_table_exists($pdo, 'treasury_accounts');
$hasRefServices = dashboard_table_exists($pdo, 'ref_services');
$hasRefOperationTypes = dashboard_table_exists($pdo, 'ref_operation_types');

$where = [];
$params = [];

if ($hasOperations && dashboard_column_exists($pdo, 'operations', 'operation_date')) {
    $where[] = 'o.operation_date BETWEEN ? AND ?';
    $params[] = $from;
    $params[] = $to;
}

if ($type !== '' && $hasOperations && dashboard_column_exists($pdo, 'operations', 'operation_type_code')) {
    $where[] = 'o.operation_type_code = ?';
    $params[] = $type;
}

if ($service !== '' && $hasOperations && dashboard_column_exists($pdo, 'operations', 'service_id')) {
    $where[] = 'o.service_id = ?';
    $params[] = (int)$service;
}

if ($country !== '' && $hasClients && dashboard_column_exists($pdo, 'clients', 'country_commercial')) {
    $where[] = 'c.country_commercial = ?';
    $params[] = $country;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$joinClients = ($hasClients && dashboard_column_exists($pdo, 'operations', 'client_id'))
    ? 'LEFT JOIN clients c ON c.id = o.client_id'
    : '';

$joinServices = ($hasRefServices && dashboard_column_exists($pdo, 'operations', 'service_id'))
    ? 'LEFT JOIN ref_services rs ON rs.id = o.service_id'
    : '';

$summary = [
    'total_operations' => 0,
    'total_amount' => 0.0,
    'total_revenue_706' => 0.0,
    'total_client_debit_411' => 0.0,
    'total_treasury_credit_512' => 0.0,
    'total_treasury_debit_512' => 0.0,
];

if ($hasOperations) {
    $summarySql = "
        SELECT
            COUNT(*) AS total_operations,
            COALESCE(SUM(o.amount), 0) AS total_amount,
            COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0) AS total_revenue_706,
            COALESCE(SUM(CASE WHEN o.debit_account_code LIKE '411%' THEN o.amount ELSE 0 END), 0) AS total_client_debit_411,
            COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '512%' THEN o.amount ELSE 0 END), 0) AS total_treasury_credit_512,
            COALESCE(SUM(CASE WHEN o.debit_account_code LIKE '512%' THEN o.amount ELSE 0 END), 0) AS total_treasury_debit_512
        FROM operations o
        {$joinClients}
        {$joinServices}
        {$whereSql}
    ";
    $summary = array_merge($summary, dashboard_fetch_one($pdo, $summarySql, $params));
}

$treasurySummary = [
    'opening_balance' => 0.0,
    'current_balance' => 0.0,
    'accounts_count' => 0,
];

if ($hasTreasury) {
    $openingExpr = dashboard_column_exists($pdo, 'treasury_accounts', 'opening_balance') ? 'COALESCE(SUM(opening_balance),0)' : '0';
    $currentExpr = dashboard_column_exists($pdo, 'treasury_accounts', 'current_balance') ? 'COALESCE(SUM(current_balance),0)' : '0';

    $treasurySummary = dashboard_fetch_one($pdo, "
        SELECT
            {$openingExpr} AS opening_balance,
            {$currentExpr} AS current_balance,
            COUNT(*) AS accounts_count
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
    ");
}

/* Engagements clients */
$engagementSummary = [
    'positive_balances' => 0.0,
    'dormant_accounts' => 0,
    'clients_france' => 0,
    'clients_allemagne' => 0,
    'clients_belgique' => 0,
];

if ($hasClients) {
    $clientBalanceColumn = dashboard_column_exists($pdo, 'clients', 'balance')
        ? 'COALESCE(balance,0)'
        : (dashboard_column_exists($pdo, 'clients', 'current_balance') ? 'COALESCE(current_balance,0)' : '0');

    $engagementSummary = dashboard_fetch_one($pdo, "
        SELECT
            COALESCE(SUM(CASE WHEN {$clientBalanceColumn} > 0 THEN {$clientBalanceColumn} ELSE 0 END),0) AS positive_balances,
            COALESCE(SUM(CASE WHEN {$clientBalanceColumn} <= 0 AND COALESCE(is_active,1)=1 THEN 1 ELSE 0 END),0) AS dormant_accounts,
            COALESCE(SUM(CASE WHEN COALESCE(country_destination,'') = 'France' AND {$clientBalanceColumn} > 0 THEN 1 ELSE 0 END),0) AS clients_france,
            COALESCE(SUM(CASE WHEN COALESCE(country_destination,'') = 'Allemagne' AND {$clientBalanceColumn} > 0 THEN 1 ELSE 0 END),0) AS clients_allemagne,
            COALESCE(SUM(CASE WHEN COALESCE(country_destination,'') = 'Belgique' AND {$clientBalanceColumn} > 0 THEN 1 ELSE 0 END),0) AS clients_belgique
        FROM clients
        WHERE COALESCE(is_active,1)=1
    ");
}

/* Projection virements 3 prochains mois */
$futureTransfers = [];
if ($hasClients) {
    $clientBalanceColumn = dashboard_column_exists($pdo, 'clients', 'balance')
        ? 'COALESCE(balance,0)'
        : (dashboard_column_exists($pdo, 'clients', 'current_balance') ? 'COALESCE(current_balance,0)' : '0');

    for ($i = 0; $i < 3; $i++) {
        $monthLabel = date('Y-m', strtotime("+{$i} month"));
        $futureTransfers[] = [
            'month_label' => $monthLabel,
            'estimated_amount' => (float)($engagementSummary['positive_balances'] ?? 0),
        ];
    }
}

$byType = [];
if ($hasOperations && dashboard_column_exists($pdo, 'operations', 'operation_type_code')) {
    $byType = dashboard_fetch_all($pdo, "
        SELECT
            COALESCE(o.operation_type_code, 'N/A') AS label,
            COUNT(*) AS total_count,
            COALESCE(SUM(o.amount), 0) AS total_amount
        FROM operations o
        {$joinClients}
        {$joinServices}
        {$whereSql}
        GROUP BY o.operation_type_code
        ORDER BY total_amount DESC, total_count DESC
        LIMIT 10
    ", $params);
}

$byService = [];
if ($hasOperations && $hasRefServices && dashboard_column_exists($pdo, 'operations', 'service_id')) {
    $byService = dashboard_fetch_all($pdo, "
        SELECT
            COALESCE(rs.label, 'N/A') AS label,
            COUNT(*) AS total_count,
            COALESCE(SUM(o.amount), 0) AS total_amount
        FROM operations o
        {$joinClients}
        LEFT JOIN ref_services rs ON rs.id = o.service_id
        {$whereSql}
        GROUP BY rs.label
        ORDER BY total_amount DESC, total_count DESC
        LIMIT 10
    ", $params);
}

$byCountry = [];
if ($hasOperations && $hasClients && dashboard_column_exists($pdo, 'clients', 'country_commercial')) {
    $byCountry = dashboard_fetch_all($pdo, "
        SELECT
            COALESCE(c.country_commercial, 'N/A') AS label,
            COUNT(*) AS total_count,
            COALESCE(SUM(o.amount), 0) AS total_amount
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        {$joinServices}
        {$whereSql}
        GROUP BY c.country_commercial
        ORDER BY total_amount DESC, total_count DESC
        LIMIT 10
    ", $params);
}

$monthlyRevenue = [];
if ($hasOperations && dashboard_column_exists($pdo, 'operations', 'operation_date')) {
    $monthlyRevenue = dashboard_fetch_all($pdo, "
        SELECT
            DATE_FORMAT(o.operation_date, '%Y-%m') AS month_label,
            COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0) AS revenue_706,
            COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '512%' THEN o.amount ELSE 0 END), 0) AS treasury_in_512,
            COALESCE(SUM(CASE WHEN o.debit_account_code LIKE '411%' THEN o.amount ELSE 0 END), 0) AS client_debit_411
        FROM operations o
        {$joinClients}
        {$joinServices}
        {$whereSql}
        GROUP BY DATE_FORMAT(o.operation_date, '%Y-%m')
        ORDER BY month_label ASC
    ", $params);
}

$topClients = [];
if ($hasOperations && $hasClients && dashboard_column_exists($pdo, 'operations', 'client_id')) {
    $topClients = dashboard_fetch_all($pdo, "
        SELECT
            COALESCE(c.client_code, '') AS client_code,
            COALESCE(c.full_name, 'Client inconnu') AS full_name,
            COUNT(*) AS total_count,
            COALESCE(SUM(o.amount), 0) AS total_amount,
            COALESCE(SUM(CASE WHEN o.credit_account_code LIKE '706%' THEN o.amount ELSE 0 END), 0) AS revenue_706
        FROM operations o
        LEFT JOIN clients c ON c.id = o.client_id
        {$joinServices}
        {$whereSql}
        GROUP BY c.id, c.client_code, c.full_name
        ORDER BY revenue_706 DESC, total_amount DESC
        LIMIT 10
    ", $params);
}

$audit = [
    'missing_706' => 0,
    'missing_512' => 0,
    'missing_client' => 0,
    'same_account' => 0,
    'manual_ops' => 0,
    'missing_service' => 0,
    'missing_type' => 0,
    'negative_or_zero' => 0,
];

if ($hasOperations) {
    $baseJoin = "{$joinClients} {$joinServices}";
    $baseWhere = $whereSql;

    $audit['missing_706'] = (int)(dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM operations o
        {$baseJoin}
        {$baseWhere}
        " . ($baseWhere ? " AND " : " WHERE ") . "
        (
            COALESCE(o.credit_account_code,'') NOT LIKE '706%'
            AND COALESCE(o.operation_type_code,'') IN (
                'FRAIS_GESTION',
                'FRAIS_SERVICE',
                'COMMISSION_DE_TRANSFERT',
                'CA_PLACEMENT'
            )
        )
    ", $params)['total'] ?? 0);

    $audit['missing_512'] = (int)(dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM operations o
        {$baseJoin}
        {$baseWhere}
        " . ($baseWhere ? " AND " : " WHERE ") . "
        (
            COALESCE(o.credit_account_code,'') NOT LIKE '512%'
            AND COALESCE(o.debit_account_code,'') NOT LIKE '512%'
            AND COALESCE(o.operation_type_code,'') IN (
                'VERSEMENT',
                'VIREMENT',
                'REGULARISATION'
            )
        )
    ", $params)['total'] ?? 0);

    if (dashboard_column_exists($pdo, 'operations', 'client_id')) {
        $audit['missing_client'] = (int)(dashboard_fetch_one($pdo, "
            SELECT COUNT(*) AS total
            FROM operations o
            {$baseJoin}
            {$baseWhere}
            " . ($baseWhere ? " AND " : " WHERE ") . "
            (o.client_id IS NULL OR o.client_id = 0)
        ", $params)['total'] ?? 0);
    }

    $audit['same_account'] = (int)(dashboard_fetch_one($pdo, "
        SELECT COUNT(*) AS total
        FROM operations o
        {$baseJoin}
        {$baseWhere}
        " . ($baseWhere ? " AND " : " WHERE ") . "
        COALESCE(o.debit_account_code,'') <> ''
        AND COALESCE(o.debit_account_code,'') = COALESCE(o.credit_account_code,'')
    ", $params)['total'] ?? 0);

    if (dashboard_column_exists($pdo, 'operations', 'is_manual_accounting')) {
        $audit['manual_ops'] = (int)(dashboard_fetch_one($pdo, "
            SELECT COUNT(*) AS total
            FROM operations o
            {$baseJoin}
            {$baseWhere}
            " . ($baseWhere ? " AND " : " WHERE ") . "
            COALESCE(o.is_manual_accounting,0) = 1
        ", $params)['total'] ?? 0);
    }

    if (dashboard_column_exists($pdo, 'operations', 'service_id')) {
        $audit['missing_service'] = (int)(dashboard_fetch_one($pdo, "
            SELECT COUNT(*) AS total
            FROM operations o
            {$baseJoin}
            {$baseWhere}
            " . ($baseWhere ? " AND " : " WHERE ") . "
            (o.service_id IS NULL OR o.service_id = 0)
        ", $params)['total'] ?? 0);
    }

    if (dashboard_column_exists($pdo, 'operations', 'operation_type_code')) {
        $audit['missing_type'] = (int)(dashboard_fetch_one($pdo, "
            SELECT COUNT(*) AS total
            FROM operations o
            {$baseJoin}
            {$baseWhere}
            " . ($baseWhere ? " AND " : " WHERE ") . "
            COALESCE(o.operation_type_code,'') = ''
        ", $params)['total'] ?? 0);
    }

    if (dashboard_column_exists($pdo, 'operations', 'amount')) {
        $audit['negative_or_zero'] = (int)(dashboard_fetch_one($pdo, "
            SELECT COUNT(*) AS total
            FROM operations o
            {$baseJoin}
            {$baseWhere}
            " . ($baseWhere ? " AND " : " WHERE ") . "
            COALESCE(o.amount,0) <= 0
        ", $params)['total'] ?? 0);
    }
}

$totalOps = (int)($summary['total_operations'] ?? 0);
$totalIssues = array_sum($audit);

$qualityScore = $totalOps > 0
    ? max(0, 100 - round(($totalIssues / max($totalOps, 1)) * 100))
    : 100;

$qualityLevelClass = 'audit-badge-success';
$qualityLevelLabel = 'OK';
if ($qualityScore < 80) {
    $qualityLevelClass = 'audit-badge-danger';
    $qualityLevelLabel = 'Critique';
} elseif ($qualityScore < 95) {
    $qualityLevelClass = 'audit-badge-warning';
    $qualityLevelLabel = 'Attention';
}

$detailedAnomalies = [];
foreach ([
    'missing_706' => '706 attendu absent ou incorrect',
    'missing_512' => 'Flux sans compte 512 détecté',
    'missing_client' => 'Client non rattaché',
    'same_account' => 'Compte débité = compte crédité',
    'manual_ops' => 'Opérations en mode manuel',
    'missing_service' => 'Service absent',
    'missing_type' => 'Type absent',
    'negative_or_zero' => 'Montants nuls ou négatifs',
] as $key => $label) {
    if (($audit[$key] ?? 0) > 0) {
        $detailedAnomalies[] = [
            'key' => $key,
            'label' => $label,
            'total' => (int)$audit[$key],
            'link' => dashboard_attention_link($key),
        ];
    }
}

$countryOptions = [];
if ($hasClients && dashboard_column_exists($pdo, 'clients', 'country_commercial')) {
    $countryOptions = dashboard_fetch_all($pdo, "
        SELECT DISTINCT country_commercial AS label
        FROM clients
        WHERE COALESCE(country_commercial,'') <> ''
        ORDER BY country_commercial ASC
    ");
}

$serviceOptions = [];
if ($hasRefServices) {
    $serviceOptions = dashboard_fetch_all($pdo, "
        SELECT id, label
        FROM ref_services
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ");
}

$typeOptions = [];
if ($hasRefOperationTypes) {
    $typeOptions = dashboard_fetch_all($pdo, "
        SELECT id, code, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ");
}

$chartMonths = array_map(static fn($r) => $r['month_label'], $monthlyRevenue);
$chartRevenue = array_map(static fn($r) => (float)$r['revenue_706'], $monthlyRevenue);
$chartTreasury = array_map(static fn($r) => (float)$r['treasury_in_512'], $monthlyRevenue);
$chartClientDebit = array_map(static fn($r) => (float)$r['client_debit_411'], $monthlyRevenue);

$chartTypeLabels = array_map(static fn($r) => $r['label'], $byType);
$chartTypeAmounts = array_map(static fn($r) => (float)$r['total_amount'], $byType);

$chartServiceLabels = array_map(static fn($r) => $r['label'], $byService);
$chartServiceAmounts = array_map(static fn($r) => (float)$r['total_amount'], $byService);

$futureTransferLabels = array_map(static fn($r) => $r['month_label'], $futureTransfers);
$futureTransferAmounts = array_map(static fn($r) => (float)$r['estimated_amount'], $futureTransfers);

/* LOT 2 - intelligence système non destructive */
$intelligence = [
    'rows_analyzed' => 0,
    'rules_enriched' => 0,
    'warnings' => 0,
    'dangers' => 0,
    'infos' => 0,
    'auto_mapped_ratio' => 0,
];

if ($hasOperations) {
    $intelligenceRows = dashboard_fetch_all($pdo, "
        SELECT
            o.id,
            o.amount,
            o.currency_code,
            o.client_id,
            o.operation_type_code,
            o.reference,
            o.service_id,
            o.debit_account_code,
            o.credit_account_code,
            " . ($hasClients ? "c.country_commercial, c.country_destination," : "NULL AS country_commercial, NULL AS country_destination,") . "
            " . ($hasRefServices ? "rs.code AS service_code" : "NULL AS service_code") . "
        FROM operations o
        {$joinClients}
        {$joinServices}
        {$whereSql}
        ORDER BY o.id DESC
        LIMIT 300
    ", $params);

    $intelligence['rows_analyzed'] = count($intelligenceRows);

    foreach ($intelligenceRows as $opRow) {
        $typeCode = (string)($opRow['operation_type_code'] ?? '');
        $serviceCode = (string)($opRow['service_code'] ?? '');

        if (function_exists('sl_rules_build_summary')) {
            $summaryRules = sl_rules_build_summary(
                $typeCode,
                $serviceCode,
                (string)($opRow['country_commercial'] ?? ''),
                (string)($opRow['country_destination'] ?? '')
            );

            if (
                !empty($summaryRules['requires_client']) ||
                !empty($summaryRules['requires_linked_bank']) ||
                !empty($summaryRules['requires_manual_accounts']) ||
                !empty($summaryRules['service_account_search_text'])
            ) {
                $intelligence['rules_enriched']++;
            }
        }

        if (function_exists('sl_detect_operation_anomalies')) {
            $anomaliesDetected = sl_detect_operation_anomalies([
                'amount' => (float)($opRow['amount'] ?? 0),
                'currency_code' => (string)($opRow['currency_code'] ?? ''),
                'client_id' => isset($opRow['client_id']) ? (int)$opRow['client_id'] : 0,
                'operation_type_code' => $typeCode,
                'service_code' => $serviceCode,
                'manual_debit_account_code' => '',
                'manual_credit_account_code' => '',
                'reference' => (string)($opRow['reference'] ?? ''),
                'country_commercial' => (string)($opRow['country_commercial'] ?? ''),
                'country_destination' => (string)($opRow['country_destination'] ?? ''),
            ]);

            foreach ($anomaliesDetected as $anomaly) {
                $level = strtolower((string)($anomaly['level'] ?? 'info'));
                if ($level === 'warning') {
                    $intelligence['warnings']++;
                } elseif ($level === 'danger') {
                    $intelligence['dangers']++;
                } elseif ($level === 'info') {
                    $intelligence['infos']++;
                }
            }
        }
    }

    if ($intelligence['rows_analyzed'] > 0) {
        $intelligence['auto_mapped_ratio'] = (int)round(($intelligence['rules_enriched'] / max(1, $intelligence['rows_analyzed'])) * 100);
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-card sl-stable-block sl-filter-card" style="margin-bottom:20px;">
            <form method="GET" class="sl-grid sl-grid-5" novalidate>
                <div>
                    <label for="from">Du</label>
                    <input type="date" id="from" name="from" value="<?= e($from) ?>">
                </div>

                <div>
                    <label for="to">Au</label>
                    <input type="date" id="to" name="to" value="<?= e($to) ?>">
                </div>

                <div>
                    <label for="country">Pays commercial</label>
                    <select id="country" name="country">
                        <option value="">Tous</option>
                        <?php foreach ($countryOptions as $option): ?>
                            <option value="<?= e($option['label']) ?>" <?= $country === $option['label'] ? 'selected' : '' ?>>
                                <?= e($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="service">Service</label>
                    <select id="service" name="service">
                        <option value="">Tous</option>
                        <?php foreach ($serviceOptions as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= $service === (string)$option['id'] ? 'selected' : '' ?>>
                                <?= e($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="type">Type opération</label>
                    <select id="type" name="type">
                        <option value="">Tous</option>
                        <?php foreach ($typeOptions as $option): ?>
                            <option value="<?= e($option['code']) ?>" <?= $type === $option['code'] ? 'selected' : '' ?>>
                                <?= e($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="grid-column:1 / -1; display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-success">Actualiser</button>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </section>

        <section class="sl-grid sl-grid-4 sl-stable-block">
            <?= dashboard_card_link(APP_URL . 'modules/operations/operations_list.php', 'Opérations', (string)(int)($summary['total_operations'] ?? 0), $from . ' → ' . $to, 'sl-kpi-card--green') ?>
            <?= dashboard_card_link(APP_URL . 'modules/operations/operations_list.php', 'Montant total', e(dashboard_money((float)($summary['total_amount'] ?? 0))), 'Toutes opérations', 'sl-kpi-card--blue') ?>
            <?= dashboard_card_link(APP_URL . 'modules/service_accounts/index.php', 'CA 706', e(dashboard_money((float)($summary['total_revenue_706'] ?? 0))), 'Produits comptabilisés', 'sl-kpi-card--emerald') ?>
            <?= dashboard_card_link(APP_URL . 'modules/treasury/index.php', 'Solde 512', e(dashboard_money((float)($treasurySummary['current_balance'] ?? 0))), ((int)($treasurySummary['accounts_count'] ?? 0)) . ' compte(s)', 'sl-kpi-card--violet') ?>
        </section>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-top:20px;">
            <?= dashboard_card_link(APP_URL . 'modules/clients/client_accounts.php', 'Engagements clients', e(dashboard_money((float)($engagementSummary['positive_balances'] ?? 0))), 'Solde total > 0', 'sl-kpi-card--emerald') ?>
            <?= dashboard_card_link(APP_URL . 'modules/clients/client_accounts.php', 'Dormants', (string)(int)($engagementSummary['dormant_accounts'] ?? 0), 'Comptes à surveiller', 'sl-kpi-card--warning') ?>
            <?= dashboard_card_link(APP_URL . 'modules/clients/clients_list.php', 'France / Allemagne / Belgique', (string)((int)($engagementSummary['clients_france'] ?? 0) + (int)($engagementSummary['clients_allemagne'] ?? 0) + (int)($engagementSummary['clients_belgique'] ?? 0)), 'Clients avec solde > 0', 'sl-kpi-card--blue') ?>
            <?= dashboard_card_link(APP_URL . 'modules/statements/account_statements.php', 'Virements 3 mois', e(dashboard_money((float)array_sum($futureTransferAmounts))), 'Projection cumulée', 'sl-kpi-card--violet') ?>
        </section>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__top">
                    <div class="sl-kpi-card__icon">🧠</div>
                    <span class="sl-kpi-card__tag">LOT 2</span>
                </div>
                <div class="sl-kpi-card__label">Opérations analysées</div>
                <div class="sl-kpi-card__value"><?= (int)$intelligence['rows_analyzed'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Périmètre intelligent</span>
                    <strong>Max 300 lignes</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__top">
                    <div class="sl-kpi-card__icon">⚙️</div>
                    <span class="sl-kpi-card__tag">Règles</span>
                </div>
                <div class="sl-kpi-card__label">Lignes enrichies</div>
                <div class="sl-kpi-card__value"><?= (int)$intelligence['rules_enriched'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Taux d’enrichissement</span>
                    <strong><?= (int)$intelligence['auto_mapped_ratio'] ?>%</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__top">
                    <div class="sl-kpi-card__icon">⚠️</div>
                    <span class="sl-kpi-card__tag">Anomalies</span>
                </div>
                <div class="sl-kpi-card__label">Warnings</div>
                <div class="sl-kpi-card__value"><?= (int)$intelligence['warnings'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Infos détectées</span>
                    <strong><?= (int)$intelligence['infos'] ?></strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__top">
                    <div class="sl-kpi-card__icon">🚨</div>
                    <span class="sl-kpi-card__tag">Contrôle</span>
                </div>
                <div class="sl-kpi-card__label">Dangers</div>
                <div class="sl-kpi-card__value"><?= (int)$intelligence['dangers'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Moteur intelligent</span>
                    <strong>Actif</strong>
                </div>
            </div>
        </section>

        <section class="sl-grid sl-grid-3 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card sl-card-highlight sl-card-audit-main">
                <div class="sl-card-head">
                    <div>
                        <h3>Qualité des données</h3>
                        <p class="sl-card-head-subtitle">Vue synthétique de la qualité globale du périmètre analysé</p>
                    </div>
                    <span class="sl-pill <?= e($qualityLevelClass) ?>"><?= e($qualityLevelLabel) ?></span>
                </div>

                <div class="sl-quality-layout">
                    <div class="sl-score-ring">
                        <div class="sl-score-ring__value"><?= (int)$qualityScore ?>%</div>
                    </div>

                    <div class="sl-quality-metrics">
                        <div class="sl-kpi-line">
                            <span>Opérations auditées</span>
                            <strong><?= (int)$totalOps ?></strong>
                        </div>
                        <div class="sl-kpi-line">
                            <span>Problèmes détectés</span>
                            <strong><?= (int)$totalIssues ?></strong>
                        </div>
                        <div class="sl-kpi-line">
                            <span>Période</span>
                            <strong><?= e($from) ?> → <?= e($to) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sl-card sl-card-critical">
                <div class="sl-card-head">
                    <div>
                        <h3>Anomalies critiques</h3>
                        <p class="sl-card-head-subtitle">Points à surveiller en priorité</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Audit</span>
                </div>

                <div class="sl-metric-stack">
                    <a class="sl-metric-tile clickable" href="<?= e(dashboard_attention_link('missing_706')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Sans 706</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['missing_706'] ?></strong>
                    </a>
                    <a class="sl-metric-tile clickable" href="<?= e(dashboard_attention_link('missing_512')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Sans 512</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['missing_512'] ?></strong>
                    </a>
                    <a class="sl-metric-tile clickable" href="<?= e(dashboard_attention_link('missing_client')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Sans client</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['missing_client'] ?></strong>
                    </a>
                    <a class="sl-metric-tile clickable" href="<?= e(dashboard_attention_link('same_account')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Débit = Crédit</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['same_account'] ?></strong>
                    </a>
                </div>
            </div>

            <div class="sl-card sl-card-risk">
                <div class="sl-card-head">
                    <div>
                        <h3>Risque opérationnel</h3>
                        <p class="sl-card-head-subtitle">Indicateurs sensibles liés à la production</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Contrôle</span>
                </div>

                <div class="sl-metric-stack">
                    <a class="sl-metric-tile sl-metric-tile--compact clickable" href="<?= e(dashboard_attention_link('manual_ops')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Mode manuel</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['manual_ops'] ?></strong>
                    </a>
                    <a class="sl-metric-tile sl-metric-tile--compact clickable" href="<?= e(dashboard_attention_link('missing_service')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Sans service</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['missing_service'] ?></strong>
                    </a>
                    <a class="sl-metric-tile sl-metric-tile--compact clickable" href="<?= e(dashboard_attention_link('missing_type')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Sans type</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['missing_type'] ?></strong>
                    </a>
                    <a class="sl-metric-tile sl-metric-tile--compact clickable" href="<?= e(dashboard_attention_link('negative_or_zero')) ?>" style="text-decoration:none;">
                        <span class="sl-metric-tile__label">Montant ≤ 0</span>
                        <strong class="sl-metric-tile__value"><?= (int)$audit['negative_or_zero'] ?></strong>
                    </a>
                </div>
            </div>
        </section>

        <section class="sl-grid sl-grid-3 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Indicateurs comptables</h3>
                        <p class="sl-card-head-subtitle">Synthèse des mouvements principaux 411 / 512</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">411 / 512</span>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Débit clients 411</span>
                        <strong><?= e(dashboard_money((float)($summary['total_client_debit_411'] ?? 0))) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Crédit 512</span>
                        <strong><?= e(dashboard_money((float)($summary['total_treasury_credit_512'] ?? 0))) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Débit 512</span>
                        <strong><?= e(dashboard_money((float)($summary['total_treasury_debit_512'] ?? 0))) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Solde ouverture 512</span>
                        <strong><?= e(dashboard_money((float)($treasurySummary['opening_balance'] ?? 0))) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Solde courant 512</span>
                        <strong><?= e(dashboard_money((float)($treasurySummary['current_balance'] ?? 0))) ?></strong>
                    </div>
                </div>
            </div>

            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Top clients</h3>
                        <p class="sl-card-head-subtitle">Les meilleurs contributeurs sur la période</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">706</span>
                </div>

                <?php if ($topClients): ?>
                    <div class="sl-client-ranking">
                        <?php foreach ($topClients as $index => $clientRow): ?>
                            <div class="sl-client-ranking__item">
                                <div class="sl-client-ranking__left">
                                    <div class="sl-rank-badge"><?= $index + 1 ?></div>
                                    <div>
                                        <strong class="sl-client-name"><?= e(($clientRow['client_code'] ?: 'N/A') . ' - ' . $clientRow['full_name']) ?></strong>
                                        <div class="sl-client-meta">
                                            <span><?= (int)$clientRow['total_count'] ?> opération(s)</span>
                                            <span><?= e(dashboard_money((float)$clientRow['total_amount'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="sl-client-ranking__right">
                                    <span class="sl-client-revenue-label">CA 706</span>
                                    <strong class="sl-client-revenue-value"><?= e(dashboard_money((float)$clientRow['revenue_706'])) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="sl-muted">Aucune donnée client sur la période.</p>
                <?php endif; ?>
            </div>

            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Anomalies détaillées</h3>
                        <p class="sl-card-head-subtitle">Répartition des écarts identifiés</p>
                    </div>
                    <span class="sl-pill sl-pill-soft"><?= count($detailedAnomalies) ?> type(s)</span>
                </div>

                <?php if ($detailedAnomalies): ?>
                    <div class="sl-anomaly-list">
                        <?php foreach ($detailedAnomalies as $item): ?>
                            <a class="sl-anomaly-list__item" href="<?= e($item['link']) ?>" style="text-decoration:none;">
                                <span class="sl-anomaly-list__label"><?= e($item['label']) ?></span>
                                <strong class="sl-anomaly-list__value"><?= (int)$item['total'] ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="sl-muted">Aucune anomalie détectée sur les règles auditées.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="sl-grid sl-grid-2 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Évolution mensuelle</h3>
                        <p class="sl-card-head-subtitle">Suivi du CA 706, du crédit 512 et du débit 411</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Tendance</span>
                </div>
                <div class="sl-chart-box sl-chart-box--large">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Répartition par type</h3>
                        <p class="sl-card-head-subtitle">Top 10 des types les plus volumineux</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Top 10</span>
                </div>
                <div class="sl-chart-box">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </section>

        <section class="sl-grid sl-grid-2 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Répartition par service</h3>
                        <p class="sl-card-head-subtitle">Vision des services les plus contributifs</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Top 10</span>
                </div>
                <div class="sl-chart-box">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>

            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Projection virements mensuels</h3>
                        <p class="sl-card-head-subtitle">Projection sur les 3 prochains mois</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Projection</span>
                </div>
                <div class="sl-chart-box">
                    <canvas id="futureTransferChart"></canvas>
                </div>
            </div>
        </section>

        <section class="sl-grid sl-grid-2 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Répartition par pays commercial</h3>
                        <p class="sl-card-head-subtitle">Vue consolidée par zone commerciale</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Pays</span>
                </div>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Pays</th>
                                <th>Nb opérations</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($byCountry): ?>
                                <?php foreach ($byCountry as $row): ?>
                                    <tr>
                                        <td><?= e($row['label']) ?></td>
                                        <td><?= (int)$row['total_count'] ?></td>
                                        <td><?= e(dashboard_money((float)$row['total_amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">Aucune donnée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Engagements par destination</h3>
                        <p class="sl-card-head-subtitle">Clients avec solde positif</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Destinations</span>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>France</span>
                        <strong><?= (int)($engagementSummary['clients_france'] ?? 0) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Allemagne</span>
                        <strong><?= (int)($engagementSummary['clients_allemagne'] ?? 0) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Belgique</span>
                        <strong><?= (int)($engagementSummary['clients_belgique'] ?? 0) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Montant engagements</span>
                        <strong><?= e(dashboard_money((float)($engagementSummary['positive_balances'] ?? 0))) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Comptes dormants</span>
                        <strong><?= (int)($engagementSummary['dormant_accounts'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="sl-grid sl-grid-2 sl-stable-block" style="margin-top:20px;">
            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Types d’opérations</h3>
                        <p class="sl-card-head-subtitle">Détail tabulaire complémentaire</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Détail</span>
                </div>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Nb opérations</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($byType): ?>
                                <?php foreach ($byType as $row): ?>
                                    <tr>
                                        <td><?= e($row['label']) ?></td>
                                        <td><?= (int)$row['total_count'] ?></td>
                                        <td><?= e(dashboard_money((float)$row['total_amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">Aucune donnée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sl-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Services</h3>
                        <p class="sl-card-head-subtitle">Détail tabulaire complémentaire</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Détail</span>
                </div>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Nb opérations</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($byService): ?>
                                <?php foreach ($byService as $row): ?>
                                    <tr>
                                        <td><?= e($row['label']) ?></td>
                                        <td><?= (int)$row['total_count'] ?></td>
                                        <td><?= e(dashboard_money((float)$row['total_amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">Aucune donnée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="sl-grid sl-grid-2 sl-stable-block" style="margin-top:20px;">
            <div class="card">
                <h3>Accès rapides</h3>
                <div class="btn-group" style="flex-direction:column;align-items:flex-start;">
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_services.php">Gérer les services</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php">Gérer les types d’opération</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_accounts.php">Gérer les comptes</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/clients_list.php">Gérer les clients</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_accounting_rules.php">Gérer les règles comptables</a>
                </div>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Lecture métier</h3>
                <div class="dashboard-note">
                    Ce dashboard doit conserver la vision globale, les engagements, la trésorerie, les virements projetés et l’analyse dynamique du chiffre d’affaires par pays, période et catégorie. :contentReference[oaicite:2]{index=2} :contentReference[oaicite:3]{index=3}
                </div>
            </div>
        </section>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            Chart.defaults.animation = false;
            Chart.defaults.responsive = true;
            Chart.defaults.maintainAspectRatio = false;

            const monthlyCanvas = document.getElementById('monthlyChart');
            if (monthlyCanvas) {
                new Chart(monthlyCanvas, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($chartMonths, JSON_UNESCAPED_UNICODE) ?>,
                        datasets: [
                            {
                                label: 'CA 706',
                                data: <?= json_encode($chartRevenue, JSON_UNESCAPED_UNICODE) ?>,
                                tension: 0.25,
                                borderWidth: 2,
                                pointRadius: 2,
                                fill: false
                            },
                            {
                                label: 'Crédit 512',
                                data: <?= json_encode($chartTreasury, JSON_UNESCAPED_UNICODE) ?>,
                                tension: 0.25,
                                borderWidth: 2,
                                pointRadius: 2,
                                fill: false
                            },
                            {
                                label: 'Débit 411',
                                data: <?= json_encode($chartClientDebit, JSON_UNESCAPED_UNICODE) ?>,
                                tension: 0.25,
                                borderWidth: 2,
                                pointRadius: 2,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        resizeDelay: 200,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            const typeCanvas = document.getElementById('typeChart');
            if (typeCanvas) {
                new Chart(typeCanvas, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($chartTypeLabels, JSON_UNESCAPED_UNICODE) ?>,
                        datasets: [{
                            label: 'Montant',
                            data: <?= json_encode($chartTypeAmounts, JSON_UNESCAPED_UNICODE) ?>,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        resizeDelay: 200,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            const serviceCanvas = document.getElementById('serviceChart');
            if (serviceCanvas) {
                new Chart(serviceCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($chartServiceLabels, JSON_UNESCAPED_UNICODE) ?>,
                        datasets: [{
                            label: 'Montant',
                            data: <?= json_encode($chartServiceAmounts, JSON_UNESCAPED_UNICODE) ?>,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        resizeDelay: 200
                    }
                });
            }

            const futureTransferCanvas = document.getElementById('futureTransferChart');
            if (futureTransferCanvas) {
                new Chart(futureTransferCanvas, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($futureTransferLabels, JSON_UNESCAPED_UNICODE) ?>,
                        datasets: [{
                            label: 'Montant à virer',
                            data: <?= json_encode($futureTransferAmounts, JSON_UNESCAPED_UNICODE) ?>,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        resizeDelay: 200,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>