<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'clients_view');

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$countryFilter = trim((string)($_GET['country'] ?? ''));
$activeFilter = trim((string)($_GET['active'] ?? ''));

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
    )";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}

if ($statusFilter !== '') {
    $sql .= " AND c.client_status = ?";
    $params[] = $statusFilter;
}

if ($countryFilter !== '') {
    $sql .= " AND (
        c.country_origin = ?
        OR c.country_destination = ?
        OR c.country_commercial = ?
    )";
    array_push($params, $countryFilter, $countryFilter, $countryFilter);
}

if ($activeFilter === '1' || $activeFilter === '0') {
    $sql .= " AND COALESCE(c.is_active, 1) = ?";
    $params[] = (int)$activeFilter;
}

$sql .= " ORDER BY c.client_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statuses = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT client_status
        FROM clients
        WHERE client_status IS NOT NULL
          AND client_status <> ''
        ORDER BY client_status ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$countries = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT country_origin AS country_name
        FROM clients
        WHERE country_origin IS NOT NULL
          AND country_origin <> ''
        UNION
        SELECT DISTINCT country_destination AS country_name
        FROM clients
        WHERE country_destination IS NOT NULL
          AND country_destination <> ''
        UNION
        SELECT DISTINCT country_commercial AS country_name
        FROM clients
        WHERE country_commercial IS NOT NULL
          AND country_commercial <> ''
        ORDER BY country_name ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$pageTitle = 'Clients';
$pageSubtitle = 'Recherche, lecture rapide et accès aux fiches complètes.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h2>Liste des clients</h2>
                <p class="muted">Le cœur du projet : identité, rattachement financier, comptes et suivi.</p>
            </div>

            <div class="btn-group">
                <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/clients/client_create.php">Nouveau client</a>
                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">Import CSV</a>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="code, nom, email, téléphone, compte...">

                <select name="status">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="country">
                    <option value="">Tous les pays</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?= e($country) ?>" <?= $countryFilter === $country ? 'selected' : '' ?>>
                            <?= e($country) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="active">
                    <option value="">Tous les états</option>
                    <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Actifs</option>
                    <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Archivés</option>
                </select>

                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
            </form>
        </div>

        <div class="table-card">
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
                        <th>Statut</th>
                        <th>État</th>
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
                            <td><?= e($row['client_status'] ?? '') ?></td>
                            <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">Voir</a>
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="10">Aucun client trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>