<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceAccess($pdo, 'imports_journal_page');

$rows = tableExists($pdo, 'import_batches')
    ? $pdo->query("
        SELECT *
        FROM import_batches
        ORDER BY created_at DESC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Journal des imports';
$pageSubtitle = 'Historique des traitements, validations et rejets.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="table-card">
            <h1>Journal des imports</h1>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Fichier</th>
                        <th>Type</th>
                        <th>Lignes</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['created_at'] ?? '') ?></td>
                            <td><?= e($row['source_filename'] ?? '') ?></td>
                            <td><?= e($row['batch_type'] ?? '') ?></td>
                            <td><?= (int)($row['rows_count'] ?? 0) ?></td>
                            <td><?= e($row['status'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="5">Aucun import enregistré.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>