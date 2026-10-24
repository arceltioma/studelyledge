<?php
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/app.php';
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getPDO();
}

require_once __DIR__ . '/admin_functions.php';

$footerYear = date('Y');

if (!function_exists('sl_footer_safe_count')) {
    function sl_footer_safe_count(PDO $pdo, string $sql, array $params = []): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('sl_footer_safe_sum')) {
    function sl_footer_safe_sum(PDO $pdo, string $sql, array $params = []): float
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('sl_footer_table_ready')) {
    function sl_footer_table_ready(PDO $pdo, string $table): bool
    {
        return function_exists('tableExists') ? tableExists($pdo, $table) : false;
    }
}

if (!function_exists('sl_footer_column_ready')) {
    function sl_footer_column_ready(PDO $pdo, string $table, string $column): bool
    {
        return function_exists('columnExists') ? columnExists($pdo, $table, $column) : false;
    }
}

if (!function_exists('sl_footer_support_status_filter')) {
    function sl_footer_support_status_filter(PDO $pdo, string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        if (!sl_footer_table_ready($pdo, 'support_requests') || !sl_footer_column_ready($pdo, 'support_requests', 'status')) {
            return '1=1';
        }

        return $prefix . "status IN ('open', 'in_progress', 'pending')";
    }
}

if (!function_exists('sl_footer_support_count_by_type')) {
    function sl_footer_support_count_by_type(PDO $pdo, string $type, bool $onlyOpen = true): int
    {
        if (!sl_footer_table_ready($pdo, 'support_requests')) {
            return 0;
        }

        $hasType = sl_footer_column_ready($pdo, 'support_requests', 'request_type');
        $hasStatus = sl_footer_column_ready($pdo, 'support_requests', 'status');

        $where = ['1=1'];
        $params = [];

        if ($hasType) {
            $where[] = 'request_type = ?';
            $params[] = $type;
        }

        if ($onlyOpen && $hasStatus) {
            $where[] = "status IN ('open', 'in_progress', 'pending')";
        }

        return sl_footer_safe_count(
            $pdo,
            "SELECT COUNT(*) FROM support_requests WHERE " . implode(' AND ', $where),
            $params
        );
    }
}

if (!function_exists('sl_footer_count_contact_messages')) {
    function sl_footer_count_contact_messages(PDO $pdo): int
    {
        if (sl_footer_table_ready($pdo, 'contact_messages')) {
            return sl_footer_safe_count($pdo, "SELECT COUNT(*) FROM contact_messages");
        }

        return 0;
    }
}

$databaseConnected = false;
$systemOperational = true;

$totalActiveClients = 0;
$totalTreasuryBalance = 0.0;
$totalServiceAccountsTotal = 0.0;

$totalOpenQuestions = 0;
$totalOpenBugs = 0;
$totalPendingAccessRequests = 0;
$totalContactMessages = 0;
$totalOpenSupport = 0;

