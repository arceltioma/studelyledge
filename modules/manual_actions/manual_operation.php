<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'manual_actions_create');
}

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

if (!function_exists('sl_manual_find_by_code')) {
    function sl_manual_find_by_code(array $rows, string $code, string $key = 'account_code'): ?array
    {
        $needle = trim($code);
        foreach ($rows as $row) {
            if (trim((string)($row[$key] ?? '')) === $needle) {
                return $row;
            }
        }
        return null;
    }
}

if (!function_exists('sl_manual_account_family')) {
    function sl_manual_account_family(string $accountCode): string
    {
        $code = trim($accountCode);

        if (str_starts_with($code, '411')) {
            return '411';
        }
        if (str_starts_with($code, '512')) {
            return '512';
        }
        if (str_starts_with($code, '706')) {
            return '706';
        }

        return '';
    }
}

if (!function_exists('sl_manual_is_allowed_pair')) {
    function sl_manual_is_allowed_pair(string $debitFamily, string $creditFamily, string $debitCode, string $creditCode): bool
    {
        if ($debitCode === '' || $creditCode === '') {
            return false;
        }

        if ($debitCode === $creditCode) {
            return false;
        }

        $validFamilies = ['411', '512', '706'];

        return in_array($debitFamily, $validFamilies, true)
            && in_array($creditFamily, $validFamilies, true);
    }
}

if (!function_exists('sl_manual_needs_client')) {
    function sl_manual_needs_client(string $debitFamily, string $creditFamily): bool
    {
        return $debitFamily === '411' || $creditFamily === '411';
    }
}

