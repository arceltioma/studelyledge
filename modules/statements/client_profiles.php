<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'client_profiles_view');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Fiches clients';
$pageSubtitle = 'Prévisualisation puis génération des fiches PDF, en unitaire ou en masse';

if (!function_exists('cp_like')) {
    function cp_like(string $value): string
    {
        return '%' . trim($value) . '%';
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterCountry = trim((string)($_GET['filter_country'] ?? ''));
$filterClientType = trim((string)($_GET['filter_client_type'] ?? ''));

$prefillClientId = (int)($_GET['prefill_client_id'] ?? 0);

$sqlClients = "
    SELECT id, client_code, full_name, country_commercial, client_type, email, phone, generated_client_account
    FROM clients
    WHERE COALESCE(is_active,1)=1
";
$paramsClients = [];

if ($filterSearch !== '') {
    $sqlClients .= "
        AND (
            client_code LIKE ?
            OR full_name LIKE ?
            OR COALESCE(email,'') LIKE ?
            OR COALESCE(phone,'') LIKE ?
            OR COALESCE(generated_client_account,'') LIKE ?
        )
    ";
    $paramsClients[] = cp_like($filterSearch);
    $paramsClients[] = cp_like($filterSearch);
    $paramsClients[] = cp_like($filterSearch);
    $paramsClients[] = cp_like($filterSearch);
    $paramsClients[] = cp_like($filterSearch);
}

if ($filterCountry !== '') {
    $sqlClients .= " AND COALESCE(country_commercial,'') = ? ";
    $paramsClients[] = $filterCountry;
}

if ($filterClientType !== '') {
    $sqlClients .= " AND COALESCE(client_type,'') = ? ";
    $paramsClients[] = $filterClientType;
}

$sqlClients .= " ORDER BY client_code ASC ";

$stmtClients = $pdo->prepare($sqlClients);
$stmtClients->execute($paramsClients);
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

$countryOptions = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT country_commercial
        FROM clients
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(country_commercial,'') <> ''
        ORDER BY country_commercial ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$clientTypeOptions = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT DISTINCT client_type
        FROM clients
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(client_type,'') <> ''
        ORDER BY client_type ASC
    ")->fetchAll(PDO::FETCH_COLUMN)
    : [];

$previewPdf = (string)($_SESSION['client_profiles_preview_pdf'] ?? '');

if (isset($_GET['cancel_preview']) && $_GET['cancel_preview'] === '1') {
    unset($_SESSION['client_profiles_preview_pdf']);
    $_SESSION['success_message'] = 'Prévisualisation annulée.';
    header('Location: ' . APP_URL . 'modules/statements/client_profiles.php');
    exit;
}

$fields = [
    'identity' => 'Identité',
    'contact' => 'Coordonnées',
    'countries' => 'Pays',
    'treasury_account' => 'Compte 512 lié',
    'currency' => 'Devise',
    'client_account' => 'Compte client généré',
    'balances' => 'Soldes',
    'postal_address' => 'Adresse postale',
    'passport_number' => 'Numéro de passport',
    'passport_issue_country' => 'Lieu de délivrance du passport',
    'passport_issue_date' => 'Date de délivrance du passport',
    'passport_expiry_date' => 'Date d’expiration du passport',
    'email' => 'Email',
    'phone' => 'Téléphone',
    'client_type' => 'Type de client',
    'country_origin' => 'Pays d’origine',
    'country_destination' => 'Pays de destination',
    'country_commercial' => 'Pays commercial',
    'all' => 'Fiche complète',
];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="success"><?= e((string)$_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="error"><?= e((string)$_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Filtres clients</h3>

                <form method="GET">
                    <input type="hidden" name="prefill_client_id" value="<?= (int)$prefillClientId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input
                                type="text"
                                name="filter_search"
                                value="<?= e($filterSearch) ?>"
                                placeholder="Code, nom, email, téléphone, compte 411..."
                            >
                        </div>

                        <div>
                            <label>Pays commercial</label>
                            <select name="filter_country">
                                <option value="">Tous</option>
                                <?php foreach ($countryOptions as $country): ?>
                                    <option value="<?= e((string)$country) ?>" <?= $filterCountry === (string)$country ? 'selected' : '' ?>>
                                        <?= e((string)$country) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Type client</label>
                            <select name="filter_client_type">
                                <option value="">Tous</option>
                                <?php foreach ($clientTypeOptions as $clientType): ?>
                                    <option value="<?= e((string)$clientType) ?>" <?= $filterClientType === (string)$clientType ? 'selected' : '' ?>>
                                        <?= e((string)$clientType) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/statements/client_profiles.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">Synthèse</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Client pré-sélectionné</span>
                        <strong><?= $prefillClientId > 0 ? (int)$prefillClientId : '—' ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Clients affichés</span>
                        <strong><?= count($clients) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Pays commercial</span>
                        <strong><?= e($filterCountry !== '' ? $filterCountry : 'Tous') ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Type client</span>
                        <strong><?= e($filterClientType !== '' ? $filterClientType : 'Tous') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Préparer l’export</h3>

                <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="document_kind" value="profile">

                    <div class="sl-table-wrap" style="max-height:420px; overflow:auto;">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Code</th>
                                    <th>Nom</th>
                                    <th>Pays commercial</th>
                                    <th>Type</th>
                                    <th>Compte 411</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($clients): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr class="<?= $prefillClientId === (int)$client['id'] ? 'table-row-highlight' : '' ?>">
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    name="client_ids[]"
                                                    value="<?= (int)$client['id'] ?>"
                                                    <?= $prefillClientId === (int)$client['id'] ? 'checked' : '' ?>
                                                >
                                            </td>
                                            <td><?= e((string)$client['client_code']) ?></td>
                                            <td><?= e((string)$client['full_name']) ?></td>
                                            <td><?= e((string)($client['country_commercial'] ?? '—')) ?></td>
                                            <td><?= e((string)($client['client_type'] ?? '—')) ?></td>
                                            <td><?= e((string)($client['generated_client_account'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">Aucun client disponible.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:18px;">
                        <label>Champs exportés</label>
                        <div class="dashboard-grid-2">
                            <?php foreach ($fields as $key => $label): ?>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="checkbox" name="fields[]" value="<?= e($key) ?>" <?= $key === 'all' ? '' : 'checked' ?>>
                                    <?= e($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="mode" value="preview_client_profiles" class="btn btn-secondary">Prévisualiser l’opération demandée</button>
                        <button type="submit" name="mode" value="generate_client_profiles" class="btn btn-success">Générer le/les PDF</button>
                        <a href="<?= e(APP_URL) ?>modules/statements/client_profiles.php?cancel_preview=1" class="btn btn-outline">Annuler</a>
                        <a href="<?= e(APP_URL) ?>modules/statements/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Aperçu du style</h3>

                <?php if ($previewPdf !== ''): ?>
                    <div style="height:780px; border:1px solid rgba(148,163,184,0.18); border-radius:14px; overflow:hidden;">
                        <iframe
                            src="<?= e(APP_URL . ltrim($previewPdf, '/')) ?>"
                            style="width:100%; height:100%; border:none; overflow:auto;"
                            title="Aperçu PDF fiches clients"
                        ></iframe>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <a class="btn btn-secondary" href="<?= e(APP_URL . ltrim($previewPdf, '/')) ?>" target="_blank">Ouvrir l’aperçu</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Clique sur <strong>Prévisualiser l’opération demandée</strong> pour afficher ici la fiche PDF avant génération.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
