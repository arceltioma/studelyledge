<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'statements_export');

$clients = tableExists($pdo, 'clients')
    ? $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        WHERE COALESCE(is_active,1)=1
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

$pageTitle = 'Fiches clients';
$pageSubtitle = 'Exports orientés identité et profil, avec sélection de champs ou fiche complète.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <form method="POST" action="<?= e(APP_URL) ?>modules/statements/generate_bulk_pdf.php">
            <?= csrf_input() ?>

            <div class="dashboard-grid-2">
                <div class="form-card">
                    <h3 class="section-title">Clients</h3>
                    <?php foreach ($clients as $client): ?>
                        <label class="block-option">
                            <input type="checkbox" name="client_ids[]" value="<?= (int)$client['id'] ?>">
                            <?= e(($client['client_code'] ?? '') . ' - ' . ($client['full_name'] ?? '')) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-card">
                    <h3 class="section-title">Champs à inclure</h3>
                    <?php foreach ($fields as $key => $label): ?>
                        <label class="block-option">
                            <input type="checkbox" name="fields[]" value="<?= e($key) ?>" <?= $key !== 'all' ? 'checked' : '' ?>>
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <input type="hidden" name="document_kind" value="profile">

            <div class="btn-group">
                <button class="btn btn-primary" type="submit">Exporter les fiches sélectionnées</button>
            </div>
        </form>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>