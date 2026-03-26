<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'imports_journal');

require_once __DIR__ . '/../../includes/header.php';

$importId = (int)($_GET['import_id'] ?? 0);

if ($importId <= 0) {
    exit('Import invalide.');
}

$import = null;
if (tableExists($pdo, 'imports')) {
    $stmtImport = $pdo->prepare("
        SELECT *
        FROM imports
        WHERE id = ?
        LIMIT 1
    ");
    $stmtImport->execute([$importId]);
    $import = $stmtImport->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$import) {
    exit('Import introuvable.');
}

$rows = [];
if (tableExists($pdo, 'import_rows')) {
    $stmtRows = $pdo->prepare("
        SELECT *
        FROM import_rows
        WHERE import_id = ?
          AND status = 'rejected'
        ORDER BY id DESC
    ");
    $stmtRows->execute([$importId]);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Lignes rejetées',
            'Les lignes rejetées restent visibles tant qu’elles n’ont pas été corrigées proprement.'
        ); ?>

        <div class="page-title">
            <div>
                <h1>Import #<?= (int)$import['id'] ?></h1>
                <p class="muted">Fichier : <?= e($import['file_name'] ?? '') ?></p>
            </div>

            <div class="btn-group">
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/imports/import_journal.php">Retour journal</a>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID ligne</th>
                        <th>Statut</th>
                        <th>Erreur</th>
                        <th>Donnée brute</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><span class="status-pill status-danger"><?= e($row['status'] ?? '') ?></span></td>
                            <td><?= e($row['error_message'] ?? '') ?></td>
                            <td>
                                <pre style="white-space:pre-wrap;max-width:520px;"><?= e($row['raw_data'] ?? '') ?></pre>
                            </td>
                            <td>
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/imports/correct_rejected_row.php?id=<?= (int)$row['id'] ?>">Corriger</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="5">Aucune ligne rejetée ouverte.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>