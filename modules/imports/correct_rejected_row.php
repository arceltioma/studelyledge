<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_validate');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Ligne invalide.');
}

$stmtRow = $pdo->prepare("
    SELECT *
    FROM import_rows
    WHERE id = ?
    LIMIT 1
");
$stmtRow->execute([$id]);
$row = $stmtRow->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    exit('Ligne introuvable.');
}

$raw = json_decode((string)($row['raw_data'] ?? '{}'), true);
if (!is_array($raw)) {
    $raw = [];
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $clientId = ($_POST['client_id'] ?? '') !== '' ? (int)$_POST['client_id'] : null;
        $operationTypeCode = trim((string)($_POST['operation_type_code'] ?? ''));
        $serviceCode = trim((string)($_POST['service_code'] ?? ''));
        $treasuryId = ($_POST['treasury_account_id'] ?? '') !== '' ? (int)$_POST['treasury_account_id'] : null;
        $operationDate = trim((string)($_POST['operation_date'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $amount = (float)($_POST['amount'] ?? 0);

        if ($operationTypeCode === '') {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Le montant doit être supérieur à zéro.');
        }

        $serviceId = null;
        if ($serviceCode !== '') {
            $stmtService = $pdo->prepare("SELECT id FROM ref_services WHERE code = ? LIMIT 1");
            $stmtService->execute([$serviceCode]);
            $serviceId = $stmtService->fetchColumn() ?: null;
        }

        $sourceTreasuryCode = null;
        if ($treasuryId) {
            $stmtTreasury = $pdo->prepare("SELECT account_code FROM treasury_accounts WHERE id = ? LIMIT 1");
            $stmtTreasury->execute([$treasuryId]);
            $sourceTreasuryCode = $stmtTreasury->fetchColumn() ?: null;
        }

        $pdo->beginTransaction();

        if ($operationTypeCode === 'VIREMENT_INTERNE') {
            if (!$treasuryId) {
                throw new RuntimeException('Compte interne requis pour un virement interne.');
            }

            createInternalTreasuryMovement($pdo, [
                'source_treasury_account_id' => $treasuryId,
                'target_treasury_account_id' => $treasuryId,
                'amount' => $amount,
                'operation_date' => $operationDate !== '' ? $operationDate : date('Y-m-d'),
                'reference' => $reference !== '' ? $reference : null,
                'label' => $label !== '' ? $label : 'Virement interne corrigé',
            ]);
        } else {
            if (!$clientId) {
                throw new RuntimeException('Le client est obligatoire.');
            }

            createOperationWithAccounting($pdo, [
                'operation_type_code' => $operationTypeCode,
                'service_id' => $serviceId,
                'client_id' => $clientId,
                'amount' => $amount,
                'operation_date' => $operationDate !== '' ? $operationDate : date('Y-m-d'),
                'reference' => $reference !== '' ? $reference : null,
                'label' => $label !== '' ? $label : 'Opération corrigée',
                'notes' => 'Correction manuelle de ligne rejetée',
                'source_treasury_code' => $sourceTreasuryCode,
            ]);
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE import_rows
            SET
                status = 'corrected',
                error_message = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$id]);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'correct_rejected_import_row',
                'imports',
                'import_row',
                $id,
                'Correction manuelle d’une ligne rejetée'
            );
        }

        $pdo->commit();

        $successMessage = 'Ligne corrigée avec succès.';
        $stmtRow->execute([$id]);
        $row = $stmtRow->fetch(PDO::FETCH_ASSOC);
        $raw = json_decode((string)($row['raw_data'] ?? '{}'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Corriger une ligne rejetée';
$pageSubtitle = 'Une fois corrigée, la ligne sort du bloc des rejets ouverts.';
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
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date opération</label>
                            <input type="date" name="operation_date" value="<?= e($raw['operation_date'] ?? date('Y-m-d')) ?>">
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e((string)($raw['credit'] ?? $raw['debit'] ?? $raw['amount'] ?? '')) ?>">
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_code" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= e($type['code']) ?>" <?= (($raw['operation_type_code'] ?? '') === $type['code']) ? 'selected' : '' ?>>
                                        <?= e($type['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_code">
                                <option value="">Aucun</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e($service['code']) ?>" <?= (($raw['service_code'] ?? '') === $service['code']) ? 'selected' : '' ?>>
                                        <?= e($service['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Client</label>
                            <select name="client_id">
                                <option value="">Aucun</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>" <?= ((string)($raw['client_id'] ?? '') === (string)$client['id']) ? 'selected' : '' ?>>
                                        <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte interne</label>
                            <select name="treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $ta): ?>
                                    <option value="<?= (int)$ta['id'] ?>" <?= ((string)($raw['treasury_account_id'] ?? '') === (string)$ta['id']) ? 'selected' : '' ?>>
                                        <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($raw['label'] ?? '') ?>">
                    </div>

                    <div>
                        <label>Référence</label>
                        <input type="text" name="reference" value="<?= e($raw['reference'] ?? '') ?>">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Corriger et valider</button>
                        <a href="<?= e(APP_URL) ?>modules/imports/rejected_rows.php?import_id=<?= (int)($row['import_id'] ?? 0) ?>" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Donnée brute</h3>
                <pre><?= e($row['raw_data'] ?? '') ?></pre>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>