if (!function_exists('sl_manual_build_account_options')) {
    function sl_manual_build_account_options(array $clientAccounts, array $treasuryAccounts, array $serviceAccounts): array
    {
        $options = [];

        foreach ($clientAccounts as $row) {
            $code = trim((string)($row['account_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $options[] = [
                'family' => '411',
                'account_code' => $code,
                'account_label' => trim((string)($row['account_label'] ?? '')),
                'client_id' => (int)($row['client_id'] ?? 0),
                'display' => $code . ' - ' . trim((string)($row['account_label'] ?? '')),
            ];
        }

        foreach ($treasuryAccounts as $row) {
            $code = trim((string)($row['account_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $options[] = [
                'family' => '512',
                'account_code' => $code,
                'account_label' => trim((string)($row['account_label'] ?? '')),
                'client_id' => 0,
                'display' => $code . ' - ' . trim((string)($row['account_label'] ?? '')),
            ];
        }

        foreach ($serviceAccounts as $row) {
            $code = trim((string)($row['account_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $options[] = [
                'family' => '706',
                'account_code' => $code,
                'account_label' => trim((string)($row['account_label'] ?? '')),
                'client_id' => 0,
                'display' => $code . ' - ' . trim((string)($row['account_label'] ?? '')),
            ];
        }

        usort($options, function (array $a, array $b): int {
            return strcmp((string)$a['account_code'], (string)$b['account_code']);
        });

        return $options;
    }
}

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT
            c.id,
            c.client_code,
            c.full_name,
            c.generated_client_account,
            COALESCE(c.is_active, 1) AS is_active
        FROM clients c
        WHERE COALESCE(c.generated_client_account, '') <> ''
        ORDER BY c.client_code ASC
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
        SELECT
            rs.id,
            rs.code,
            rs.label,
            rs.operation_type_id,
            rot.code AS operation_type_code,
            rot.label AS operation_type_label
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
        WHERE COALESCE(rs.is_active,1)=1
        ORDER BY rs.label ASC
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

$clientAccounts = [];
foreach ($clients as $client) {
    $accountCode = trim((string)($client['generated_client_account'] ?? ''));
    if ($accountCode === '') {
        continue;
    }

    $clientAccounts[] = [
        'client_id' => (int)($client['id'] ?? 0),
        'account_code' => $accountCode,
        'account_label' => trim((string)($client['client_code'] ?? '') . ' - ' . (string)($client['full_name'] ?? '')),
    ];
}

$allAccountOptions = sl_manual_build_account_options($clientAccounts, $treasuryAccounts, $serviceAccounts);

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
        $sourceAccountCode = trim($formData['source_account_code']);
        $destinationAccountCode = trim($formData['destination_account_code']);
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

        if ($sourceAccountCode === '' || $destinationAccountCode === '') {
            throw new RuntimeException('Compte débité et compte crédité obligatoires.');
        }

        if ($sourceAccountCode === $destinationAccountCode) {
            throw new RuntimeException('Le compte débité et le compte crédité doivent être différents.');
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

        $debitFamily = sl_manual_account_family($sourceAccountCode);
        $creditFamily = sl_manual_account_family($destinationAccountCode);

        if ($debitFamily === '' || $creditFamily === '') {
            throw new RuntimeException('Les comptes choisis doivent appartenir aux familles 411, 512 ou 706.');
        }

        if (!sl_manual_is_allowed_pair($debitFamily, $creditFamily, $sourceAccountCode, $destinationAccountCode)) {
            throw new RuntimeException('Combinaison non autorisée ou même compte en débit et crédit.');
        }

        $needsClient = sl_manual_needs_client($debitFamily, $creditFamily);
        if ($needsClient && (!$clientId || $clientId <= 0)) {
            throw new RuntimeException('Le client est obligatoire dès qu’un compte 411 est impliqué.');
        }

        $selectedClient = $clientId ? sl_manual_find_by_id($clients, $clientId) : null;

        if ($needsClient) {
            if (!$selectedClient) {
                throw new RuntimeException('Client introuvable.');
            }

            sl_assert_client_operation_allowed($pdo, (int)$clientId);
        }

        $resolvedDebitAccount = null;
        $resolvedCreditAccount = null;

        if ($debitFamily === '512') {
            $resolvedDebitAccount = sl_manual_find_by_code($treasuryAccounts, $sourceAccountCode);
        } elseif ($debitFamily === '706') {
            $resolvedDebitAccount = sl_manual_find_by_code($serviceAccounts, $sourceAccountCode);
        } else {
            $resolvedDebitAccount = sl_manual_find_by_code($clientAccounts, $sourceAccountCode);
        }

        if (!$resolvedDebitAccount) {
            throw new RuntimeException('Compte débité introuvable ou inactif.');
        }

        if ($creditFamily === '512') {
            $resolvedCreditAccount = sl_manual_find_by_code($treasuryAccounts, $destinationAccountCode);
        } elseif ($creditFamily === '706') {
            $resolvedCreditAccount = sl_manual_find_by_code($serviceAccounts, $destinationAccountCode);
        } else {
            $resolvedCreditAccount = sl_manual_find_by_code($clientAccounts, $destinationAccountCode);
        }

        if (!$resolvedCreditAccount) {
            throw new RuntimeException('Compte crédité introuvable ou inactif.');
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
            'label' => $label !== ''
                ? $label
                : trim(
                    (string)($selectedType['label'] ?? '')
                    . ' - '
                    . (string)($selectedService['label'] ?? '')
                    . ' - '
                    . $sourceAccountCode
                    . ' → '
                    . $destinationAccountCode
                ),
            'notes' => $notes !== '' ? $notes : 'Opération manuelle inter-comptes',
            'source_type' => 'manual',
            'operation_kind' => 'manual',
            'flow_type' => ($debitFamily === $creditFamily) ? 'internal_transfer' : 'cross_transfer',
            'source_treasury_code' => '',
            'target_treasury_code' => '',
            'manual_debit_account_code' => $sourceAccountCode,
            'manual_credit_account_code' => $destinationAccountCode,
        ];

        $preview = resolveAccountingOperationV2($pdo, $payload);

        if (($preview['debit_account_code'] ?? '') !== $sourceAccountCode) {
            throw new RuntimeException('La résolution comptable ne reprend pas le compte débité manuel attendu.');
        }

        if (($preview['credit_account_code'] ?? '') !== $destinationAccountCode) {
            throw new RuntimeException('La résolution comptable ne reprend pas le compte crédité manuel attendu.');
        }

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $operationId = createOperationWithAccountingV2($pdo, $payload);

sl_audit_and_notify_action(
    $pdo,
    (int)$_SESSION['user_id'],
    'create_manual_operation',
    'manual_actions',
    'operation',
    $operationId,
    [
        'debit' => $sourceAccountCode,
        'credit' => $destinationAccountCode,
        'amount' => $amount,
        'client_id' => $clientId,
        'type' => $typeCode,
        'service' => $serviceCode,
        'flow_type' => $payload['flow_type']
    ],
    'manual_operation_created',
    'Nouvelle opération manuelle : ' . $sourceAccountCode . ' → ' . $destinationAccountCode,
    'success',
    APP_URL . 'modules/operations/operation_view.php?id=' . $operationId
);
            $pdo->commit();

            header('Location: ' . APP_URL . 'modules/operations/operation_view.php?id=' . $operationId);
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Opération manuelle';
$pageSubtitle = 'Création contrôlée d’un mouvement manuel entre comptes 411, 512 et 706.';

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

        <div class="sl-kpi-grid" style="margin-bottom:20px;">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Familles gérées</div>
                <div class="sl-kpi-card__value">3</div>
                <div class="sl-kpi-card__meta"><span>411 / 512 / 706</span><strong>Complètes</strong></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Comptes 512</div>
                <div class="sl-kpi-card__value"><?= count($treasuryAccounts) ?></div>
                <div class="sl-kpi-card__meta"><span>Trésorerie active</span><strong>Disponibles</strong></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Comptes 706</div>
                <div class="sl-kpi-card__value"><?= count($serviceAccounts) ?></div>
                <div class="sl-kpi-card__meta"><span>Services actifs</span><strong>Disponibles</strong></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Comptes 411</div>
                <div class="sl-kpi-card__value"><?= count($clientAccounts) ?></div>
                <div class="sl-kpi-card__meta"><span>Clients générés</span><strong>Disponibles</strong></div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Saisie de l’opération</h3>
                <p class="muted" style="margin-bottom:18px;">
                    Débit et crédit sont imposés manuellement, avec contrôles stricts et traçabilité.
                </p>

                <div class="dashboard-note" style="margin-bottom:18px;">
                    Flux autorisés :
                    <strong>411↔411</strong>,
                    <strong>411↔512</strong>,
                    <strong>411↔706</strong>,
                    <strong>512↔512</strong>,
                    <strong>512↔706</strong>,
                    <strong>706↔706</strong>.
                    <br>
                    <strong>Interdit :</strong> utiliser exactement le même compte en débit et en crédit.
                    <br>
                    <strong>Règle client :</strong> dès qu’un compte 411 est impliqué, le client est obligatoire.
                </div>

                <form method="POST" id="manualOperationForm">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Client</label>
                            <select name="client_id" id="manual_client_id">
                                <option value="">Choisir</option>
                                <?php foreach ($clients as $client): ?>
                                    <option
                                        value="<?= (int)$client['id'] ?>"
                                        data-client-account="<?= e((string)($client['generated_client_account'] ?? '')) ?>"
                                        <?= $formData['client_id'] == $client['id'] ? 'selected' : '' ?>
                                    >
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
                                        <?= e((string)($type['label'] ?? '')) ?>
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
                                        <?= e((string)($service['label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e($formData['operation_date']) ?>" required>
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" min="0.01" name="amount" value="<?= e($formData['amount']) ?>" required>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= e($formData['reference']) ?>" placeholder="Ex: REGUL-2026-001">
                        </div>

                        <div>
                            <label>Compte débité</label>
                            <select name="source_account_code" id="manual_debit_account" required>
                                <option value="">Choisir</option>
                                <?php foreach ($allAccountOptions as $option): ?>
                                    <option
                                        value="<?= e((string)$option['account_code']) ?>"
                                        data-family="<?= e((string)$option['family']) ?>"
                                        <?= $formData['source_account_code'] === (string)$option['account_code'] ? 'selected' : '' ?>
                                    >
                                        <?= e((string)$option['display']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte crédité</label>
                            <select name="destination_account_code" id="manual_credit_account" required>
                                <option value="">Choisir</option>
                                <?php foreach ($allAccountOptions as $option): ?>
                                    <option
                                        value="<?= e((string)$option['account_code']) ?>"
                                        data-family="<?= e((string)$option['family']) ?>"
                                        <?= $formData['destination_account_code'] === (string)$option['account_code'] ? 'selected' : '' ?>
                                    >
                                        <?= e((string)$option['display']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($formData['label']) ?>" placeholder="Libellé libre ou automatique si vide">
                    </div>

                    <div style="margin-top:16px;">
                        <label>Motif / Notes</label>
                        <textarea name="notes" rows="5" placeholder="Précise la logique métier ou la régularisation réalisée"><?= e($formData['notes']) ?></textarea>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">Aperçu comptable</h3>

                <?php if ($preview): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Compte débité</span>
                            <strong><?= e((string)($preview['debit_account_code'] ?? '—')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Compte crédité</span>
                            <strong><?= e((string)($preview['credit_account_code'] ?? '—')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Famille débit</span>
                            <strong><?= e(sl_manual_account_family((string)($preview['debit_account_code'] ?? ''))) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Famille crédit</span>
                            <strong><?= e(sl_manual_account_family((string)($preview['credit_account_code'] ?? ''))) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Type de flux</span>
                            <strong><?= e(($payload['flow_type'] ?? '') === 'internal_transfer' ? 'Interne' : 'Croisé') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Analytique</span>
                            <strong><?= e((string)($preview['analytic_account']['account_code'] ?? '—')) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Hash anti-doublon</span>
                            <strong style="word-break:break-all;"><?= e((string)($preview['operation_hash'] ?? '—')) ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($preview['preview_lines']) && is_array($preview['preview_lines'])): ?>
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
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        Prévisualise d’abord pour vérifier strictement le couple débit / crédit, le type de flux et la cohérence avec la famille 411 / 512 / 706.
                    </div>
                <?php endif; ?>

                <div class="dashboard-note" style="margin-top:18px;">
                    <strong>Historisation :</strong> toute création manuelle génère un log détaillé et une notification.
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientSelect = document.getElementById('manual_client_id');
            const debitSelect = document.getElementById('manual_debit_account');
            const creditSelect = document.getElementById('manual_credit_account');

            function getSelectedFamily(select) {
                if (!select) return '';
                const option = select.options[select.selectedIndex];
                return option ? (option.getAttribute('data-family') || '') : '';
            }

            function getSelectedClientAccount() {
                if (!clientSelect) return '';
                const option = clientSelect.options[clientSelect.selectedIndex];
                return option ? (option.getAttribute('data-client-account') || '') : '';
            }

            function updateClientRequirement() {
                const debitFamily = getSelectedFamily(debitSelect);
                const creditFamily = getSelectedFamily(creditSelect);
                const needsClient = debitFamily === '411' || creditFamily === '411';

                if (clientSelect) {
                    clientSelect.required = needsClient;
                }
            }

            function sync411Visibility() {
                const client411 = getSelectedClientAccount();
                const debitFamily = getSelectedFamily(debitSelect);
                const creditFamily = getSelectedFamily(creditSelect);
                const needsClient = debitFamily === '411' || creditFamily === '411';

                [debitSelect, creditSelect].forEach(function (select) {
                    if (!select) return;

                    Array.from(select.options).forEach(function (option) {
                        const family = option.getAttribute('data-family') || '';
                        if (family !== '411' || option.value === '') {
                            option.hidden = false;
                            return;
                        }

                        option.hidden = needsClient && client411 !== '' && option.value !== client411;
                    });
                });
            }

            function validateSameAccount() {
                if (!debitSelect || !creditSelect) return;

                if (debitSelect.value !== '' && creditSelect.value !== '' && debitSelect.value === creditSelect.value) {
                    alert('Le même compte ne peut pas être utilisé à la fois en débit et en crédit.');
                }
            }

            function refreshRules() {
                updateClientRequirement();
                sync411Visibility();
                validateSameAccount();
            }

            if (clientSelect) {
                clientSelect.addEventListener('change', refreshRules);
            }

            if (debitSelect) {
                debitSelect.addEventListener('change', refreshRules);
            }

            if (creditSelect) {
                creditSelect.addEventListener('change', refreshRules);
            }

            refreshRules();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>