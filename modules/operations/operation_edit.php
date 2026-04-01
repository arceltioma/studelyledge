<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_edit_page');
} else {
    enforcePagePermission($pdo, 'operations_create');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Opération invalide.');
}

$stmtOperation = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
$stmtOperation->execute([$id]);
$operation = $stmtOperation->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label, is_active
        FROM ref_operation_types
        WHERE COALESCE(is_active,1) = 1
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT
            rs.*,
            rot.code AS operation_type_code,
            rot.label AS operation_type_label
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        WHERE COALESCE(rs.is_active,1) = 1
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT
            c.id,
            c.client_code,
            c.full_name,
            c.country_commercial,
            c.country_destination,
            c.generated_client_account,
            c.initial_treasury_account_id,
            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label
        FROM clients c
        LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
        WHERE COALESCE(c.is_active,1) = 1
        ORDER BY c.client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1) = 1
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [];

if (!function_exists('sl_edit_find_by_id')) {
    function sl_edit_find_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

$currentOperationTypeId = null;
foreach ($operationTypes as $type) {
    if (sl_normalize_code((string)($type['code'] ?? '')) === sl_normalize_code((string)($operation['operation_type_code'] ?? ''))) {
        $currentOperationTypeId = (int)$type['id'];
        break;
    }
}

$currentServiceId = (int)($operation['service_id'] ?? 0) ?: null;

$formData = [
    'operation_date' => $operation['operation_date'] ?? date('Y-m-d'),
    'amount' => $operation['amount'] ?? '',
    'currency_code' => $operation['currency_code'] ?? 'EUR',
    'client_id' => $operation['client_id'] ?? '',
    'operation_type_id' => $currentOperationTypeId,
    'service_id' => $currentServiceId,
    'linked_bank_account_id' => $operation['linked_bank_account_id'] ?? '',
    'reference' => $operation['reference'] ?? '',
    'label' => $operation['label'] ?? '',
    'notes' => $operation['notes'] ?? '',
    'manual_debit_account_code' => '',
    'manual_credit_account_code' => '',
    'source_treasury_account_id' => '',
    'target_treasury_account_id' => '',
];

$preview = null;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $formData = [
            'operation_date' => trim((string)($_POST['operation_date'] ?? date('Y-m-d'))),
            'amount' => (float)($_POST['amount'] ?? 0),
            'currency_code' => trim((string)($_POST['currency_code'] ?? 'EUR')),
            'client_id' => ($_POST['client_id'] ?? '') !== '' ? (int)$_POST['client_id'] : null,
            'operation_type_id' => (int)($_POST['operation_type_id'] ?? 0),
            'service_id' => ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null,
            'linked_bank_account_id' => ($_POST['linked_bank_account_id'] ?? '') !== '' ? (int)$_POST['linked_bank_account_id'] : null,
            'reference' => trim((string)($_POST['reference'] ?? '')),
            'label' => trim((string)($_POST['label'] ?? '')),
            'notes' => trim((string)($_POST['notes'] ?? '')),
            'manual_debit_account_code' => trim((string)($_POST['manual_debit_account_code'] ?? '')),
            'manual_credit_account_code' => trim((string)($_POST['manual_credit_account_code'] ?? '')),
            'source_treasury_account_id' => ($_POST['source_treasury_account_id'] ?? '') !== '' ? (int)$_POST['source_treasury_account_id'] : null,
            'target_treasury_account_id' => ($_POST['target_treasury_account_id'] ?? '') !== '' ? (int)$_POST['target_treasury_account_id'] : null,
        ];

        if ($formData['operation_type_id'] <= 0) {
            throw new RuntimeException('Type d’opération obligatoire.');
        }
        if ($formData['service_id'] === null) {
            throw new RuntimeException('Type de service obligatoire.');
        }
        if ((float)$formData['amount'] <= 0) {
            throw new RuntimeException('Montant invalide.');
        }
        if ($formData['operation_date'] === '') {
            throw new RuntimeException('Date obligatoire.');
        }

        $selectedType = sl_edit_find_by_id($operationTypes, (int)$formData['operation_type_id']);
        $selectedService = sl_edit_find_by_id($services, (int)$formData['service_id']);

        if (!$selectedType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }
        if (!$selectedService) {
            throw new RuntimeException('Service introuvable.');
        }

        $typeCode = sl_normalize_code((string)($selectedType['code'] ?? ''));
        $serviceCode = sl_normalize_code((string)($selectedService['code'] ?? ''));

        if (!sl_service_allowed_for_type($typeCode, $serviceCode)) {
            throw new RuntimeException('Service incompatible avec le type d’opération.');
        }

        $sourceTreasuryCode = '';
        $targetTreasuryCode = '';

        if (!empty($formData['source_treasury_account_id'])) {
            $sourceTreasury = sl_edit_find_by_id($treasuryAccounts, (int)$formData['source_treasury_account_id']);
            $sourceTreasuryCode = (string)($sourceTreasury['account_code'] ?? '');
        }

        if (!empty($formData['target_treasury_account_id'])) {
            $targetTreasury = sl_edit_find_by_id($treasuryAccounts, (int)$formData['target_treasury_account_id']);
            $targetTreasuryCode = (string)($targetTreasury['account_code'] ?? '');
        }

        $payload = [
            'operation_date' => $formData['operation_date'],
            'amount' => (float)$formData['amount'],
            'currency_code' => $formData['currency_code'] !== '' ? $formData['currency_code'] : 'EUR',
            'client_id' => $formData['client_id'],
            'service_id' => (int)$formData['service_id'],
            'service_code' => $serviceCode,
            'operation_type_id' => (int)$formData['operation_type_id'],
            'operation_type_code' => $typeCode,
            'linked_bank_account_id' => $formData['linked_bank_account_id'],
            'reference' => $formData['reference'] !== '' ? $formData['reference'] : null,
            'label' => $formData['label'] !== '' ? $formData['label'] : trim(($selectedType['label'] ?? '') . ' - ' . ($selectedService['label'] ?? '')),
            'notes' => $formData['notes'] !== '' ? $formData['notes'] : null,
            'source_type' => 'manual_edit',
            'operation_kind' => 'manual',
            'source_treasury_code' => $sourceTreasuryCode,
            'target_treasury_code' => $targetTreasuryCode,
            'manual_debit_account_code' => $formData['manual_debit_account_code'],
            'manual_credit_account_code' => $formData['manual_credit_account_code'],
        ];

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if (($_POST['action_mode'] ?? 'preview') === 'save') {
            $pdo->beginTransaction();

            createOperationWithAccountingV2($pdo, $payload, $id);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_operation',
                    'operations',
                    'operation',
                    $id,
                    'Modification d’une opération V2'
                );
            }

            $pdo->commit();
            header('Location: ' . APP_URL . 'modules/operations/operation_view.php?id=' . $id);
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier une opération';
$pageSubtitle = 'Même moteur métier que la création, avec contrôle anti-doublons.';
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
                        <label>Date</label>
                        <input type="date" name="operation_date" value="<?= e((string)$formData['operation_date']) ?>" required>
                    </div>

                    <div>
                        <label>Montant</label>
                        <input type="number" step="0.01" name="amount" value="<?= e((string)$formData['amount']) ?>" required>
                    </div>

                    <div>
                        <label>Devise</label>
                        <select name="currency_code" required>
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= e($currency['code']) ?>" <?= (string)$formData['currency_code'] === (string)$currency['code'] ? 'selected' : '' ?>>
                                    <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Client</label>
                        <select name="client_id" id="client_id">
                            <option value="">Choisir</option>
                            <?php foreach ($clients as $client): ?>
                                <option
                                    value="<?= (int)$client['id'] ?>"
                                    data-country-commercial="<?= e($client['country_commercial'] ?? '') ?>"
                                    data-country-destination="<?= e($client['country_destination'] ?? '') ?>"
                                    data-client-account="<?= e($client['generated_client_account'] ?? '') ?>"
                                    data-treasury-account="<?= e($client['treasury_account_code'] ?? '') ?>"
                                    <?= (string)$formData['client_id'] === (string)$client['id'] ? 'selected' : '' ?>
                                >
                                    <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type opération</label>
                        <select name="operation_type_id" id="operation_type_id" required>
                            <option value="">Choisir</option>
                            <?php foreach ($operationTypes as $type): ?>
                                <option value="<?= (int)$type['id'] ?>" data-type-code="<?= e(sl_normalize_code($type['code'] ?? '')) ?>" <?= (string)$formData['operation_type_id'] === (string)$type['id'] ? 'selected' : '' ?>>
                                    <?= e($type['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type service</label>
                        <select name="service_id" id="service_id" required>
                            <option value="">Choisir d’abord un type</option>
                            <?php foreach ($services as $service): ?>
                                <option
                                    value="<?= (int)$service['id'] ?>"
                                    data-type-id="<?= (int)($service['operation_type_id'] ?? 0) ?>"
                                    data-type-code="<?= e(sl_normalize_code($service['operation_type_code'] ?? '')) ?>"
                                    data-service-code="<?= e(sl_normalize_code($service['code'] ?? '')) ?>"
                                    <?= (string)$formData['service_id'] === (string)$service['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($service['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="linked-bank-account-wrapper">
                        <label>Compte bancaire lié</label>
                        <input type="number" name="linked_bank_account_id" id="linked_bank_account_id" value="<?= e((string)$formData['linked_bank_account_id']) ?>">
                    </div>

                    <div>
                        <label>Référence / Intitulé</label>
                        <input type="text" name="reference" value="<?= e((string)$formData['reference']) ?>">
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label>Note / Motif</label>
                        <textarea name="notes" rows="4"><?= e((string)$formData['notes']) ?></textarea>
                    </div>

                    <div id="source-treasury-wrapper">
                        <label>Compte 512 source</label>
                        <select name="source_treasury_account_id" id="source_treasury_account_id">
                            <option value="">Choisir</option>
                            <?php foreach ($treasuryAccounts as $acc): ?>
                                <option value="<?= (int)$acc['id'] ?>" <?= (string)$formData['source_treasury_account_id'] === (string)$acc['id'] ? 'selected' : '' ?>>
                                    <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="target-treasury-wrapper">
                        <label>Compte 512 cible</label>
                        <select name="target_treasury_account_id" id="target_treasury_account_id">
                            <option value="">Choisir</option>
                            <?php foreach ($treasuryAccounts as $acc): ?>
                                <option value="<?= (int)$acc['id'] ?>" <?= (string)$formData['target_treasury_account_id'] === (string)$acc['id'] ? 'selected' : '' ?>>
                                    <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="manual-debit-wrapper">
                        <label>Compte débité manuel</label>
                        <input type="text" name="manual_debit_account_code" value="<?= e((string)$formData['manual_debit_account_code']) ?>">
                    </div>

                    <div id="manual-credit-wrapper">
                        <label>Compte crédité manuel</label>
                        <input type="text" name="manual_credit_account_code" value="<?= e((string)$formData['manual_credit_account_code']) ?>">
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label>Libellé libre</label>
                    <input type="text" name="label" value="<?= e((string)$formData['label']) ?>">
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                    <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/operations/operation_view.php?id=<?= (int)$id ?>">Annuler</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h3>Aperçu Comptable</h3>
            <?php if ($preview): ?>
                <div class="stat-row"><span class="metric-label">Débit</span><span class="metric-value"><?= e($preview['debit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Crédit</span><span class="metric-value"><?= e($preview['credit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Mode manuel</span><span class="metric-value"><?= !empty($preview['is_manual_accounting']) ? 'Oui' : 'Non' ?></span></div>
                <div class="stat-row"><span class="metric-label">Hash anti-doublon</span><span class="metric-value"><?= e($preview['operation_hash'] ?? '') ?></span></div>
            <?php else: ?>
                <div class="dashboard-note">Résumé de l’opération avant validation.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const map = <?= json_encode(sl_operation_service_map(), JSON_UNESCAPED_UNICODE) ?>;
        const typeSelect = document.getElementById('operation_type_id');
        const serviceSelect = document.getElementById('service_id');
        const clientSelect = document.getElementById('client_id');
        const linkedBankWrapper = document.getElementById('linked-bank-account-wrapper');
        const sourceWrapper = document.getElementById('source-treasury-wrapper');
        const targetWrapper = document.getElementById('target-treasury-wrapper');
        const manualDebitWrapper = document.getElementById('manual-debit-wrapper');
        const manualCreditWrapper = document.getElementById('manual-credit-wrapper');

        if (!typeSelect || !serviceSelect || !clientSelect) {
            return;
        }

        const originalServiceOptions = Array.from(serviceSelect.querySelectorAll('option')).map(option => option.cloneNode(true));

        function getSelectedTypeCode() {
            const selected = typeSelect.options[typeSelect.selectedIndex];
            return selected ? (selected.getAttribute('data-type-code') || '') : '';
        }

        function getSelectedServiceCode() {
            const selected = serviceSelect.options[serviceSelect.selectedIndex];
            return selected ? (selected.getAttribute('data-service-code') || '') : '';
        }

        function refreshServices() {
            const typeCode = getSelectedTypeCode();
            const currentValue = serviceSelect.value;

            serviceSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = typeCode ? 'Choisir' : 'Choisir d’abord un type';
            serviceSelect.appendChild(placeholder);

            const allowedCodes = map[typeCode] || [];
            let stillValid = false;

            originalServiceOptions.forEach(option => {
                if (option.value === '') return;
                const serviceCode = option.getAttribute('data-service-code') || '';
                if (allowedCodes.includes(serviceCode)) {
                    const cloned = option.cloneNode(true);
                    if (cloned.value === currentValue) {
                        stillValid = true;
                    }
                    serviceSelect.appendChild(cloned);
                }
            });

            serviceSelect.value = stillValid ? currentValue : '';
            refreshVisibility();
        }

        function refreshVisibility() {
            const typeCode = getSelectedTypeCode();
            const serviceCode = getSelectedServiceCode();
            const key = typeCode + '::' + serviceCode;

            const needsLinkedBank =
                (typeCode === 'VIREMENT' && serviceCode !== 'INTERNE' && serviceCode !== '') ||
                typeCode === 'VERSEMENT' ||
                typeCode === 'REGULARISATION';

            const manualCases = [
                'VIREMENT::INTERNE',
                'CA_DIVERS::CA_DIVERS',
                'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
                'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
                'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
                'CA_LOGEMENT::CA_LOGEMENT'
            ];

            const isManual = manualCases.includes(key);
            const isInternal = key === 'VIREMENT::INTERNE';

            linkedBankWrapper.style.display = needsLinkedBank ? '' : 'none';
            targetWrapper.style.display = isInternal ? '' : 'none';
            manualDebitWrapper.style.display = isManual ? '' : 'none';
            manualCreditWrapper.style.display = isManual ? '' : 'none';

            if (isInternal) {
                clientSelect.value = '';
                clientSelect.closest('div').style.display = 'none';
            } else {
                clientSelect.closest('div').style.display = '';
            }
        }

        typeSelect.addEventListener('change', refreshServices);
        serviceSelect.addEventListener('change', refreshVisibility);

        refreshServices();
    });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>