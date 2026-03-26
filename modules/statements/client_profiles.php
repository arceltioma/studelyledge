<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'statements_export');

require_once __DIR__ . '/../../includes/header.php';

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        ORDER BY client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$fields = [
    'identity' => 'Identité',
    'contact' => 'Coordonnées',
    'countries' => 'Pays',
    'finance' => 'Rattachement financier',
    'accounts' => 'Comptes',
    'operations' => 'Historique opérations',
    'all' => 'Fiche complète',
];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar(
            'Fiches clients',
            'Exports orientés identité et profil, avec sélection de champs ou fiche complète.'
        ); ?>

        <form method="POST" action="<?= APP_URL ?>modules/statements/generate_bulk_pdf.php">
            <div class="dashboard-grid-2">
                <div class="form-card">
                    <h3 class="section-title">Clients</h3>
                    <?php foreach ($clients as $client): ?>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="client_ids[]" value="<?= (int)$client['id'] ?>">
                            <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-card">
                    <h3 class="section-title">Champs à inclure</h3>
                    <?php foreach ($fields as $key => $label): ?>
                        <label style="display:block;margin-bottom:8px;">
                            <input type="checkbox" name="fields[]" value="<?= e($key) ?>" <?= $key !== 'all' ? 'checked' : '' ?>>
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <input type="hidden" name="document_kind" value="profile">

            <div class="btn-group" style="margin-top:20px;">
                <button class="btn btn-primary">Exporter les fiches sélectionnées</button>
            </div>
        </form>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>