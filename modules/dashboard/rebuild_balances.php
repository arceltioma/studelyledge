<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_dashboard_view');

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';
$report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (tableExists($pdo, 'bank_accounts')) {
            $pdo->exec("UPDATE bank_accounts SET balance = COALESCE(initial_balance, 0)");
            $report[] = 'Réinitialisation des soldes clients à partir des soldes initiaux.';
        }

        if (tableExists($pdo, 'treasury_accounts')) {
            $pdo->exec("UPDATE treasury_accounts SET current_balance = COALESCE(opening_balance, 0)");
            $report[] = 'Réinitialisation des soldes 512 à partir des soldes d’ouverture.';
        }

        if (tableExists($pdo, 'service_accounts')) {
            $pdo->exec("UPDATE service_accounts SET current_balance = 0");
            $report[] = 'Réinitialisation des soldes 706 à zéro.';
        }

        if (tableExists($pdo, 'operations')) {
            $stmtOps = $pdo->query("SELECT * FROM operations ORDER BY id ASC");
            $operations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

            foreach ($operations as $op) {
                $resolved = [
                    'debit_account_code' => $op['debit_account_code'] ?? null,
                    'credit_account_code' => $op['credit_account_code'] ?? null,
                    'analytic_account' => !empty($op['service_account_code'])
                        ? ['account_code' => $op['service_account_code']]
                        : null,
                ];

                $payload = [
                    'operation_type_code' => $op['operation_type_code'] ?? '',
                    'amount' => (float)($op['amount'] ?? 0),
                ];

                applyAccountingBalanceEffects($pdo, $payload, $resolved, (int)($op['bank_account_id'] ?? 0));
            }

            $report[] = 'Rejeu des opérations clients.';
        }

        if (tableExists($pdo, 'treasury_movements')) {
            $stmtMoves = $pdo->query("SELECT * FROM treasury_movements ORDER BY id ASC");
            $moves = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

            foreach ($moves as $move) {
                $amount = (float)($move['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                if (!empty($move['source_treasury_account_id'])) {
                    updateTreasuryBalanceDelta($pdo, (int)$move['source_treasury_account_id'], -$amount);
                }

                if (!empty($move['target_treasury_account_id'])) {
                    updateTreasuryBalanceDelta($pdo, (int)$move['target_treasury_account_id'], +$amount);
                }
            }

            $report[] = 'Rejeu des virements internes.';
        }

        $pdo->commit();
        $successMessage = 'Recalcul global terminé.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Recalcul global des soldes',
            'Remettre toute la base à l’équerre à partir des écritures existantes.'
        ); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Lancer le recalcul</h3>
                <form method="POST">
                    <p class="muted">
                        Cette action remet les soldes à leur base initiale puis rejoue les opérations et les virements internes.
                    </p>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-danger">Recalculer maintenant</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Rapport</h3>
                <?php if ($report): ?>
                    <ul>
                        <?php foreach ($report as $line): ?>
                            <li><?= e($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="dashboard-note">Aucun recalcul lancé pour le moment.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>