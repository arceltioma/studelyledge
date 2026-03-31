<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'statements_export_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$selectFields = [
    'c.id',
    'c.client_code',
    'c.full_name',
    'c.first_name',
    'c.last_name',
    'c.country_commercial',
    'c.generated_client_account',
    'ba.balance AS current_balance',
    'COUNT(o.id) AS operations_count'
];

if (columnExists($pdo, 'clients', 'postal_address')) {
    $selectFields[] = 'c.postal_address';
}

$sql = "
    SELECT
        " . implode(",\n        ", $selectFields) . "
    FROM clients c
    LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
    LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
    LEFT JOIN operations o ON o.client_id = c.id
";
$params = [];
$where = ["1=1"];

if ($search !== '') {
    $searchClause = "(c.client_code LIKE ? OR c.full_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.generated_client_account LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];

    if (columnExists($pdo, 'clients', 'postal_address')) {
        $searchClause .= " OR c.postal_address LIKE ?";
        $params[] = $like;
    }

    $searchClause .= ")";
    $where[] = $searchClause;
}

if ($dateFrom !== '') {
    $where[] = "(o.operation_date IS NULL OR o.operation_date >= ?)";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "(o.operation_date IS NULL OR o.operation_date <= ?)";
    $params[] = $dateTo;
}

$sql .= " WHERE " . implode(' AND ', $where);
$sql .= "
    GROUP BY
        c.id, c.client_code, c.full_name, c.first_name, c.last_name,
        c.country_commercial, c.generated_client_account, ba.balance
";

if (columnExists($pdo, 'clients', 'postal_address')) {
    $sql .= ", c.postal_address";
}

$sql .= " ORDER BY c.client_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Relevés de compte PDF';
$pageSubtitle = 'Prépare des relevés modernes avec chronologie des mouvements et suivi du solde.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid" style="margin-bottom:20px;">
            <div class="card">
                <h3>Style bancaire</h3>
                <div class="dashboard-note">Débit, crédit, solde initial, solde après chaque ligne et solde final.</div>
            </div>
            <div class="card">
                <h3>Export rapide</h3>
                <div class="dashboard-note">PDF unitaire en vert pour un accès immédiat, ou export multi-clients en un clic.</div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Préparer les relevés</h3>

                <form method="GET" class="inline-form">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code client, nom, adresse, 411...">
                    </div>

                    <div>
                        <label>Date début</label>
                        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                    </div>

                    <div>
                        <label>Date fin</label>
                        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                    </div>
                </form>

                <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php" style="margin-top:20px;">
                    <?= function_exists('csrf_input') ? csrf_input() : '' ?>
                    <input type="hidden" name="document_kind" value="statement">
                    <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
                    <input type="hidden" name="date_to" value="<?= e($dateTo) ?>">

                    <div class="dashboard-note" style="margin-bottom:16px;">
                        Les relevés générés ont un rendu inspiré des relevés bancaires professionnels avec en-tête premium et suivi du solde ligne par ligne.
                    </div>

                    <div class="table-card" style="padding:0; box-shadow:none;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="select_all_statements"></th>
                                    <th>Code</th>
                                    <th>Nom</th>
                                    <th>Adresse postale</th>
                                    <th>Pays commercial</th>
                                    <th>Compte 411</th>
                                    <th>Nb opérations</th>
                                    <th>Solde courant</th>
                                    <th>Action rapide</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><input type="checkbox" name="client_ids[]" value="<?= (int)$client['id'] ?>" class="statement-checkbox"></td>
                                        <td><?= e($client['client_code'] ?? '') ?></td>
                                        <td><?= e($client['full_name'] ?? trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''))) ?></td>
                                        <td><?= columnExists($pdo, 'clients', 'postal_address') ? e($client['postal_address'] ?? '') : '' ?></td>
                                        <td><?= e($client['country_commercial'] ?? '') ?></td>
                                        <td><?= e($client['generated_client_account'] ?? '') ?></td>
                                        <td><?= (int)($client['operations_count'] ?? 0) ?></td>
                                        <td><?= number_format((float)($client['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                                        <td>
                                            <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php?single=1&document_kind=statement&client_id=<?= (int)$client['id'] ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                                PDF unitaire
                                            </a>
                                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/statements/client_statement.php?client_id=<?= (int)$client['id'] ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                                Consulter
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$clients): ?>
                                    <tr><td colspan="9">Aucun client trouvé.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary">Générer les relevés PDF</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Qualité visuelle</h3>
                <div class="dashboard-note">
                    La page garde le flux d’export existant tout en ajoutant l’adresse postale pour une lecture client plus complète.
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select_all_statements');
            const boxes = Array.from(document.querySelectorAll('.statement-checkbox'));

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    boxes.forEach(box => box.checked = selectAll.checked);
                });
            }
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>