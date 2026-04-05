<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'statements_export_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$pageTitle = 'Hub Export';
$pageSubtitle = 'Prévisualisation, validation et génération des documents';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main">
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="dashboard-grid-2">

<!-- LEFT -->
<div class="card">
    <h3>Paramétrage export</h3>

    <form id="exportForm" method="POST">

        <label>Type document</label>
        <select name="document_kind">
            <option value="statement">Relevé client</option>
            <option value="profile">Fiche client</option>
        </select>

        <label>Période</label>
        <div style="display:flex; gap:10px;">
            <input type="date" name="date_from">
            <input type="date" name="date_to">
        </div>

        <label>Clients</label>
        <select name="client_ids[]" multiple size="8">
            <?php
            $clients = $pdo->query("SELECT id, client_code, full_name FROM clients ORDER BY client_code")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($clients as $c):
            ?>
                <option value="<?= (int)$c['id'] ?>">
                    <?= e($c['client_code'] . ' - ' . $c['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="btn-group" style="margin-top:20px;">
            <button type="button" onclick="previewExport()" class="btn btn-secondary">
                👁 Prévisualiser l’opération demandée
            </button>

            <button type="button" onclick="generateExport()" class="btn btn-success">
                📄 Générer le/les PDF
            </button>

            <button type="reset" class="btn btn-outline">
                ❌ Annuler
            </button>
        </div>

    </form>
</div>

<!-- RIGHT -->
<div class="card">
    <h3>Aperçu du style</h3>

    <iframe id="previewFrame"
            style="width:100%; height:700px; border:1px solid #ccc; border-radius:10px;">
    </iframe>

</div>

</div>

<script>
function previewExport() {
    const form = document.getElementById('exportForm');
    const data = new FormData(form);

    fetch("<?= APP_URL ?>modules/statements/generate_bulk_pdf.php?preview=1", {
        method: "POST",
        body: data
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById('previewFrame').srcdoc = html;
    });
}

function generateExport() {
    const form = document.getElementById('exportForm');
    form.action = "<?= APP_URL ?>modules/statements/generate_bulk_pdf.php";
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>