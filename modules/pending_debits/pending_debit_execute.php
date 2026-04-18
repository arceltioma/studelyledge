<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/pending_debits_engine.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'pending_debits_execute_page');
} else {
    enforcePagePermission($pdo, 'pending_debits_execute');
}

$pendingDebitId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($pendingDebitId <= 0) {
    exit('Débit dû invalide.');
}

if (!tableExists($pdo, 'pending_client_debits')) {
    exit('Table pending_client_debits introuvable.');
}

$select = ['pd.*'];
$joinClient = '';

if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';
    $select[] = 'c.client_code';
    $select[] = 'c.full_name';
    $select[] = 'c.generated_client_account';
    $select[] = 'c.currency AS client_currency';
    $select[] = 'COALESCE(c.is_active,1) AS client_is_active';
}

$sql = "
    SELECT " . implode(', ', $select) . "
    FROM pending_client_debits pd
    {$joinClient}
    WHERE pd.id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pendingDebitId]);
$pendingDebit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pendingDebit) {
    exit('Débit dû introuvable.');
}

$status = (string)($pendingDebit['status'] ?? '');
if (in_array($status, ['resolved', 'cancelled'], true)) {
    exit('Ce débit dû ne peut plus être exécuté.');
}

$errorMessage = '';
$successMessage = '';

$formData = [
    'execution_amount' => (string)number_format((float)($pendingDebit['remaining_amount'] ?? 0), 2, '.', ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'execution_amount' => trim((string)($_POST['execution_amount'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $clientId = (int)($pendingDebit['client_id'] ?? 0);
        if ($clientId > 0) {
            sl_assert_client_operation_allowed($pdo, $clientId);
        }

        $requestedAmount = (float)str_replace(',', '.', $formData['execution_amount']);
        if ($requestedAmount <= 0) {
            throw new RuntimeException('Le montant à initier doit être supérieur à 0.');
        }

        $result = sl_execute_pending_client_debit(
            $pdo,
            $pendingDebitId,
            $requestedAmount,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
        );

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'execute_pending_debit',
                'pending_client_debits',
                'pending_client_debit',
                $pendingDebitId,
                'Exécution manuelle d’un débit dû #' . $pendingDebitId
            );
        }

        header('Location: ' . APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId . '&executed=1');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Initier un débit dû';
$pageSubtitle = 'Exécution manuelle d’un débit dû client 411';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3>Exécuter le débit dû</h3>
                <p class="muted">Le montant saisi sera initié dans la limite du solde disponible du compte 411 client.</p>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$pendingDebitId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Client</label>
                            <input
                                type="text"
                                value="<?= e(trim((string)($pendingDebit['client_code'] ?? '') . ' - ' . (string)($pendingDebit['full_name'] ?? '')) ?: '—') ?>"
                                disabled
                            >
                        </div>

                        <div>
                            <label>Compte 411</label>
                            <input
                                type="text"
                                value="<?= e((string)($pendingDebit['generated_client_account'] ?? '—')) ?>"
                                disabled
                            >
                        </div>

                        <div>
                            <label>Montant restant dû</label>
                            <input
                                type="text"
                                value="<?= e(number_format((float)($pendingDebit['remaining_amount'] ?? 0), 2, ',', ' ')) ?>"
                                disabled
                            >
                        </div>

                        <div>
                            <label>Devise</label>
                            <input
                                type="text"
                                value="<?= e((string)($pendingDebit['currency_code'] ?? $pendingDebit['client_currency'] ?? 'EUR')) ?>"
                                disabled
                            >
                        </div>

                        <div style="grid-column:1 / -1;">
                            <label>Montant à initier maintenant</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="execution_amount"
                                value="<?= e($formData['execution_amount']) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Initier maintenant</button>
                        <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$pendingDebitId ?>" class="btn btn-outline">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Rappel</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Statut actuel</span><strong><?= e((string)($pendingDebit['status'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Déjà exécuté</span><strong><?= e(number_format((float)($pendingDebit['executed_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Montant initial</span><strong><?= e(number_format((float)($pendingDebit['initial_amount'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Libellé</span><strong><?= e((string)($pendingDebit['label'] ?? '—')) ?></strong></div>
                </div>

                <div class="dashboard-note" style="margin-top:16px;">
                    Si le solde du compte 411 est insuffisant, seule la partie réellement exécutable sera initiée.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>