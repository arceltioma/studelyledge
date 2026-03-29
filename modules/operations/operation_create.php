<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'operations_create');

function opOld(string $key, mixed $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
}

function opFindById(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int)($row['id'] ?? 0) === $id) {
            return $row;
        }
    }
    return null;
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
            rot.is_active AS operation_type_active
        FROM ref_services rs
        LEFT JOIN ref_operation_types rot ON rot.id = rs.operation_type_id
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
            c.country_destination,
            c.country_commercial,
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
        WHERE COALESCE(is_active,1) = 1
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

$successMessage = '';
$errorMessage = '';
$preview = null;
$previewService706Label = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $operationDate = trim((string)($_POST['operation_date'] ?? date('Y-m-d')));
        $operationTypeId = (int)($_POST['operation_type_id'] ?? 0);
        $serviceId = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
        $clientId = ($_POST['client_id'] ?? '') !== '' ? (int)$_POST['client_id'] : null;
        $sourceTreasuryId = ($_POST['source_treasury_account_id'] ?? '') !== '' ? (int)$_POST['source_treasury_account_id'] : null;
        $targetTreasuryId = ($_POST['target_treasury_account_id'] ?? '') !== '' ? (int)$_POST['target_treasury_account_id'] : null;
        $amount = (float)($_POST['amount'] ?? 0);
        $reference = trim((string)($_POST['reference'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($operationTypeId <= 0) {
            throw new RuntimeException('Type d’opération obligatoire.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        if ($operationDate === '') {
            throw new RuntimeException('Date obligatoire.');
        }

        $selectedType = opFindById($operationTypes, $operationTypeId);
        if (!$selectedType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        if ((int)($selectedType['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Le type d’opération sélectionné est archivé.');
        }

        $typeCode = (string)$selectedType['code'];
        $selectedService = null;
        if ($serviceId !== null) {
            $selectedService = opFindById($services, $serviceId);
            if (!$selectedService) {
                throw new RuntimeException('Service introuvable.');
            }

            if ((int)($selectedService['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le service sélectionné est archivé.');
            }

            if ((int)($selectedService['operation_type_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le type parent du service est archivé.');
            }
        }

        $selectedClient = null;
        if ($clientId !== null) {
            $selectedClient = opFindById($clients, $clientId);
            if (!$selectedClient) {
                throw new RuntimeException('Client introuvable.');
            }

            if ((int)($selectedClient['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Le client sélectionné est archivé.');
            }
        }

        $selectedSourceTreasury = null;
        if ($sourceTreasuryId !== null) {
            $selectedSourceTreasury = opFindById($treasuryAccounts, $sourceTreasuryId);
            if (!$selectedSourceTreasury) {
                throw new RuntimeException('Compte interne source introuvable.');
            }
        }

        $selectedTargetTreasury = null;
        if ($targetTreasuryId !== null) {
            $selectedTargetTreasury = opFindById($treasuryAccounts, $targetTreasuryId);
            if (!$selectedTargetTreasury) {
                throw new RuntimeException('Compte interne cible introuvable.');
            }
        }

        $isInternalTransfer = ($typeCode === 'VIREMENT_INTERNE');

        $serviceBasedTypes = [
            'REGULARISATION_POSITIVE',
            'REGULARISATION_NEGATIVE',
            'FRAIS_DE_SERVICE',
            'FRAIS_SERVICE',
            'FRAIS_BANCAIRES',
            'AUTRES_FRAIS',
            'CA_PLACEMENT',
            'CA_DIVERS',
            'CA_DEBOURS_LOGEMENT',
            'CA_DEBOURS_ASSURANCE',
            'CA_COURTAGE_PRET',
            'FRAIS_DEBOURS_MICROFINANCE',
        ];

        if (!$isInternalTransfer && !$selectedClient) {
            throw new RuntimeException('Le client est obligatoire pour cette opération.');
        }

        if (in_array($typeCode, $serviceBasedTypes, true) && !$selectedService) {
            throw new RuntimeException('Le service est obligatoire pour ce type d’opération.');
        }

        if ($selectedService && in_array($typeCode, $serviceBasedTypes, true)) {
            if ((int)($selectedService['operation_type_id'] ?? 0) !== (int)$selectedType['id']) {
                throw new RuntimeException('Le service sélectionné n’est pas rattaché au type d’opération choisi.');
            }
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

            if ($selectedService) {
                throw new RuntimeException('Un virement interne ne doit pas être rattaché à un service.');
            }

            $preview = [
                'debit_account_code' => $selectedSourceTreasury['account_code'],
                'credit_account_code' => $selectedTargetTreasury['account_code'],
                'analytic_account' => null,
            ];

            if ($actionMode === 'save') {
                $pdo->beginTransaction();

                $movementId = createInternalTreasuryMovement($pdo, [
                    'source_treasury_account_id' => (int)$selectedSourceTreasury['id'],
                    'target_treasury_account_id' => (int)$selectedTargetTreasury['id'],
                    'amount' => $amount,
                    'operation_date' => $operationDate,
                    'reference' => $reference !== '' ? $reference : null,
                    'label' => $label !== '' ? $label : $selectedType['label'],
                ]);

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'create_internal_transfer',
                        'operations',
                        'treasury_movement',
                        $movementId,
                        'Création d’un virement interne'
                    );
                }

                $pdo->commit();
                $successMessage = 'Virement interne enregistré.';
                $_POST = [];
                $preview = null;
            }
        } else {
            $resolvedSourceTreasuryCode = $selectedSourceTreasury['account_code']
                ?? $selectedClient['treasury_account_code']
                ?? null;

            $payload = [
                'operation_type_code' => $typeCode,
                'operation_type_id' => (int)$selectedType['id'],
                'service_id' => $selectedService['id'] ?? null,
                'client_id' => $selectedClient['id'] ?? null,
                'amount' => $amount,
                'operation_date' => $operationDate,
                'reference' => $reference !== '' ? $reference : null,
                'label' => $label !== '' ? $label : $selectedType['label'],
                'notes' => $notes !== '' ? $notes : null,
                'source_type' => 'manual',
                'operation_kind' => 'manual',
                'source_treasury_code' => $resolvedSourceTreasuryCode,
                'target_treasury_code' => $selectedTargetTreasury['account_code'] ?? null,
            ];

            $preview = resolveAccountingOperation($pdo, $payload);

            if (!empty($preview['analytic_account']['account_code'])) {
                $previewService706Label = $preview['analytic_account']['account_code'] . ' - ' . ($preview['analytic_account']['account_label'] ?? '');
            }

            if ($actionMode === 'save') {
                $pdo->beginTransaction();

                $operationId = createOperationWithAccounting($pdo, $payload);

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'create_operation',
                        'operations',
                        'operation',
                        $operationId,
                        'Création d’une opération alignée sur les règles métier'
                    );
                }

                $pdo->commit();
                $successMessage = 'Opération enregistrée.';
                $_POST = [];
                $preview = null;
                $previewService706Label = '';
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Créer une opération';
$pageSubtitle = 'Le moteur applique les règles 411 / 512 / 706 et résout automatiquement le bon 706 selon destination, pays commercial et service.';
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

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Date</label>
                            <input type="date" name="operation_date" value="<?= opOld('operation_date', date('Y-m-d')) ?>">
                        </div>

                        <div>
                            <label>Montant</label>
                            <input type="number" step="0.01" name="amount" value="<?= opOld('amount') ?>" required>
                        </div>

                        <div>
                            <label>Type d’opération</label>
                            <select name="operation_type_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($operationTypes as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>" <?= opOld('operation_type_id') == $type['id'] ? 'selected' : '' ?>>
                                        <?= e($type['label']) ?> (<?= e($type['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Service</label>
                            <select name="service_id">
                                <option value="">Aucun</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= (int)$service['id'] ?>" <?= opOld('service_id') == $service['id'] ? 'selected' : '' ?>>
                                        <?= e(($service['label'] ?? '') . ' (' . ($service['code'] ?? '') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Client</label>
                            <select name="client_id">
                                <option value="">Aucun</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int)$client['id'] ?>" <?= opOld('client_id') == $client['id'] ? 'selected' : '' ?>>
                                        <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                        <?php if (!empty($client['country_destination']) || !empty($client['country_commercial'])): ?>
                                            <?= e(' [' . ($client['country_destination'] ?? '') . ' / ' . ($client['country_commercial'] ?? '') . ']') ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Référence</label>
                            <input type="text" name="reference" value="<?= opOld('reference') ?>">
                        </div>

                        <div>
                            <label>Compte interne source</label>
                            <select name="source_treasury_account_id">
                                <option value="">Auto / Aucun</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= opOld('source_treasury_account_id') == $acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Compte interne cible</label>
                            <select name="target_treasury_account_id">
                                <option value="">Aucun</option>
                                <?php foreach ($treasuryAccounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= opOld('target_treasury_account_id') == $acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['account_code'] . ' - ' . $acc['account_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= opOld('label') ?>">
                    </div>

                    <div>
                        <label>Notes / motif</label>
                        <textarea name="notes" rows="4"><?= opOld('notes') ?></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
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
                        <span class="metric-value"><?= e($previewService706Label !== '' ? $previewService706Label : '—') ?></span>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Le moteur affichera ici le débit, le crédit et le 706 automatiquement résolu avant écriture.
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

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>