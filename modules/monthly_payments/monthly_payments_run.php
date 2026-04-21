<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'monthly_payments_run');
}

$pageTitle = 'Exécution des mensualités';
$pageSubtitle = 'Génération manuelle des virements mensuels clients';

$runDate = trim((string)($_GET['run_date'] ?? $_POST['run_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
    $runDate = date('Y-m-d');
}

$dayOfMonth = (int)date('j', strtotime($runDate));

$results = [
    'created' => 0,
    'skipped' => 0,
    'errors' => 0,
    'logs' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.client_code,
                c.full_name,
                c.generated_client_account,
                c.mensualite_amount,
                c.mensualite_day,
                c.mensualite_treasury_account_id,
                ta.account_code AS treasury_account_code
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.mensualite_treasury_account_id
            WHERE COALESCE(c.is_active,1)=1
              AND COALESCE(c.mensualite_amount,0) > 0
              AND COALESCE(c.mensualite_day,26) = ?
            ORDER BY c.client_code ASC
        ");
        $stmt->execute([$dayOfMonth]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $pdo->beginTransaction();

        foreach ($clients as $client) {
            try {
                $clientId = (int)$client['id'];
                $amount = (float)$client['mensualite_amount'];
                $treasuryCode = trim((string)($client['treasury_account_code'] ?? ''));

                if ($clientId > 0) {
                    sl_assert_client_operation_allowed($pdo, $clientId);
                }

                if ($treasuryCode === '') {
                    $results['errors']++;
                    $results['logs'][] = $client['client_code'] . ' : compte 512 manquant';
                    continue;
                }

                $reference = 'MENS-' . $client['client_code'] . '-' . str_replace('-', '', $runDate);

                $stmtExists = $pdo->prepare("
                    SELECT id
                    FROM operations
                    WHERE reference = ?
                    LIMIT 1
                ");
                $stmtExists->execute([$reference]);
                if ($stmtExists->fetchColumn()) {
                    $results['skipped']++;
                    $results['logs'][] = $client['client_code'] . ' : déjà générée';
                    continue;
                }

                $payload = [
                    'operation_date' => $runDate,
                    'amount' => $amount,
                    'currency_code' => 'EUR',
                    'client_id' => $clientId,
                    'service_id' => 21,
                    'service_code' => 'MENSUEL',
                    'operation_type_id' => 18,
                    'operation_type_code' => 'VIREMENT',
                    'linked_bank_account_id' => null,
                    'reference' => $reference,
                    'label' => 'MENSUALITE - ' . $client['client_code'],
                    'notes' => 'Mensualité automatique client',
                    'source_type' => 'monthly_payment',
                    'operation_kind' => 'monthly_payment',
                    'source_treasury_code' => '',
                    'target_treasury_code' => $treasuryCode,
                    'manual_debit_account_code' => $client['generated_client_account'],
                    'manual_credit_account_code' => $treasuryCode,
                ];

                createOperationWithAccountingV2($pdo, $payload);

                $results['created']++;
                $results['logs'][] = $client['client_code'] . ' : créée';
            } catch (Throwable $e) {
                $results['errors']++;
                $results['logs'][] = $client['client_code'] . ' : erreur - ' . $e->getMessage();
            }
        }

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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtRun->execute([
            $runDate,
            $dayOfMonth,
            count($clients),
            $results['created'],
            $results['skipped'],
            $results['errors'],
            $_SESSION['user_id'] ?? null
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $results['logs'][] = 'Erreur globale : ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Lancer les mensualités</h3>

                <form method="POST">
                    <?= csrf_input() ?>

                    <div>
                        <label>Date d’exécution</label>
                        <input type="date" name="run_date" value="<?= e($runDate) ?>" required>
                    </div>

                    <div class="dashboard-note" style="margin-top:16px;">
                        Le traitement prendra tous les clients actifs ayant une mensualité configurée sur le jour <strong><?= (int)$dayOfMonth ?></strong>.
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Exécuter</button>
                        <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Résultat du traitement</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Créées</span><strong><?= (int)$results['created'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Ignorées</span><strong><?= (int)$results['skipped'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Erreurs</span><strong><?= (int)$results['errors'] ?></strong></div>
                </div>

                <div class="sl-log-box" style="margin-top:16px;">
                    <?php foreach ($results['logs'] as $log): ?>
                        <div class="sl-log-line"><?= e($log) ?></div>
                    <?php endforeach; ?>

                    <?php if (!$results['logs']): ?>
                        <div class="dashboard-note">Aucune exécution encore lancée.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
