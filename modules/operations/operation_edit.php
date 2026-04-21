<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'operation_edit');
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

$treasuryAccounts = function_exists('sl_fetch_postable_treasury_accounts')
    ? sl_fetch_postable_treasury_accounts($pdo)
    : (tableExists($pdo, 'treasury_accounts')
        ? $pdo->query("
            SELECT id, account_code, account_label
            FROM treasury_accounts
            WHERE COALESCE(is_active,1)=1
            ORDER BY account_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC)
        : []);

$serviceAccounts = function_exists('sl_fetch_postable_service_accounts')
    ? sl_fetch_postable_service_accounts($pdo)
    : (tableExists($pdo, 'service_accounts')
        ? $pdo->query("
            SELECT id, account_code, account_label
            FROM service_accounts
            WHERE COALESCE(is_active,1)=1
            ORDER BY account_code ASC
        ")->fetchAll(PDO::FETCH_ASSOC)
        : []);

$currencies = function_exists('sl_get_currency_options')
    ? sl_get_currency_options($pdo)
    : [['code' => 'EUR', 'label' => 'Euro']];

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

$formData = [
    'operation_date' => (string)($operation['operation_date'] ?? date('Y-m-d')),
    'amount' => (string)($operation['amount'] ?? ''),
    'currency_code' => (string)($operation['currency_code'] ?? 'EUR'),
    'client_id' => (string)($operation['client_id'] ?? ''),
    'operation_type_id' => (string)($operation['operation_type_id'] ?? ''),
    'service_id' => (string)($operation['service_id'] ?? ''),
    'linked_bank_account_id' => (string)($operation['linked_bank_account_id'] ?? ''),
    'reference' => (string)($operation['reference'] ?? ''),
    'label' => (string)($operation['label'] ?? ''),
    'notes' => (string)($operation['notes'] ?? ''),
    'source_account_code' => '',
    'destination_account_code' => '',
];

$preview = null;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'operation_date' => trim((string)($_POST['operation_date'] ?? date('Y-m-d'))),
        'amount' => trim((string)($_POST['amount'] ?? '')),
        'currency_code' => trim((string)($_POST['currency_code'] ?? 'EUR')),
        'client_id' => trim((string)($_POST['client_id'] ?? '')),
        'operation_type_id' => trim((string)($_POST['operation_type_id'] ?? '')),
        'service_id' => trim((string)($_POST['service_id'] ?? '')),
        'linked_bank_account_id' => trim((string)($_POST['linked_bank_account_id'] ?? '')),
        'reference' => trim((string)($_POST['reference'] ?? '')),
        'label' => trim((string)($_POST['label'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
        'source_account_code' => trim((string)($_POST['source_account_code'] ?? '')),
        'destination_account_code' => trim((string)($_POST['destination_account_code'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $operationDate = $formData['operation_date'];
        $amount = (float)str_replace(',', '.', $formData['amount']);
        $currencyCode = $formData['currency_code'] !== '' ? $formData['currency_code'] : 'EUR';
        $clientId = $formData['client_id'] !== '' ? (int)$formData['client_id'] : null;
        $operationTypeId = $formData['operation_type_id'] !== '' ? (int)$formData['operation_type_id'] : 0;
        $serviceId = $formData['service_id'] !== '' ? (int)$formData['service_id'] : null;
        $linkedBankAccountId = $formData['linked_bank_account_id'] !== '' ? (int)$formData['linked_bank_account_id'] : null;
        $reference = $formData['reference'];
        $label = $formData['label'];
        $notes = $formData['notes'];
        $sourceAccountCode = $formData['source_account_code'];
        $destinationAccountCode = $formData['destination_account_code'];
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($operationDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $operationDate)) {
            throw new RuntimeException('Date invalide.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        if ($operationTypeId <= 0) {
            throw new RuntimeException('Type d’opération obligatoire.');
        }

        if ($serviceId === null || $serviceId <= 0) {
            throw new RuntimeException('Service obligatoire.');
        }

        $selectedType = sl_edit_find_by_id($operationTypes, $operationTypeId);
        $selectedService = sl_edit_find_by_id($services, $serviceId);

        if (!$selectedType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        if (!$selectedService) {
            throw new RuntimeException('Service introuvable.');
        }

        $typeCode = function_exists('sl_normalize_code')
            ? sl_normalize_code((string)($selectedType['code'] ?? ''))
            : strtoupper(trim((string)($selectedType['code'] ?? '')));

        $serviceCode = function_exists('sl_normalize_code')
            ? sl_normalize_code((string)($selectedService['code'] ?? ''))
            : strtoupper(trim((string)($selectedService['code'] ?? '')));

        if (function_exists('sl_service_allowed_for_type') && !sl_service_allowed_for_type($typeCode, $serviceCode)) {
            throw new RuntimeException('Service incompatible avec le type d’opération.');
        }

        $manualCases = function_exists('sl_manual_accounting_cases')
            ? sl_manual_accounting_cases()
            : [
                'VIREMENT::INTERNE',
                'CA_DIVERS::CA_DIVERS',
                'CA_DEBOURDS_ASSURANCE::CA_DEBOURDS_ASSURANCE',
                'FRAIS_DEBOURDS_MICROFINANCE::FRAIS_DEBOURDS_MICROFINANCE',
                'CA_COURTAGE_PRET::CA_COURTAGE_PRET',
                'CA_LOGEMENT::CA_LOGEMENT',
            ];

        $manualKey = $typeCode . '::' . $serviceCode;
        $isInternalTransfer = ($manualKey === 'VIREMENT::INTERNE');
        $isManualCase = in_array($manualKey, $manualCases, true);

        $requiresClient = !$isInternalTransfer;
        if ($requiresClient && !$clientId) {
            throw new RuntimeException('Le client est obligatoire pour cette opération.');
        }

        if ($clientId !== null && $clientId > 0) {
            sl_assert_client_operation_allowed($pdo, $clientId);
        }

        if (($isInternalTransfer || $isManualCase) && ($sourceAccountCode === '' || $destinationAccountCode === '')) {
            throw new RuntimeException('Le compte source et le compte destination sont obligatoires.');
        }

        $payload = [
            'operation_date' => $operationDate,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'client_id' => $clientId,
            'service_id' => $serviceId,
            'service_code' => $serviceCode,
            'operation_type_id' => $operationTypeId,
            'operation_type_code' => $typeCode,
            'linked_bank_account_id' => $linkedBankAccountId,
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== ''
                ? $label
                : trim((string)($selectedType['label'] ?? '') . ' - ' . (string)($selectedService['label'] ?? '')),
            'notes' => $notes !== '' ? $notes : null,
            'source_type' => 'manual',
            'operation_kind' => (string)($operation['operation_kind'] ?? 'manual'),
            'source_treasury_code' => $isInternalTransfer ? $sourceAccountCode : '',
            'target_treasury_code' => $isInternalTransfer ? $destinationAccountCode : '',
            'manual_debit_account_code' => $isManualCase ? $sourceAccountCode : '',
            'manual_credit_account_code' => $isManualCase ? $destinationAccountCode : '',
        ];

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if ($actionMode === 'save') {
            $resultId = createOperationWithAccountingV2($pdo, $payload, $operationId);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_operation',
                    'operations',
                    'operation',
                    $resultId,
                    'Modification d’une opération'
                );
            }

            if (function_exists('createNotification') && isset($_SESSION['user_id'])) {
                createNotification(
                    $pdo,
                    'operation_update',
                    'Opération modifiée : ' . ($payload['label'] ?? 'Opération'),
                    'info',
                    APP_URL . 'modules/operations/operation_view.php?id=' . $resultId,
                    'operation',
                    $resultId,
                    (int)$_SESSION['user_id']
                );
            }

            $stmt = $pdo->prepare("SELECT * FROM operations WHERE id = ? LIMIT 1");
            $stmt->execute([$resultId]);
            $operation = $stmt->fetch(PDO::FETCH_ASSOC) ?: $operation;
            $operationId = (int)($operation['id'] ?? $operationId);

            $successMessage = 'Opération mise à jour avec succès.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$displayPreview = $preview ?: [
    'debit_account_code' => $operation['debit_account_code'] ?? '',
    'credit_account_code' => $operation['credit_account_code'] ?? '',
    'is_manual_accounting' => $operation['is_manual_accounting'] ?? 0,
    'operation_hash' => $operation['operation_hash'] ?? '',
];

$pageTitle = 'Modifier une opération';
$pageSubtitle = 'Édition sécurisée avec prévisualisation avant validation';

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
                <h3>Modification de l’opération</h3>
                <p class="muted">Prévisualise le résultat comptable avant de valider la mise à jour.</p>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$operationId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e($formData['operation_date']) ?>" required>
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e($formData['amount']) ?>" required>
                        </div>

                        <div>
                            <label>Devise</label>
                            <select name="currency_code" required>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= $formData['currency_code'] === $currency['code'] ? 'selected' : '' ?>>
                                        <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="client-wrapper">
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
                                        <?= $formData['client_id'] == $client['id'] ? 'selected' : '' ?>
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
                                <?php foreach ($operationTypes as $typeRow): ?>
                                    <option
                                        value="<?= (int)$typeRow['id'] ?>"
                                        data-type-code="<?= e(function_exists('sl_normalize_code') ? sl_normalize_code($typeRow['code'] ?? '') : strtoupper(trim((string)($typeRow['code'] ?? '')))) ?>"
                                        <?= $formData['operation_type_id'] == $typeRow['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($typeRow['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id" id="service_id" required>
                                <option value="">Choisir d’abord un type</option>
                                <?php foreach ($services as $serviceRow): ?>
                                    <option
                                        value="<?= (int)$serviceRow['id'] ?>"
                                        data-type-id="<?= (int)($serviceRow['operation_type_id'] ?? 0) ?>"
                                        data-type-code="<?= e(function_exists('sl_normalize_code') ? sl_normalize_code($serviceRow['operation_type_code'] ?? '') : strtoupper(trim((string)($serviceRow['operation_type_code'] ?? '')))) ?>"
                                        data-service-code="<?= e(function_exists('sl_normalize_code') ? sl_normalize_code($serviceRow['code'] ?? '') : strtoupper(trim((string)($serviceRow['code'] ?? '')))) ?>"
                                        <?= $formData['service_id'] == $serviceRow['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($serviceRow['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="linked-bank-account-wrapper">
                            <label>Compte Bancaire 512</label>
                            <select name="linked_bank_account_id" id="linked_bank_account_id">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= $formData['linked_bank_account_id'] == $acc['id'] ? 'selected' : '' ?>>
                                        <?= e(($acc['account_code'] ?? '') . ' - ' . ($acc['account_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= e($formData['reference']) ?>">
                        </div>

                        <div style="grid-column:1 / -1;">
                            <label>Note / Motif</label>
                            <textarea name="notes" rows="4"><?= e($formData['notes']) ?></textarea>
                        </div>

                        <div id="source-account-wrapper">
                            <label>Compte source (débit)</label>
                            <select name="source_account_code" id="source_account_code">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $formData['source_account_code'] === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($serviceAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $formData['source_account_code'] === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="destination-account-wrapper">
                            <label>Compte destination (crédit)</label>
                            <select name="destination_account_code" id="destination_account_code">
                                <option value="">Choisir</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $formData['destination_account_code'] === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php foreach ($serviceAccounts as $acc): ?>
                                    <option value="<?= e($acc['account_code']) ?>" <?= $formData['destination_account_code'] === ($acc['account_code'] ?? '') ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="grid-column:1 / -1;">
                            <label>Libellé libre</label>
                            <input type="text" name="label" value="<?= e($formData['label']) ?>">
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Prévisualisation avant validation</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Date</span><strong><?= e($formData['operation_date']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Montant</span><strong><?= e(number_format((float)$formData['amount'], 2, ',', ' ')) ?> <?= e($formData['currency_code']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte débité</span><strong><?= e($displayPreview['debit_account_code'] ?? '') ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte crédité</span><strong><?= e($displayPreview['credit_account_code'] ?? '') ?></strong></div>
                    <div class="sl-data-list__row"><span>Mode manuel</span><strong><?= !empty($displayPreview['is_manual_accounting']) ? 'Oui' : 'Non' ?></strong></div>
                    <div class="sl-data-list__row"><span>Hash anti-doublon</span><strong style="word-break:break-all;"><?= e($displayPreview['operation_hash'] ?? '') ?></strong></div>
                </div>

                <?php if ($preview && !empty($preview['preview_lines']) && is_array($preview['preview_lines'])): ?>
                    <div class="sl-table-wrap" style="margin-top:18px;">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Sens</th>
                                    <th>Compte</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preview['preview_lines'] as $line): ?>
                                    <tr>
                                        <td><?= e((string)($line['side'] ?? '')) ?></td>
                                        <td><?= e((string)($line['account'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note" style="margin-top:18px;">
                        Prévisualise la modification pour vérifier les comptes avant validation.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const map = <?= json_encode(function_exists('sl_operation_service_map') ? sl_operation_service_map() : [], JSON_UNESCAPED_UNICODE) ?>;
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
                    if (allowedCodes.length === 0 || allowedCodes.includes(serviceCode)) {
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