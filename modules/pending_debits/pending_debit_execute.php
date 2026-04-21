<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/pending_debits_engine.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'pending_debits_execute');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('sl_create_notification_if_possible')) {
    function sl_create_notification_if_possible(
        PDO $pdo,
        string $type,
        string $message,
        string $level = 'info',
        ?string $linkUrl = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $createdBy = null
    ): void {
        if (!tableExists($pdo, 'notifications')) {
            return;
        }

        $columns = [];
        $values = [];
        $params = [];

        $map = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'link_url' => $linkUrl,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ];

        foreach ($map as $column => $value) {
            if (columnExists($pdo, 'notifications', $column)) {
                $columns[] = $column;
                $values[] = '?';
                $params[] = $value;
            }
        }

        if (columnExists($pdo, 'notifications', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = 'NOW()';
        }

        if (!$columns) {
            return;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
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

        if (isset($pendingDebit['client_is_active']) && (int)$pendingDebit['client_is_active'] !== 1) {
            throw new RuntimeException('Le client lié à ce débit dû est archivé ou inactif.');
        }

        $requestedAmount = (float)str_replace(',', '.', $formData['execution_amount']);
        if ($requestedAmount <= 0) {
            throw new RuntimeException('Le montant à initier doit être supérieur à 0.');
        }

        $remainingBefore = (float)($pendingDebit['remaining_amount'] ?? 0);
        if ($requestedAmount > $remainingBefore) {
            throw new RuntimeException('Le montant demandé dépasse le restant dû.');
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        $result = sl_execute_pending_client_debit(
            $pdo,
            $pendingDebitId,
            $requestedAmount,
            $userId > 0 ? $userId : null
        );

        $executedAmount = (float)($result['executed_amount'] ?? 0);
        $remainingAfter = (float)($result['remaining_amount'] ?? max(0, $remainingBefore - $executedAmount));
        $newStatus = (string)($result['status'] ?? ($remainingAfter <= 0 ? 'resolved' : ($executedAmount > 0 ? 'partial' : $status)));
        $operationId = (int)($result['operation_id'] ?? 0);

        if (function_exists('logUserAction') && $userId > 0) {
            logUserAction(
                $pdo,
                $userId,
                'execute_pending_debit',
                'pending_debits',
                'pending_client_debit',
                $pendingDebitId,
                'Exécution manuelle d’un débit dû #'
                . $pendingDebitId
                . ' | montant demandé: ' . number_format($requestedAmount, 2, '.', '')
                . ' | montant exécuté: ' . number_format($executedAmount, 2, '.', '')
                . ' | restant après exécution: ' . number_format($remainingAfter, 2, '.', '')
                . ($operationId > 0 ? ' | opération créée: #' . $operationId : '')
            );
        }

        $clientCode = (string)($pendingDebit['client_code'] ?? '');
        $clientName = (string)($pendingDebit['full_name'] ?? '');
        $currencyCode = (string)($pendingDebit['currency_code'] ?? $pendingDebit['client_currency'] ?? 'EUR');
        $label = (string)($pendingDebit['label'] ?? 'Débit dû');

        if ($executedAmount > 0) {
            sl_create_notification_if_possible(
                $pdo,
                $newStatus === 'resolved' ? 'pending_debit_resolved' : 'pending_debit_executed',
                'Débit dû exécuté'
                . ($clientCode !== '' ? ' pour le client ' . $clientCode : '')
                . ($clientName !== '' ? ' - ' . $clientName : '')
                . ' | exécuté : ' . number_format($executedAmount, 2, ',', ' ')
                . ' ' . $currencyCode
                . ' | restant : ' . number_format($remainingAfter, 2, ',', ' ')
                . ' ' . $currencyCode
                . ($label !== '' ? ' | ' . $label : ''),
                $newStatus === 'resolved' ? 'success' : 'info',
                APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId,
                'pending_client_debit',
                $pendingDebitId,
                $userId > 0 ? $userId : null
            );
        } else {
            sl_create_notification_if_possible(
                $pdo,
                'pending_debit_execution_failed',
                'Aucune exécution possible pour le débit dû'
                . ($clientCode !== '' ? ' du client ' . $clientCode : '')
                . ($clientName !== '' ? ' - ' . $clientName : '')
                . ' | demandé : ' . number_format($requestedAmount, 2, ',', ' ')
                . ' ' . $currencyCode
                . ' | restant : ' . number_format($remainingBefore, 2, ',', ' ')
                . ' ' . $currencyCode,
                'warning',
                APP_URL . 'modules/pending_debits/pending_debit_view.php?id=' . $pendingDebitId,
                'pending_client_debit',
                $pendingDebitId,
                $userId > 0 ? $userId : null
            );
        }

        $_SESSION['success_message'] = $executedAmount > 0
            ? (
                $newStatus === 'resolved'
                    ? 'Le débit dû a été entièrement exécuté.'
                    : 'Le débit dû a été exécuté partiellement avec succès.'
            )
            : 'Aucun montant n’a pu être exécuté pour ce débit dû.';

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
                                max="<?= e(number_format((float)($pendingDebit['remaining_amount'] ?? 0), 2, '.', '')) ?>"
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