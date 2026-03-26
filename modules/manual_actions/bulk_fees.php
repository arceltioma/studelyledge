<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

$message = '';
$error = '';
$previewData = null;

$clients = $pdo->query("
    SELECT id, client_code, first_name, last_name, country
    FROM clients
    ORDER BY client_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$statuses = $pdo->query("
    SELECT id, name
    FROM statuses
    ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$countries = $pdo->query("
    SELECT DISTINCT country
    FROM clients
    WHERE country IS NOT NULL AND country <> ''
    ORDER BY country ASC
")->fetchAll(PDO::FETCH_COLUMN);

function getTargetClients(PDO $pdo, string $mode, array $post): array
{
    switch ($mode) {
        case 'all':
            $stmt = $pdo->query("
                SELECT id, client_code, first_name, last_name, country
                FROM clients
                ORDER BY client_code ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'selected':
            $selectedClients = $post['client_ids'] ?? [];
            if (!is_array($selectedClients) || empty($selectedClients)) {
                return [];
            }

            $selectedClients = array_map('intval', $selectedClients);
            $selectedClients = array_filter($selectedClients, fn($id) => $id > 0);

            if (empty($selectedClients)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($selectedClients), '?'));
            $stmt = $pdo->prepare("
                SELECT id, client_code, first_name, last_name, country
                FROM clients
                WHERE id IN ($placeholders)
                ORDER BY client_code ASC
            ");
            $stmt->execute($selectedClients);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'country':
            $country = clean_input($post['country'] ?? '');
            if ($country === '') {
                return [];
            }

            $stmt = $pdo->prepare("
                SELECT id, client_code, first_name, last_name, country
                FROM clients
                WHERE country = ?
                ORDER BY client_code ASC
            ");
            $stmt->execute([$country]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'status':
            $statusId = (int)($post['status_id'] ?? 0);
            if ($statusId <= 0) {
                return [];
            }

            $stmt = $pdo->prepare("
                SELECT c.id, c.client_code, c.first_name, c.last_name, c.country
                FROM clients c
                WHERE c.status_id = ?
                ORDER BY c.client_code ASC
            ");
            $stmt->execute([$statusId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        default:
            return [];
    }
}

function isDuplicateBulkFee(PDO $pdo, int $clientId, string $date, string $label, float $amount): bool
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM operations
        WHERE client_id = ?
          AND operation_date = ?
          AND operation_type = 'debit'
          AND label = ?
          AND amount = ?
          AND source_type = 'bulk_fee'
        LIMIT 1
    ");
    $stmt->execute([$clientId, $date, $label, $amount]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = clean_input($_POST['step'] ?? 'preview');
    $mode = clean_input($_POST['apply_mode'] ?? '');
    $date = clean_input($_POST['operation_date'] ?? '');
    $label = clean_input($_POST['label'] ?? 'Frais de service');
    $amount = (float)($_POST['amount'] ?? 0);

    if ($date === '' || $label === '' || $amount <= 0) {
        $error = 'Merci de renseigner une date, un libellé et un montant valide.';
    } elseif (!in_array($mode, ['all', 'selected', 'country', 'status'], true)) {
        $error = 'Mode d’application invalide.';
    } else {
        $targetClients = getTargetClients($pdo, $mode, $_POST);

        if (empty($targetClients)) {
            $error = 'Aucun client cible trouvé pour ce mode d’application.';
        } else {
            $duplicates = [];
            $toInsert = [];

            foreach ($targetClients as $client) {
                if (isDuplicateBulkFee($pdo, (int)$client['id'], $date, $label, $amount)) {
                    $duplicates[] = $client;
                } else {
                    $toInsert[] = $client;
                }
            }

            if ($step === 'confirm') {
                $insert = $pdo->prepare("
                    INSERT INTO operations (
                        client_id,
                        operation_date,
                        operation_type,
                        label,
                        amount,
                        source_type,
                        created_by,
                        created_at
                    ) VALUES (?, ?, 'debit', ?, ?, 'bulk_fee', ?, NOW())
                ");

                $insertedCount = 0;

                foreach ($toInsert as $client) {
                    $insert->execute([
                        (int)$client['id'],
                        $date,
                        $label,
                        $amount,
                        (int)$_SESSION['user_id']
                    ]);
                    $insertedCount++;
                }

                $message = 'Traitement terminé : ' . $insertedCount . ' écriture(s) ajoutée(s), ' . count($duplicates) . ' doublon(s) ignoré(s).';
            } else {
                $previewData = [
                    'mode' => $mode,
                    'date' => $date,
                    'label' => $label,
                    'amount' => $amount,
                    'targetClients' => $targetClients,
                    'toInsert' => $toInsert,
                    'duplicates' => $duplicates,
                    'selectedClientIds' => $_POST['client_ids'] ?? [],
                    'country' => $_POST['country'] ?? '',
                    'status_id' => $_POST['status_id'] ?? '',
                ];
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Frais en masse', 'Appliquer des frais ciblés avec prévisualisation et contrôle.'); ?>
        <div class="form-card">
            <?php if ($message): ?>
                <div class="success auto-hide"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="bulkFeesForm">
                <input type="hidden" name="step" value="preview">

                <label for="apply_mode">Mode d’application</label>
                <select name="apply_mode" id="apply_mode" required>
                    <option value="">Choisir un mode</option>
                    <option value="all">Tous les clients</option>
                    <option value="selected">Liste de clients spécifiques</option>
                    <option value="country">Tous les clients d’un pays</option>
                    <option value="status">Tous les clients d’un statut</option>
                </select>

                <div id="selected_clients_block" style="display:none;">
                    <label for="client_ids">Clients spécifiques</label>
                    <select name="client_ids[]" id="client_ids" multiple size="10">
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int)$client['id'] ?>">
                                <?= e($client['client_code'] . ' - ' . $client['first_name'] . ' ' . $client['last_name'] . ' (' . $client['country'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="muted">Maintiens Ctrl ou Cmd pour sélectionner plusieurs clients.</p>
                </div>

                <div id="country_block" style="display:none;">
                    <label for="country">Pays</label>
                    <select name="country" id="country">
                        <option value="">Choisir un pays</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= e($country) ?>"><?= e($country) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="status_block" style="display:none;">
                    <label for="status_id">Statut</label>
                    <select name="status_id" id="status_id">
                        <option value="">Choisir un statut</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= (int)$status['id'] ?>"><?= e($status['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bulk-counter" id="bulk_counter_box">
                    <strong>Clients ciblés :</strong> <span id="bulk_counter">0</span>
                </div>

                <label for="operation_date">Date de l’opération</label>
                <input type="date" name="operation_date" id="operation_date" required>

                <label for="label">Libellé</label>
                <input type="text" name="label" id="label" value="Frais de service" required>

                <label for="amount">Montant des frais</label>
                <input type="number" step="0.01" name="amount" id="amount" placeholder="Montant des frais" required>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Prévisualiser</button>
                    <a href="<?= APP_URL ?>modules/dashboard/dashboard.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>

        <?php if ($previewData): ?>
            <div class="table-card">
                <h3 class="section-title">Aperçu avant validation</h3>

                <div class="card-grid" style="margin-bottom:16px;">
                    <div class="card">
                        <h3>Clients ciblés</h3>
                        <div class="kpi"><?= count($previewData['targetClients']) ?></div>
                    </div>
                    <div class="card">
                        <h3>Écritures à ajouter</h3>
                        <div class="kpi"><?= count($previewData['toInsert']) ?></div>
                    </div>
                    <div class="card">
                        <h3>Doublons ignorés</h3>
                        <div class="kpi"><?= count($previewData['duplicates']) ?></div>
                    </div>
                </div>

                <p><strong>Date :</strong> <?= e($previewData['date']) ?></p>
                <p><strong>Libellé :</strong> <?= e($previewData['label']) ?></p>
                <p><strong>Montant :</strong> <?= number_format((float)$previewData['amount'], 2, ',', ' ') ?> €</p>

                <h4>Clients concernés par l’application du frais</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Pays</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($previewData['toInsert'])): ?>
                            <tr><td colspan="3">Aucune écriture nouvelle ne sera créée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($previewData['toInsert'] as $client): ?>
                                <tr>
                                    <td><?= e($client['client_code']) ?></td>
                                    <td><?= e($client['first_name'] . ' ' . $client['last_name']) ?></td>
                                    <td><?= e($client['country']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($previewData['duplicates'])): ?>
                    <h4>Écritures déjà existantes détectées</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Pays</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData['duplicates'] as $client): ?>
                                <tr>
                                    <td><?= e($client['client_code']) ?></td>
                                    <td><?= e($client['first_name'] . ' ' . $client['last_name']) ?></td>
                                    <td><?= e($client['country']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <form method="POST" style="margin-top:20px;">
                    <input type="hidden" name="step" value="confirm">
                    <input type="hidden" name="apply_mode" value="<?= e($previewData['mode']) ?>">
                    <input type="hidden" name="operation_date" value="<?= e($previewData['date']) ?>">
                    <input type="hidden" name="label" value="<?= e($previewData['label']) ?>">
                    <input type="hidden" name="amount" value="<?= e((string)$previewData['amount']) ?>">

                    <?php if ($previewData['mode'] === 'selected'): ?>
                        <?php foreach ($previewData['selectedClientIds'] as $clientId): ?>
                            <input type="hidden" name="client_ids[]" value="<?= (int)$clientId ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($previewData['mode'] === 'country'): ?>
                        <input type="hidden" name="country" value="<?= e($previewData['country']) ?>">
                    <?php endif; ?>

                    <?php if ($previewData['mode'] === 'status'): ?>
                        <input type="hidden" name="status_id" value="<?= (int)$previewData['status_id'] ?>">
                    <?php endif; ?>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Confirmer l’application</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modeSelect = document.getElementById('apply_mode');
    const selectedClientsBlock = document.getElementById('selected_clients_block');
    const countryBlock = document.getElementById('country_block');
    const statusBlock = document.getElementById('status_block');
    const clientSelect = document.getElementById('client_ids');
    const countrySelect = document.getElementById('country');
    const statusSelect = document.getElementById('status_id');
    const counter = document.getElementById('bulk_counter');

    const allClientsCount = <?= count($clients) ?>;
    const countryCounts = <?= json_encode(array_count_values(array_column($clients, 'country'))) ?>;
    const statusCounts = <?= json_encode(
        array_reduce($pdo->query("SELECT status_id, COUNT(*) as total FROM clients GROUP BY status_id")->fetchAll(PDO::FETCH_ASSOC), function($carry, $item) {
            $carry[$item['status_id']] = (int)$item['total'];
            return $carry;
        }, [])
    ) ?>;

    function toggleBlocks() {
        const mode = modeSelect.value;

        selectedClientsBlock.style.display = 'none';
        countryBlock.style.display = 'none';
        statusBlock.style.display = 'none';

        if (mode === 'selected') selectedClientsBlock.style.display = 'block';
        if (mode === 'country') countryBlock.style.display = 'block';
        if (mode === 'status') statusBlock.style.display = 'block';

        updateCounter();
    }

    function updateCounter() {
        const mode = modeSelect.value;
        let count = 0;

        if (mode === 'all') {
            count = allClientsCount;
        } else if (mode === 'selected') {
            count = Array.from(clientSelect.selectedOptions).length;
        } else if (mode === 'country') {
            count = countryCounts[countrySelect.value] || 0;
        } else if (mode === 'status') {
            count = statusCounts[statusSelect.value] || 0;
        }

        counter.textContent = count;
    }

    modeSelect.addEventListener('change', toggleBlocks);
    clientSelect.addEventListener('change', updateCounter);
    countrySelect.addEventListener('change', updateCounter);
    statusSelect.addEventListener('change', updateCounter);

    toggleBlocks();
});
</script>