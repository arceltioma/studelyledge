<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'statements_export');

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Relevés de comptes';
$pageSubtitle = 'Exports orientés flux : débits, crédits, soldes, historique.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="form-card">
            <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_statement_pdf.php">
                <?= csrf_input() ?>

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

                <div class="btn-group">
                    <button class="btn btn-primary" type="submit">Exporter PDF unitaire</button>
                </div>
            </form>
        </div>

        <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php" class="table-card">
            <?= csrf_input() ?>

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

            <table>
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

            <div class="btn-group">
                <button class="btn btn-success" type="submit">Exporter la sélection</button>
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

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>