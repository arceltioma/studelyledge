<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'manual_actions_create_page');
} else {
    enforcePagePermission($pdo, 'manual_actions_create');
}

$pageTitle = 'Exécuter les mensualités';
$pageSubtitle = 'Génération traçable des opérations mensuelles';

$runDate = trim((string)($_POST['run_date'] ?? date('Y-m-d')));
$scheduledDay = (int)($_POST['scheduled_day'] ?? (int)date('d'));

$successMessage = '';
$errorMessage = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
            throw new RuntimeException('Date d’exécution invalide.');
        }

        if ($scheduledDay < 1 || $scheduledDay > 31) {
            throw new RuntimeException('Jour planifié invalide.');
        }

        $pdo->beginTransaction();

        $stmtClients = $pdo->prepare("
            SELECT *
            FROM clients
            WHERE COALESCE(is_active,1) = 1
              AND COALESCE(monthly_enabled,0) = 1
              AND COALESCE(monthly_amount,0) > 0
              AND COALESCE(monthly_day,26) = ?
            ORDER BY client_code ASC
        ");
        $stmtClients->execute([$scheduledDay]);
        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtRun = $pdo->prepare("
            INSERT INTO monthly_payment_runs (
                run_date,
                scheduled_day,
                total_clients,
                total_created,
                total_skipped,
                total_errors,
                executed_by,
                created_at
            ) VALUES (
                ?,
                ?,
                0,
                0,
                0,
                0,
                ?,
                NOW()
            )
        ");
        $stmtRun->execute([
            $runDate,
            $scheduledDay,
            $_SESSION['user_id'] ?? null
        ]);
        $runId = (int)$pdo->lastInsertId();

        $totalClients = 0;
        $totalCreated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $details = [];

        foreach ($clients as $client) {
            $totalClients++;

            $clientId = (int)($client['id'] ?? 0);
            $clientCode = (string)($client['client_code'] ?? '');
            $amount = (float)($client['monthly_amount'] ?? 0);
            $monthlyTreasuryId = (int)($client['monthly_treasury_account_id'] ?? 0);
            $reference = sl_monthly_payment_build_reference($client, $runDate);
            $label = 'Mensualité - ' . ($clientCode . ' - ' . ($client['full_name'] ?? ''));

            try {
                if ($monthlyTreasuryId <= 0) {
                    sl_monthly_payment_create_run_item($pdo, [
                        'run_id' => $runId,
                        'client_id' => $clientId,
                        'client_code' => $clientCode,
                        'operation_id' => null,
                        'status' => 'skipped',
                        'amount' => $amount,
                        'treasury_account_id' => null,
                        'treasury_account_code' => null,
                        'reference' => $reference,
                        'label' => $label,
                        'message' => 'Aucun compte 512 mensualité défini.',
                    ]);

                    $totalSkipped++;
                    $details[] = $clientCode . ' ignoré : aucun compte 512 mensualité.';
                    continue;
                }

                $stmtTreasury = $pdo->prepare("
                    SELECT *
                    FROM treasury_accounts
                    WHERE id = ?
                      AND COALESCE(is_active,1)=1
                    LIMIT 1
                ");
                $stmtTreasury->execute([$monthlyTreasuryId]);
                $treasury = $stmtTreasury->fetch(PDO::FETCH_ASSOC);

                if (!$treasury) {
                    sl_monthly_payment_create_run_item($pdo, [
                        'run_id' => $runId,
                        'client_id' => $clientId,
                        'client_code' => $clientCode,
                        'operation_id' => null,
                        'status' => 'skipped',
                        'amount' => $amount,
                        'treasury_account_id' => $monthlyTreasuryId,
                        'treasury_account_code' => null,
                        'reference' => $reference,
                        'label' => $label,
                        'message' => 'Compte 512 mensualité introuvable ou inactif.',
                    ]);

                    $totalSkipped++;
                    $details[] = $clientCode . ' ignoré : compte 512 mensualité introuvable.';
                    continue;
                }

                $exists = sl_monthly_payment_operation_exists(
                    $pdo,
                    $clientId,
                    $runDate,
                    $amount,
                    $reference
                );

                if ($exists) {
                    sl_monthly_payment_create_run_item($pdo, [
                        'run_id' => $runId,
                        'client_id' => $clientId,
                        'client_code' => $clientCode,
                        'operation_id' => null,
                        'status' => 'skipped',
                        'amount' => $amount,
                        'treasury_account_id' => (int)($treasury['id'] ?? 0),
                        'treasury_account_code' => (string)($treasury['account_code'] ?? ''),
                        'reference' => $reference,
                        'label' => $label,
                        'message' => 'Opération déjà générée pour cette date.',
                    ]);

                    $totalSkipped++;
                    $details[] = $clientCode . ' ignoré : opération déjà générée.';
                    continue;
                }

                $operationId = sl_monthly_payment_create_operation_with_run(
                    $pdo,
                    $client,
                    $treasury,
                    $amount,
                    $scheduledDay,
                    $runDate,
                    $runId,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    $label
                );

                sl_monthly_payment_create_run_item($pdo, [
                    'run_id' => $runId,
                    'client_id' => $clientId,
                    'client_code' => $clientCode,
                    'operation_id' => $operationId,
                    'status' => 'created',
                    'amount' => $amount,
                    'treasury_account_id' => (int)($treasury['id'] ?? 0),
                    'treasury_account_code' => (string)($treasury['account_code'] ?? ''),
                    'reference' => $reference,
                    'label' => $label,
                    'message' => 'Opération mensuelle créée avec succès.',
                ]);

                $totalCreated++;
                $details[] = $clientCode . ' créé.';
            } catch (Throwable $e) {
                sl_monthly_payment_create_run_item($pdo, [
                    'run_id' => $runId,
                    'client_id' => $clientId > 0 ? $clientId : null,
                    'client_code' => $clientCode !== '' ? $clientCode : null,
                    'operation_id' => null,
                    'status' => 'error',
                    'amount' => $amount,
                    'treasury_account_id' => $monthlyTreasuryId > 0 ? $monthlyTreasuryId : null,
                    'treasury_account_code' => null,
                    'reference' => $reference,
                    'label' => $label,
                    'message' => $e->getMessage(),
                ]);

                $totalErrors++;
                $details[] = $clientCode . ' erreur : ' . $e->getMessage();
            }
        }

        $stmtUpdateRun = $pdo->prepare("
            UPDATE monthly_payment_runs
            SET
                total_clients = ?,
                total_created = ?,
                total_skipped = ?,
                total_errors = ?
            WHERE id = ?
        ");
        $stmtUpdateRun->execute([
            $totalClients,
            $totalCreated,
            $totalSkipped,
            $totalErrors,
            $runId
        ]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'execute_monthly_run',
                'monthly_payments',
                'monthly_payment_run',
                $runId,
                'Exécution d’une génération mensuelle traçable'
            );
        }

        $pdo->commit();

        $result = [
            'run_id' => $runId,
            'total_clients' => $totalClients,
            'total_created' => $totalCreated,
            'total_skipped' => $totalSkipped,
            'total_errors' => $totalErrors,
            'details' => $details,
        ];

        $successMessage = 'Exécution terminée.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="card">
            <form method="POST">
                <?= csrf_input() ?>

                <div class="dashboard-grid-2">
                    <div>
                        <label>Date d’exécution</label>
                        <input type="date" name="run_date" value="<?= e($runDate) ?>" required>
                    </div>
                    <div>
                        <label>Jour planifié</label>
                        <input type="number" name="scheduled_day" min="1" max="31" value="<?= e((string)$scheduledDay) ?>" required>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Exécuter la génération</button>
                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_runs_list.php" class="btn btn-outline">Historique</a>
                </div>
            </form>
        </div>

        <?php if ($result): ?>
            <div class="dashboard-grid-4" style="margin-top:20px;">
                <div class="card"><h3>Clients ciblés</h3><div class="metric-value"><?= (int)$result['total_clients'] ?></div></div>
                <div class="card"><h3>Créées</h3><div class="metric-value"><?= (int)$result['total_created'] ?></div></div>
                <div class="card"><h3>Ignorées</h3><div class="metric-value"><?= (int)$result['total_skipped'] ?></div></div>
                <div class="card"><h3>Erreurs</h3><div class="metric-value"><?= (int)$result['total_errors'] ?></div></div>
            </div>

            <div class="card" style="margin-top:20px;">
                <h3>Détail du run #<?= (int)$result['run_id'] ?></h3>
                <div class="btn-group" style="margin-bottom:15px;">
                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_run_view.php?id=<?= (int)$result['run_id'] ?>" class="btn btn-primary">Voir le détail complet</a>
                </div>
                <ul>
                    <?php foreach ($result['details'] as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>