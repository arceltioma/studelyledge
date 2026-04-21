<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

$pagePermission = 'statements_export_bulk';
studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'bulk_statement_export');
}

$countries = $pdo->query("
    SELECT DISTINCT country_destination
    FROM clients
    WHERE COALESCE(is_active,1) = 1
      AND country_destination IS NOT NULL
      AND country_destination <> ''
    ORDER BY country_destination ASC
")->fetchAll(PDO::FETCH_COLUMN);

$statuses = tableExists($pdo, 'statuses')
    ? $pdo->query("
        SELECT id, name
        FROM statuses
        ORDER BY sort_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$clients = $pdo->query("
    SELECT id, client_code, first_name, last_name, full_name, country_destination
    FROM clients
    WHERE COALESCE(is_active,1) = 1
    ORDER BY client_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

$totalActiveClients = count($clients);

$pageTitle = 'Export de relevés en masse';
$pageSubtitle = 'Générer plusieurs relevés PDF en une seule opération.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/statements/index.php" class="btn btn-outline">Retour relevés</a>
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
                <div class="kpi">ZIP</div>
                <p class="muted">Un PDF par client</p>
            </div>
        </div>

        <div class="form-card">
            <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php" id="bulkStatementForm">
                <?= csrf_input() ?>

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
                            <option value="">Choisir</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?= e($country) ?>"><?= e($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status_id">Statut</label>
                        <select name="status_id" id="status_id">
                            <option value="">Choisir</option>
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
                <select name="client_ids[]" id="clients" multiple size="12">
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int)$client['id'] ?>" data-country="<?= e($client['country_destination'] ?? '') ?>">
                            <?= e(($client['client_code'] ?? '') . ' — ' . ($client['full_name'] ?? ($client['first_name'] . ' ' . $client['last_name'])) . ' (' . ($client['country_destination'] ?? 'N/A') . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="bulkCounter" class="bulk-counter">0 client sélectionné pour le moment.</div>

                <div id="bulkSummary" class="suggestion-box">
                    <strong>Résumé du filtre choisi</strong>
                    <div class="muted" id="bulkSummaryText">
                        Aucun mode d’export sélectionné pour le moment.
                    </div>
                </div>

                <div id="bulkWarning" class="warning" style="display:none;">
                    Attention : le volume ciblé est élevé. L’export peut être plus long à générer.
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">Générer les relevés PDF</button>
                    <a href="<?= e(APP_URL) ?>modules/statements/bulk_statement_export.php" class="btn btn-outline">Réinitialiser</a>
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
    const status = document.getElementById('status_id');
    const clients = document.getElementById('clients');
    const counter = document.getElementById('bulkCounter');
    const summaryText = document.getElementById('bulkSummaryText');
    const warningBox = document.getElementById('bulkWarning');

    const totalClients = <?= (int)$totalActiveClients ?>;
    const statusLabels = <?= json_encode(array_column($statuses, 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;

    function updateCounter() {
        const selected = Array.from(clients.options).filter(opt => opt.selected).length;
        counter.textContent = selected + ' client(s) sélectionné(s).';
        warningBox.style.display = selected >= 50 ? 'block' : 'none';
    }

    function updateSummary() {
        const modeValue = mode.value;
        let text = 'Aucun mode d’export sélectionné pour le moment.';

        if (modeValue === 'country' && country.value !== '') {
            text = 'Export ciblé sur tous les clients du pays : ' + country.value + '.';
        } else if (modeValue === 'status' && status.value !== '') {
            text = 'Export ciblé sur le statut : ' + (statusLabels[status.value] || status.value) + '.';
        } else if (modeValue === 'all') {
            text = 'Export ciblé sur tous les clients actifs.';
        } else if (modeValue === 'selection') {
            text = 'Export ciblé sur une sélection manuelle de clients.';
        }

        summaryText.textContent = text;
    }

    [mode, country, status, clients].forEach(el => {
        if (el) {
            el.addEventListener('change', function () {
                updateCounter();
                updateSummary();
            });
        }
    });

    updateCounter();
    updateSummary();
});
</script>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>