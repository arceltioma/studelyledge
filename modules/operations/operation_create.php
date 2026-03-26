<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

require_once __DIR__ . '/../../includes/header.php';

function opOld(string $key, mixed $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
}

$operationTypes = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("SELECT id, code, label FROM ref_operation_types ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC)
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
            sa.account_code AS service_account_code,
            ta.account_code AS treasury_account_code
        FROM ref_services rs
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
            ta.account_code AS treasury_account_code,
            ta.account_label AS treasury_account_label
        FROM clients c
        LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
        ORDER BY c.client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$treasuryAccounts = tableExists($pdo, 'treasury_accounts')
    ? $pdo->query("SELECT id, account_code, account_label FROM treasury_accounts ORDER BY account_code ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$successMessage = '';
$errorMessage = '';
$preview = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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

        $selectedType = null;
        foreach ($operationTypes as $type) {
            if ((int)$type['id'] === $operationTypeId) {
                $selectedType = $type;
                break;
            }
        }
        if (!$selectedType) {
            throw new RuntimeException('Type d’opération introuvable.');
        }

        $selectedService = null;
        if ($serviceId !== null) {
            foreach ($services as $service) {
                if ((int)$service['id'] === $serviceId) {
                    $selectedService = $service;
                    break;
                }
            }
            if (!$selectedService) {
                throw new RuntimeException('Service introuvable.');
            }
            if (!empty($selectedService['operation_type_id']) && (int)$selectedService['operation_type_id'] !== $operationTypeId) {
                throw new RuntimeException('Le service n’est pas lié à ce type d’opération.');
            }
        }

        $selectedClient = null;
        if ($clientId !== null) {
            foreach ($clients as $client) {
                if ((int)$client['id'] === $clientId) {
                    $selectedClient = $client;
                    break;
                }
            }
            if (!$selectedClient) {
                throw new RuntimeException('Client introuvable.');
            }
        }

        $selectedSourceTreasury = null;
        if ($sourceTreasuryId !== null) {
            foreach ($treasuryAccounts as $acc) {
                if ((int)$acc['id'] === $sourceTreasuryId) {
                    $selectedSourceTreasury = $acc;
                    break;
                }
            }
        }

        $selectedTargetTreasury = null;
        if ($targetTreasuryId !== null) {
            foreach ($treasuryAccounts as $acc) {
                if ((int)$acc['id'] === $targetTreasuryId) {
                    $selectedTargetTreasury = $acc;
                    break;
                }
            }
        }

        $payload = [
            'operation_type_code' => $selectedType['code'],
            'operation_type_id' => $selectedType['id'],
            'service_id' => $selectedService['id'] ?? null,
            'client_id' => $selectedClient['id'] ?? null,
            'amount' => $amount,
            'operation_date' => $operationDate,
            'reference' => $reference,
            'label' => $label !== '' ? $label : $selectedType['label'],
            'notes' => $notes,
            'source_treasury_code' => $selectedSourceTreasury['account_code'] ?? ($selectedService['treasury_account_code'] ?? ($selectedClient['treasury_account_code'] ?? null)),
            'target_treasury_code' => $selectedTargetTreasury['account_code'] ?? null,
        ];

        if ($selectedType['code'] === 'VIREMENT_INTERNE') {
            if (!$selectedSourceTreasury || !$selectedTargetTreasury) {
                throw new RuntimeException('Les comptes source et cible sont obligatoires.');
            }

            $preview = [
                'debit_account_code' => $selectedSourceTreasury['account_code'],
                'credit_account_code' => $selectedTargetTreasury['account_code'],
                'analytic_account' => null,
            ];

            if ($actionMode === 'save') {
                $pdo->beginTransaction();

                createInternalTreasuryMovement($pdo, [
                    'source_treasury_account_id' => $selectedSourceTreasury['id'],
                    'target_treasury_account_id' => $selectedTargetTreasury['id'],
                    'amount' => $amount,
                    'operation_date' => $operationDate,
                    'reference' => $reference,
                    'label' => $payload['label'],
                ]);

                $pdo->commit();
                $successMessage = 'Virement interne enregistré.';
                $_POST = [];
                $preview = null;
            }
        } else {
            $preview = resolveAccountingOperation($pdo, $payload);

            if ($actionMode === 'save') {
                $pdo->beginTransaction();
                createOperationWithAccounting($pdo, $payload);
                $pdo->commit();
                $successMessage = 'Opération enregistrée.';
                $_POST = [];
                $preview = null;
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Créer une opération',
            'Le moteur comptable résout les comptes avant validation.'
        ); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <div class="dashboard-grid-2">
                        <div><label>Date</label><input type="date" name="operation_date" value="<?= opOld('operation_date', date('Y-m-d')) ?>"></div>
                        <div><label>Montant</label><input type="number" step="0.01" name="amount" value="<?= opOld('amount') ?>"></div>

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
                                    <option value="<?= (int)$client['id'] ?>" <?= opOld('client_id') == $client['id'] ? 'selected' : '' ?>>
                                        <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div><label>Référence</label><input type="text" name="reference" value="<?= opOld('reference') ?>"></div>

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

                    <div style="margin-top:16px;">
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= opOld('label') ?>">
                    </div>

                    <div style="margin-top:16px;">
                        <label>Notes</label>
                        <textarea name="notes" rows="4"><?= opOld('notes') ?></textarea>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Créer</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Aperçu comptable</h3>
                <?php if ($preview): ?>
                    <div class="stat-row"><span class="metric-label">Débit</span><span class="metric-value"><?= e($preview['debit_account_code'] ?? '—') ?></span></div>
                    <div class="stat-row"><span class="metric-label">Crédit</span><span class="metric-value"><?= e($preview['credit_account_code'] ?? '—') ?></span></div>
                    <div class="stat-row"><span class="metric-label">Analytique</span><span class="metric-value"><?= e($preview['analytic_account']['account_code'] ?? '—') ?></span></div>
                <?php else: ?>
                    <div class="dashboard-note">Le moteur affichera ici le débit, le crédit et l’analytique avant écriture.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>