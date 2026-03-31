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

$selectFields = [
    'c.id',
    'c.client_code',
    'c.full_name',
    'c.first_name',
    'c.last_name',
    'c.client_type',
    'c.country_commercial',
    'c.generated_client_account',
    'ba.balance AS current_balance'
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
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (
        c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR c.generated_client_account LIKE ?";

    if (columnExists($pdo, 'clients', 'postal_address')) {
        $sql .= " OR c.postal_address LIKE ?";
    }

    $sql .= ")";

    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];

    if (columnExists($pdo, 'clients', 'postal_address')) {
        $params[] = $like;
    }
}

$sql .= " ORDER BY c.client_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Fiches clients PDF';
$pageSubtitle = 'Compose des fiches clients élégantes et choisis précisément les données à exporter.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card-grid" style="margin-bottom:20px;">
            <div class="card">
                <h3>Rendu premium</h3>
                <div class="dashboard-note">Logo, cartes d’information, synthèse visuelle et meilleure hiérarchie de lecture.</div>
            </div>
            <div class="card">
                <h3>Export à la carte</h3>
                <div class="dashboard-note">Choisis uniquement les blocs à afficher dans la fiche PDF finale.</div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Filtrer les clients</h3>

                <form method="GET" class="inline-form">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Code client, nom, adresse, 411...">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                    </div>
                </form>

                <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php" style="margin-top:20px;">
                    <?= function_exists('csrf_input') ? csrf_input() : '' ?>
                    <input type="hidden" name="document_kind" value="profile">

                    <div class="card" style="margin-bottom:18px; background:#f8fbff;">
                        <h3 class="section-title">Données à inclure dans la fiche</h3>
                        <div class="card-grid">
                            <label class="block-option"><input type="checkbox" name="fields[]" value="all" checked> <span>Toutes les données</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="identity"> <span>Identité</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="contact"> <span>Contact + adresse</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="countries"> <span>Pays</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="client_account"> <span>Compte 411</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="treasury_account"> <span>Compte 512 lié</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="currency"> <span>Devise</span></label>
                            <label class="block-option"><input type="checkbox" name="fields[]" value="balances"> <span>Soldes</span></label>
                        </div>
                    </div>

                    <div class="dashboard-note" style="margin-bottom:16px;">
                        Sélectionne un ou plusieurs clients pour générer des fiches PDF modernes et professionnelles.
                    </div>

                    <div class="table-card" style="padding:0; box-shadow:none;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="select_all_profiles"></th>
                                    <th>Code</th>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Pays commercial</th>
                                    <th>Adresse postale</th>
                                    <th>Compte 411</th>
                                    <th>Solde courant</th>
                                    <th>Action rapide</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><input type="checkbox" name="client_ids[]" value="<?= (int)$client['id'] ?>" class="profile-checkbox"></td>
                                        <td><?= e($client['client_code'] ?? '') ?></td>
                                        <td><?= e($client['full_name'] ?? trim((string)($client['first_name'] ?? '') . ' ' . (string)($client['last_name'] ?? ''))) ?></td>
                                        <td><?= e($client['client_type'] ?? '') ?></td>
                                        <td><?= e($client['country_commercial'] ?? '') ?></td>
                                        <td><?= columnExists($pdo, 'clients', 'postal_address') ? e($client['postal_address'] ?? '') : '' ?></td>
                                        <td><?= e($client['generated_client_account'] ?? '') ?></td>
                                        <td><?= number_format((float)($client['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                                        <td>
                                            <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php?single=1&document_kind=profile&client_id=<?= (int)$client['id'] ?>">
                                                PDF unitaire
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
                        <button type="submit" class="btn btn-primary">Générer les fiches PDF</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Aperçu du style</h3>
                <div class="dashboard-note">
                    Le nouveau rendu met davantage en valeur l’identité client avec un en-tête premium, des cartes plus lisibles et une structure plus élégante.
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select_all_profiles');
            const boxes = Array.from(document.querySelectorAll('.profile-checkbox'));
            const allBox = document.querySelector('input[name="fields[]"][value="all"]');
            const fieldBoxes = Array.from(document.querySelectorAll('input[name="fields[]"]')).filter(el => el.value !== 'all');

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    boxes.forEach(box => box.checked = selectAll.checked);
                });
            }

            if (allBox) {
                allBox.addEventListener('change', function () {
                    if (allBox.checked) {
                        fieldBoxes.forEach(box => box.checked = false);
                    }
                });
            }

            fieldBoxes.forEach(box => {
                box.addEventListener('change', function () {
                    if (box.checked && allBox) {
                        allBox.checked = false;
                    }
                });
            });
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>