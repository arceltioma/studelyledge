<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

/* LOT 2 - nouveaux moteurs additifs */
require_once __DIR__ . '/../../includes/rules_engine.php';
require_once __DIR__ . '/../../includes/anomaly_engine.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'operations_create_page');
} else {
    enforcePagePermission($pdo, 'operations_create');
}

$pageTitle = 'Créer une opération';
$pageSubtitle = 'Création sécurisée avec aperçu comptable';

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

$currencies = function_exists('sl_get_currency_options') ? sl_get_currency_options($pdo) : [
    ['code' => 'EUR', 'label' => 'Euro']
];

if (!function_exists('sl_find_by_id')) {
    function sl_find_by_id(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('sl_render_rules_summary')) {
    function sl_render_rules_summary(array $summary): string
    {
        $parts = [];

        if (!empty($summary['requires_client'])) {
            $parts[] = 'Client requis';
        }
        if (!empty($summary['requires_linked_bank'])) {
            $parts[] = 'Compte lié requis';
        }
        if (!empty($summary['requires_manual_accounts'])) {
            $parts[] = 'Comptes manuels requis';
        }
        if (!empty($summary['service_account_search_text'])) {
            $parts[] = 'Recherche 706: ' . $summary['service_account_search_text'];
        }

        return $parts ? implode(' | ', $parts) : '—';
    }
}

$preview = null;
$rulesSummary = [];
$anomalies = [];
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

        $selectedType = sl_find_by_id($operationTypes, $operationTypeId);
        $selectedService = sl_find_by_id($services, $serviceId);

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

        $selectedClient = null;
        if ($clientId !== null) {
            $selectedClient = sl_find_by_id($clients, $clientId);
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
            'operation_kind' => 'manual',
            'source_treasury_code' => ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') ? $sourceAccountCode : '',
            'target_treasury_code' => ($typeCode === 'VIREMENT' && $serviceCode === 'INTERNE') ? $destinationAccountCode : '',
            'manual_debit_account_code' => $isManualCase ? $sourceAccountCode : '',
            'manual_credit_account_code' => $isManualCase ? $destinationAccountCode : '',
        ];

        /* LOT 2 - résumé des règles */
        if (function_exists('sl_rules_build_summary')) {
            $rulesSummary = sl_rules_build_summary(
                $typeCode,
                $serviceCode,
                (string)($selectedClient['country_commercial'] ?? ''),
                (string)($selectedClient['country_destination'] ?? '')
            );
        }

        /* LOT 2 - détection anomalies */
        if (function_exists('sl_detect_operation_anomalies')) {
            $anomalies = sl_detect_operation_anomalies(array_merge($payload, [
                'country_commercial' => (string)($selectedClient['country_commercial'] ?? ''),
                'country_destination' => (string)($selectedClient['country_destination'] ?? ''),
            ]));
        }

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $newId = createOperationWithAccountingV2($pdo, $payload);

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_operation',
                    'operations',
                    'operation',
                    $newId,
                    'Création d’une opération'
                );
            }

            /* LOT 1B / LOT 2 - notification standard */
            if (function_exists('createNotification')) {
                createNotification(
                    $pdo,
                    'operation_create',
                    'Une nouvelle opération #' . (int)$newId . ' a été créée.',
                    'success',
                    APP_URL . 'modules/operations/operation_view.php?id=' . (int)$newId,
                    'operation',
                    (int)$newId,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                );
            }

            /* LOT 1B / LOT 2 - alerte spécifique si mode manuel */
            if (!empty($preview['is_manual_accounting']) && function_exists('createNotification')) {
                createNotification(
                    $pdo,
                    'manual_accounting',
                    'Une opération en mode manuel a été enregistrée.',
                    'warning',
                    APP_URL . 'modules/operations/operation_view.php?id=' . (int)$newId,
                    'operation',
                    (int)$newId,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
                );
            }

            $pdo->commit();
            $successMessage = 'Opération créée avec succès.';
            $_POST = [];

            /* on vide aussi les enrichissements visuels après succès */
            $preview = null;
            $rulesSummary = [];
            $anomalies = [];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

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
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e($_POST['operation_date'] ?? date('Y-m-d')) ?>" required>
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= e($_POST['amount'] ?? '') ?>" required>
                        </div>

                        <div>
                            <label>Devise</label>
                            <?php $selectedCurrency = (string)($_POST['currency_code'] ?? 'EUR'); ?>
                            <select name="currency_code" required>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= e($currency['code']) ?>" <?= $selectedCurrency === $currency['code'] ? 'selected' : '' ?>>
                                        <?= e($currency['code'] . ' - ' . $currency['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="client-wrapper">
                            <label>Client</label>
                            <?php $selectedClient = (string)($_POST['client_id'] ?? ''); ?>
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
                            <?php $selectedTypeId = (string)($_POST['operation_type_id'] ?? ''); ?>
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
                            <?php $selectedServiceId = (string)($_POST['service_id'] ?? ''); ?>
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
                            <input type="number" name="linked_bank_account_id" id="linked_bank_account_id" value="<?= e($_POST['linked_bank_account_id'] ?? '') ?>">
                        </div>

                        <div>
                            <label>Référence / Intitulé</label>
                            <input type="text" name="reference" value="<?= e($_POST['reference'] ?? '') ?>">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label>Note / Motif</label>
                            <textarea name="notes" rows="4"><?= e($_POST['notes'] ?? '') ?></textarea>
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
                            <input type="text" value="<?= e($preview['debit_account_code'] ?? '') ?>" readonly>
                        </div>

                        <div>
                            <label>Compte crédité calculé</label>
                            <input type="text" value="<?= e($preview['credit_account_code'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Libellé libre</label>
                        <input type="text" name="label" value="<?= e($_POST['label'] ?? '') ?>">
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
                <div class="stat-row"><span class="metric-label">Débit</span><span class="metric-value"><?= e($preview['debit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Crédit</span><span class="metric-value"><?= e($preview['credit_account_code'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Mode manuel</span><span class="metric-value"><?= !empty($preview['is_manual_accounting']) ? 'Oui' : 'Non' ?></span></div>
                <div class="stat-row"><span class="metric-label">Hash anti-doublon</span><span class="metric-value"><?= e($preview['operation_hash'] ?? '') ?></span></div>

                <!-- LOT 2 : résumé intelligent -->
                <hr style="margin:16px 0;">

                <h4 style="margin-bottom:10px;">Règles intelligentes</h4>
                <div class="stat-row">
                    <span class="metric-label">Résumé</span>
                    <span class="metric-value"><?= e(sl_render_rules_summary($rulesSummary)) ?></span>
                </div>

                <?php if (!empty($rulesSummary['service_account_tokens']) && is_array($rulesSummary['service_account_tokens'])): ?>
                    <div class="stat-row">
                        <span class="metric-label">Tokens 706</span>
                        <span class="metric-value"><?= e(implode(' | ', $rulesSummary['service_account_tokens'])) ?></span>
                    </div>
                <?php endif; ?>

                <hr style="margin:16px 0;">

                <h4 style="margin-bottom:10px;">Anomalies détectées</h4>
                <?php if ($anomalies): ?>
                    <?php foreach ($anomalies as $anomaly): ?>
                        <div class="stat-row">
                            <span class="metric-label"><?= e((string)($anomaly['code'] ?? '')) ?></span>
                            <span class="metric-value"><?= e((string)($anomaly['message'] ?? '')) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="stat-row">
                        <span class="metric-label">Statut</span>
                        <span class="metric-value">Aucune anomalie détectée</span>
                    </div>
                <?php endif; ?>
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