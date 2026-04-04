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

$pageTitle = 'Fiches clients';
$pageSubtitle = 'Prévisualisation puis génération des fiches PDF, en unitaire ou en masse';

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name, country_commercial, client_type
        FROM clients
        WHERE COALESCE(is_active,1)=1
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($clients): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td><input type="checkbox" name="client_ids[]" value="<?= (int)$client['id'] ?>"></td>
                                            <td><?= e((string)$client['client_code']) ?></td>
                                            <td><?= e((string)$client['full_name']) ?></td>
                                            <td><?= e((string)($client['country_commercial'] ?? '—')) ?></td>
                                            <td><?= e((string)($client['client_type'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5">Aucun client disponible.</td></tr>
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