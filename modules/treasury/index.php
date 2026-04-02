<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'treasury_view_page');
} else {
    enforcePagePermission($pdo, 'treasury_view');
}

$pageTitle = 'Comptes internes';
$pageSubtitle = 'Gestion des comptes de trésorerie internes';

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$rows = [];

if (tableExists($pdo, 'treasury_accounts')) {
    $labelColumn = columnExists($pdo, 'treasury_accounts', 'account_label') ? 'account_label' : 'account_code';
    $openingCol = columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : 'NULL';
    $currentCol = columnExists($pdo, 'treasury_accounts', 'current_balance') ? 'current_balance' : 'NULL';
    $activeCol = columnExists($pdo, 'treasury_accounts', 'is_active') ? 'is_active' : '1';

    $rows = $pdo->query("
        SELECT
            id,
            account_code,
            {$labelColumn} AS account_label,
            {$openingCol} AS opening_balance,
            {$currentCol} AS current_balance,
            {$activeCol} AS is_active
        FROM treasury_accounts
        ORDER BY account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$canCreate = currentUserCan($pdo, 'treasury_create') || currentUserCan($pdo, 'treasury_manage') || currentUserCan($pdo, 'admin_manage');
$canEdit = currentUserCan($pdo, 'treasury_edit') || currentUserCan($pdo, 'treasury_manage') || currentUserCan($pdo, 'admin_manage');
$canArchive = currentUserCan($pdo, 'treasury_delete') || currentUserCan($pdo, 'treasury_manage') || currentUserCan($pdo, 'admin_manage');
$canView = currentUserCan($pdo, 'treasury_view') || currentUserCan($pdo, 'treasury_manage') || currentUserCan($pdo, 'admin_manage');

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-hero">

            <?php if ($canCreate): ?>
                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/treasury/treasury_create.php" class="btn btn-success">+ Nouveau compte</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($flashSuccess !== ''): ?>
            <div class="success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError !== ''): ?>
            <div class="error"><?= e($flashError) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code compte</th>
                            <th>Intitulé</th>
                            <th>Solde ouverture</th>
                            <th>Solde courant</th>
                            <th>Statut</th>
                            <th style="width: 260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <?php $isActive = (int)($row['is_active'] ?? 1) === 1; ?>
                                <tr>
                                    <td><strong><?= e($row['account_code'] ?? '') ?></strong></td>
                                    <td><?= e($row['account_label'] ?? '') ?></td>
                                    <td><?= e(number_format((float)($row['opening_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                    <td><?= e(number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                    <td>
                                        <span class="<?= $isActive ? 'badge badge-success' : 'badge badge-danger' ?>">
                                            <?= $isActive ? 'Actif' : 'Archivé' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($canView): ?>
                                                <a href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)$row['id'] ?>" class="btn btn-secondary">
                                                    Voir
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($canEdit && $isActive): ?>
                                                <a href="<?= e(APP_URL) ?>modules/treasury/treasury_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-success">
                                                    Modifier
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($canArchive): ?>
                                                <a
                                                    href="<?= e(APP_URL) ?>modules/treasury/treasury_archive.php?id=<?= (int)$row['id'] ?>"
                                                    class="btn btn-danger"
                                                    onclick="return confirm('Confirmer le changement de statut de ce compte de trésorerie ?');"
                                                >
                                                    <?= $isActive ? 'Archiver' : 'Réactiver' ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">Aucun compte interne trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>