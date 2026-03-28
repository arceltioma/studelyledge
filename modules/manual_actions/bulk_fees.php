<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'manual_actions_create');

$services = tableExists($pdo, 'ref_services')
    ? $pdo->query("
        SELECT id, code, label
        FROM ref_services
        WHERE COALESCE(is_active,1)=1
        ORDER BY label ASC
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

$countries = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT country_destination
        FROM clients
        WHERE COALESCE(is_active,1)=1
          AND country_destination IS NOT NULL
          AND country_destination <> ''
        ORDER BY country_destination ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$statuses = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT client_status
        FROM clients
        WHERE client_status IS NOT NULL
          AND client_status <> ''
        ORDER BY client_status ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$previewRows = [];
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $mode = trim((string)($_POST['mode'] ?? 'selection'));
        $country = trim((string)($_POST['country'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $selectedIds = array_values(array_filter(array_map('intval', $_POST['client_ids'] ?? []), fn($v) => $v > 0));

        $operationTypeCode = trim((string)($_POST['operation_type_code'] ?? 'FRAIS_SERVICE'));
        $serviceId = ($_POST['service_id'] ?? '') !== '' ? (int)$_POST['service_id'] : null;
        $operationDate = trim((string)($_POST['operation_date'] ?? date('Y-m-d')));
        $amount = (float)($_POST['amount'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $actionMode = trim((string)($_POST['action_mode'] ?? 'preview'));

        if ($amount <= 0) {
            throw new RuntimeException('Montant invalide.');
        }

        $sql = "SELECT id, client_code, full_name, client_status, country_destination FROM clients WHERE COALESCE(is_active,1)=1";
        $params = [];

        if ($mode === 'country' && $country !== '') {
            $sql .= " AND country_destination = ?";
            $params[] = $country;
        } elseif ($mode === 'status' && $status !== '') {
            $sql .= " AND client_status = ?";
            $params[] = $status;
        } elseif ($mode === 'selection') {
            if (!$selectedIds) {
                throw new RuntimeException('Aucun client sélectionné.');
            }
            $ph = implode(',', array_fill(0, count($selectedIds), '?'));
            $sql .= " AND id IN ($ph)";
            $params = array_merge($params, $selectedIds);
        } elseif ($mode === 'all') {
            // no extra filter
        } else {
            throw new RuntimeException('Mode de sélection invalide.');
        }

        $sql .= " ORDER BY client_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$clients) {
            throw new RuntimeException('Aucun client correspondant.');
        }

        foreach ($clients as $client) {
            $payload = [
                'client_id' => (int)$client['id'],
                'operation_type_code' => $operationTypeCode,
                'service_id' => $serviceId,
                'amount' => $amount,
                'operation_date' => $operationDate,
                'label' => $label !== '' ? $label : 'Frais de masse',
                'notes' => $notes !== '' ? $notes : 'Application de frais en masse',
                'source_type' => 'bulk_fees',
                'operation_kind' => 'bulk',
            ];

            $resolved = resolveAccountingOperation($pdo, $payload);

            $previewRows[] = [
                'client_id' => (int)$client['id'],
                'client_code' => $client['client_code'] ?? '',
                'full_name' => $client['full_name'] ?? '',
                'client_status' => $client['client_status'] ?? '',
                'country_destination' => $client['country_destination'] ?? '',
                'amount' => $amount,
                'debit_account_code' => $resolved['debit_account_code'] ?? '',
                'credit_account_code' => $resolved['credit_account_code'] ?? '',
                'analytic_account_code' => $resolved['analytic_account']['account_code'] ?? '',
                'payload' => $payload,
            ];
        }

        if ($actionMode === 'save') {
            $pdo->beginTransaction();

            $createdCount = 0;
            foreach ($previewRows as $row) {
                $operationId = createOperationWithAccounting($pdo, $row['payload']);
                $createdCount++;

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'create_bulk_fee_operation',
                        'manual_actions',
                        'operation',
                        $operationId,
                        'Application de frais en masse'
                    );
                }
            }

            $pdo->commit();
            $successMessage = $createdCount . ' frais ont été appliqués.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$clientList = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name, country_destination, client_status
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
        LIMIT 300
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Frais en masse';
$pageSubtitle = 'Appliquer des frais selon des critères métier, avec aperçu avant validation.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <form method="POST">
            <?= csrf_input() ?>

            <div class="dashboard-grid-2">
                <div class="form-card">
                    <h3 class="section-title">Ciblage</h3>

                    <div>
                        <label>Mode</label>
                        <select name="mode">
                            <option value="selection">Sélection manuelle</option>
                            <option value="country">Par pays</option>
                            <option value="status">Par statut</option>
                            <option value="all">Tous les clients actifs</option>
                        </select>
                    </div>

                    <div>
                        <label>Pays</label>
                        <select name="country">
                            <option value="">Choisir</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Choisir</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= e($status) ?>"><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <label>Clients (sélection manuelle)</label>
                    <select name="client_ids[]" multiple size="12">
                        <?php foreach ($clientList as $client): ?>
                            <option value="<?= (int)$client['id'] ?>">
                                <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '') . ' [' . ($client['country_destination'] ?? 'N/A') . ' | ' . ($client['client_status'] ?? 'N/A') . ']') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-card">
                    <h3 class="section-title">Frais à appliquer</h3>

                    <div>
                        <label>Type d’opération</label>
                        <select name="operation_type_code">
                            <?php foreach ($operationTypes as $type): ?>
                                <option value="<?= e($type['code']) ?>" <?= $type['code'] === 'FRAIS_SERVICE' ? 'selected' : '' ?>>
                                    <?= e($type['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Service</label>
                        <select name="service_id">
                            <option value="">Aucun</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int)$service['id'] ?>"><?= e($service['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Date</label>
                        <input type="date" name="operation_date" value="<?= e(date('Y-m-d')) ?>">
                    </div>

                    <div>
                        <label>Montant unitaire</label>
                        <input type="number" step="0.01" name="amount" required>
                    </div>

                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="Frais de service">
                    </div>

                    <div>
                        <label>Notes</label>
                        <textarea name="notes" rows="4">Application de frais en masse</textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="action_mode" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="action_mode" value="save" class="btn btn-success">Appliquer les frais</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($previewRows): ?>
            <div class="table-card">
                <h3 class="section-title">Prévisualisation</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Statut</th>
                            <th>Pays</th>
                            <th>Montant</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Analytique</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRows as $row): ?>
                            <tr>
                                <td><?= e($row['client_code'] . ' - ' . $row['full_name']) ?></td>
                                <td><?= e($row['client_status']) ?></td>
                                <td><?= e($row['country_destination']) ?></td>
                                <td><?= number_format((float)$row['amount'], 2, ',', ' ') ?></td>
                                <td><?= e($row['debit_account_code']) ?></td>
                                <td><?= e($row['credit_account_code']) ?></td>
                                <td><?= e($row['analytic_account_code']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>