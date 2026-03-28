<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'imports_journal');

function importBadgeClass(string $status): string
{
    return match ($status) {
        'validated' => 'status-success',
        'validated_with_rejections' => 'status-warning',
        'rejected', 'failed', 'error' => 'status-danger',
        'processing', 'pending' => 'status-info',
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

$pageTitle = 'Journal des imports';
$pageSubtitle = 'Le tableau de bord des batchs, de leurs statuts et des lignes à reprendre.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flashSuccess !== ''): ?><div class="success"><?= e($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError !== ''): ?><div class="error"><?= e($flashError) ?></div><?php endif; ?>

        <?php if ($flashDetails): ?>
            <div class="warning">
                <strong>Détails :</strong>
                <ul>
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

        <div class="table-card">
            <div class="page-title page-title-inline">
                <div>
                    <h3 class="section-title">Historique</h3>
                    <p class="muted">Suivi des imports réalisés et accès aux rejets.</p>
                </div>

                <div class="btn-group">
                    <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/imports/import_preview.php">Nouveau relevé</a>
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
                        <th>Lignes</th>
                        <th>Rejets</th>
                        <th>Action</th>
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
                            <td>
                                <span class="status-pill <?= importBadgeClass((string)($row['status'] ?? 'unknown')) ?>">
                                    <?= e($row['status'] ?? 'unknown') ?>
                                </span>
                            </td>
                            <td><?= e($row['created_at'] ?? '') ?></td>
                            <td><?= $rowCount ?></td>
                            <td><?= $rejectCount ?></td>
                            <td>
                                <?php if ($rejectCount > 0): ?>
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/imports/rejected_rows.php?import_id=<?= $importId ?>">Voir rejets</a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="7">Aucun import enregistré.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>