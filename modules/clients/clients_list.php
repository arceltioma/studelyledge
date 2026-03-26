<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'clients_view');

require_once __DIR__ . '/../../includes/header.php';

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
    )";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like, $like];
}

$sql .= " ORDER BY c.client_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Clients',
            'Le cœur du projet : identité, rattachement financier, comptes, suivi.'
        ); ?>

        <div class="page-title">
            <div>
                <h1>Liste des clients</h1>
                <p class="muted">Recherche, lecture rapide et accès aux fiches complètes.</p>
            </div>

            <div class="btn-group">
                <a class="btn btn-primary" href="<?= APP_URL ?>modules/clients/client_create.php">Nouveau client</a>
                <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/import_clients_csv.php">Import CSV</a>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <div>
                    <label>Recherche</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="code, nom, email, téléphone...">
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
                        <th>Devise</th>
                        <th>Compte client</th>
                        <th>Compte interne lié</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['client_code'] ?? '') ?></td>
                            <td><?= e($row['full_name'] ?? '') ?></td>
                            <td><?= e($row['email'] ?? '') ?></td>
                            <td><?= e($row['phone'] ?? '') ?></td>
                            <td><?= e($row['currency'] ?? '') ?></td>
                            <td><?= e($row['generated_client_account'] ?? '') ?></td>
                            <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                                    <a class="btn btn-secondary" href="<?= APP_URL ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="8">Aucun client trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>