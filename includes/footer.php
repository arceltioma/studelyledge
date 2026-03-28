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

$totalActiveClients = 0;
$totalOperations = 0;
$totalRejectedImports = 0;
$totalOpenSupport = 0;
$totalTreasuryBalance = 0.0;

try {
    if (tableExists($pdo, 'clients')) {
        $totalActiveClients = (int)$pdo->query("
            SELECT COUNT(*)
            FROM clients
            WHERE COALESCE(is_active, 1) = 1
        ")->fetchColumn();
    }

    if (tableExists($pdo, 'operations')) {
        $totalOperations = (int)$pdo->query("
            SELECT COUNT(*)
            FROM operations
        ")->fetchColumn();
    }

    if (tableExists($pdo, 'import_rows')) {
        $totalRejectedImports = (int)$pdo->query("
            SELECT COUNT(*)
            FROM import_rows
            WHERE status = 'rejected'
        ")->fetchColumn();
    }

    if (tableExists($pdo, 'support_requests')) {
        $totalOpenSupport = (int)$pdo->query("
            SELECT COUNT(*)
            FROM support_requests
            WHERE status IN ('open', 'in_progress')
        ")->fetchColumn();
    }

    if (tableExists($pdo, 'treasury_accounts')) {
        $totalTreasuryBalance = (float)$pdo->query("
            SELECT COALESCE(SUM(current_balance), 0)
            FROM treasury_accounts
            WHERE COALESCE(is_active, 1) = 1
        ")->fetchColumn();
    }
} catch (Throwable $e) {
    // On garde un footer silencieux même si une requête de métrique échoue.
}

$footerSystemStatus = 'Système opérationnel';
$footerDataStatus = $totalRejectedImports > 0
    ? 'Imports à corriger'
    : 'Imports synchronisés';

$footerSupportStatus = $totalOpenSupport > 0
    ? $totalOpenSupport . ' ticket(s) ouvert(s)'
    : 'Aucun ticket ouvert';

$showAdminLinks = function_exists('currentUserCan')
    && (
        currentUserCan($pdo, 'admin_dashboard_view')
        || currentUserCan($pdo, 'admin_logs_view')
        || currentUserCan($pdo, 'support_admin_manage')
    );
?>

<footer class="app-footer">
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
                <span class="status-label">État application</span>
                <span class="status-value"><?= e($footerSystemStatus) ?></span>
            </div>

            <div class="status-item">
                <span class="status-label">Clients actifs</span>
                <span class="status-value"><?= number_format($totalActiveClients, 0, ',', ' ') ?></span>
            </div>

            <div class="status-item">
                <span class="status-label">Opérations</span>
                <span class="status-value"><?= number_format($totalOperations, 0, ',', ' ') ?></span>
            </div>

            <div class="status-item">
                <span class="status-label">Trésorerie 512</span>
                <span class="status-value"><?= number_format($totalTreasuryBalance, 2, ',', ' ') ?> €</span>
            </div>

            <div class="status-item">
                <span class="status-label">Qualité imports</span>
                <span class="status-value"><?= e($footerDataStatus) ?></span>
            </div>

            <div class="status-item">
                <span class="status-label">Support</span>
                <span class="status-value"><?= e($footerSupportStatus) ?></span>
            </div>
        </div>

        <div class="footer-links">
            <div class="footer-left">
                <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="muted">Dashboard</a>
                <span class="footer-separator">•</span>
                <a href="<?= e(APP_URL) ?>modules/statements/index.php" class="muted">Exports</a>
                <span class="footer-separator">•</span>
                <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="muted">Journal imports</a>
                <span class="footer-separator">•</span>
                <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="muted">Support</a>
            </div>

            <div class="footer-center">
                <?php if ($showAdminLinks): ?>
                    <a href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php" class="muted">Admin</a>
                    <span class="footer-separator">•</span>
                    <a href="<?= e(APP_URL) ?>modules/admin/user_logs.php" class="muted">Logs</a>
                    <span class="footer-separator">•</span>
                    <a href="<?= e(APP_URL) ?>modules/admin/support_requests.php" class="muted">Tickets</a>
                <?php else: ?>
                    <span class="muted">Accès gouverné par rôle</span>
                <?php endif; ?>
            </div>

            <div class="footer-right">
                <span class="muted">Base centralisée connectée</span>
            </div>
        </div>
    </div>
</footer>