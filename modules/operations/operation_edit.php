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

if (!function_exists('sl_normalize_code')) {
    function sl_normalize_code(?string $value): string
    {
        $value = strtoupper(trim((string)$value));
        $value = str_replace(
            ['É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Ä', 'Î', 'Ï', 'Ô', 'Ö', 'Ù', 'Û', 'Ü', 'Ç', ' ', '-', '/', '\''],
            ['E', 'E', 'E', 'E', 'A', 'A', 'A', 'I', 'I', 'O', 'O', 'U', 'U', 'U', 'C', '_', '_', '_', ''],
            $value
        );
        $value = preg_replace('/[^A-Z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim((string)$value, '_');
    }
}

if (!function_exists('sl_operation_service_map')) {
    function sl_operation_service_map(): array
    {
        return [
            'VERSEMENT' => ['VERSEMENT'],
            'VIREMENT' => ['INTERNE', 'MENSUEL', 'EXCEPTIONEL', 'REGULIER'],
            'REGULARISATION' => ['POSITIVE', 'NEGATIVE'],
            'FRAIS_SERVICE' => ['AVI', 'ATS'],
            'FRAIS_GESTION' => ['GESTION'],
            'COMMISSION_DE_TRANSFERT' => ['COMMISSION_DE_TRANSFERT'],
            'CA_PLACEMENT' => ['CA_PLACEMENT'],
            'CA_DIVERS' => ['CA_DIVERS'],
            'CA_LOGEMENT' => ['CA_LOGEMENT'],
            'CA_COURTAGE_PRET' => ['CA_COURTAGE_PRET'],
            'FRAIS_DEBOURDS_MICROFINANCE' => ['FRAIS_DEBOURDS_MICROFINANCE'],
        ];
    }
}

if (!function_exists('sl_service_allowed_for_type')) {
    function sl_service_allowed_for_type(?string $typeCode, ?string $serviceCode): bool
    {
        $map = sl_operation_service_map();
        $typeCode = sl_normalize_code($typeCode);
        $serviceCode = sl_normalize_code($serviceCode);

        if ($typeCode === '' || $serviceCode === '' || !isset($map[$typeCode])) {
            return false;
        }

        return in_array($serviceCode, $map[$typeCode], true);
    }
}

if (!function_exists('opEditFindById')) {
    function opEditFindById(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Opération invalide.');
}

$stmtOperation = $pdo->prepare("
    SELECT *
    FROM operations
    WHERE id = ?
    LIMIT 1
");
$stmtOperation->execute([$id]);
$operation = $stmtOperation->fetch(PDO::FETCH_ASSOC);

if (!$operation) {
    exit('Opération introuvable.');
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("
        SELECT id, code, label, direction, is_active
        FROM ref_operation_types
        ORDER BY label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rs.operation_type_id,
            rs.service_account_id,
            rs.treasury_account_id,
            rs.is_active,

            rot.code AS operation_type_code,
            rot.label AS operation_type_label,
            rot.is_active AS operation_type_active,

            sa.account_code AS service_account_code,
            sa.account_label AS service_account_label,
            sa.operation_type_label AS service_account_operation_type_label,
            sa.destination_country_label AS service_account_destination_country_label,
            sa.commercial_country_label AS service_account_commercial_country_label,
            sa.is_postable AS service_account_postable,
            sa.is_active AS service_account_active,

            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        LEFT JOIN service_accounts sa ON sa.id = rs.service_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = rs.treasury_account_id
        ORDER BY rs.label ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT
            c.id,
            c.client_code,
            c.full_name,
            c.generated_client_account,
            c.initial_treasury_account_id,
            c.client_status,
            c.is_active,
            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label
        FROM clients c
        LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
        ORDER BY c.client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT id, account_code, account_label, is_active
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$serviceAccounts = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT
            sa.id,
            sa.account_code,
            sa.account_label,
            sa.operation_type_label,
            sa.destination_country_label,
            sa.commercial_country_label,
            sa.is_postable,
            sa.is_active
        FROM service_accounts sa
        WHERE COALESCE(sa.is_active,1) = 1
          AND COALESCE(sa.is_postable,0) = 1
        ORDER BY sa.account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$currentOperationTypeId = null;
if (!empty($operation['operation_type_code'])) {
    foreach ($operationTypes as $type) {
        if (sl_normalize_code($type['code'] ?? '') === sl_normalize_code($operation['operation_type_code'] ?? '')) {
            $currentOperationTypeId = (int)$type['id'];
            break;
        }
    }
}

$currentServiceId = null;
if (!empty($operation['service_account_code'])) {
    foreach ($services as $service) {
        if (($service['service_account_code'] ?? '') === ($operation['service_account_code'] ?? '')) {
            $currentServiceId = (int)$service['id'];
            break;
        }
    }
}

$currentSourceTreasuryId = null;
$currentTargetTreasuryId = null;

if (!empty($operation['debit_account_code'])) {
    foreach ($treasuryAccounts as $acc) {
        if (($acc['account_code'] ?? '') === ($operation['debit_account_code'] ?? '')) {
            $currentSourceTreasuryId = (int)$acc['id'];
            break;
        }
    }
}
if (!empty($operation['credit_account_code'])) {
    foreach ($treasuryAccounts as $acc) {
        if (($acc['account_code'] ?? '') === ($operation['credit_account_code'] ?? '')) {
            $currentTargetTreasuryId = (int)$acc['id'];
            break;
        }
    }
}

$formData = [
    'id' => $id,
    'operation_date' => $operation['operation_date'] ?? date('Y-m-d'),
    'operation_type_id' => $currentOperationTypeId,
    'service_id' => $currentServiceId,
    'client_id' => $operation['client_id'] ?? '',
    'source_treasury_account_id' => $currentSourceTreasuryId,
    'target_treasury_account_id' => $currentTargetTreasuryId,
    'amount' => $operation['amount'] ?? '',
    'reference' => $operation['reference'] ?? '',
    'label' => $operation['label'] ?? '',
    'notes' => $operation['notes'] ?? '',
];

$successMessage = '';
$errorMessage = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'id' => $id,
        'operation_date' => trim((string)($_POST['operation_date'] ?? date('Y-m-d'))),
        'operation_type_id' => (int)($_POST['operation_type_id'] ?? 0),
        'service_id' => ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null,
        'client_id' => ($_POST['client_id'] ?? '') !== '' ? (int)$_POST['client_id'] : null,
        'source_treasury_account_id' => ($_POST['source_treasury_account_id'] ?? '') !== '' ? (int)$_POST['source_treasury_account_id'] : null,
        'target_treasury_account_id' => ($_POST['target_treasury_account_id'] ?? '') !== '' ? (int)$_POST['target_treasury_account_id'] : null,
        'amount' => (float)($_POST['amount'] ?? 0),
        'reference' => trim((string)($_POST['reference'] ?? '')),
        'label' => trim((string)($_POST['label'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($formData['operation_type_id'] <= 0) {
            throw new RuntimeException('Type d’opération obligatoire.');
        }

        if ($formData['amount'] <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        if ($formData['operation_date'] === '') {
            throw new RuntimeException('Date obligatoire.');
        }

        $selectedType = opEditFindById($operationTypes, (int)$formData['operation_type_id']);
        if (!$selectedType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        if ((int)($selectedType['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Le type d’opération sélectionné est archivé.');
        }

        $selectedService = null;
        if ($formData['service_id'] !== null) {
            $selectedService = opEditFindById($services, (int)$formData['service_id']);
            if (!$selectedService) {
                throw new RuntimeException('Service introuvable.');
            }

            if ((int)($selectedService['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le service sélectionné est archivé.');
            }

            if (!sl_service_allowed_for_type($selectedType['code'] ?? '', $selectedService['code'] ?? '')) {
                throw new RuntimeException('Le service sélectionné ne correspond pas au type d’opération choisi.');
            }
        }

        $selectedClient = null;
        if ($formData['client_id'] !== null) {
            $selectedClient = opEditFindById($clients, (int)$formData['client_id']);
            if (!$selectedClient) {
                throw new RuntimeException('Client introuvable.');
            }

            if ((int)($selectedClient['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le client sélectionné est archivé.');
            }
        }

        $selectedSourceTreasury = null;
        if ($formData['source_treasury_account_id'] !== null) {
            $selectedSourceTreasury = opEditFindById($treasuryAccounts, (int)$formData['source_treasury_account_id']);
            if (!$selectedSourceTreasury) {
                throw new RuntimeException('Compte interne source introuvable.');
            }
        }

        $selectedTargetTreasury = null;
        if ($formData['target_treasury_account_id'] !== null) {
            $selectedTargetTreasury = opEditFindById($treasuryAccounts, (int)$formData['target_treasury_account_id']);
            if (!$selectedTargetTreasury) {
                throw new RuntimeException('Compte interne cible introuvable.');
            }
        }

        $typeCode = sl_normalize_code((string)$selectedType['code']);
        $isInternalTransfer = ($typeCode === 'VIREMENT' && $selectedService && sl_normalize_code($selectedService['code'] ?? '') === 'INTERNE');

        if (!$isInternalTransfer && !$selectedClient) {
            throw new RuntimeException('Le client est obligatoire pour cette opération.');
        }

        if (!$selectedService) {
            throw new RuntimeException('Le service est obligatoire.');
        }

        if ($isInternalTransfer) {
            if (!$selectedSourceTreasury || !$selectedTargetTreasury) {
                throw new RuntimeException('Les comptes source et cible sont obligatoires pour un virement interne.');
            }

            if ((int)$selectedSourceTreasury['id'] === (int)$selectedTargetTreasury['id']) {
                throw new RuntimeException('Les comptes source et cible doivent être différents.');
            }

            if ($selectedClient) {
                throw new RuntimeException('Un virement interne ne doit pas être rattaché à un client.');
            }

            $preview = [
                'debit_account_code' => $selectedSourceTreasury['account_code'],
                'credit_account_code' => $selectedTargetTreasury['account_code'],
                'analytic_account' => null,
            ];
        } else {
            $resolvedSourceTreasuryCode = $selectedSourceTreasury['account_code']
                ?? $selectedService['treasury_account_code']
                ?? $selectedClient['treasury_account_code']
                ?? null;

            $payload = [
                'operation_type_code' => $typeCode,
                'operation_type_id' => (int)$selectedType['id'],
                'service_id' => $selectedService['id'] ?? null,
                'client_id' => $selectedClient['id'] ?? null,
                'amount' => $formData['amount'],
                'operation_date' => $formData['operation_date'],
                'reference' => $formData['reference'] !== '' ? $formData['reference'] : null,
                'label' => $formData['label'] !== '' ? $formData['label'] : trim(($selectedType['label'] ?? '') . ' - ' . ($selectedService['label'] ?? '')),
                'notes' => $formData['notes'] !== '' ? $formData['notes'] : null,
                'source_type' => 'manual_edit',
                'operation_kind' => 'manual',
                'source_treasury_code' => $resolvedSourceTreasuryCode,
                'target_treasury_code' => $selectedTargetTreasury['account_code'] ?? null,
            ];

            $preview = resolveAccountingOperation($pdo, $payload);
        }

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $resolvedDebit = $preview['debit_account_code'] ?? null;
            $resolvedCredit = $preview['credit_account_code'] ?? null;
            $resolvedAnalytic = $preview['analytic_account']['account_code'] ?? null;

            $updateFields = [];
            $updateParams = [];

            $updateMap = [
                'client_id' => $selectedClient['id'] ?? null,
                'operation_date' => $formData['operation_date'],
                'amount' => $formData['amount'],
                'operation_type_code' => $typeCode,
                'label' => $formData['label'] !== '' ? $formData['label'] : trim(($selectedType['label'] ?? '') . ' - ' . ($selectedService['label'] ?? '')),
                'reference' => $formData['reference'] !== '' ? $formData['reference'] : null,
                'notes' => $formData['notes'] !== '' ? $formData['notes'] : null,
                'debit_account_code' => $resolvedDebit,
                'credit_account_code' => $resolvedCredit,
                'service_account_code' => $resolvedAnalytic,
            ];

            if (columnExists($pdo, 'operations', 'bank_account_id')) {
                $bankAccountId = null;
                if ($selectedClient) {
                    $bankAccount = findPrimaryBankAccountForClient($pdo, (int)$selectedClient['id']);
                    $bankAccountId = $bankAccount['id'] ?? null;
                }
                $updateMap['bank_account_id'] = $bankAccountId;
            }

            foreach ($updateMap as $column => $value) {
                if (columnExists($pdo, 'operations', $column)) {
                    $updateFields[] = $column . ' = ?';
                    $updateParams[] = $value;
                }
            }

            if (columnExists($pdo, 'operations', 'updated_at')) {
                $updateFields[] = 'updated_at = NOW()';
            }

            $updateParams[] = $id;

            $stmtUpdate = $pdo->prepare("
                UPDATE operations
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmtUpdate->execute($updateParams);

            if (function_exists('recomputeAllBalances')) {
                recomputeAllBalances($pdo);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_operation',
                    'operations',
                    'operation',
                    $id,
                    'Modification d’une opération avec filtrage dynamique type/service'
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
$pageSubtitle = 'Le service visible dépend du type d’opération choisi.';
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
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e((string)($formData['operation_date'] ?? date('Y-m-d'))) ?>">
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e((string)($formData['amount'] ?? '')) ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id" id="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" data-type-code="<?= e(sl_normalize_code($type['code'] ?? '')) ?>" <?= ((string)($formData['operation_type_id'] ?? '') === (string)$type['id']) ? 'selected' : '' ?>>
                                        <?= e($type['label']) ?> (<?= e($type['code']) ?>)<?= (int)$type['is_active'] !== 1 ? ' [archivé]' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id" id="service_id">
                                <option value="">Choisir d’abord un type</option>
                                <?php foreach ($services as $service): ?>
                                    <option
                                        value="<?= (int)$service['id'] ?>"
                                        data-operation-type-id="<?= (int)($service['operation_type_id'] ?? 0) ?>"
                                        data-operation-type-code="<?= e(sl_normalize_code($service['operation_type_code'] ?? '')) ?>"
                                        data-service-code="<?= e(sl_normalize_code($service['code'] ?? '')) ?>"
                                        <?= ((string)($formData['service_id'] ?? '') === (string)$service['id']) ? 'selected' : '' ?>
                                    >
                                        <?= e(($service['label'] ?? '') . ' (' . ($service['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Client</label>
                            <select name="client_id" id="client_id">
                                <option value="">Aucun</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>" <?= ((string)($formData['client_id'] ?? '') === (string)$client['id']) ? 'selected' : '' ?>>
                                        <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= e((string)($formData['reference'] ?? '')) ?>">
                        </div>

                        <div>
                            <label>Compte interne source</label>
                            <select name="source_treasury_account_id" id="source_treasury_account_id">
                                <option value="">Auto / Aucun</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= ((string)($formData['source_treasury_account_id'] ?? '') === (string)$acc['id']) ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte interne cible</label>
                            <select name="target_treasury_account_id" id="target_treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= ((string)($formData['target_treasury_account_id'] ?? '') === (string)$acc['id']) ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e((string)($formData['label'] ?? '')) ?>">
                    </div>

                    <div>
                        <label>Notes / motif</label>
                        <textarea name="notes" rows="4"><?= e((string)($formData['notes'] ?? '')) ?></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/operations/operation_view.php?id=<?= (int)$id ?>">Annuler</a>
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
                        Prévisualise ici le nouveau schéma débit/crédit avant validation.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Comptes 706 finaux disponibles</h3>
            <table>
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Intitulé</th>
                        <th>Type</th>
                        <th>Destination</th>
                        <th>Commercial</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serviceAccounts as $account): ?>
                        <tr>
                            <td><?= e($account['account_code'] ?? '') ?></td>
                            <td><?= e($account['account_label'] ?? '') ?></td>
                            <td><?= e($account['operation_type_label'] ?? '') ?></td>
                            <td><?= e($account['destination_country_label'] ?? '') ?></td>
                            <td><?= e($account['commercial_country_label'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$serviceAccounts): ?>
                        <tr><td colspan="5">Aucun compte 706 final disponible.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const map = <?= json_encode(sl_operation_service_map(), JSON_UNESCAPED_UNICODE) ?>;
            const typeSelect = document.getElementById('operation_type_id');
            const serviceSelect = document.getElementById('service_id');
            const clientSelect = document.getElementById('client_id');

            if (!typeSelect || !serviceSelect) {
                return;
            }

            const originalServiceOptions = Array.from(serviceSelect.querySelectorAll('option')).map(option => option.cloneNode(true));

            function getSelectedTypeCode() {
                const selected = typeSelect.options[typeSelect.selectedIndex];
                return selected ? (selected.getAttribute('data-type-code') || '') : '';
            }

            function refreshServices() {
                const selectedTypeId = typeSelect.value;
                const selectedTypeCode = getSelectedTypeCode();
                const currentValue = serviceSelect.value;

                serviceSelect.innerHTML = '';

                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = selectedTypeId ? 'Choisir' : 'Choisir d’abord un type';
                serviceSelect.appendChild(placeholder);

                let stillValid = false;

                originalServiceOptions.forEach(option => {
                    if (option.value === '') {
                        return;
                    }

                    const serviceTypeId = option.getAttribute('data-operation-type-id') || '';
                    const serviceCode = option.getAttribute('data-service-code') || '';
                    const allowedCodes = map[selectedTypeCode] || [];

                    if ((selectedTypeId !== '' && serviceTypeId === selectedTypeId) || allowedCodes.includes(serviceCode)) {
                        const cloned = option.cloneNode(true);
                        if (cloned.value === currentValue) {
                            stillValid = true;
                        }
                        serviceSelect.appendChild(cloned);
                    }
                });

                serviceSelect.value = stillValid ? currentValue : '';
                refreshClientVisibility();
            }

            function refreshClientVisibility() {
                const selectedTypeCode = getSelectedTypeCode();
                const selectedService = serviceSelect.options[serviceSelect.selectedIndex];
                const selectedServiceCode = selectedService ? (selectedService.getAttribute('data-service-code') || '') : '';
                const isInternal = selectedTypeCode === 'VIREMENT' && selectedServiceCode === 'INTERNE';

                if (clientSelect) {
                    clientSelect.closest('div').style.display = isInternal ? 'none' : '';
                    if (isInternal) {
                        clientSelect.value = '';
                    }
                }
            }

            typeSelect.addEventListener('change', refreshServices);
            serviceSelect.addEventListener('change', refreshClientVisibility);

            refreshServices();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>