try {
    $databaseConnected = (bool)$pdo->query('SELECT 1')->fetchColumn();

    if (sl_footer_table_ready($pdo, 'clients')) {
        $clientWhere = sl_footer_column_ready($pdo, 'clients', 'is_active')
            ? "WHERE COALESCE(is_active, 1) = 1"
            : '';

        $totalActiveClients = sl_footer_safe_count(
            $pdo,
            "SELECT COUNT(*) FROM clients {$clientWhere}"
        );
    }

    if (sl_footer_table_ready($pdo, 'treasury_accounts')) {
        if (sl_footer_column_ready($pdo, 'treasury_accounts', 'current_balance')) {
            $where = sl_footer_column_ready($pdo, 'treasury_accounts', 'is_active')
                ? "WHERE COALESCE(is_active, 1) = 1"
                : '';

            $totalTreasuryBalance = sl_footer_safe_sum(
                $pdo,
                "SELECT COALESCE(SUM(current_balance), 0) FROM treasury_accounts {$where}"
            );
        } elseif (sl_footer_column_ready($pdo, 'treasury_accounts', 'balance')) {
            $where = sl_footer_column_ready($pdo, 'treasury_accounts', 'is_active')
                ? "WHERE COALESCE(is_active, 1) = 1"
                : '';

            $totalTreasuryBalance = sl_footer_safe_sum(
                $pdo,
                "SELECT COALESCE(SUM(balance), 0) FROM treasury_accounts {$where}"
            );
        }
    }

    if (sl_footer_table_ready($pdo, 'service_accounts')) {
        if (sl_footer_column_ready($pdo, 'service_accounts', 'current_balance')) {
            $where = sl_footer_column_ready($pdo, 'service_accounts', 'is_active')
                ? "WHERE COALESCE(is_active, 1) = 1"
                : '';

            $totalServiceAccountsTotal = sl_footer_safe_sum(
                $pdo,
                "SELECT COALESCE(SUM(current_balance), 0) FROM service_accounts {$where}"
            );
        } elseif (sl_footer_column_ready($pdo, 'service_accounts', 'balance')) {
            $where = sl_footer_column_ready($pdo, 'service_accounts', 'is_active')
                ? "WHERE COALESCE(is_active, 1) = 1"
                : '';

            $totalServiceAccountsTotal = sl_footer_safe_sum(
                $pdo,
                "SELECT COALESCE(SUM(balance), 0) FROM service_accounts {$where}"
            );
        }
    }

    $totalOpenQuestions = sl_footer_support_count_by_type($pdo, 'question', true);
    $totalOpenBugs = sl_footer_support_count_by_type($pdo, 'bug', true);
    $totalPendingAccessRequests = sl_footer_support_count_by_type($pdo, 'access', true);
    $totalContactMessages = sl_footer_count_contact_messages($pdo);
    $totalOpenSupport = $totalOpenQuestions + $totalOpenBugs + $totalPendingAccessRequests;
} catch (Throwable $e) {
    $systemOperational = false;
}

if (!$databaseConnected) {
    $systemOperational = false;
}

$databaseStatusLabel = $databaseConnected ? 'Connectée' : 'Déconnectée';
$databaseStatusClass = $databaseConnected ? 'sl-footer-status--ok' : 'sl-footer-status--ko';

$systemStatusLabel = $systemOperational ? 'Opérationnel' : 'Indisponible';
$systemStatusClass = $systemOperational ? 'sl-footer-status--ok' : 'sl-footer-status--ko';

$showAdminLinks = function_exists('currentUserCan')
    && (
        currentUserCan($pdo, 'admin_dashboard_view_page')
        || currentUserCan($pdo, 'audit_logs_view_page')
        || currentUserCan($pdo, 'user_logs_view_page')
        || currentUserCan($pdo, 'support_requests_view_page')
        || currentUserCan($pdo, 'admin_manage')
    );
?>

