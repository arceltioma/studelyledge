<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if (!function_exists('sl_analytics_table_exists')) {
    function sl_analytics_table_exists(PDO $pdo, string $table): bool
    {
        return function_exists('tableExists') ? tableExists($pdo, $table) : true;
    }
}

if (!function_exists('sl_analytics_format_axis_value')) {
    function sl_analytics_format_axis_value(?string $value): string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : 'Non renseigné';
    }
}

if (!function_exists('sl_analytics_safe_date')) {
    function sl_analytics_safe_date(string $value, string $fallback = ''): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
    }
}

if (!function_exists('sl_analytics_previous_period')) {
    function sl_analytics_previous_period(string $dateFrom, string $dateTo): array
    {
        if ($dateFrom === '' || $dateTo === '') {
            return ['', ''];
        }

        $fromTs = strtotime($dateFrom . ' 00:00:00');
        $toTs = strtotime($dateTo . ' 00:00:00');

        if ($fromTs === false || $toTs === false || $toTs < $fromTs) {
            return ['', ''];
        }

        $days = (int)floor(($toTs - $fromTs) / 86400) + 1;
        $prevTo = strtotime('-1 day', $fromTs);
        $prevFrom = strtotime('-' . ($days - 1) . ' day', $prevTo);

        return [
            date('Y-m-d', $prevFrom),
            date('Y-m-d', $prevTo),
        ];
    }
}

if (!function_exists('sl_analytics_variation_percent')) {
    function sl_analytics_variation_percent(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.00001) {
            return null;
        }
        return (($current - $previous) / abs($previous)) * 100;
    }
}

if (!function_exists('sl_analytics_fetch_clients')) {
    function sl_analytics_fetch_clients(PDO $pdo): array
    {
        if (!sl_analytics_table_exists($pdo, 'clients')) {
            return [];
        }

        $sql = "
            SELECT id, client_code, full_name
            FROM clients
            ORDER BY client_code ASC, full_name ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_analytics_fetch_countries')) {
    function sl_analytics_fetch_countries(PDO $pdo): array
    {
        if (!sl_analytics_table_exists($pdo, 'clients') || !columnExists($pdo, 'clients', 'country_destination')) {
            return [];
        }

        $sql = "
            SELECT DISTINCT country_destination
            FROM clients
            WHERE COALESCE(country_destination, '') <> ''
            ORDER BY country_destination ASC
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter(array_map('strval', $rows), static fn($v) => trim($v) !== ''));
    }
}

