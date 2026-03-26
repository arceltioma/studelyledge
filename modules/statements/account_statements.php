<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'statements_export');

require_once __DIR__ . '/../../includes/header.php';

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Relevés de comptes',
            'Exports orientés flux : débits, crédits, soldes, historique.'
        ); ?>

        <div class="form-card">
            <form method="POST" action="<?= APP_URL ?>modules/statements/generate_statement_pdf.php">
                <div>
                    <label>Client</label>
                    <select name="client_id" required>
                        <option value="">Sélectionner</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int)$client['id'] ?>">
                                <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="dashboard-grid-2" style="margin-top:16px;">
                    <div>
                        <label>Du</label>
                        <input type="date" name="date_from">
                    </div>
                    <div>
                        <label>Au</label>
                        <input type="date" name="date_to">
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button class="btn btn-primary">Exporter PDF unitaire</button>
                </div>
            </form>
        </div>

        <form method="POST" action="<?= APP_URL ?>modules/statements/generate_bulk_pdf.php" class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Export masse</h3>

            <div class="dashboard-grid-2">
                <div>
                    <label>Du</label>
                    <input type="date" name="date_from">
                </div>
                <div>
                    <label>Au</label>
                    <input type="date" name="date_to">
                </div>
            </div>

            <table style="margin-top:20px;">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="check_all_statements"></th>
                        <th>Code client</th>
                        <th>Nom</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><input type="checkbox" name="client_ids[]" value="<?= (int)$client['id'] ?>" class="statement-check"></td>
                            <td><?= e($client['client_code'] ?? '') ?></td>
                            <td><?= e($client['full_name'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clients): ?>
                        <tr><td colspan="3">Aucun client disponible.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <input type="hidden" name="document_kind" value="statement">

            <div class="btn-group" style="margin-top:20px;">
                <button class="btn btn-success">Exporter la sélection</button>
            </div>
        </form>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const master = document.getElementById('check_all_statements');
    const boxes = document.querySelectorAll('.statement-check');
    if (master) {
        master.addEventListener('change', function () {
            boxes.forEach(cb => cb.checked = master.checked);
        });
    }
});
</script>