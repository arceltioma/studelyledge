<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'imports_create');

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
    exit('Batch introuvable.');
}

$rows = [];
$stmtRows = $pdo->prepare("
    SELECT *
    FROM import_rows
    WHERE batch_id = ?
    ORDER BY row_number ASC
");
$stmtRows->execute([$batchId]);
$rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);

$previewResults = [];
$readyCount = 0;
$rejectedCount = 0;

foreach ($rows as $row) {
    $raw = json_decode((string)($row['raw_data'] ?? ''), true);
    $rawRow = $raw['row'] ?? [];

    $clientCode = trim((string)($row['client_code'] ?? ''));
    $operationDate = trim((string)($row['operation_date'] ?? ''));
    $operationType = strtoupper(trim((string)($row['operation_type'] ?? '')));
    $label = trim((string)($row['label'] ?? ''));
    $amount = (float)($row['amount'] ?? 0);
    $reference = trim((string)($row['reference'] ?? ''));
    $sourceType = trim((string)($row['source_type'] ?? 'import'));

    $serviceCode = trim((string)($rawRow['service_code'] ?? $rawRow['code_service'] ?? ''));
    $sourceTreasuryCode = trim((string)($rawRow['treasury_account_code'] ?? $rawRow['compte_512'] ?? ''));
    $targetTreasuryCode = trim((string)($rawRow['target_treasury_account_code'] ?? $rawRow['compte_512_cible'] ?? ''));

    $client = null;
    $service = null;
    $error = null;
    $resolved = null;

    try {
        if ($clientCode !== '') {
            $stmtClient = $pdo->prepare("
                SELECT *
                FROM clients
                WHERE client_code = ?
                LIMIT 1
            ");
            $stmtClient->execute([$clientCode]);
            $client = $stmtClient->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($operationType !== 'VIREMENT_INTERNE' && !$client) {
            throw new RuntimeException('Client introuvable.');
        }

        if ($serviceCode !== '') {
            $stmtService = $pdo->prepare("
                SELECT *
                FROM ref_services
                WHERE code = ?
                LIMIT 1
            ");
            $stmtService->execute([$serviceCode]);
            $service = $stmtService->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$service) {
                throw new RuntimeException('Service introuvable.');
            }
        }

        $payload = [
            'operation_type_code' => $operationType,
            'client_id' => $client['id'] ?? null,
            'service_id' => $service['id'] ?? null,
            'amount' => $amount,
            'operation_date' => $operationDate !== '' ? $operationDate : date('Y-m-d'),
            'reference' => $reference !== '' ? $reference : null,
            'label' => $label !== '' ? $label : null,
            'source_type' => $sourceType !== '' ? $sourceType : 'import',
            'operation_kind' => 'import',
            'source_treasury_code' => $sourceTreasuryCode !== '' ? $sourceTreasuryCode : null,
            'target_treasury_code' => $targetTreasuryCode !== '' ? $targetTreasuryCode : null,
        ];

        $resolved = resolveAccountingOperation($pdo, $payload);

        $stmtUpdate = $pdo->prepare("
            UPDATE import_rows
            SET status = 'ready',
                error_message = NULL
            WHERE id = ?
        ");
        $stmtUpdate->execute([(int)$row['id']]);

        $readyCount++;
    } catch (Throwable $e) {
        $error = $e->getMessage();

        $stmtUpdate = $pdo->prepare("
            UPDATE import_rows
            SET status = 'rejected',
                error_message = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([$error, (int)$row['id']]);

        $rejectedCount++;
    }

    $previewResults[] = [
        'row' => $row,
        'client' => $client,
        'service' => $service,
        'resolved' => $resolved,
        'error' => $error,
    ];
}

$stmtBatchUpdate = $pdo->prepare("
    UPDATE import_batches
    SET ready_rows = ?,
        rejected_rows = ?,
        status = ?
    WHERE id = ?
");
$stmtBatchUpdate->execute([
    $readyCount,
    $rejectedCount,
    $rejectedCount > 0 ? 'validated_with_rejections' : 'validated',
    $batchId
]);

$pageTitle = 'Prévisualisation import';
$pageSubtitle = 'La prévisualisation applique exactement le même moteur comptable que la saisie manuelle.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid">
            <div class="card">
                <h3>Lignes totales</h3>
                <div class="kpi"><?= count($previewResults) ?></div>
            </div>
            <div class="card">
                <h3>Prêtes</h3>
                <div class="kpi"><?= (int)$readyCount ?></div>
            </div>
            <div class="card">
                <h3>Rejetées</h3>
                <div class="kpi"><?= (int)$rejectedCount ?></div>
            </div>
        </div>

        <div class="btn-group" style="margin:20px 0;">
            <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-outline">Retour upload</a>
            <a href="<?= e(APP_URL) ?>modules/imports/rejected_rows.php?batch_id=<?= (int)$batchId ?>" class="btn btn-secondary">Voir les rejets</a>
            <?php if ($rejectedCount === 0): ?>
                <a href="<?= e(APP_URL) ?>modules/imports/validate_import_batch.php?batch_id=<?= (int)$batchId ?>" class="btn btn-success" onclick="return confirm('Valider et insérer toutes les lignes prêtes ?');">Valider l’import</a>
            <?php else: ?>
                <button type="button" class="btn btn-outline" disabled>Validation verrouillée</button>
            <?php endif; ?>
        </div>

        <?php if ($rejectedCount > 0): ?>
            <div class="warning">
                La validation finale est verrouillée tant qu’il reste des lignes rejetées.
            </div>
        <?php endif; ?>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Ligne</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Service</th>
                        <th>Montant</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>706 résolu</th>
                        <th>Statut</th>
                        <th>Erreur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewResults as $item): ?>
                        <?php $row = $item['row']; ?>
                        <tr>
                            <td><?= (int)($row['row_number'] ?? 0) ?></td>
                            <td><?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($item['client']['full_name'] ?? ''))) ?></td>
                            <td><?= e($row['operation_type'] ?? '') ?></td>
                            <td><?= e(trim((string)($item['service']['code'] ?? '') . ' - ' . (string)($item['service']['label'] ?? ''))) ?></td>
                            <td><?= number_format((float)($row['amount'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= e($item['resolved']['debit_account_code'] ?? '') ?></td>
                            <td><?= e($item['resolved']['credit_account_code'] ?? '') ?></td>
                            <td><?= e($item['resolved']['analytic_account']['account_code'] ?? '') ?></td>
                            <td><?= e($item['error'] ? 'rejected' : 'ready') ?></td>
                            <td><?= e($item['error'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$previewResults): ?>
                        <tr><td colspan="10">Aucune ligne à prévisualiser.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>