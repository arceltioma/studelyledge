<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'treasury_view');

function rebuildAllBalances(PDO $pdo): array
{
    $report = [
        'bank_accounts' => 0,
        'treasury_accounts' => 0,
        'service_accounts' => 0,
    ];

    if (tableExists($pdo, 'bank_accounts') && tableExists($pdo, 'operations')) {
        $bankAccounts = $pdo->query("
            SELECT id, account_number, COALESCE(initial_balance, 0) AS initial_balance
            FROM bank_accounts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtBank = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
            FROM operations
        ");

        $stmtUpdateBank = $pdo->prepare("
            UPDATE bank_accounts
            SET balance = ?, updated_at = NOW()
            WHERE id = ?
        ");

        foreach ($bankAccounts as $account) {
            $stmtBank->execute([$account['account_number'], $account['account_number']]);
            $totals = $stmtBank->fetch(PDO::FETCH_ASSOC) ?: [];

            $newBalance = (float)$account['initial_balance']
                + (float)($totals['total_debit'] ?? 0)
                - (float)($totals['total_credit'] ?? 0);

            $stmtUpdateBank->execute([$newBalance, (int)$account['id']]);
            $report['bank_accounts']++;
        }
    }

    if (tableExists($pdo, 'treasury_accounts')) {
        $treasuryAccounts = $pdo->query("
            SELECT id, account_code, COALESCE(opening_balance,0) AS opening_balance
            FROM treasury_accounts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtTreasuryOps = tableExists($pdo, 'operations')
            ? $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
                FROM operations
            ")
            : null;

        $stmtTreasuryMov = tableExists($pdo, 'treasury_movements')
            ? $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN target_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_in,
                    COALESCE(SUM(CASE WHEN source_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_out
                FROM treasury_movements
            ")
            : null;

        $stmtUpdateTreasury = $pdo->prepare("
            UPDATE treasury_accounts
            SET current_balance = ?, updated_at = NOW()
            WHERE id = ?
        ");

        foreach ($treasuryAccounts as $account) {
            $opsDebit = 0.0;
            $opsCredit = 0.0;
            $movIn = 0.0;
            $movOut = 0.0;

            if ($stmtTreasuryOps) {
                $stmtTreasuryOps->execute([$account['account_code'], $account['account_code']]);
                $ops = $stmtTreasuryOps->fetch(PDO::FETCH_ASSOC) ?: [];
                $opsDebit = (float)($ops['total_debit'] ?? 0);
                $opsCredit = (float)($ops['total_credit'] ?? 0);
            }

            if ($stmtTreasuryMov) {
                $stmtTreasuryMov->execute([(int)$account['id'], (int)$account['id']]);
                $mov = $stmtTreasuryMov->fetch(PDO::FETCH_ASSOC) ?: [];
                $movIn = (float)($mov['total_in'] ?? 0);
                $movOut = (float)($mov['total_out'] ?? 0);
            }

            $newBalance = (float)$account['opening_balance'] + $opsDebit - $opsCredit + $movIn - $movOut;
            $stmtUpdateTreasury->execute([$newBalance, (int)$account['id']]);
            $report['treasury_accounts']++;
        }
    }

    if (tableExists($pdo, 'service_accounts') && tableExists($pdo, 'operations')) {
        $serviceAccounts = $pdo->query("
            SELECT id, account_code
            FROM service_accounts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtService = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit,
                COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit
            FROM operations
        ");

        $stmtUpdateService = $pdo->prepare("
            UPDATE service_accounts
            SET current_balance = ?, updated_at = NOW()
            WHERE id = ?
        ");

        foreach ($serviceAccounts as $account) {
            $stmtService->execute([$account['account_code'], $account['account_code']]);
            $totals = $stmtService->fetch(PDO::FETCH_ASSOC) ?: [];

            $newBalance = (float)($totals['total_credit'] ?? 0) - (float)($totals['total_debit'] ?? 0);
            $stmtUpdateService->execute([$newBalance, (int)$account['id']]);
            $report['service_accounts']++;
        }
    }

    return $report;
}

$successMessage = '';
$errorMessage = '';
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $pdo->beginTransaction();
        $report = rebuildAllBalances($pdo);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'rebuild_balances',
                'dashboard',
                'balances',
                null,
                'Recalcul global des soldes 411/512/706'
            );
        }

        $pdo->commit();
        $successMessage = 'Recalcul terminé avec succès.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Recalcul des soldes';
$pageSubtitle = 'Remettre tous les soldes à l’équerre à partir des écritures.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Lancer le recalcul</h3>
                <form method="POST">
                    <?= csrf_input() ?>
                    <p class="muted">
                        Cette opération recalcule :
                        les comptes bancaires clients, les comptes 512 et les comptes 706,
                        à partir des opérations et virements internes enregistrés.
                    </p>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger">Recalculer maintenant</button>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour dashboard</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Dernier rapport</h3>
                <?php if ($report): ?>
                    <div class="stat-row"><span class="metric-label">Comptes bancaires</span><span class="metric-value"><?= (int)$report['bank_accounts'] ?></span></div>
                    <div class="stat-row"><span class="metric-label">Comptes 512</span><span class="metric-value"><?= (int)$report['treasury_accounts'] ?></span></div>
                    <div class="stat-row"><span class="metric-label">Comptes 706</span><span class="metric-value"><?= (int)$report['service_accounts'] ?></span></div>
                <?php else: ?>
                    <div class="dashboard-note">Aucun recalcul lancé dans cette session.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>