<footer class="app-footer">
    <style>
        .rich-footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid rgba(148, 163, 184, 0.16);
        }

        .footer-top,
        .footer-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .footer-top {
            margin-bottom: 16px;
        }

        .footer-left,
        .footer-center,
        .footer-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .footer-separator {
            color: #94a3b8;
        }

        .system-status-bar {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        @media (max-width: 1280px) {
            .system-status-bar {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .system-status-bar {
                grid-template-columns: 1fr;
            }
        }

        .status-item {
            min-height: 92px;
            padding: 14px 16px;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(148, 163, 184, 0.14);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .status-label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
        }

        .status-value {
            display: block;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.35;
            color: #0f172a;
        }

        .status-multi {
            display: grid;
            gap: 6px;
        }

        .status-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #334155;
        }

        .status-line strong {
            color: #0f172a;
            font-weight: 800;
        }

        .sl-footer-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid transparent;
        }

        .sl-footer-status--ok {
            color: #166534;
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(34, 197, 94, 0.28);
        }

        .sl-footer-status--ko {
            color: #b91c1c;
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.28);
        }

        .footer-links {
            padding-top: 12px;
            border-top: 1px solid rgba(148, 163, 184, 0.12);
        }

        .footer-links a {
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .muted {
            color: #64748b;
        }
    </style>

    <div class="rich-footer">

        <div class="footer-top">
            <div class="footer-left">
                <strong><?= e(APP_NAME) ?></strong>
                <span class="footer-separator">•</span>
                <span class="muted">Pilotage financier & suivi des engagements</span>
            </div>

            <div class="footer-right">
                <span class="muted">© <?= e((string)$footerYear) ?></span>
            </div>
        </div>

        <div class="system-status-bar">
            <div class="status-item">
                <span class="status-label">Base centralisée</span>
                <span class="status-value">
                    <span class="sl-footer-status <?= e($databaseStatusClass) ?>">
                        <?= e($databaseStatusLabel) ?>
                    </span>
                </span>
            </div>

            <div class="status-item">
                <span class="status-label">Système</span>
                <span class="status-value">
                    <span class="sl-footer-status <?= e($systemStatusClass) ?>">
                        <?= e($systemStatusLabel) ?>
                    </span>
                </span>
            </div>

            <div class="status-item">
                <span class="status-label">Clients actifs</span>
                <span class="status-value"><?= number_format($totalActiveClients, 0, ',', ' ') ?></span>
            </div>

            <div class="status-item">
                <span class="status-label">Trésorerie 512</span>
                <span class="status-value"><?= number_format($totalTreasuryBalance, 2, ',', ' ') ?> €</span>
            </div>

            <div class="status-item">
                <span class="status-label">CA 706</span>
                <span class="status-value"><?= number_format($totalServiceAccountsTotal, 2, ',', ' ') ?> €</span>
            </div>

            <div class="status-item">
                <span class="status-label">Support</span>
                <div class="status-multi">
                    <div class="status-line">
                        <span>Tickets ouverts</span>
                        <strong><?= number_format($totalOpenQuestions, 0, ',', ' ') ?></strong>
                    </div>
                    <div class="status-line">
                        <span>Bugs déclarés</span>
                        <strong><?= number_format($totalOpenBugs, 0, ',', ' ') ?></strong>
                    </div>
                    <div class="status-line">
                        <span>Demandes d’accès</span>
                        <strong><?= number_format($totalPendingAccessRequests, 0, ',', ' ') ?></strong>
                    </div>
                    <div class="status-line">
                        <span>Messages contact</span>
                        <strong><?= number_format($totalContactMessages, 0, ',', ' ') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-links">
            <div class="footer-left">
                <a href="<?= e(APP_URL) ?>pages/contact.php" class="muted">Contact</a>
                <span class="footer-separator">•</span>
                <a href="<?= e(APP_URL) ?>pages/copyright.php" class="muted">Copyright</a>
                <span class="footer-separator">•</span>
                <a href="<?= e(APP_URL) ?>pages/cookies_policy.php" class="muted">Cookies</a>
                <span class="footer-separator">•</span>
                <a href="<?= e(APP_URL) ?>pages/sitemap.php" class="muted">Sitemap</a>
            </div>

            <div class="footer-center">
                <?php if ($showAdminLinks): ?>
                    <a href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php" class="muted">Admin</a>
                    <span class="footer-separator">•</span>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="muted">Logs</a>
                    <span class="footer-separator">•</span>
                    <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="muted">Support</a>
                <?php else: ?>
                    <span class="muted">Accès gouverné par rôle</span>
                <?php endif; ?>
            </div>

            <div class="footer-right">
                <span class="muted">Demandes support actives : <?= number_format($totalOpenSupport, 0, ',', ' ') ?></span>
            </div>
        </div>
    </div>
</footer>