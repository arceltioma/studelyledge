<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'statements_export_bulk';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$countries = $pdo->query("
    SELECT DISTINCT country
    FROM clients
    WHERE is_active = 1
      AND country IS NOT NULL
      AND country <> ''
    ORDER BY country ASC
")->fetchAll(PDO::FETCH_COLUMN);

$statuses = $pdo->query("
    SELECT id, name
    FROM statuses
    ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$clients = $pdo->query("
    SELECT id, client_code, first_name, last_name, country
    FROM clients
    WHERE is_active = 1
    ORDER BY client_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalActiveClients = count($clients);
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar('Export de relevés en masse', 'Générer plusieurs relevés PDF en une seule opération.'); ?>

        <div class="page-title">

            <div class="btn-group">
                <a href="<?= APP_URL ?>modules/statements/index.php" class="btn btn-outline">Retour relevés</a>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <h3>Clients actifs</h3>
                <div class="kpi"><?= $totalActiveClients ?></div>
                <p class="muted">Base potentielle d’export</p>
            </div>

            <div class="card">
                <h3>Sortie</h3>
                <div class="kpi" style="font-size:22px;">ZIP</div>
                <p class="muted">Un PDF par client</p>
            </div>
        </div>

        <div class="form-card">
            <form method="POST" action="<?= APP_URL ?>modules/statements/generate_bulk_pdf.php" id="bulkStatementForm">
                <label for="mode">Mode d’export</label>
                <select name="mode" id="mode" required>
                    <option value="">Choisir un mode</option>
                    <option value="country">Tous les clients d’un pays</option>
                    <option value="status">Tous les clients d’un statut</option>
                    <option value="all">Tous les clients</option>
                    <option value="selection">Ensemble de clients sélectionnés</option>
                </select>

                <div class="dashboard-grid-2">
                    <div>
                        <label for="country">Pays</label>
                        <select name="country" id="country">
                            <option value="">--</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status">Statut</label>
                        <select name="status" id="status">
                            <option value="">--</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= (int)$status['id'] ?>"><?= e($status['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="dashboard-grid-2">
                    <div>
                        <label for="date_from">Du</label>
                        <input type="date" name="date_from" id="date_from">
                    </div>
                    <div>
                        <label for="date_to">Au</label>
                        <input type="date" name="date_to" id="date_to">
                    </div>
                </div>

                <label for="clients">Clients sélectionnés</label>
                <select name="clients[]" id="clients" multiple size="12">
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client['id'] ?>" data-country="<?= e($client['country']) ?>">
                            <?= e($client['client_code'] . ' — ' . $client['first_name'] . ' ' . $client['last_name'] . ' (' . $client['country'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="bulkCounter" class="bulk-counter">0 client sélectionné pour le moment.</div>

                <div id="bulkSummary" class="suggestion-box">
                    <strong>Résumé du filtre choisi</strong>
                    <div class="muted" id="bulkSummaryText" style="margin-top:8px;">
                        Aucun mode d’export sélectionné pour le moment.
                    </div>
                </div>

                <div id="bulkWarning" class="warning" style="display:none;">
                    Attention : le volume ciblé est élevé. L’export peut être plus long à générer.
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">Générer les relevés PDF</button>
                    <a href="<?= APP_URL ?>modules/statements/bulk_statement_export.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('mode');
    const country = document.getElementById('country');
    const status = document.getElementById('status');
    const clients = document.getElementById('clients');
    const counter = document.getElementById('bulkCounter');
    const summaryText = document.getElementById('bulkSummaryText');
    const warningBox = document.getElementById('bulkWarning');

    const totalClients = <?= (int)$totalActiveClients ?>;
    const statusLabels = <?= json_encode(array_column($statuses, 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;

    function getSelectedClientsCount() {
        return Array.from(clients.selectedOptions).length;
    }

    function getTargetedCount() {
        if (mode.value === 'all') return totalClients;
        if (mode.value === 'selection') return getSelectedClientsCount();

        if (mode.value === 'country' && country.value !== '') {
            let count = 0;
            Array.from(clients.options).forEach(option => {
                if (option.dataset.country === country.value) count++;
            });
            return count;
        }

        if (mode.value === 'status' && status.value !== '') return -1;
        return 0;
    }

    function updateCounterAndSummary() {
        const selectedCount = getSelectedClientsCount();
        const targetedCount = getTargetedCount();

        if (mode.value === 'selection') {
            counter.textContent = selectedCount + ' client(s) sélectionné(s) manuellement.';
        } else if (mode.value === 'all') {
            counter.textContent = totalClients + ' client(s) actif(s) seront exporté(s).';
        } else if (mode.value === 'country' && country.value !== '') {
            counter.textContent = targetedCount + ' client(s) du pays sélectionné seront exporté(s).';
        } else if (mode.value === 'status' && status.value !== '') {
            counter.textContent = 'Le ciblage se fera sur le statut sélectionné.';
        } else {
            counter.textContent = '0 client sélectionné pour le moment.';
        }

        if (mode.value === 'all') {
            summaryText.textContent = 'Export de tous les clients actifs du système.';
        } else if (mode.value === 'country') {
            summaryText.textContent = country.value !== ''
                ? 'Export des relevés pour tous les clients du pays : ' + country.value + '.'
                : 'Choisis un pays pour cibler l’export.';
        } else if (mode.value === 'status') {
            summaryText.textContent = status.value !== ''
                ? 'Export des relevés pour tous les clients du statut : ' + (statusLabels[status.value] || status.value) + '.'
                : 'Choisis un statut pour cibler l’export.';
        } else if (mode.value === 'selection') {
            summaryText.textContent = selectedCount > 0
                ? 'Export ciblé sur ' + selectedCount + ' client(s) sélectionné(s) manuellement.'
                : 'Sélectionne un ou plusieurs clients dans la liste.';
        } else {
            summaryText.textContent = 'Aucun mode d’export sélectionné pour le moment.';
        }

        let warning = false;
        if (mode.value === 'all' && totalClients >= 50) warning = true;
        if (mode.value === 'country' && targetedCount >= 50) warning = true;
        if (mode.value === 'selection' && selectedCount >= 50) warning = true;

        warningBox.style.display = warning ? 'block' : 'none';
    }

    mode.addEventListener('change', updateCounterAndSummary);
    country.addEventListener('change', updateCounterAndSummary);
    status.addEventListener('change', updateCounterAndSummary);
    clients.addEventListener('change', updateCounterAndSummary);
    updateCounterAndSummary();
});
</script>