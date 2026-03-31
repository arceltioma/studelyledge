<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$search = trim((string)($_GET['search'] ?? ''));

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR c.email LIKE ?
        OR c.phone LIKE ?
        OR c.generated_client_account LIKE ?
        " . (columnExists($pdo, 'clients', 'postal_address') ? " OR c.postal_address LIKE ? " : "") . "
    )";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like, $like, $like];
    if (columnExists($pdo, 'clients', 'postal_address')) {
        $params[] = $like;
    }
}

$sql .= " ORDER BY c.client_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Clients';
$pageSubtitle = 'Recherche, lecture rapide et gestion homogène des clients.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h1>Liste des clients</h1>
                <p class="muted">Identité, coordonnées, rattachement financier et accès rapide.</p>
            </div>

            <div class="btn-group">
                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_create_page') : currentUserCan($pdo, 'clients_create')): ?>
                    <a class="btn btn-primary" href="<?= APP_URL ?>modules/clients/client_create.php">Nouveau client</a>
                <?php endif; ?>

                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'imports_upload_page') : currentUserCan($pdo, 'clients_create')): ?>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/import_clients_csv.php">Import CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <div>
                    <label>Recherche</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="code, nom, email, téléphone, adresse, compte...">
                </div>

                <div class="btn-group" style="margin-top:26px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= APP_URL ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Code client</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Adresse postale</th>
                        <th>Compte client</th>
                        <th>Compte interne lié</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $isActive = ((int)($row['is_active'] ?? 1) === 1); ?>
                        <tr>
                            <td><?= e($row['client_code'] ?? '') ?></td>
                            <td><?= e($row['full_name'] ?? '') ?></td>
                            <td><?= e($row['email'] ?? '') ?></td>
                            <td><?= e($row['phone'] ?? '') ?></td>
                            <td><?= columnExists($pdo, 'clients', 'postal_address') ? e($row['postal_address'] ?? '') : '' ?></td>
                            <td><?= e($row['generated_client_account'] ?? '') ?></td>
                            <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                            <td>
                                <span class="status-pill <?= $isActive ? 'status-success' : 'status-warning' ?>">
                                    <?= $isActive ? 'Actif' : 'Archivé' ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-primary" href="<?= APP_URL ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">Voir</a>

                                    <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_edit_page') : currentUserCan($pdo, 'clients_edit')): ?>
                                        <a class="btn btn-success" href="<?= APP_URL ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    <?php endif; ?>

                                    <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_archive_page') : currentUserCan($pdo, 'clients_edit')): ?>
                                        <?php if ($isActive): ?>
                                            <a class="btn btn-danger" href="<?= APP_URL ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=archive">Archiver</a>
                                        <?php else: ?>
                                            <a class="btn btn-danger" href="<?= APP_URL ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=restore">Réactiver</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="9">Aucun client trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>