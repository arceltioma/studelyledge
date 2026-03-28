<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

function recomputeAllBalancesAfterOperationMutation(PDO $pdo): void
{
    /*
    |--------------------------------------------------------------------------
    | 1. Recalcul des comptes bancaires clients (solde = initial + débits - crédits)
    |--------------------------------------------------------------------------
    */
    if (
        function_exists('tableExists') &&
        tableExists($pdo, 'bank_accounts') &&
        tableExists($pdo, 'operations')
    ) {
        $bankAccounts = $pdo->query("
            SELECT id, account_number, COALESCE(initial_balance, 0) AS initial_balance
            FROM bank_accounts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtBankMovements = $pdo->prepare("
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
            $accountNumber = (string)($account['account_number'] ?? '');
            $initialBalance = (float)($account['initial_balance'] ?? 0);

            $stmtBankMovements->execute([$accountNumber, $accountNumber]);
            $totals = $stmtBankMovements->fetch(PDO::FETCH_ASSOC) ?: [];

            $newBalance = $initialBalance
                + (float)($totals['total_debit'] ?? 0)
                - (float)($totals['total_credit'] ?? 0);

            $stmtUpdateBank->execute([$newBalance, (int)$account['id']]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Recalcul des comptes 512 (solde = ouverture + débits - crédits
    |    + virements entrants - virements sortants)
    |--------------------------------------------------------------------------
    */
    if (
        function_exists('tableExists') &&
        tableExists($pdo, 'treasury_accounts')
    ) {
        $treasuryAccounts = $pdo->query("
            SELECT id, account_code, COALESCE(opening_balance, 0) AS opening_balance
            FROM treasury_accounts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtTreasuryOps = null;
        if (tableExists($pdo, 'operations')) {
            $stmtTreasuryOps = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN debit_account_code = ? THEN amount ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN credit_account_code = ? THEN amount ELSE 0 END), 0) AS total_credit
                FROM operations
            ");
        }

        $stmtTreasuryMovements = null;
        if (tableExists($pdo, 'treasury_movements')) {
            $stmtTreasuryMovements = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN target_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_in,
                    COALESCE(SUM(CASE WHEN source_treasury_account_id = ? THEN amount ELSE 0 END), 0) AS total_out
                FROM treasury_movements
            ");
        }

        $stmtUpdateTreasury = $pdo->prepare("
            UPDATE treasury_accounts
            SET current_balance = ?, updated_at = NOW()
            WHERE id = ?
        ");

        foreach ($treasuryAccounts as $account) {
            $openingBalance = (float)($account['opening_balance'] ?? 0);
            $accountCode = (string)($account['account_code'] ?? '');
            $accountId = (int)$account['id'];

            $opsDebit = 0.0;
            $opsCredit = 0.0;
            $movIn = 0.0;
            $movOut = 0.0;

            if ($stmtTreasuryOps) {
                $stmtTreasuryOps->execute([$accountCode, $accountCode]);
                $ops = $stmtTreasuryOps->fetch(PDO::FETCH_ASSOC) ?: [];
                $opsDebit = (float)($ops['total_debit'] ?? 0);
                $opsCredit = (float)($ops['total_credit'] ?? 0);
            }

            if ($stmtTreasuryMovements) {
                $stmtTreasuryMovements->execute([$accountId, $accountId]);
                $mov = $stmtTreasuryMovements->fetch(PDO::FETCH_ASSOC) ?: [];
                $movIn = (float)($mov['total_in'] ?? 0);
                $movOut = (float)($mov['total_out'] ?? 0);
            }

            $newBalance = $openingBalance + $opsDebit - $opsCredit + $movIn - $movOut;
            $stmtUpdateTreasury->execute([$newBalance, $accountId]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Recalcul des comptes 706 (solde = crédits - débits)
    |--------------------------------------------------------------------------
    */
    if (
        function_exists('tableExists') &&
        tableExists($pdo, 'service_accounts') &&
        tableExists($pdo, 'operations')
    ) {
        $serviceAccounts = $pdo->query("
            SELECT id, account_code
            FROM service_accounts
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtServiceMovements = $pdo->prepare("
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
            $accountCode = (string)($account['account_code'] ?? '');
            $stmtServiceMovements->execute([$accountCode, $accountCode]);
            $totals = $stmtServiceMovements->fetch(PDO::FETCH_ASSOC) ?: [];

            $newBalance = (float)($totals['total_credit'] ?? 0) - (float)($totals['total_debit'] ?? 0);
            $stmtUpdateService->execute([$newBalance, (int)$account['id']]);
        }
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Opération invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        o.*,
        c.client_code,
        c.full_name
    FROM operations o
    LEFT JOIN clients c ON c.id = o.client_id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("DELETE FROM operations WHERE id = ?");
        $stmtDelete->execute([$id]);

        recomputeAllBalancesAfterOperationMutation($pdo);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'delete_operation',
                'operations',
                'operation',
                $id,
                'Suppression d’une opération avec recalcul des soldes'
            );
        }

        $pdo->commit();

        header('Location: ' . APP_URL . 'modules/operations/operations_list.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Supprimer une opération';
$pageSubtitle = 'Confirmation avant suppression d’une écriture avec recalcul des soldes.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Confirmation</h3>
                <p>Tu es sur le point de supprimer cette opération. Cette action est définitive et déclenchera un recalcul des soldes liés.</p>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Détails de l’opération</h3>
                <div class="stat-row"><span class="metric-label">Date</span><span class="metric-value"><?= e($operation['operation_date'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Client</span><span class="metric-value"><?= e(trim((string)($operation['client_code'] ?? '') . ' - ' . (string)($operation['full_name'] ?? ''))) ?></span></div>
                <div class="stat-row"><span class="metric-label">Libellé</span><span class="metric-value"><?= e($operation['label'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Débit</span><span class="metric-value"><?= e($operation['debit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Crédit</span><span class="metric-value"><?= e($operation['credit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Montant</span><span class="metric-value"><?= number_format((float)($operation['amount'] ?? 0), 2, ',', ' ') ?></span></div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>