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
        SELECT id, code, label, operation_type_id
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

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM service_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$successMessage = '';
$errorMessage = '';
$preview = null;

$formData = [
    'client_id' => '',
    'operation_type_id' => '',
    'operation_type_code' => '',
    'service_id' => '',
    'service_code' => '',
    'operation_date' => date('Y-m-d'),
    'amount' => '',
    'label' => '',
    'reference' => '',
    'notes' => '',
    'source_account_code' => '',
    'destination_account_code' => '',
];

if (!function_exists('sl_manual_find_by_id')) {
    function sl_manual_find_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'client_id' => trim((string)($_POST['client_id'] ?? '')),
        'operation_type_id' => trim((string)($_POST['operation_type_id'] ?? '')),
        'operation_type_code' => '',
        'service_id' => trim((string)($_POST['service_id'] ?? '')),
        'service_code' => '',
        'operation_date' => trim((string)($_POST['operation_date'] ?? date('Y-m-d'))),
        'amount' => trim((string)($_POST['amount'] ?? '')),
        'label' => trim((string)($_POST['label'] ?? '')),
        'reference' => trim((string)($_POST['reference'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
        'source_account_code' => trim((string)($_POST['source_account_code'] ?? '')),
        'destination_account_code' => trim((string)($_POST['destination_account_code'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $clientId = $formData['client_id'] !== '' ? (int)$formData['client_id'] : null;
        $operationTypeId = $formData['operation_type_id'] !== '' ? (int)$formData['operation_type_id'] : 0;
        $serviceId = $formData['service_id'] !== '' ? (int)$formData['service_id'] : 0;
        $operationDate = $formData['operation_date'];
        $amount = (float)str_replace(',', '.', $formData['amount']);
        $label = $formData['label'];
        $reference = $formData['reference'];
        $notes = $formData['notes'];
        $sourceAccountCode = $formData['source_account_code'];
        $destinationAccountCode = $formData['destination_account_code'];
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($operationTypeId <= 0) {
            throw new RuntimeException('Type d’opération obligatoire.');
        }

        if ($serviceId <= 0) {
            throw new RuntimeException('Service obligatoire.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $operationDate)) {
            throw new RuntimeException('Date invalide.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        $selectedType = sl_manual_find_by_id($operationTypes, $operationTypeId);
        $selectedService = sl_manual_find_by_id($services, $serviceId);

        if (!$selectedType || !$selectedService) {
            throw new RuntimeException('Type ou service introuvable.');
        }

        $typeCode = function_exists('sl_normalize_code')
            ? sl_normalize_code((string)($selectedType['code'] ?? ''))
            : strtoupper(trim((string)($selectedType['code'] ?? '')));

        $serviceCode = function_exists('sl_normalize_code')
            ? sl_normalize_code((string)($selectedService['code'] ?? ''))
            : strtoupper(trim((string)($selectedService['code'] ?? '')));

        $formData['operation_type_code'] = $typeCode;
        $formData['service_code'] = $serviceCode;

        $isInternal = ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE');

        if (!$isInternal && (!$clientId || $clientId <= 0)) {
            throw new RuntimeException('Client obligatoire.');
        }

        if ($sourceAccountCode === '' || $destinationAccountCode === '') {
            throw new RuntimeException('Compte source et compte destination obligatoires.');
        }

        $payload = [
            'client_id' => $clientId,
            'operation_type_id' => $operationTypeId,
            'operation_type_code' => $typeCode,
            'service_id' => $serviceId,
            'service_code' => $serviceCode,
            'amount' => $amount,
            'operation_date' => $operationDate,
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== '' ? $label : trim((string)($selectedType['label'] ?? '') . ' - ' . (string)($selectedService['label'] ?? '')),
            'notes' => $notes !== '' ? $notes : 'Opération manuelle de régularisation',
            'source_type' => 'manual',
            'operation_kind' => 'manual',
            'source_treasury_code' => $isInternal ? $sourceAccountCode : '',
            'target_treasury_code' => $isInternal ? $destinationAccountCode : '',
            'manual_debit_account_code' => $sourceAccountCode,
            'manual_credit_account_code' => $destinationAccountCode,
        ];

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $operationId = createOperationWithAccountingV2($pdo, $payload);

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

            $formData = [
                'client_id' => '',
                'operation_type_id' => '',
                'operation_type_code' => '',
                'service_id' => '',
                'service_code' => '',
                'operation_date' => date('Y-m-d'),
                'amount' => '',
                'label' => '',
                'reference' => '',
                'notes' => '',
                'source_account_code' => '',
                'destination_account_code' => '',
            ];
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
                            <select name="client_id">
                                <option value="">Choisir</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>" <?= $formData['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                        <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= $formData['operation_type_id'] == $type['id'] ? 'selected' : '' ?>>
                                        <?= e($type['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= (int)$service['id'] ?>" <?= $formData['service_id'] == $service['id'] ? 'selected' : '' ?>>
                                        <?= e($service['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e($formData['operation_date']) ?>">
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e($formData['amount']) ?>" required>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= e($formData['reference']) ?>">
                        </div>

                        <div>
                            <label>Compte source (débit)</label>
                            <select name="source_account_code" required>
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $ta): ?>
                                    <option value="<?= e($ta['account_code']) ?>" <?= $formData['source_account_code'] === ($ta['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($serviceAccounts as $sa): ?>
                                    <option value="<?= e($sa['account_code']) ?>" <?= $formData['source_account_code'] === ($sa['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($sa['account_code'] . ' - ' . $sa['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte destination (crédit)</label>
                            <select name="destination_account_code" required>
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $ta): ?>
                                    <option value="<?= e($ta['account_code']) ?>" <?= $formData['destination_account_code'] === ($ta['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($ta['account_code'] . ' - ' . $ta['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($serviceAccounts as $sa): ?>
                                    <option value="<?= e($sa['account_code']) ?>" <?= $formData['destination_account_code'] === ($sa['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($sa['account_code'] . ' - ' . $sa['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($formData['label']) ?>">
                    </div>

                    <div>
                        <label>Motif / Notes</label>
                        <textarea name="notes" rows="5"><?= e($formData['notes']) ?></textarea>
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