if (!function_exists('sl_analytics_fetch_operation_types')) {
    function sl_analytics_fetch_operation_types(PDO $pdo): array
    {
        if (!sl_analytics_table_exists($pdo, 'ref_operation_types')) {
            return [];
        }

        $sql = "
            SELECT code, label
            FROM ref_operation_types
            ORDER BY label ASC, code ASC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_analytics_build_query')) {
    function sl_analytics_build_query(PDO $pdo, array $filters, bool $forTop10 = false): array
    {
        $hasOperations = sl_analytics_table_exists($pdo, 'operations');
        $hasClients = sl_analytics_table_exists($pdo, 'clients');
        $hasOperationTypes = sl_analytics_table_exists($pdo, 'ref_operation_types');

        if (!$hasOperations) {
            return ['sql' => '', 'params' => []];
        }

        $xAxis = $filters['x_axis'] ?? 'month';

        $operationDateExpr = columnExists($pdo, 'operations', 'operation_date') ? 'o.operation_date' : 'NULL';
        $operationTypeCodeExpr = columnExists($pdo, 'operations', 'operation_type_code') ? 'o.operation_type_code' : "''";
        $sourceTypeExpr = columnExists($pdo, 'operations', 'source_type') ? 'o.source_type' : "''";
        $amountExpr = columnExists($pdo, 'operations', 'amount') ? 'o.amount' : '0';
        $creditAccountExpr = columnExists($pdo, 'operations', 'credit_account_code') ? 'o.credit_account_code' : "''";
        $debitAccountExpr = columnExists($pdo, 'operations', 'debit_account_code') ? 'o.debit_account_code' : "''";
        $clientIdExpr = columnExists($pdo, 'operations', 'client_id') ? 'o.client_id' : 'NULL';

        switch ($xAxis) {
            case 'day':
                $groupSql = "DATE({$operationDateExpr})";
                $labelSql = "DATE({$operationDateExpr})";
                break;
            case 'month':
                $groupSql = "DATE_FORMAT({$operationDateExpr}, '%Y-%m')";
                $labelSql = "DATE_FORMAT({$operationDateExpr}, '%Y-%m')";
                break;
            case 'country':
                $groupSql = $hasClients && columnExists($pdo, 'clients', 'country_destination')
                    ? "c.country_destination"
                    : "''";
                $labelSql = $groupSql;
                break;
            case 'client':
                $groupSql = $hasClients && columnExists($pdo, 'clients', 'client_code')
                    ? "c.client_code"
                    : "''";
                $labelSql = $hasClients && columnExists($pdo, 'clients', 'client_code') && columnExists($pdo, 'clients', 'full_name')
                    ? "CONCAT(c.client_code, ' - ', c.full_name)"
                    : $groupSql;
                break;
            case 'operation_type':
                $groupSql = $operationTypeCodeExpr;
                $labelSql = $hasOperationTypes && columnExists($pdo, 'ref_operation_types', 'label')
                    ? "COALESCE(rot.label, {$operationTypeCodeExpr})"
                    : $operationTypeCodeExpr;
                break;
            case 'source_type':
            default:
                $groupSql = $sourceTypeExpr;
                $labelSql = $sourceTypeExpr;
                break;
        }

        $sql = "
            SELECT
                {$labelSql} AS chart_label,
                COALESCE(SUM(
                    CASE
                        WHEN {$creditAccountExpr} LIKE '411%' OR {$creditAccountExpr} LIKE '706%'
                        THEN {$amountExpr}
                        ELSE 0
                    END
                ), 0) AS total_credit,
                COALESCE(SUM(
                    CASE
                        WHEN {$debitAccountExpr} LIKE '411%' OR {$debitAccountExpr} LIKE '706%'
                        THEN {$amountExpr}
                        ELSE 0
                    END
                ), 0) AS total_debit,
                COALESCE(SUM(
                    CASE
                        WHEN {$creditAccountExpr} LIKE '411%' OR {$creditAccountExpr} LIKE '706%'
                        THEN {$amountExpr}
                        ELSE 0
                    END
                ), 0)
                -
                COALESCE(SUM(
                    CASE
                        WHEN {$debitAccountExpr} LIKE '411%' OR {$debitAccountExpr} LIKE '706%'
                        THEN {$amountExpr}
                        ELSE 0
                    END
                ), 0) AS total_net
            FROM operations o
            " . ($hasClients ? "LEFT JOIN clients c ON c.id = {$clientIdExpr}" : "") . "
            " . ($hasOperationTypes && columnExists($pdo, 'ref_operation_types', 'code') ? "LEFT JOIN ref_operation_types rot ON rot.code = {$operationTypeCodeExpr}" : "") . "
            WHERE 1=1
        ";

        $params = [];

        if (($filters['date_from'] ?? '') !== '' && columnExists($pdo, 'operations', 'operation_date')) {
            $sql .= " AND o.operation_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '' && columnExists($pdo, 'operations', 'operation_date')) {
            $sql .= " AND o.operation_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (($filters['client_id'] ?? '') !== '' && columnExists($pdo, 'operations', 'client_id')) {
            $sql .= " AND o.client_id = ?";
            $params[] = (int)$filters['client_id'];
        }

        if (($filters['country'] ?? '') !== '' && $hasClients && columnExists($pdo, 'clients', 'country_destination')) {
            $sql .= " AND c.country_destination = ?";
            $params[] = $filters['country'];
        }

        if (($filters['operation_type_filter'] ?? '') !== '' && columnExists($pdo, 'operations', 'operation_type_code')) {
            $sql .= " AND o.operation_type_code = ?";
            $params[] = $filters['operation_type_filter'];
        }

        $sql .= " GROUP BY {$groupSql} ";

        if ($forTop10) {
            $sql .= " ORDER BY total_net DESC, chart_label ASC LIMIT 10 ";
        } else {
            if (in_array($xAxis, ['day', 'month'], true)) {
                $sql .= " ORDER BY {$groupSql} ASC ";
            } else {
                $sql .= " ORDER BY total_net DESC, {$groupSql} ASC ";
            }
            $sql .= " LIMIT " . (int)($filters['limit'] ?? 24) . " ";
        }

        return ['sql' => $sql, 'params' => $params];
    }
}

if (!function_exists('sl_analytics_run_query')) {
    function sl_analytics_run_query(PDO $pdo, array $filters, bool $forTop10 = false): array
    {
        $built = sl_analytics_build_query($pdo, $filters, $forTop10);
        if ($built['sql'] === '') {
            return [];
        }

        $stmt = $pdo->prepare($built['sql']);
        $stmt->execute($built['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['chart_label'] = sl_analytics_format_axis_value((string)($row['chart_label'] ?? ''));
            $row['total_credit'] = (float)($row['total_credit'] ?? 0);
            $row['total_debit'] = (float)($row['total_debit'] ?? 0);
            $row['total_net'] = (float)($row['total_net'] ?? 0);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('sl_analytics_totals_from_rows')) {
    function sl_analytics_totals_from_rows(array $rows): array
    {
        $credit = 0.0;
        $debit = 0.0;
        $net = 0.0;

        foreach ($rows as $row) {
            $credit += (float)($row['total_credit'] ?? 0);
            $debit += (float)($row['total_debit'] ?? 0);
            $net += (float)($row['total_net'] ?? 0);
        }

        return [
            'credits' => $credit,
            'debits' => $debit,
            'net' => $net,
            'buckets' => count($rows),
        ];
    }
}

if (!function_exists('sl_analytics_best_bucket')) {
    function sl_analytics_best_bucket(array $rows): ?array
    {
        if (!$rows) {
            return null;
        }

        $best = null;
        foreach ($rows as $row) {
            if ($best === null || (float)$row['total_net'] > (float)$best['total_net']) {
                $best = $row;
            }
        }

        return $best;
    }
}

if (!function_exists('sl_analytics_worst_bucket')) {
    function sl_analytics_worst_bucket(array $rows): ?array
    {
        if (!$rows) {
            return null;
        }

        $worst = null;
        foreach ($rows as $row) {
            if ($worst === null || (float)$row['total_net'] < (float)$worst['total_net']) {
                $worst = $row;
            }
        }

        return $worst;
    }
}

$xAxis = trim((string)($_GET['x_axis'] ?? 'month'));
$chartType = trim((string)($_GET['chart_type'] ?? 'line'));
$dateFrom = sl_analytics_safe_date(trim((string)($_GET['date_from'] ?? '')), '');
$dateTo = sl_analytics_safe_date(trim((string)($_GET['date_to'] ?? '')), '');
$clientId = trim((string)($_GET['client_id'] ?? ''));
$country = trim((string)($_GET['country'] ?? ''));
$operationTypeFilter = trim((string)($_GET['operation_type_filter'] ?? ''));
$limit = (int)($_GET['limit'] ?? 24);
$export = trim((string)($_GET['export'] ?? ''));

$allowedX = ['day', 'month', 'country', 'client', 'operation_type', 'source_type'];
$allowedChartTypes = ['bar', 'line'];

if (!in_array($xAxis, $allowedX, true)) {
    $xAxis = 'month';
}
if (!in_array($chartType, $allowedChartTypes, true)) {
    $chartType = 'line';
}
if (!in_array($limit, [10, 20, 24, 50, 100], true)) {
    $limit = 24;
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

$clients = sl_analytics_fetch_clients($pdo);
$countries = sl_analytics_fetch_countries($pdo);
$operationTypes = sl_analytics_fetch_operation_types($pdo);

$currentFilters = [
    'x_axis' => $xAxis,
    'chart_type' => $chartType,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'client_id' => $clientId,
    'country' => $country,
    'operation_type_filter' => $operationTypeFilter,
    'limit' => $limit,
];

$rows = sl_analytics_run_query($pdo, $currentFilters, false);
$top10Rows = sl_analytics_run_query($pdo, $currentFilters, true);

$totals = sl_analytics_totals_from_rows($rows);
$bestBucket = sl_analytics_best_bucket($rows);
$worstBucket = sl_analytics_worst_bucket($rows);

[$previousDateFrom, $previousDateTo] = sl_analytics_previous_period($dateFrom, $dateTo);

$previousTotals = [
    'credits' => 0.0,
    'debits' => 0.0,
    'net' => 0.0,
    'buckets' => 0,
];

if ($previousDateFrom !== '' && $previousDateTo !== '') {
    $previousFilters = $currentFilters;
    $previousFilters['date_from'] = $previousDateFrom;
    $previousFilters['date_to'] = $previousDateTo;

    $previousRows = sl_analytics_run_query($pdo, $previousFilters, false);
    $previousTotals = sl_analytics_totals_from_rows($previousRows);
}

$netVariation = sl_analytics_variation_percent((float)$totals['net'], (float)$previousTotals['net']);
$creditVariation = sl_analytics_variation_percent((float)$totals['credits'], (float)$previousTotals['credits']);
$debitVariation = sl_analytics_variation_percent((float)$totals['debits'], (float)$previousTotals['debits']);

$chartLabels = [];
$creditValues = [];
$debitValues = [];
$netValues = [];

foreach ($rows as $row) {
    $chartLabels[] = (string)$row['chart_label'];
    $creditValues[] = (float)$row['total_credit'];
    $debitValues[] = (float)$row['total_debit'];
    $netValues[] = (float)$row['total_net'];
}

$alerts = [];

if ((float)$totals['net'] < 0) {
    $alerts[] = [
        'level' => 'danger',
        'title' => 'Net global négatif',
        'message' => 'La période analysée présente un net consolidé négatif.',
    ];
}

if ($worstBucket && (float)$worstBucket['total_net'] < 0) {
    $alerts[] = [
        'level' => 'warning',
        'title' => 'Segment déficitaire',
        'message' => 'Le segment "' . (string)$worstBucket['chart_label'] . '" est actuellement le plus faible.',
    ];
}

if ($netVariation !== null && $netVariation < -20) {
    $alerts[] = [
        'level' => 'warning',
        'title' => 'Baisse marquée vs N-1',
        'message' => 'Le net est en baisse de ' . number_format($netVariation, 2, ',', ' ') . ' % par rapport à la période précédente.',
    ];
}

if (!$alerts) {
    $alerts[] = [
        'level' => 'success',
        'title' => 'Situation stable',
        'message' => 'Aucune alerte majeure détectée sur la période analysée.',
    ];
}

if ($export === 'csv') {
    $filename = 'analytics_revenue_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, [$xAxisLabels[$xAxis], 'Credits', 'Debits', 'Net'], ';');

    foreach ($rows as $row) {
        fputcsv($out, [
            (string)$row['chart_label'],
            number_format((float)$row['total_credit'], 2, '.', ''),
            number_format((float)$row['total_debit'], 2, '.', ''),
            number_format((float)$row['total_net'], 2, '.', ''),
        ], ';');
    }

    fclose($out);
    exit;
}

$pageTitle = 'Analytics';
$pageSubtitle = 'Analyse premium des flux financiers, comparatifs et signaux d’alerte.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .ra2-hero,
            .ra2-filter-card,
            .ra2-panel,
            .ra2-table-card,
            .ra2-side-card,
            .ra2-alert-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
            }

            .ra2-hero {
                padding: 22px 24px;
                margin-bottom: 20px;
                background: linear-gradient(135deg, rgba(15,23,42,1) 0%, rgba(30,41,59,0.96) 45%, rgba(79,70,229,0.95) 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            }

            .ra2-hero__top {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:18px;
                flex-wrap:wrap;
            }

            .ra2-hero__title {
                margin:0 0 8px;
                font-size:28px;
                font-weight:800;
                letter-spacing:-0.02em;
            }

            .ra2-hero__subtitle {
                margin:0;
                max-width:920px;
                color:rgba(255,255,255,0.86);
                line-height:1.55;
            }

            .ra2-kpi-grid {
                display:grid;
                grid-template-columns:repeat(5, minmax(0, 1fr));
                gap:14px;
                margin-bottom:20px;
            }

            @media (max-width: 1100px) {
                .ra2-kpi-grid {
                    grid-template-columns:repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 680px) {
                .ra2-kpi-grid {
                    grid-template-columns:1fr;
                }
            }

            .ra2-kpi {
                border-radius:18px;
                padding:16px;
                background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border: 1px solid rgba(148,163,184,0.14);
            }

            .ra2-kpi__label {
                display:block;
                font-size:12px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                margin-bottom:8px;
            }

            .ra2-kpi__value {
                display:block;
                font-size:28px;
                line-height:1;
                font-weight:800;
                color:#0f172a;
            }

            .ra2-kpi__meta {
                margin-top:8px;
                color:#64748b;
                font-size:12px;
            }

            .ra2-filter-card,
            .ra2-panel,
            .ra2-table-card,
            .ra2-side-card {
                padding:20px;
                margin-bottom:20px;
            }

            .ra2-filter-grid {
                display:grid;
                grid-template-columns:repeat(7, minmax(0, 1fr));
                gap:14px;
                align-items:end;
            }

            @media (max-width: 1250px) {
                .ra2-filter-grid {
                    grid-template-columns:repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 760px) {
                .ra2-filter-grid {
                    grid-template-columns:1fr;
                }
            }

            .ra2-main-grid {
                display:grid;
                grid-template-columns:1.35fr .65fr;
                gap:20px;
                margin-bottom:20px;
            }

            @media (max-width: 1100px) {
                .ra2-main-grid {
                    grid-template-columns:1fr;
                }
            }

            .ra2-grid-2 {
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:20px;
                margin-bottom:20px;
            }

            @media (max-width: 1100px) {
                .ra2-grid-2 {
                    grid-template-columns:1fr;
                }
            }

            .ra2-title {
                margin:0 0 8px;
                font-size:20px;
                font-weight:800;
                color:#0f172a;
            }

            .ra2-subtitle {
                margin:0 0 16px;
                color:#64748b;
                line-height:1.55;
            }

            .ra2-chart-shell {
                border-radius:18px;
                padding:16px;
                background:rgba(248,250,252,0.72);
                border:1px solid rgba(148,163,184,0.14);
            }

            .ra2-chart-wrap {
                position:relative;
                min-height:420px;
            }

            .ra2-empty {
                padding:20px;
                border-radius:18px;
                background:rgba(248,250,252,0.8);
                border:1px dashed rgba(148,163,184,0.35);
                color:#64748b;
            }

            .ra2-insight-list,
            .ra2-alert-list {
                display:grid;
                gap:12px;
            }

            .ra2-insight-box,
            .ra2-alert-card {
                border-radius:16px;
                padding:14px 16px;
                background:linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border:1px solid rgba(148,163,184,0.14);
            }

            .ra2-insight-box__label,
            .ra2-alert-card__label {
                display:block;
                font-size:12px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                margin-bottom:8px;
            }

            .ra2-insight-box__value,
            .ra2-alert-card__title {
                display:block;
                font-size:16px;
                font-weight:800;
                color:#0f172a;
                line-height:1.4;
            }

            .ra2-insight-box__meta,
            .ra2-alert-card__message {
                display:block;
                margin-top:6px;
                color:#64748b;
                font-size:12px;
                line-height:1.5;
            }

            .ra2-alert-card--success {
                border-color: rgba(16,185,129,0.20);
                background: rgba(16,185,129,0.05);
            }

            .ra2-alert-card--warning {
                border-color: rgba(245,158,11,0.22);
                background: rgba(245,158,11,0.06);
            }

            .ra2-alert-card--danger {
                border-color: rgba(239,68,68,0.22);
                background: rgba(239,68,68,0.06);
            }

            .ra2-table-wrap {
                overflow-x:auto;
            }

            .ra2-table {
                width:100%;
                border-collapse:collapse;
            }

            .ra2-table th,
            .ra2-table td {
                padding:12px 14px;
                border-bottom:1px solid rgba(148,163,184,0.12);
                text-align:left;
                white-space:nowrap;
            }

            .ra2-table th {
                font-size:12px;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                font-weight:800;
            }

            .ra2-net-positive {
                color:#047857;
                font-weight:800;
            }

            .ra2-net-negative {
                color:#b91c1c;
                font-weight:800;
            }

            .ra2-pill {
                display:inline-flex;
                align-items:center;
                padding:5px 10px;
                border-radius:999px;
                font-size:11px;
                font-weight:800;
                letter-spacing:.02em;
            }

            .ra2-pill--green {
                background:rgba(16,185,129,0.10);
                color:#047857;
            }

            .ra2-pill--red {
                background:rgba(239,68,68,0.10);
                color:#b91c1c;
            }

            .ra2-pill--blue {
                background:rgba(59,130,246,0.10);
                color:#1d4ed8;
            }
        </style>

        <section class="ra2-hero">
            <div class="ra2-hero__top">
                <div>
                    <h1 class="ra2-hero__title">Revenue Analysis Premium v2</h1>
                    <p class="ra2-hero__subtitle">
                        Cette page permet de piloter les flux financiers 411 / 706 selon plusieurs axes, avec filtres métier, comparatif période précédente,
                        top 10 automatique, export CSV et alertes analytiques.
                    </p>
                </div>

                <div class="btn-group">
                    <?php if (currentUserCan($pdo, 'dashboard_view')): ?>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour dashboard</a>
                    <?php endif; ?>
                    <a href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </section>

        <section class="ra2-kpi-grid">
            <div class="ra2-kpi">
                <span class="ra2-kpi__label">Crédits</span>
                <span class="ra2-kpi__value"><?= number_format((float)$totals['credits'], 2, ',', ' ') ?> €</span>
                <div class="ra2-kpi__meta">
                    Variation :
                    <?= $creditVariation === null ? 'N/A' : number_format($creditVariation, 2, ',', ' ') . ' %' ?>
                </div>
            </div>

            <div class="ra2-kpi">
                <span class="ra2-kpi__label">Débits</span>
                <span class="ra2-kpi__value"><?= number_format((float)$totals['debits'], 2, ',', ' ') ?> €</span>
                <div class="ra2-kpi__meta">
                    Variation :
                    <?= $debitVariation === null ? 'N/A' : number_format($debitVariation, 2, ',', ' ') . ' %' ?>
                </div>
            </div>

            <div class="ra2-kpi">
                <span class="ra2-kpi__label">Net</span>
                <span class="ra2-kpi__value"><?= number_format((float)$totals['net'], 2, ',', ' ') ?> €</span>
                <div class="ra2-kpi__meta">
                    Variation :
                    <?= $netVariation === null ? 'N/A' : number_format($netVariation, 2, ',', ' ') . ' %' ?>
                </div>
            </div>

            <div class="ra2-kpi">
                <span class="ra2-kpi__label">Groupes</span>
                <span class="ra2-kpi__value"><?= (int)$totals['buckets'] ?></span>
                <div class="ra2-kpi__meta">Points visibles</div>
            </div>

            <div class="ra2-kpi">
                <span class="ra2-kpi__label">Top segment</span>
                <span class="ra2-kpi__value" style="font-size:18px;">
                    <?= e((string)($bestBucket['chart_label'] ?? '—')) ?>
                </span>
                <div class="ra2-kpi__meta">
                    <?= $bestBucket ? number_format((float)$bestBucket['total_net'], 2, ',', ' ') . ' €' : 'Aucune donnée' ?>
                </div>
            </div>
        </section>

        <section class="ra2-filter-card">
            <h3 class="ra2-title">Filtres analytiques</h3>
            <p class="ra2-subtitle">Affinage de l’axe d’analyse, de la période et du périmètre métier.</p>

            <form method="GET">
                <div class="ra2-filter-grid">
                    <div>
                        <label>Axe X</label>
                        <select name="x_axis">
                            <?php foreach ($xAxisLabels as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $xAxis === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type de graphe</label>
                        <select name="chart_type">
                            <?php foreach ($chartTypeLabels as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $chartType === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Du</label>
                        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                    </div>

                    <div>
                        <label>Au</label>
                        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                    </div>

                    <div>
                        <label>Client</label>
                        <select name="client_id">
                            <option value="">Tous</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int)$client['id'] ?>" <?= $clientId === (string)$client['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$client['client_code'] . ' - ' . (string)$client['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays</label>
                        <select name="country">
                            <option value="">Tous</option>
                            <?php foreach ($countries as $countryItem): ?>
                                <option value="<?= e($countryItem) ?>" <?= $country === $countryItem ? 'selected' : '' ?>>
                                    <?= e($countryItem) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type d’opération</label>
                        <select name="operation_type_filter">
                            <option value="">Tous</option>
                            <?php foreach ($operationTypes as $type): ?>
                                <option value="<?= e((string)$type['code']) ?>" <?= $operationTypeFilter === (string)$type['code'] ? 'selected' : '' ?>>
                                    <?= e((string)$type['label'] . ' (' . (string)$type['code'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Nb max groupes</label>
                        <select name="limit">
                            <?php foreach ([10, 20, 24, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= $limit === $size ? 'selected' : '' ?>>
                                    <?= $size ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Analyser</button>
                    <a href="<?= e(APP_URL) ?>modules/analytics/revenue_analysis.php" class="btn btn-outline">Réinitialiser</a>
                    <button type="submit" name="export" value="csv" class="btn btn-secondary">Export CSV</button>
                </div>
            </form>
        </section>

        <div class="ra2-main-grid">
            <section class="ra2-panel">
                <h3 class="ra2-title">Graphe analytique</h3>
                <p class="ra2-subtitle">
                    Axe : <strong><?= e($xAxisLabels[$xAxis]) ?></strong> —
                    Type : <strong><?= e($chartTypeLabels[$chartType]) ?></strong>
                </p>

                <?php if (!$rows): ?>
                    <div class="ra2-empty">Aucune donnée analytique à afficher pour les filtres sélectionnés.</div>
                <?php else: ?>
                    <div class="ra2-chart-shell">
                        <div class="ra2-chart-wrap">
                            <canvas id="analyticsChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="ra2-side-card">
                <h3 class="ra2-title">Alertes analytiques</h3>
                <p class="ra2-subtitle">Lecture rapide des signaux faibles et écarts majeurs.</p>

                <div class="ra2-alert-list">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="ra2-alert-card ra2-alert-card--<?= e((string)$alert['level']) ?>">
                            <span class="ra2-alert-card__label">Alerte</span>
                            <span class="ra2-alert-card__title"><?= e((string)$alert['title']) ?></span>
                            <span class="ra2-alert-card__message"><?= e((string)$alert['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ra2-insight-list" style="margin-top:16px;">
                    <div class="ra2-insight-box">
                        <span class="ra2-insight-box__label">Période N-1</span>
                        <span class="ra2-insight-box__value">
                            <?= e($previousDateFrom !== '' ? $previousDateFrom : 'N/A') ?>
                            →
                            <?= e($previousDateTo !== '' ? $previousDateTo : 'N/A') ?>
                        </span>
                        <span class="ra2-insight-box__meta">Comparatif automatique</span>
                    </div>

                    <div class="ra2-insight-box">
                        <span class="ra2-insight-box__label">Point faible</span>
                        <span class="ra2-insight-box__value"><?= e((string)($worstBucket['chart_label'] ?? '—')) ?></span>
                        <span class="ra2-insight-box__meta">
                            <?= $worstBucket ? number_format((float)$worstBucket['total_net'], 2, ',', ' ') . ' €' : 'Aucune donnée' ?>
                        </span>
                    </div>
                </div>
            </aside>
        </div>

        <div class="ra2-grid-2">
            <section class="ra2-table-card">
                <h3 class="ra2-title">Top 10 automatique</h3>
                <p class="ra2-subtitle">Segments les plus contributeurs sur le net visible.</p>

                <?php if (!$top10Rows): ?>
                    <div class="ra2-empty">Aucune donnée disponible.</div>
                <?php else: ?>
                    <div class="ra2-table-wrap">
                        <table class="ra2-table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th><?= e($xAxisLabels[$xAxis]) ?></th>
                                    <th>Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top10Rows as $index => $row): ?>
                                    <tr>
                                        <td>#<?= $index + 1 ?></td>
                                        <td><?= e((string)$row['chart_label']) ?></td>
                                        <td class="<?= (float)$row['total_net'] >= 0 ? 'ra2-net-positive' : 'ra2-net-negative' ?>">
                                            <?= number_format((float)$row['total_net'], 2, ',', ' ') ?> €
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="ra2-table-card">
                <h3 class="ra2-title">Comparatif période</h3>
                <p class="ra2-subtitle">N vs période précédente de même durée.</p>

                <div class="ra2-table-wrap">
                    <table class="ra2-table">
                        <thead>
                            <tr>
                                <th>Indicateur</th>
                                <th>Période N</th>
                                <th>Période N-1</th>
                                <th>Variation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Crédits</td>
                                <td><?= number_format((float)$totals['credits'], 2, ',', ' ') ?> €</td>
                                <td><?= number_format((float)$previousTotals['credits'], 2, ',', ' ') ?> €</td>
                                <td><?= $creditVariation === null ? 'N/A' : number_format($creditVariation, 2, ',', ' ') . ' %' ?></td>
                            </tr>
                            <tr>
                                <td>Débits</td>
                                <td><?= number_format((float)$totals['debits'], 2, ',', ' ') ?> €</td>
                                <td><?= number_format((float)$previousTotals['debits'], 2, ',', ' ') ?> €</td>
                                <td><?= $debitVariation === null ? 'N/A' : number_format($debitVariation, 2, ',', ' ') . ' %' ?></td>
                            </tr>
                            <tr>
                                <td>Net</td>
                                <td><?= number_format((float)$totals['net'], 2, ',', ' ') ?> €</td>
                                <td><?= number_format((float)$previousTotals['net'], 2, ',', ' ') ?> €</td>
                                <td><?= $netVariation === null ? 'N/A' : number_format($netVariation, 2, ',', ' ') . ' %' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="ra2-table-card">
            <h3 class="ra2-title">Synthèse tabulaire détaillée</h3>

            <?php if (!$rows): ?>
                <div class="ra2-empty">Aucune donnée trouvée.</div>
            <?php else: ?>
                <div class="ra2-table-wrap">
                    <table class="ra2-table">
                        <thead>
                            <tr>
                                <th><?= e($xAxisLabels[$xAxis]) ?></th>
                                <th>Crédits</th>
                                <th>Débits</th>
                                <th>Net</th>
                                <th>Lecture</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $net = (float)$row['total_net']; ?>
                                <tr>
                                    <td><?= e((string)$row['chart_label']) ?></td>
                                    <td><?= number_format((float)$row['total_credit'], 2, ',', ' ') ?> €</td>
                                    <td><?= number_format((float)$row['total_debit'], 2, ',', ' ') ?> €</td>
                                    <td class="<?= $net >= 0 ? 'ra2-net-positive' : 'ra2-net-negative' ?>">
                                        <?= number_format($net, 2, ',', ' ') ?> €
                                    </td>
                                    <td>
                                        <?php if ($net > 0): ?>
                                            <span class="ra2-pill ra2-pill--green">Excédentaire</span>
                                        <?php elseif ($net < 0): ?>
                                            <span class="ra2-pill ra2-pill--red">Déficitaire</span>
                                        <?php else: ?>
                                            <span class="ra2-pill ra2-pill--blue">Équilibré</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

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

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const canvas = document.getElementById('analyticsChart');
    if (!canvas) {
        return;
    }

    const data = window.analyticsChartData || {};
    const ctx = canvas.getContext('2d');

    new Chart(ctx, {
        type: data.chartType || 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label: 'Crédits',
                    data: data.creditValues || [],
                    borderWidth: 2,
                    tension: 0.28
                },
                {
                    label: 'Débits',
                    data: data.debitValues || [],
                    borderWidth: 2,
                    tension: 0.28
                },
                {
                    label: 'Net',
                    data: data.netValues || [],
                    borderWidth: 3,
                    tension: 0.28
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.raw || 0);
                            return context.dataset.label + ' : ' + value.toLocaleString('fr-FR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }) + ' €';
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: data.xAxisLabel || 'Axe'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Montant (€)'
                    },
                    ticks: {
                        callback: function(value) {
                            return Number(value).toLocaleString('fr-FR') + ' €';
                        }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>