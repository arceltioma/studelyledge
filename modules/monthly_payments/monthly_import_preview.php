<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_preview_page');
} else {
    enforcePagePermission($pdo, 'imports_preview');
}

$importId = (int) ($_GET['id'] ?? 0);
if ($importId <= 0) {
    exit('Import invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM monthly_payment_imports
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$importId]);
$import = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$import) {
    exit('Import introuvable.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM monthly_payment_import_rows
    WHERE import_id = ?
    ORDER BY row_number ASC, id ASC
");
$stmt->execute([$importId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$validCount = 0;
$errorCount = 0;
$pendingCount = 0;

$previewRows = [];

foreach ($rows as $row) {
    $check = sl_monthly_payment_validate_row($pdo, $row);

    if ($check['is_valid']) {
        $validCount++;
    } else {
        $errorCount++;
    }

    if (($row['status'] ?? 'pending') === 'pending') {
        $pendingCount++;
    }

    $previewRows[] = [
        'row' => $row,
        'check' => $check,
    ];
}

$pageTitle = 'Prévisualisation import mensualités';
$pageSubtitle = 'Contrôle des lignes avant validation de l’import';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-4">
            <div class="card"><h3>Total</h3><div class="metric-value"><?= count($rows) ?></div></div>
            <div class="card"><h3>Valides</h3><div class="metric-value"><?= $validCount ?></div></div>
            <div class="card"><h3>En erreur</h3><div class="metric-value"><?= $errorCount ?></div></div>
            <div class="card"><h3>Pending</h3><div class="metric-value"><?= $pendingCount ?></div></div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_import_validate.php?id=<?= (int) $importId ?>" class="btn btn-success">Valider cet import</a>
                <a href="<?= e(APP_URL) ?>modules/monthly_payments/monthly_import_create.php" class="btn btn-outline">Nouvel import</a>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Lignes importées</h3>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>512</th>
                            <th>Jour</th>
                            <th>Libellé</th>
                            <th>Résolution</th>
                            <th>Statut contrôle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRows as $item): ?>
                            <?php
                                $row = $item['row'];
                                $check = $item['check'];
                                $client = $check['client'] ?? null;
                                $treasury = $check['treasury'] ?? null;
                            ?>
                            <tr>
                                <td><?= (int) ($row['row_number'] ?? 0) ?></td>
                                <td><?= e((string) ($row['client_code'] ?? '')) ?></td>
                                <td><?= e(number_format((float) ($row['monthly_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e((string) ($row['treasury_account_code'] ?? '')) ?></td>
                                <td><?= e((string) ($row['monthly_day'] ?? '')) ?></td>
                                <td><?= e((string) ($row['label'] ?? '')) ?></td>
                                <td>
                                    <?= e($client['full_name'] ?? 'Client non résolu') ?><br>
                                    <small><?= e($treasury['account_label'] ?? 'Compte 512 non résolu') ?></small>
                                </td>
                                <td>
                                    <?php if ($check['is_valid']): ?>
                                        <span class="success" style="display:inline-block;">Valide</span>
                                    <?php else: ?>
                                        <span class="error" style="display:inline-block;">
                                            <?= e(implode(' | ', $check['errors'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$previewRows): ?>
                            <tr><td colspan="8">Aucune ligne trouvée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
