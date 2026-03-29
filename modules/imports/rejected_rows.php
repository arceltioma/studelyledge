<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_journal');

$batchId = (int)($_GET['batch_id'] ?? 0);
if ($batchId <= 0) {
    exit('Batch import invalide.');
}

$batch = null;
if (tableExists($pdo, 'import_batches')) {
    $stmtBatch = $pdo->prepare("
        SELECT *
        FROM import_batches
        WHERE id = ?
        LIMIT 1
    ");
    $stmtBatch->execute([$batchId]);
    $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$batch) {
    exit('Batch import introuvable.');
}

$rows = [];
if (tableExists($pdo, 'import_rows')) {
    $stmtRows = $pdo->prepare("
        SELECT *
        FROM import_rows
        WHERE batch_id = ?
          AND status = 'rejected'
        ORDER BY row_number ASC, id ASC
    ");
    $stmtRows->execute([$batchId]);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);
}

function rejectedRowRawSummary(array $row): array
{
    $raw = json_decode((string)($row['raw_data'] ?? ''), true);
    $rawRow = $raw['row'] ?? [];

    return [
        'service_code' => trim((string)($rawRow['service_code'] ?? $rawRow['code_service'] ?? '')),
        'source_512' => trim((string)($rawRow['treasury_account_code'] ?? $rawRow['compte_512'] ?? '')),
        'target_512' => trim((string)($rawRow['target_treasury_account_code'] ?? $rawRow['compte_512_cible'] ?? '')),
    ];
}

$pageTitle = 'Lignes rejetées';
$pageSubtitle = 'Les lignes rejetées restent visibles tant qu’elles n’ont pas été corrigées ou réimportées proprement.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h1>Batch #<?= (int)$batch['id'] ?></h1>
                <p class="muted">Fichier : <?= e($batch['file_name'] ?? '') ?></p>
            </div>

            <div class="btn-group">
                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/imports/import_journal.php">Retour journal</a>
                <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/imports/import_preview.php?batch_id=<?= (int)$batch['id'] ?>">Retour prévisualisation</a>
            </div>
        </div>

        <div class="card-grid" style="margin-bottom:20px;">
            <div class="card">
                <h3>Statut batch</h3>
                <div class="kpi"><?= e($batch['status'] ?? 'pending') ?></div>
            </div>
            <div class="card">
                <h3>Total lignes</h3>
                <div class="kpi"><?= (int)($batch['total_rows'] ?? 0) ?></div>
            </div>
            <div class="card">
                <h3>Lignes prêtes</h3>
                <div class="kpi"><?= (int)($batch['ready_rows'] ?? 0) ?></div>
            </div>
            <div class="card">
                <h3>Lignes rejetées</h3>
                <div class="kpi"><?= count($rows) ?></div>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Ligne</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Service / 512</th>
                        <th>Erreur</th>
                        <th>Donnée brute</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $rawMeta = rejectedRowRawSummary($row); ?>
                        <tr>
                            <td><?= (int)($row['row_number'] ?? 0) ?></td>
                            <td><?= e($row['client_code'] ?? '') ?></td>
                            <td><?= e($row['operation_type'] ?? '') ?></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 2, ',', ' ') ?></td>
                            <td>
                                <?php if ($rawMeta['service_code'] !== ''): ?>
                                    <div>Service : <?= e($rawMeta['service_code']) ?></div>
                                <?php endif; ?>
                                <?php if ($rawMeta['source_512'] !== ''): ?>
                                    <div>512 source : <?= e($rawMeta['source_512']) ?></div>
                                <?php endif; ?>
                                <?php if ($rawMeta['target_512'] !== ''): ?>
                                    <div>512 cible : <?= e($rawMeta['target_512']) ?></div>
                                <?php endif; ?>
                                <?php if ($rawMeta['service_code'] === '' && $rawMeta['source_512'] === '' && $rawMeta['target_512'] === ''): ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['error_message'] ?? '') ?></td>
                            <td>
                                <pre style="white-space:pre-wrap;max-width:520px;"><?= e($row['raw_data'] ?? '') ?></pre>
                            </td>
                            <td>
                                <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/imports/correct_rejected_row.php?id=<?= (int)$row['id'] ?>">
                                    Corriger
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="8">Aucune ligne rejetée ouverte pour ce batch.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>