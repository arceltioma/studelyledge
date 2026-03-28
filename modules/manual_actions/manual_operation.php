<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'manual_actions_create');

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_services
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$successMessage = '';
$errorMessage = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $clientId = (int)($_POST['client_id'] ?? 0);
        $operationTypeCode = trim((string)($_POST['operation_type_code'] ?? ''));
        $serviceId = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
        $treasuryId = ($_POST['treasury_account_id'] ?? '') !== '' ? (int)$_POST['treasury_account_id'] : null;
        $operationDate = trim((string)($_POST['operation_date'] ?? date('Y-m-d')));
        $amount = (float)($_POST['amount'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($clientId <= 0) {
            throw new RuntimeException('Client obligatoire.');
        }
        if ($operationTypeCode === '') {
            throw new RuntimeException('Type d’opération obligatoire.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        $sourceTreasuryCode = null;
        if ($treasuryId > 0) {
            $stmtTreasury = $pdo->prepare("SELECT account_code FROM treasury_accounts WHERE id = ? LIMIT 1");
            $stmtTreasury->execute([$treasuryId]);
            $sourceTreasuryCode = $stmtTreasury->fetchColumn() ?: null;
        }

        $payload = [
            'client_id' => $clientId,
            'operation_type_code' => $operationTypeCode,
            'service_id' => $serviceId,
            'amount' => $amount,
            'operation_date' => $operationDate,
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== '' ? $label : 'Régularisation manuelle',
            'notes' => $notes !== '' ? $notes : 'Opération manuelle de régularisation',
            'source_type' => 'manual',
            'operation_kind' => 'manual',
            'source_treasury_code' => $sourceTreasuryCode,
        ];

        $preview = resolveAccountingOperation($pdo, $payload);

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $operationId = createOperationWithAccounting($pdo, $payload);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_manual_operation',
                    'manual_actions',
                    'operation',
                    $operationId,
                    'Création d’une régularisation manuelle'
                );
            }

            $pdo->commit();
            $successMessage = 'Opération manuelle enregistrée.';
            $preview = null;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Opération manuelle';
$pageSubtitle = 'Créer une régularisation manuelle avec prévisualisation comptable.';
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
                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Client</label>
                            <select name="client_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>"><?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_code" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= e($type['code']) ?>"><?= e($type['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id">
                                <option value="">Aucun</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= (int)$service['id'] ?>"><?= e($service['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte interne</label>
                            <select name="treasury_account_id">
                                <option value="">Auto / Aucun</option>
                                <?php foreach ($treasuryAccounts as $ta): ?>
                                    <option value="<?= (int)$ta['id'] ?>"><?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e(date('Y-m-d')) ?>">
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" required>
                        </div>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label">
                    </div>

                    <div>
                        <label>Référence</label>
                        <input type="text" name="reference">
                    </div>

                    <div>
                        <label>Motif / Notes</label>
                        <textarea name="notes" rows="5"></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Aperçu comptable</h3>

                <?php if ($preview): ?>
                    <div class="stat-row">
                        <span class="metric-label">Débit</span>
                        <span class="metric-value"><?= e($preview['debit_account_code'] ?? '—') ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="metric-label">Crédit</span>
                        <span class="metric-value"><?= e($preview['credit_account_code'] ?? '—') ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="metric-label">Analytique</span>
                        <span class="metric-value"><?= e($preview['analytic_account']['account_code'] ?? '—') ?></span>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Prévisualise d’abord pour vérifier les comptes débit / crédit avant validation.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>