<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'statements_view_page');
} else {
    enforcePagePermission($pdo, 'statements_export');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Relevés de comptes';
$pageSubtitle = 'Prévisualisation puis génération des relevés PDF, en unitaire ou en masse';

if (!function_exists('as_like')) {
    function as_like(string $value): string
    {
        return '%' . trim($value) . '%';
    }
}

if (!function_exists('as_valid_date')) {
    function as_valid_date(string $value): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterCountry = trim((string)($_GET['filter_country'] ?? ''));

$prefillClientId = (int)($_GET['prefill_client_id'] ?? 0);
$prefillDateFrom = trim((string)($_GET['prefill_date_from'] ?? date('Y-m-01')));
$prefillDateTo = trim((string)($_GET['prefill_date_to'] ?? date('Y-m-t')));

if (!as_valid_date($prefillDateFrom)) {
    $prefillDateFrom = date('Y-m-01');
}
if (!as_valid_date($prefillDateTo)) {
    $prefillDateTo = date('Y-m-t');
}
if ($prefillDateFrom > $prefillDateTo) {
    [$prefillDateFrom, $prefillDateTo] = [$prefillDateTo, $prefillDateFrom];
}

$sqlClients = "
    SELECT id, client_code, full_name, country_commercial, generated_client_account, client_type
    FROM clients
    WHERE COALESCE(is_active,1)=1
";
$paramsClients = [];

if ($filterSearch !== '') {
    $sqlClients .= "
        AND (
            client_code LIKE ?
            OR full_name LIKE ?
            OR COALESCE(generated_client_account,'') LIKE ?
        )
    ";
    $paramsClients[] = as_like($filterSearch);
    $paramsClients[] = as_like($filterSearch);
    $paramsClients[] = as_like($filterSearch);
}

if ($filterCountry !== '') {
    $sqlClients .= " AND COALESCE(country_commercial,'') = ? ";
    $paramsClients[] = $filterCountry;
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

$previewPdf = (string)($_SESSION['account_statements_preview_pdf'] ?? '');

if (isset($_GET['cancel_preview']) && $_GET['cancel_preview'] === '1') {
    unset($_SESSION['account_statements_preview_pdf']);
    $_SESSION['success_message'] = 'Prévisualisation annulée.';
    header('Location: ' . APP_URL . 'modules/statements/account_statements.php');
    exit;
}

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
                    <input type="hidden" name="prefill_date_from" value="<?= e($prefillDateFrom) ?>">
                    <input type="hidden" name="prefill_date_to" value="<?= e($prefillDateTo) ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input
                                type="text"
                                name="filter_search"
                                value="<?= e($filterSearch) ?>"
                                placeholder="Code client, nom, compte 411..."
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
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/statements/account_statements.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 class="section-title">Contexte de prévisualisation</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Client pré-sélectionné</span>
                        <strong><?= $prefillClientId > 0 ? (int)$prefillClientId : '—' ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Période</span>
                        <strong><?= e($prefillDateFrom) ?> → <?= e($prefillDateTo) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Clients visibles</span>
                        <strong><?= count($clients) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Préparer l’export</h3>

                <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="document_kind" value="statement">

                    <div class="dashboard-grid-2" style="margin-bottom:16px;">
                        <div>
                            <label>Du</label>
                            <input type="date" name="date_from" value="<?= e($prefillDateFrom) ?>" required>
                        </div>
                        <div>
                            <label>Au</label>
                            <input type="date" name="date_to" value="<?= e($prefillDateTo) ?>" required>
                        </div>
                    </div>

                    <div class="sl-table-wrap" style="max-height:520px; overflow:auto;">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Code</th>
                                    <th>Nom</th>
                                    <th>Pays commercial</th>
                                    <th>Compte 411</th>
                                    <th>Type</th>
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
                                            <td><?= e((string)($client['generated_client_account'] ?? '—')) ?></td>
                                            <td><?= e((string)($client['client_type'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">Aucun client disponible.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="mode" value="preview_account_statement" class="btn btn-secondary">Prévisualiser l’opération demandée</button>
                        <button type="submit" name="mode" value="generate_account_statement" class="btn btn-success">Générer le/les PDF</button>
                        <a href="<?= e(APP_URL) ?>modules/statements/account_statements.php?cancel_preview=1" class="btn btn-outline">Annuler</a>
                        <a href="<?= e(APP_URL) ?>modules/statements/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Qualité visuelle</h3>

                <?php if ($previewPdf !== ''): ?>
                    <div style="height:780px; border:1px solid rgba(148,163,184,0.18); border-radius:14px; overflow:hidden;">
                        <iframe
                            src="<?= e(APP_URL . ltrim($previewPdf, '/')) ?>"
                            style="width:100%; height:100%; border:none; overflow:auto;"
                            title="Aperçu PDF relevés"
                        ></iframe>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <a class="btn btn-secondary" href="<?= e(APP_URL . ltrim($previewPdf, '/')) ?>" target="_blank">Ouvrir l’aperçu</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Clique sur <strong>Prévisualiser l’opération demandée</strong> pour afficher ici le relevé avant génération finale.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>