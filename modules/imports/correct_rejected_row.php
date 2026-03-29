<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_journal');

$rowId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($rowId <= 0) {
    exit('Ligne rejetée invalide.');
}

$stmtRow = $pdo->prepare("
    SELECT *
    FROM import_rows
    WHERE id = ?
    LIMIT 1
");
$stmtRow->execute([$rowId]);
$importRow = $stmtRow->fetch(PDO::FETCH_ASSOC);

if (!$importRow) {
    exit('Ligne rejetée introuvable.');
}

$raw = json_decode((string)($importRow['raw_data'] ?? ''), true);
$rawRow = $raw['row'] ?? [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
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

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_operation_types
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

        $clientId = ($_POST['client_id'] ?? '') !== '' ? (int)$_POST['client_id'] : null;
        $serviceId = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
        $operationTypeCode = trim((string)($_POST['operation_type_code'] ?? ''));
        $operationDate = trim((string)($_POST['operation_date'] ?? ''));
        $amount = (float)($_POST['amount'] ?? 0);
        $reference = trim((string)($_POST['reference'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $sourceTreasuryCode = trim((string)($_POST['source_treasury_code'] ?? ''));
        $targetTreasuryCode = trim((string)($_POST['target_treasury_code'] ?? ''));
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        $payload = [
            'operation_type_code' => $operationTypeCode,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'amount' => $amount,
            'operation_date' => $operationDate,
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== '' ? $label : null,
            'source_type' => 'import_correction',
            'operation_kind' => 'import',
            'source_treasury_code' => $sourceTreasuryCode !== '' ? $sourceTreasuryCode : null,
            'target_treasury_code' => $targetTreasuryCode !== '' ? $targetTreasuryCode : null,
        ];

        $preview = resolveAccountingOperation($pdo, $payload);

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $operationId = createOperationWithAccounting($pdo, $payload);

            $stmtUpdate = $pdo->prepare("
                UPDATE import_rows
                SET status = 'corrected',
                    error_message = NULL
                WHERE id = ?
            ");
            $stmtUpdate->execute([$rowId]);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'correct_rejected_import_row',
                    'imports',
                    'import_row',
                    $rowId,
                    'Correction d’une ligne rejetée et création opération #' . $operationId
                );
            }

            $pdo->commit();
            $successMessage = 'Ligne corrigée et opération créée.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Corriger une ligne rejetée';
$pageSubtitle = 'La correction utilise exactement le même moteur comptable que la saisie manuelle.';
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
                    <input type="hidden" name="id" value="<?= (int)$rowId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_code" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <?php $selected = (($importRow['operation_type'] ?? '') === $type['code']) ? 'selected' : ''; ?>
                                    <option value="<?= e($type['code']) ?>" <?= $selected ?>>
                                        <?= e($type['label'] . ' (' . $type['code'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e($importRow['operation_date'] ?? date('Y-m-d')) ?>" required>
                        </div>

                        <div>
                            <label>Client</label>
                            <select name="client_id">
                                <option value="">Aucun</option>
                                <?php foreach ($clients as $client): ?>
                                    <?php $selected = (($importRow['client_code'] ?? '') === $client['client_code']) ? 'selected' : ''; ?>
                                    <option value="<?= (int)$client['id'] ?>" <?= $selected ?>>
                                        <?= e($client['client_code'] . ' - ' . $client['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id">
                                <option value="">Aucun</option>
                                <?php
                                $initialServiceCode = trim((string)($rawRow['service_code'] ?? ''));
                                foreach ($services as $service):
                                    $selected = ($initialServiceCode === ($service['code'] ?? '')) ? 'selected' : '';
                                ?>
                                    <option value="<?= (int)$service['id'] ?>" <?= $selected ?>>
                                        <?= e($service['code'] . ' - ' . $service['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e((string)($importRow['amount'] ?? '0')) ?>" required>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= e($importRow['reference'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= e($importRow['label'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Compte 512 source</label>
                            <select name="source_treasury_code">
                                <option value="">Auto / Aucun</option>
                                <?php
                                $initialSource512 = trim((string)($rawRow['treasury_account_code'] ?? $rawRow['compte_512'] ?? ''));
                                foreach ($treasuryAccounts as $acc):
                                    $selected = ($initialSource512 === ($acc['account_code'] ?? '')) ? 'selected' : '';
                                ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $selected ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte 512 cible</label>
                            <select name="target_treasury_code">
                                <option value="">Aucun</option>
                                <?php
                                $initialTarget512 = trim((string)($rawRow['target_treasury_account_code'] ?? $rawRow['compte_512_cible'] ?? ''));
                                foreach ($treasuryAccounts as $acc):
                                    $selected = ($initialTarget512 === ($acc['account_code'] ?? '')) ? 'selected' : '';
                                ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $selected ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Corriger et enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/imports/rejected_rows.php?batch_id=<?= (int)($importRow['batch_id'] ?? 0) ?>" class="btn btn-outline">Retour</a>
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
                        <span class="metric-label">706 résolu</span>
                        <span class="metric-value"><?= e($preview['analytic_account']['account_code'] ?? '—') ?></span>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        La correction passera par le moteur comptable central avant enregistrement.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Donnée brute</h3>
            <pre style="white-space:pre-wrap;"><?= e($importRow['raw_data'] ?? '') ?></pre>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>