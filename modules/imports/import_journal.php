<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'imports_journal');

require_once __DIR__ . '/../../includes/header.php';

function importBadgeClass(string $status): string
{
    return match ($status) {
        'validated' => 'status-success',
        'validated_with_rejections' => 'status-warning',
        'rejected', 'failed', 'error' => 'status-danger',
        default => 'status-info',
    };
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$flashDetails = $_SESSION['flash_details'] ?? [];

unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_details']);

$rows = tableExists($pdo, 'imports')
    ? $pdo->query("
        SELECT i.*
        FROM imports i
        ORDER BY i.id DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$totals = [
    'imports' => count($rows),
    'rows' => 0,
    'rejected' => 0,
];

if (tableExists($pdo, 'import_rows')) {
    $totals['rows'] = (int)$pdo->query("SELECT COUNT(*) FROM import_rows")->fetchColumn();
    $stmtRejected = $pdo->query("SELECT COUNT(*) FROM import_rows WHERE status = 'rejected'");
    $totals['rejected'] = (int)$stmtRejected->fetchColumn();
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Journal des imports',
            'Le tableau de bord des batchs, de leurs statuts et des lignes à reprendre.'
        ); ?>

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
            <div class="card"><h3>Imports</h3><div class="kpi"><?= (int)$totals['imports'] ?></div></div>
            <div class="card"><h3>Lignes</h3><div class="kpi"><?= (int)$totals['rows'] ?></div></div>
            <div class="card"><h3>Rejets</h3><div class="kpi"><?= (int)$totals['rejected'] ?></div></div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                <h3 class="section-title">Historique</h3>
                <div class="btn-group">
                    <a class="btn btn-primary" href="<?= APP_URL ?>modules/imports/import_preview.php">Nouveau relevé</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/import_clients_csv.php">Import clients</a>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/treasury/import_treasury_csv.php">Import comptes internes</a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fichier</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Lignes</th>
                        <th>Rejets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $importId = (int)$row['id'];
                        $rowCount = 0;
                        $rejectCount = 0;

                        if (tableExists($pdo, 'import_rows')) {
                            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM import_rows WHERE import_id = ?");
                            $stmtCount->execute([$importId]);
                            $rowCount = (int)$stmtCount->fetchColumn();

                            $stmtReject = $pdo->prepare("SELECT COUNT(*) FROM import_rows WHERE import_id = ? AND status = 'rejected'");
                            $stmtReject->execute([$importId]);
                            $rejectCount = (int)$stmtReject->fetchColumn();
                        }
                        ?>
                        <tr>
                            <td><?= $importId ?></td>
                            <td><?= e($row['file_name'] ?? '—') ?></td>
                            <td><span class="status-pill <?= importBadgeClass((string)($row['status'] ?? 'unknown')) ?>"><?= e($row['status'] ?? 'unknown') ?></span></td>
                            <td><?= e($row['created_at'] ?? '') ?></td>
                            <td><?= $rowCount ?></td>
                            <td><?= $rejectCount ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="6">Aucun import enregistré.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>