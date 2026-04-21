<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (!function_exists('sl_manual_assert_account_is_active')) {
    throw new RuntimeException("Le helper sl_manual_assert_account_is_active() est introuvable.");
}
if (!function_exists('sl_manual_get_account_current_balance')) {
    throw new RuntimeException("Le helper sl_manual_get_account_current_balance() est introuvable.");
}
if (!function_exists('sl_manual_execute_operation')) {
    throw new RuntimeException("Le helper sl_manual_execute_operation() est introuvable.");
}

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'manual_actions_create');
}

if (!function_exists('sl_manual_generate_reference')) {
    function sl_manual_generate_reference(): string
    {
        return 'MAN-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists('sl_manual_find_account_label')) {
    function sl_manual_find_account_label(array $accountsByFamily, string $accountCode): string
    {
        foreach ($accountsByFamily as $familyAccounts) {
            foreach ($familyAccounts as $account) {
                if ((string)($account['account_code'] ?? '') === $accountCode) {
                    return (string)($account['account_label'] ?? '');
                }
            }
        }

        return '';
    }
}

if (!function_exists('sl_manual_build_preview')) {
    function sl_manual_build_preview(PDO $pdo, array $formData, array $accountsByFamily): array
    {
        $sourceAccountCode = trim((string)($formData['source_account_code'] ?? ''));
        $destinationAccountCode = trim((string)($formData['destination_account_code'] ?? ''));
        $amount = (float)str_replace(',', '.', (string)($formData['amount'] ?? '0'));
        $operationDate = trim((string)($formData['operation_date'] ?? ''));
        $operationType = trim((string)($formData['operation_type'] ?? ''));
        $serviceType = trim((string)($formData['service_type'] ?? ''));
        $reference = trim((string)($formData['reference'] ?? ''));
        $label = trim((string)($formData['label'] ?? ''));
        $notes = trim((string)($formData['notes'] ?? ''));

        if ($sourceAccountCode === '' || $destinationAccountCode === '') {
            throw new RuntimeException('Le compte débité et le compte crédité sont obligatoires.');
        }

        if ($sourceAccountCode === $destinationAccountCode) {
            throw new RuntimeException('Le compte débité et le compte crédité ne peuvent pas être identiques.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $operationDate)) {
            throw new RuntimeException('Date invalide.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Le montant doit être strictement supérieur à 0.');
        }

        if ($operationType === '') {
            throw new RuntimeException('Le type d’opération est obligatoire.');
        }

        if ($serviceType === '') {
            throw new RuntimeException('Le type de service est obligatoire.');
        }

        sl_manual_assert_account_is_active($pdo, $sourceAccountCode);
        sl_manual_assert_account_is_active($pdo, $destinationAccountCode);

        $sourceData = sl_manual_get_account_current_balance($pdo, $sourceAccountCode);
        $destinationData = sl_manual_get_account_current_balance($pdo, $destinationAccountCode);

        $sourceBalance = (float)($sourceData['balance'] ?? 0);
        $sourceType = (string)($sourceData['type'] ?? '');
        $destinationType = (string)($destinationData['type'] ?? '');

        $executableAmount = $amount;
        $pendingAmount = 0.0;

        if ($sourceBalance < $amount) {
            if ($sourceType === '411') {
                $executableAmount = max(0, round($sourceBalance, 2));
                $pendingAmount = round($amount - $executableAmount, 2);
            } else {
                throw new RuntimeException('Solde insuffisant sur le compte débité ' . $sourceAccountCode . '.');
            }
        }

        if ($label === '') {
            $label = trim($operationType . ' - ' . $serviceType . ' - ' . $sourceAccountCode . ' -> ' . $destinationAccountCode);
        }

        return [
            'source_account_code' => $sourceAccountCode,
            'source_account_label' => sl_manual_find_account_label($accountsByFamily, $sourceAccountCode),
            'destination_account_code' => $destinationAccountCode,
            'destination_account_label' => sl_manual_find_account_label($accountsByFamily, $destinationAccountCode),
            'source_type' => $sourceType,
            'destination_type' => $destinationType,
            'requested_amount' => $amount,
            'executable_amount' => $executableAmount,
            'pending_amount' => $pendingAmount,
            'source_balance' => $sourceBalance,
            'operation_date' => $operationDate,
            'operation_type' => $operationType,
            'service_type' => $serviceType,
            'reference' => $reference,
            'label' => $label,
            'notes' => $notes,
        ];
    }
}

$accounts411 = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT
            c.id AS client_id,
            c.generated_client_account AS account_code,
            CONCAT(c.client_code, ' - ', c.full_name) AS account_label
        FROM clients c
        WHERE COALESCE(c.is_active, 1) = 1
          AND COALESCE(c.generated_client_account, '') <> ''
        ORDER BY c.client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$accounts512 = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("
        SELECT
            0 AS client_id,
            ta.account_code,
            ta.account_label
        FROM treasury_accounts ta
        WHERE COALESCE(ta.is_active, 1) = 1
        ORDER BY ta.account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$accounts706 = tableExists($pdo, 'service_accounts')
    ? $pdo->query("
        SELECT
            0 AS client_id,
            sa.account_code,
            sa.account_label
        FROM service_accounts sa
        WHERE COALESCE(sa.is_active, 1) = 1
        ORDER BY sa.account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$accountsByFamily = [
    '411' => $accounts411,
    '512' => $accounts512,
    '706' => $accounts706,
];

$errorMessage = '';
$successMessage = '';
$preview = null;
$lastExecution = null;

$formData = [
    'debit_family' => '411',
    'credit_family' => '512',
    'source_account_code' => '',
    'destination_account_code' => '',
    'operation_date' => date('Y-m-d'),
    'amount' => '',
    'operation_type' => '',
    'service_type' => '',
    'reference' => sl_manual_generate_reference(),
    'label' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'debit_family' => trim((string)($_POST['debit_family'] ?? '411')),
        'credit_family' => trim((string)($_POST['credit_family'] ?? '512')),
        'source_account_code' => trim((string)($_POST['source_account_code'] ?? '')),
        'destination_account_code' => trim((string)($_POST['destination_account_code'] ?? '')),
        'operation_date' => trim((string)($_POST['operation_date'] ?? date('Y-m-d'))),
        'amount' => trim((string)($_POST['amount'] ?? '')),
        'operation_type' => trim((string)($_POST['operation_type'] ?? '')),
        'service_type' => trim((string)($_POST['service_type'] ?? '')),
        'reference' => trim((string)($_POST['reference'] ?? sl_manual_generate_reference())),
        'label' => trim((string)($_POST['label'] ?? '')),
        'notes' => trim((string)($_POST['notes'] ?? '')),
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $mode = trim((string)($_POST['mode'] ?? 'preview'));

        $debitFamily = $formData['debit_family'];
        $creditFamily = $formData['credit_family'];

        if (!in_array($debitFamily, ['411', '512', '706'], true)) {
            throw new RuntimeException('Type de compte débité invalide.');
        }

        if (!in_array($creditFamily, ['411', '512', '706'], true)) {
            throw new RuntimeException('Type de compte crédité invalide.');
        }

        $preview = sl_manual_build_preview($pdo, $formData, $accountsByFamily);

        $formData['reference'] = (string)$preview['reference'];
        $formData['label'] = (string)$preview['label'];

        if ($mode === 'save') {
            $payload = [
                'source_account_code' => (string)$preview['source_account_code'],
                'destination_account_code' => (string)$preview['destination_account_code'],
                'amount' => (float)$preview['requested_amount'],
                'operation_date' => (string)$preview['operation_date'],
                'label' => (string)$preview['label'],
                'reference' => (string)$preview['reference'],
                'operation_type' => (string)$preview['operation_type'],
                'service_type' => (string)$preview['service_type'],
                'notes' => (string)$preview['notes'],
            ];

            $pdo->beginTransaction();

            $lastExecution = sl_manual_execute_operation($pdo, $payload);

            if (!is_array($lastExecution)) {
                throw new RuntimeException("Le helper sl_manual_execute_operation() a retourné un format invalide.");
            }

            $status = (string)($lastExecution['status'] ?? '');
            $message = trim((string)($lastExecution['message'] ?? ''));

            if ($status !== 'success') {
                throw new RuntimeException(
                    $message !== '' ? $message : "L'enregistrement de l'opération a échoué."
                );
            }

            $pdo->commit();

            $operationId = (int)($lastExecution['operation_id'] ?? 0);
            $pendingDebitId = (int)($lastExecution['pending_debit_id'] ?? 0);
            $executedAmount = (float)($lastExecution['executable_amount'] ?? 0);
            $pendingAmount = (float)($lastExecution['pending_amount'] ?? 0);

            if ($operationId > 0 && $pendingAmount > 0) {
                $successMessage = 'Opération enregistrée avec succès. Montant exécuté : '
                    . number_format($executedAmount, 2, ',', ' ')
                    . ' EUR. Reliquat enregistré en débit dû : '
                    . number_format($pendingAmount, 2, ',', ' ')
                    . ' EUR.';
            } elseif ($operationId > 0) {
                $successMessage = $message !== ''
                    ? $message
                    : 'Opération manuelle enregistrée avec succès.';
            } elseif ($pendingDebitId > 0) {
                $successMessage = $message !== ''
                    ? $message
                    : 'Aucun montant exécutable. Un débit dû a été créé avec succès.';
            } else {
                $successMessage = $message !== ''
                    ? $message
                    : 'Traitement terminé avec succès.';
            }

            if ($operationId > 0) {
                header('Location: ' . APP_URL . 'modules/operations/operation_view.php?id=' . $operationId . '&success=1');
                exit;
            }

            $formData = [
                'debit_family' => '411',
                'credit_family' => '512',
                'source_account_code' => '',
                'destination_account_code' => '',
                'operation_date' => date('Y-m-d'),
                'amount' => '',
                'operation_type' => '',
                'service_type' => '',
                'reference' => sl_manual_generate_reference(),
                'label' => '',
                'notes' => '',
            ];
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
$pageSubtitle = 'Création moderne, guidée et sécurisée d’une opération manuelle entre comptes 411, 512 et 706.';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<style>
.manual-family-card {
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px;
    transition: all 0.2s ease;
}

.manual-family-card--debit {
    border-color: #dc2626;
    box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.08);
}

.manual-family-card--credit {
    border-color: #16a34a;
    box-shadow: 0 0 0 1px rgba(22, 163, 74, 0.08);
}

.manual-family-options {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.manual-family-option {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 78px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}

.manual-family-option input[type="radio"] {
    margin: 0;
    transform: scale(1.15);
    accent-color: #2563eb;
}

.manual-family-option__code {
    font-weight: 700;
    line-height: 1;
    margin: 0;
}

.manual-family-option--debit {
    border: 2px solid #dc2626;
    color: #991b1b;
    background: #fff5f5;
}

.manual-family-option--debit input[type="radio"] {
    accent-color: #dc2626;
}

.manual-family-option--debit:hover {
    background: #fee2e2;
}

.manual-family-option--credit {
    border: 2px solid #16a34a;
    color: #166534;
    background: #f0fdf4;
}

.manual-family-option--credit input[type="radio"] {
    accent-color: #16a34a;
}

.manual-family-option--credit:hover {
    background: #dcfce7;
}
</style>

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

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Mode</div>
                <div class="sl-kpi-card__value">MANUEL</div>
                <div class="sl-kpi-card__meta">
                    <span>Débit / Crédit</span>
                    <strong>Guidés</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Comptes 411</div>
                <div class="sl-kpi-card__value"><?= count($accountsByFamily['411']) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Clients actifs</span>
                    <strong>Disponibles</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Comptes 512</div>
                <div class="sl-kpi-card__value"><?= count($accountsByFamily['512']) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Trésorerie</span>
                    <strong>Disponible</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Comptes 706</div>
                <div class="sl-kpi-card__value"><?= count($accountsByFamily['706']) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Services</span>
                    <strong>Disponibles</strong>
                </div>
            </div>
        </section>

        <div class="dashboard-grid-2">
            <section class="form-card">
                <div class="sl-card-head">
                    <div>
                        <h3 class="section-title">Saisie de l’opération</h3>
                        <p class="sl-card-head-subtitle">
                            Choisis les familles de comptes, puis les comptes exacts. La page filtre automatiquement les options disponibles.
                        </p>
                    </div>
                </div>

                <div class="dashboard-note" style="margin-bottom:18px;">
                    Règles métier :
                    <strong>débit ≠ crédit</strong>,
                    comptes archivés interdits,
                    insuffisance de solde sur <strong>411</strong> = création d’un débit dû pour le reliquat.
                </div>

                <form method="POST" id="manualOperationForm">
                    <?= csrf_input() ?>

                    <div class="dashboard-grid-2">
                        <div class="manual-family-card manual-family-card--debit">
                            <h4 class="section-title" style="margin-bottom:14px;">Compte débité</h4>

                            <div class="manual-family-options">
                                <label class="manual-family-option manual-family-option--debit">
                                    <input type="radio" name="debit_family" value="411" <?= $formData['debit_family'] === '411' ? 'checked' : '' ?>>
                                    <span class="manual-family-option__code">411</span>
                                </label>
                                <label class="manual-family-option manual-family-option--debit">
                                    <input type="radio" name="debit_family" value="512" <?= $formData['debit_family'] === '512' ? 'checked' : '' ?>>
                                    <span class="manual-family-option__code">512</span>
                                </label>
                                <label class="manual-family-option manual-family-option--debit">
                                    <input type="radio" name="debit_family" value="706" <?= $formData['debit_family'] === '706' ? 'checked' : '' ?>>
                                    <span class="manual-family-option__code">706</span>
                                </label>
                            </div>

                            <label>Compte débité</label>
                            <select name="source_account_code" id="source_account_code" required>
                                <option value="">Choisir</option>
                                <?php foreach (['411', '512', '706'] as $family): ?>
                                    <?php foreach ($accountsByFamily[$family] as $account): ?>
                                        <option
                                            data-family="<?= e($family) ?>"
                                            value="<?= e((string)$account['account_code']) ?>"
                                            <?= $formData['source_account_code'] === (string)$account['account_code'] ? 'selected' : '' ?>
                                        >
                                            <?= e((string)$account['account_code'] . ' - ' . (string)$account['account_label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="manual-family-card manual-family-card--credit">
                            <h4 class="section-title" style="margin-bottom:14px;">Compte crédité</h4>

                            <div class="manual-family-options">
                                <label class="manual-family-option manual-family-option--credit">
                                    <input type="radio" name="credit_family" value="411" <?= $formData['credit_family'] === '411' ? 'checked' : '' ?>>
                                    <span class="manual-family-option__code">411</span>
                                </label>
                                <label class="manual-family-option manual-family-option--credit">
                                    <input type="radio" name="credit_family" value="512" <?= $formData['credit_family'] === '512' ? 'checked' : '' ?>>
                                    <span class="manual-family-option__code">512</span>
                                </label>
                                <label class="manual-family-option manual-family-option--credit">
                                    <input type="radio" name="credit_family" value="706" <?= $formData['credit_family'] === '706' ? 'checked' : '' ?>>
                                    <span class="manual-family-option__code">706</span>
                                </label>
                            </div>

                            <label>Compte crédité</label>
                            <select name="destination_account_code" id="destination_account_code" required>
                                <option value="">Choisir</option>
                                <?php foreach (['411', '512', '706'] as $family): ?>
                                    <?php foreach ($accountsByFamily[$family] as $account): ?>
                                        <option
                                            data-family="<?= e($family) ?>"
                                            value="<?= e((string)$account['account_code']) ?>"
                                            <?= $formData['destination_account_code'] === (string)$account['account_code'] ? 'selected' : '' ?>
                                        >
                                            <?= e((string)$account['account_code'] . ' - ' . (string)$account['account_label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="dashboard-grid-2" style="margin-top:20px;">
                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= e($formData['operation_date']) ?>" required>
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" min="0.01" name="amount" value="<?= e($formData['amount']) ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <input type="text" name="operation_type" value="<?= e($formData['operation_type']) ?>" placeholder="Ex: Régularisation" required>
                        </div>

                        <div>
                            <label>Type de service</label>
                            <input type="text" name="service_type" value="<?= e($formData['service_type']) ?>" placeholder="Ex: Ajustement comptable" required>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= e($formData['reference']) ?>" required>
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= e($formData['label']) ?>" placeholder="Libellé généré ou personnalisé">
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <label>Note / Commentaire</label>
                        <textarea name="notes" rows="5" placeholder="Motif, contexte ou explication"><?= e($formData['notes']) ?></textarea>
                    </div>

                    <div class="btn-group" style="margin-top:22px;">
                        <button type="submit" name="mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="mode" value="save" class="btn btn-success">Valider et enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </section>

            <section class="card">
                <div class="sl-card-head">
                    <div>
                        <h3 class="section-title">Prévisualisation</h3>
                        <p class="sl-card-head-subtitle">Aperçu de l’écriture avant validation finale</p>
                    </div>
                </div>

                <?php if ($preview): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Date</span>
                            <strong><?= e((string)$preview['operation_date']) ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Solde actuel du compte débité</span>
                            <strong><?= e(number_format((float)$preview['source_balance'], 2, ',', ' ')) ?> EUR</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Montant demandé</span>
                            <strong><?= e(number_format((float)$preview['requested_amount'], 2, ',', ' ')) ?> EUR</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Montant exécutable</span>
                            <strong><?= e(number_format((float)$preview['executable_amount'], 2, ',', ' ')) ?> EUR</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Débit dû généré</span>
                            <strong><?= e(number_format((float)$preview['pending_amount'], 2, ',', ' ')) ?> EUR</strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Compte débité</span>
                            <strong>
                                <?= e((string)$preview['source_account_code']) ?>
                                —
                                <?= e((string)$preview['source_account_label']) ?>
                                —
                                Famille <?= e((string)$preview['source_type']) ?>
                            </strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Compte crédité</span>
                            <strong>
                                <?= e((string)$preview['destination_account_code']) ?>
                                —
                                <?= e((string)$preview['destination_account_label']) ?>
                                —
                                Famille <?= e((string)$preview['destination_type']) ?>
                            </strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Type d’opération</span>
                            <strong><?= e((string)$preview['operation_type']) ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Type de service</span>
                            <strong><?= e((string)$preview['service_type']) ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Référence</span>
                            <strong><?= e((string)$preview['reference']) ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Libellé</span>
                            <strong><?= e((string)$preview['label']) ?></strong>
                        </div>

                        <div class="sl-data-list__row">
                            <span>Note</span>
                            <strong><?= e((string)$preview['notes']) ?></strong>
                        </div>
                    </div>

                    <?php if ((float)$preview['pending_amount'] > 0): ?>
                        <div class="warning" style="margin-top:16px;">
                            Le compte débité est insuffisant. La partie non exécutable sera enregistrée en débit dû.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="dashboard-note">
                        La prévisualisation affichera l’opération exacte, les comptes retenus, le solde disponible et l’éventuel reliquat transformé en débit dû.
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function getCheckedValue(name) {
        const input = document.querySelector('input[name="' + name + '"]:checked');
        return input ? input.value : '';
    }

    function filterAccountsByFamily(selectId, familyRadioName) {
        const select = document.getElementById(selectId);
        const family = getCheckedValue(familyRadioName);

        if (!select) return;

        Array.from(select.options).forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const visible = option.getAttribute('data-family') === family;
            option.hidden = !visible;
            option.disabled = !visible;
        });

        const current = select.options[select.selectedIndex];
        if (current && (current.hidden || current.disabled)) {
            select.value = '';
        }
    }

    function excludeSameAccount() {
        const sourceSelect = document.getElementById('source_account_code');
        const destinationSelect = document.getElementById('destination_account_code');

        if (!sourceSelect || !destinationSelect) return;

        const sourceValue = sourceSelect.value;

        Array.from(destinationSelect.options).forEach(function (option, index) {
            if (index === 0) return;

            if (!option.hidden) {
                option.disabled = option.value !== '' && option.value === sourceValue;
            }
        });

        if (destinationSelect.value !== '' && destinationSelect.value === sourceValue) {
            destinationSelect.value = '';
        }
    }

    function refreshUi() {
        filterAccountsByFamily('source_account_code', 'debit_family');
        filterAccountsByFamily('destination_account_code', 'credit_family');
        excludeSameAccount();
    }

    document.querySelectorAll('input[name="debit_family"]').forEach(function (radio) {
        radio.addEventListener('change', refreshUi);
    });

    document.querySelectorAll('input[name="credit_family"]').forEach(function (radio) {
        radio.addEventListener('change', refreshUi);
    });

    const sourceSelect = document.getElementById('source_account_code');
    const destinationSelect = document.getElementById('destination_account_code');

    if (sourceSelect) {
        sourceSelect.addEventListener('change', refreshUi);
    }

    if (destinationSelect) {
        destinationSelect.addEventListener('change', excludeSameAccount);
    }

    refreshUi();
});
</script>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>