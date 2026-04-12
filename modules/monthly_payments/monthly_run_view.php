<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_journal_page');
} else {
    enforcePagePermission($pdo, 'imports_journal');
}

$runId = (int)($_GET['id'] ?? 0);
if ($runId <= 0) {
    exit('Run invalide.');
}

$stmt = $pdo->prepare("
    SELECT r.*
    FROM monthly_payment_runs r
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$runId]);
$run = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$run) {
    exit('Run introuvable.');
}

$items = [];
if (tableExists($pdo, 'monthly_payment_run_items')) {
    $stmt = $pdo->prepare("
        SELECT
            i.*,
            c.full_name,
            o.operation_date,
            o.debit_account_code,
            o.credit_account_code
        FROM monthly_payment_run_items i
        LEFT JOIN clients c ON c.id = i.client_id
        LEFT JOIN operations o ON o.id = i.operation_id
        WHERE i.run_id = ?
        ORDER BY i.id ASC
    ");
    $stmt->execute([$runId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$totals = function_exists('sl_monthly_payment_get_run_totals')
    ? sl_monthly_payment_get_run_totals($pdo, $runId)
    : [
        'total_items' => count($items),
        'success_count' => 0,
        'skipped_count' => 0,
        'error_count' => 0,
        'total_amount_created' => 0,
    ];

$pageTitle = 'Détail run mensualités';
$pageSubtitle = 'Traçabilité complète du run #' . $runId;
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="btn-group" style="margin-bottom:20px;">
            <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_runs_list.php" class="btn btn-outline">Retour</a>
            <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_run_execute.php" class="btn btn-success">Nouveau run</a>
        </div>

        <div class="dashboard-grid-4">
            <div class="card">
                <div class="stat-title">Run ID</div>
                <div class="stat-value"><?= (int)$run['id'] ?></div>
                <div class="stat-subtitle">Identifiant</div>
            </div>
            <div class="card">
                <div class="stat-title">Date</div>
                <div class="stat-value"><?= e((string)$run['run_date']) ?></div>
                <div class="stat-subtitle">Date d’exécution</div>
            </div>
            <div class="card">
                <div class="stat-title">Jour planifié</div>
                <div class="stat-value"><?= (int)$run['scheduled_day'] ?></div>
                <div class="stat-subtitle">Jour clients</div>
            </div>
            <div class="card">
                <div class="stat-title">Créées</div>
                <div class="stat-value"><?= (int)$run['total_created'] ?></div>
                <div class="stat-subtitle">Opérations créées</div>
            </div>
        </div>

        <div class="dashboard-grid-4" style="margin-top:20px;">
            <div class="card">
                <div class="stat-title">Items</div>
                <div class="stat-value"><?= (int)($totals['total_items'] ?? 0) ?></div>
                <div class="stat-subtitle">Lignes du run</div>
            </div>
            <div class="card">
                <div class="stat-title">Ignorées</div>
                <div class="stat-value"><?= (int)($totals['skipped_count'] ?? 0) ?></div>
                <div class="stat-subtitle">Skip</div>
            </div>
            <div class="card">
                <div class="stat-title">Erreurs</div>
                <div class="stat-value"><?= (int)($totals['error_count'] ?? 0) ?></div>
                <div class="stat-subtitle">Ko</div>
            </div>
            <div class="card">
                <div class="stat-title">Montant créé</div>
                <div class="stat-value" style="font-size:1.3rem;"><?= number_format((float)($totals['total_amount_created'] ?? 0), 2, ',', ' ') ?></div>
                <div class="stat-subtitle">Total généré</div>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Détail des items</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID item</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Compte 512</th>
                            <th>Référence</th>
                            <th>Statut</th>
                            <th>Opération</th>
                            <th>Comptes</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= (int)($item['id'] ?? 0) ?></td>
                                <td>
                                    <?= e((string)($item['client_code'] ?? '')) ?><br>
                                    <small><?= e((string)($item['full_name'] ?? '')) ?></small>
                                </td>
                                <td><?= number_format((float)($item['amount'] ?? 0), 2, ',', ' ') ?></td>
                                <td><?= e((string)($item['treasury_account_code'] ?? '')) ?></td>
                                <td><?= e((string)($item['reference'] ?? '')) ?></td>
                                <td><?= e((string)($item['status'] ?? '')) ?></td>
                                <td>
                                    <?php if (!empty($item['operation_id'])): ?>
                                        <a href="<?= e(APP_URL) ?>modules/operations/operation_view.php?id=<?= (int)$item['operation_id'] ?>" class="btn btn-sm">
                                            #<?= (int)$item['operation_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e((string)($item['debit_account_code'] ?? '')) ?><br>
                                    <small><?= e((string)($item['credit_account_code'] ?? '')) ?></small>
                                </td>
                                <td><?= e((string)($item['message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr>
                                <td colspan="9">Aucun item trouvé pour ce run.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>