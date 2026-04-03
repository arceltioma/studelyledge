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
    enforcePagePermission($pdo, 'operations_edit');
}

$operationId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($operationId <= 0) {
    exit('Opération invalide.');
}

if (!tableExists($pdo, 'operations')) {
    exit('Table operations introuvable.');
}

$stmt = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
$stmt->execute([$operationId]);
$operation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

/* LOT 1B : état avant modification */
$beforeOperation = $operation;

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label, is_active
        FROM ref_operation_types
        WHERE COALESCE(is_active,1)=1
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
        WHERE COALESCE(rs.is_active,1)=1
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
        WHERE COALESCE(c.is_active,1)=1
        ORDER BY c.client_code ASC
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
          AND COALESCE(is_postable,0)=1
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

if (!function_exists('sl_edit_value')) {
    function sl_edit_value(string $key, array $operation, mixed $default = ''): string
    {
        if (isset($_POST[$key])) {
            return (string)$_POST[$key];
        }
        return (string)($operation[$key] ?? $default);
    }
}

$preview = null;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $operationDate = trim((string)($_POST['operation_date'] ?? date('Y-m-d')));
        $amount = (float)($_POST['amount'] ?? 0);
        $currencyCode = trim((string)($_POST['currency_code'] ?? 'EUR'));
        $clientId = ($_POST['client_id'] ?? '') !== '' ? (int)$_POST['client_id'] : null;
        $operationTypeId = (int)($_POST['operation_type_id'] ?? 0);
        $serviceId = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
        $linkedBankAccountId = ($_POST['linked_bank_account_id'] ?? '') !== '' ? (int)$_POST['linked_bank_account_id'] : null;
        $reference = trim((string)($_POST['reference'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $sourceAccountCode = trim((string)($_POST['source_account_code'] ?? ''));
        $destinationAccountCode = trim((string)($_POST['destination_account_code'] ?? ''));
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($operationDate === '') {
            throw new RuntimeException('Date obligatoire.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }
        if ($operationTypeId <= 0) {
            throw new RuntimeException('Type d’opération obligatoire.');
        }
        if ($serviceId === null) {
            throw new RuntimeException('Type de service obligatoire.');
        }

        $selectedType = sl_edit_find_by_id($operationTypes, $operationTypeId);
        $selectedService = sl_edit_find_by_id($services, $serviceId);

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

        $requiresClient = !($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE');
        if ($requiresClient && !$clientId) {
            throw new RuntimeException('Le client est obligatoire pour cette opération.');
        }

        $manualCases = [
            'VIREMENT::INTERNE',
            'CA_DIVERS::CA_DIVERS',
            'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
            'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
            'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
            'CA_LOGEMENT::CA_LOGEMENT'
        ];

        $manualKey = $typeCode . '::' . $serviceCode;
        $isManualCase = in_array($manualKey, $manualCases, true);

        if ($isManualCase && ($sourceAccountCode === '' || $destinationAccountCode === '')) {
            throw new RuntimeException('Le compte source et le compte destination sont obligatoires pour ce cas.');
        }

        $payload = [
            'operation_date' => $operationDate,
            'amount' => $amount,
            'currency_code' => $currencyCode !== '' ? $currencyCode : 'EUR',
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'service_code' => $serviceCode,
            'operation_type_id' => $operationTypeId,
            'operation_type_code' => $typeCode,
            'linked_bank_account_id' => $linkedBankAccountId,
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== '' ? $label : trim(($selectedType['label'] ?? '') . ' - ' . ($selectedService['label'] ?? '')),
            'notes' => $notes !== '' ? $notes : null,
            'source_type' => 'manual',
            'operation_kind' => $operation['operation_kind'] ?? 'manual',
            'source_treasury_code' => ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') ? $sourceAccountCode : '',
            'target_treasury_code' => ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') ? $destinationAccountCode : '',
            'manual_debit_account_code' => $isManualCase ? $sourceAccountCode : '',
            'manual_credit_account_code' => $isManualCase ? $destinationAccountCode : '',
        ];

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            createOperationWithAccountingV2($pdo, $payload, $operationId);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_operation',
                    'operations',
                    'operation',
                    $operationId,
                    'Modification d’une opération'
                );
            }

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
            $stmt->execute([$operationId]);
            $operation = $stmt->fetch(PDO::FETCH_ASSOC) ?: $operation;

            /* LOT 1B : audit trail après modification */
            if (function_exists('auditEntityChanges')) {
                auditEntityChanges(
                    $pdo,
                    'operation',
                    (int)$operationId,
                    $beforeOperation,
                    $operation,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                );
            }

            /* LOT 1B : notification standard */
            if (function_exists('createNotification')) {
                createNotification(
                    $pdo,
                    'operation_update',
                    'L’opération #' . (int)$operationId . ' a été modifiée.',
                    'info',
                    APP_URL . 'modules/operations/operation_view.php?id=' . (int)$operationId,
                    'operation',
                    (int)$operationId,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                );
            }

            /* LOT 1B : alerte spécifique si mode manuel */
            if (!empty($preview['is_manual_accounting']) && function_exists('createNotification')) {
                createNotification(
                    $pdo,
                    'manual_accounting',
                    'Une opération en mode manuel a été enregistrée.',
                    'warning',
                    APP_URL . 'modules/operations/operation_view.php?id=' . (int)$operationId,
                    'operation',
                    (int)$operationId,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                );
            }

            $successMessage = 'Opération mise à jour avec succès.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier une opération';
$pageSubtitle = 'Édition sécurisée alignée sur la logique actuelle de création.';
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
                    <input type="hidden" name="id" value="<?= (int)$operationId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e(sl_edit_value('operation_date', $operation, date('Y-m-d'))) ?>" required>
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e(sl_edit_value('amount', $operation)) ?>" required>
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency_code" required>
                                <?php
                                $selectedCurrency = sl_edit_value('currency_code', $operation, 'EUR');
                                foreach ($currencies as $currency):
                                ?>
                                    <option value="<?= e($currency['code']) ?>" <?= $selectedCurrency === $currency['code'] ? 'selected' : '' ?>>
                                        <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="client-wrapper">
                            <label>Client</label>
                            <?php $selectedClient = sl_edit_value('client_id', $operation); ?>
                            <select name="client_id" id="client_id">
                                <option value="">Choisir</option>
                                <?php foreach ($clients as $client): ?>
                                    <option
                                        value="<?= (int)$client['id'] ?>"
                                        data-country-commercial="<?= e($client['country_commercial'] ?? '') ?>"
                                        data-country-destination="<?= e($client['country_destination'] ?? '') ?>"
                                        data-client-account="<?= e($client['generated_client_account'] ?? '') ?>"
                                        data-treasury-account="<?= e($client['treasury_account_code'] ?? '') ?>"
                                        <?= $selectedClient == $client['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type opération</label>
                            <?php $selectedTypeId = sl_edit_value('operation_type_id', $operation); ?>
                            <select name="operation_type_id" id="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $typeRow): ?>
                                    <option value="<?= (int)$typeRow['id'] ?>" data-type-code="<?= e(sl_normalize_code($typeRow['code'] ?? '')) ?>" <?= $selectedTypeId == $typeRow['id'] ? 'selected' : '' ?>>
                                        <?= e($typeRow['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type service</label>
                            <?php $selectedServiceId = sl_edit_value('service_id', $operation); ?>
                            <select name="service_id" id="service_id" required>
                                <option value="">Choisir d’abord un type</option>
                                <?php foreach ($services as $serviceRow): ?>
                                    <option
                                        value="<?= (int)$serviceRow['id'] ?>"
                                        data-type-id="<?= (int)($serviceRow['operation_type_id'] ?? 0) ?>"
                                        data-type-code="<?= e(sl_normalize_code($serviceRow['operation_type_code'] ?? '')) ?>"
                                        data-service-code="<?= e(sl_normalize_code($serviceRow['code'] ?? '')) ?>"
                                        <?= $selectedServiceId == $serviceRow['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($serviceRow['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="linked-bank-account-wrapper">
                            <label>Compte bancaire lié</label>
                            <input type="number" name="linked_bank_account_id" id="linked_bank_account_id" value="<?= e(sl_edit_value('linked_bank_account_id', $operation)) ?>">
                        </div>

                        <div>
                            <label>Référence / Intitulé</label>
                            <input type="text" name="reference" value="<?= e(sl_edit_value('reference', $operation)) ?>">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label>Note / Motif</label>
                            <textarea name="notes" rows="4"><?= e(sl_edit_value('notes', $operation)) ?></textarea>
                        </div>

                        <div id="source-account-wrapper">
                            <label>Compte source (débit)</label>
                            <?php $selectedSource = (string)($_POST['source_account_code'] ?? ''); ?>
                            <select name="source_account_code" id="source_account_code">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $selectedSource === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($serviceAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $selectedSource === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="destination-account-wrapper">
                            <label>Compte destination (crédit)</label>
                            <?php $selectedDestination = (string)($_POST['destination_account_code'] ?? ''); ?>
                            <select name="destination_account_code" id="destination_account_code">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $selectedDestination === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($serviceAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $selectedDestination === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte débité calculé</label>
                            <input type="text" value="<?= e($preview['debit_account_code'] ?? ($operation['debit_account_code'] ?? '')) ?>" readonly>
                        </div>

                        <div>
                            <label>Compte crédité calculé</label>
                            <input type="text" value="<?= e($preview['credit_account_code'] ?? ($operation['credit_account_code'] ?? '')) ?>" readonly>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Libellé libre</label>
                        <input type="text" name="label" value="<?= e(sl_edit_value('label', $operation)) ?>">
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Aperçu Comptable</h3>
                <?php
                $displayPreview = $preview ?: [
                    'debit_account_code' => $operation['debit_account_code'] ?? '',
                    'credit_account_code' => $operation['credit_account_code'] ?? '',
                    'is_manual_accounting' => $operation['is_manual_accounting'] ?? 0,
                    'operation_hash' => $operation['operation_hash'] ?? '',
                ];
                ?>
                <div class="stat-row"><span class="metric-label">Débit</span><span class="metric-value"><?= e($displayPreview['debit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Crédit</span><span class="metric-value"><?= e($displayPreview['credit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Mode manuel</span><span class="metric-value"><?= !empty($displayPreview['is_manual_accounting']) ? 'Oui' : 'Non' ?></span></div>
                <div class="stat-row"><span class="metric-label">Hash anti-doublon</span><span class="metric-value"><?= e($displayPreview['operation_hash'] ?? '') ?></span></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const map = <?= json_encode(sl_operation_service_map(), JSON_UNESCAPED_UNICODE) ?>;
            const typeSelect = document.getElementById('operation_type_id');
            const serviceSelect = document.getElementById('service_id');
            const clientWrapper = document.getElementById('client-wrapper');
            const linkedBankWrapper = document.getElementById('linked-bank-account-wrapper');
            const sourceWrapper = document.getElementById('source-account-wrapper');
            const destinationWrapper = document.getElementById('destination-account-wrapper');

            if (!typeSelect || !serviceSelect) return;

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
                        if (cloned.value === currentValue) stillValid = true;
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

                const manualCases = [
                    'VIREMENT::INTERNE',
                    'CA_DIVERS::CA_DIVERS',
                    'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
                    'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
                    'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
                    'CA_LOGEMENT::CA_LOGEMENT'
                ];

                const needsLinkedBank =
                    (typeCode === 'VIREMENT' && serviceCode !== 'INTERNE' && serviceCode !== '') ||
                    typeCode === 'VERSEMENT' ||
                    typeCode === 'REGULARISATION';

                const isManual = manualCases.includes(key);
                const isInternal = key === 'VIREMENT::INTERNE';

                linkedBankWrapper.style.display = needsLinkedBank ? '' : 'none';
                sourceWrapper.style.display = (isManual || isInternal) ? '' : 'none';
                destinationWrapper.style.display = (isManual || isInternal) ? '' : 'none';
                clientWrapper.style.display = isInternal ? 'none' : '';
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