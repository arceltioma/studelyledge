<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_journal');

function importBadgeClass(string $status): string
{
    return match ($status) {
        'validated', 'imported' => 'status-success',
        'validated_with_rejections', 'pending', 'ready' => 'status-warning',
        'rejected', 'failed', 'error' => 'status-danger',
        'corrected' => 'status-info',
        default => 'status-info',
    };
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$flashDetails = $_SESSION['flash_details'] ?? [];

unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_details']);

$rows = tableExists($pdo, 'import_batches')
    ? $pdo->query("
        SELECT ib.*
        FROM import_batches ib
        ORDER BY ib.id DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$totals = [
    'imports' => count($rows),
    'rows' => 0,
    'rejected' => 0,
    'ready' => 0,
    'imported' => 0,
];

if (tableExists($pdo, 'import_rows')) {
    $totals['rows'] = (int)$pdo->query("SELECT COUNT(*) FROM import_rows")->fetchColumn();
    $totals['rejected'] = (int)$pdo->query("SELECT COUNT(*) FROM import_rows WHERE status = 'rejected'")->fetchColumn();
    $totals['ready'] = (int)$pdo->query("SELECT COUNT(*) FROM import_rows WHERE status = 'ready'")->fetchColumn();
    $totals['imported'] = (int)$pdo->query("SELECT COUNT(*) FROM import_rows WHERE status IN ('imported','corrected')")->fetchColumn();
}

$pageTitle = 'Journal des imports';
$pageSubtitle = 'Vue consolidée des batchs, statuts, lignes prêtes, rejets et validations finales.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flashSuccess !== ''): ?><div class="success"><?= e($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="error"><?= e($flashError) ?></div><?php endif; ?>

        <?php if ($flashDetails): ?>
            <div class="warning" style="margin-bottom:20px;">
                <strong>Détails :</strong>
                <ul style="margin-top:8px;">
                    <?php foreach ($flashDetails as $detail): ?>
                        <li><?= e($detail) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="card"><h3>Batchs</h3><div class="kpi"><?= (int)$totals['imports'] ?></div></div>
            <div class="card"><h3>Lignes</h3><div class="kpi"><?= (int)$totals['rows'] ?></div></div>
            <div class="card"><h3>Prêtes</h3><div class="kpi"><?= (int)$totals['ready'] ?></div></div>
            <div class="card"><h3>Rejets</h3><div class="kpi"><?= (int)$totals['rejected'] ?></div></div>
            <div class="card"><h3>Importées / corrigées</h3><div class="kpi"><?= (int)$totals['imported'] ?></div></div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                <h3 class="section-title">Historique des imports</h3>
                <div class="btn-group">
                    <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/imports/import_upload.php">Importer opérations</a>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">Import clients</a>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/treasury/import_treasury_csv.php">Import comptes internes</a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fichier</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Prêtes</th>
                        <th>Rejets</th>
                        <th>Importées</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $batchId = (int)$row['id'];
                        $totalRows = (int)($row['total_rows'] ?? 0);
                        $readyRows = (int)($row['ready_rows'] ?? 0);
                        $rejectedRows = (int)($row['rejected_rows'] ?? 0);
                        $importedRows = (int)($row['imported_rows'] ?? 0);
                        $status = (string)($row['status'] ?? 'unknown');
                        ?>
                        <tr>
                            <td><?= $batchId ?></td>
                            <td><?= e($row['file_name'] ?? '—') ?></td>
                            <td>
                                <span class="status-pill <?= importBadgeClass($status) ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>
                            <td><?= e($row['imported_at'] ?? $row['created_at'] ?? '') ?></td>
                            <td><?= $totalRows ?></td>
                            <td><?= $readyRows ?></td>
                            <td><?= $rejectedRows ?></td>
                            <td><?= $importedRows ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/imports/import_preview.php?batch_id=<?= $batchId ?>">Prévisualiser</a>

                                    <?php if ($rejectedRows > 0): ?>
                                        <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/imports/rejected_rows.php?batch_id=<?= $batchId ?>">Rejets</a>
                                    <?php endif; ?>

                                    <?php if ($rejectedRows === 0 && in_array($status, ['validated','pending','validated_with_rejections'], true) && $readyRows > 0): ?>
                                        <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/imports/validate_import_batch.php?batch_id=<?= $batchId ?>" onclick="return confirm('Valider ce batch et insérer toutes les lignes prêtes ?');">
                                            Valider
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="9">Aucun import enregistré.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>