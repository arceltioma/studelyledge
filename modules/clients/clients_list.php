<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_view');

$search = trim((string)($_GET['search'] ?? ''));
$countryCommercial = trim((string)($_GET['country_commercial'] ?? ''));
$clientType = trim((string)($_GET['client_type'] ?? ''));
$isActive = trim((string)($_GET['is_active'] ?? ''));

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];

$canCreate = currentUserCan($pdo, 'clients_create');
$canEdit = currentUserCan($pdo, 'clients_edit');
$canDelete = currentUserCan($pdo, 'clients_delete');

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ba.account_number AS bank_account_number,
        ba.initial_balance AS bank_initial_balance,
        ba.balance AS bank_current_balance,
        s.name AS status_name
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
    LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
    LEFT JOIN statuses s ON s.id = c.status_id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            c.client_code LIKE ?
            OR c.first_name LIKE ?
            OR c.last_name LIKE ?
            OR c.full_name LIKE ?
            OR c.generated_client_account LIKE ?
            OR ta.account_code LIKE ?
        )
    ";
    $searchLike = '%' . $search . '%';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
}

if ($countryCommercial !== '') {
    $sql .= " AND c.country_commercial = ? ";
    $params[] = $countryCommercial;
}

if ($clientType !== '') {
    $sql .= " AND c.client_type = ? ";
    $params[] = $clientType;
}

if ($isActive !== '') {
    $sql .= " AND COALESCE(c.is_active,1) = ? ";
    $params[] = (int)$isActive;
}

$sql .= " ORDER BY c.client_code ASC, c.id ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalClients = count($clients);
$totalCurrentBalance = 0.0;
$totalInitialBalance = 0.0;

foreach ($clients as $client) {
    $totalCurrentBalance += (float)($client['bank_current_balance'] ?? 0);
    $totalInitialBalance += (float)($client['bank_initial_balance'] ?? 0);
}

$pageTitle = 'Liste des clients';
$pageSubtitle = 'Vue consolidée des clients avec code client, compte 411, compte 512 lié, pays commercial, soldes et actions de gestion.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if ($canCreate): ?>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_create.php" class="btn btn-primary">Nouveau client</a>
                    <a href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php" class="btn btn-outline">Importer CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input
                                type="text"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Code client, nom, 411, 512..."
                            >
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <select name="country_commercial">
                                <option value="">Tous</option>
                                <?php foreach ($commercialCountries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= $countryCommercial === $country ? 'selected' : '' ?>>
                                        <?= e($country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type client</label>
                            <select name="client_type">
                                <option value="">Tous</option>
                                <?php foreach ($clientTypes as $type): ?>
                                    <option value="<?= e($type) ?>" <?= $clientType === $type ? 'selected' : '' ?>>
                                        <?= e($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>État</label>
                            <select name="is_active">
                                <option value="">Tous</option>
                                <option value="1" <?= $isActive === '1' ? 'selected' : '' ?>>Actifs</option>
                                <option value="0" <?= $isActive === '0' ? 'selected' : '' ?>>Inactifs</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Synthèse</h3>

                <div class="stat-row">
                    <span class="metric-label">Nombre de clients</span>
                    <span class="metric-value"><?= (int)$totalClients ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Total soldes initiaux 411</span>
                    <span class="metric-value"><?= number_format($totalInitialBalance, 2, ',', ' ') ?></span>
                </div>

                <div class="stat-row">
                    <span class="metric-label">Total soldes courants 411</span>
                    <span class="metric-value"><?= number_format($totalCurrentBalance, 2, ',', ' ') ?></span>
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Code client</th>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Pays commercial</th>
                        <th>Compte 411</th>
                        <th>Compte 512 lié</th>
                        <th>Solde initial</th>
                        <th>Solde courant</th>
                        <th>Statut</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <?php $isClientActive = ((int)($client['is_active'] ?? 1) === 1); ?>
                        <tr>
                            <td><?= e($client['client_code'] ?? '') ?></td>
                            <td><?= e($client['full_name'] ?? trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''))) ?></td>
                            <td><?= e($client['client_type'] ?? '') ?></td>
                            <td><?= e($client['country_commercial'] ?? '') ?></td>
                            <td><?= e($client['generated_client_account'] ?? $client['bank_account_number'] ?? '') ?></td>
                            <td><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?></td>
                            <td><?= number_format((float)($client['bank_initial_balance'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= number_format((float)($client['bank_current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= e($client['status_name'] ?? $client['client_status'] ?? '') ?></td>
                            <td><?= $isClientActive ? 'Actif' : 'Inactif' ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$client['id'] ?>" class="btn btn-secondary">
                                        Voir
                                    </a>

                                    <?php if ($canEdit): ?>
                                        <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$client['id'] ?>" class="btn btn-outline">
                                            Modifier
                                        </a>

                                        <?php if ($isClientActive): ?>
                                            <a
                                                href="<?= e(APP_URL) ?>modules/clients/clients_archive.php?id=<?= (int)$client['id'] ?>&action=archive"
                                                class="btn btn-warning"
                                                onclick="return confirm('Archiver ce client ?');"
                                            >
                                                Archiver
                                            </a>
                                        <?php else: ?>
                                            <a
                                                href="<?= e(APP_URL) ?>modules/clients/clients_archive.php?id=<?= (int)$client['id'] ?>&action=restore"
                                                class="btn btn-success"
                                                onclick="return confirm('Réactiver ce client ?');"
                                            >
                                                Réactiver
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($canDelete): ?>
                                        <a
                                            href="<?= e(APP_URL) ?>modules/clients/client_delete.php?id=<?= (int)$client['id'] ?>"
                                            class="btn btn-danger"
                                            onclick="return confirm('Ouvrir la page de suppression de ce client ?');"
                                        >
                                            Supprimer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$clients): ?>
                        <tr>
                            <td colspan="11">Aucun client trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>