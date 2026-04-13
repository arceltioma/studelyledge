<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'clients_view_page');
} else {
    enforcePagePermission($pdo, 'clients_view');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ta2.account_code AS monthly_treasury_account_code,
        ta2.account_label AS monthly_treasury_account_label
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN treasury_accounts ta2 ON ta2.id = c.monthly_treasury_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$bankAccount = null;

if (tableExists($pdo, 'client_bank_accounts') && tableExists($pdo, 'bank_accounts')) {
    $stmt = $pdo->prepare("
        SELECT ba.*
        FROM client_bank_accounts cba
        INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
        WHERE cba.client_id = ?
        ORDER BY cba.id ASC
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$bankAccount && tableExists($pdo, 'bank_accounts') && !empty($client['generated_client_account'])) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM bank_accounts
        WHERE account_number = ?
        LIMIT 1
    ");
    $stmt->execute([(string)$client['generated_client_account']]);
    $bankAccount = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pageTitle = 'Fiche client';
$pageSubtitle = 'Consultation complète du client, de son compte 411, du 512 principal et des paramètres de mensualité.';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div class="btn-group">
                <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>">Modifier</a>
                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">Retour</a>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Identité client</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Prénom</span><strong><?= e((string)($client['first_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom</span><strong><?= e((string)($client['last_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Email</span><strong><?= e((string)($client['email'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Téléphone</span><strong><?= e((string)($client['phone'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Adresse</span><strong><?= e((string)($client['postal_address'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Type client</span><strong><?= e((string)($client['client_type'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Compte client 411</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Compte généré</span><strong><?= e((string)($client['generated_client_account'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte bancaire lié</span><strong><?= e((string)($bankAccount['account_number'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom du compte</span><strong><?= e((string)($bankAccount['account_name'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde initial</span><strong><?= e(number_format((float)($bankAccount['initial_balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant</span><strong><?= e(number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Devise</span><strong><?= e((string)($client['currency'] ?? 'EUR')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Rattachement 512</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Compte 512 principal</span>
                        <strong><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? '')) ?: '—') ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Mensualité</span>
                        <strong><?= e(number_format((float)($client['monthly_amount'] ?? 0), 2, ',', ' ')) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Compte 512 mensualité</span>
                        <strong><?= e(trim((string)($client['monthly_treasury_account_code'] ?? '') . ' - ' . (string)($client['monthly_treasury_account_label'] ?? '')) ?: '—') ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Jour de mensualité</span>
                        <strong><?= e((string)($client['monthly_day'] ?? '26')) ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Mensualité active</span>
                        <strong><?= ((int)($client['monthly_enabled'] ?? 0) === 1) ? 'Oui' : 'Non' ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Dernière génération</span>
                        <strong><?= e((string)($client['monthly_last_generated_at'] ?? '—')) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Passeport</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Numéro</span><strong><?= e((string)($client['passport_number'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays de délivrance</span><strong><?= e((string)($client['passport_issue_country'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Date de délivrance</span><strong><?= e((string)($client['passport_issue_date'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Date d’expiration</span><strong><?= e((string)($client['passport_expiry_date'] ?? '—')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Zones</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Pays d'origine</span><strong><?= e((string)($client['country_origin'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays de destination</span><strong><?= e((string)($client['country_destination'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays commercial</span><strong><?= e((string)($client['country_commercial'] ?? '—')) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Métadonnées</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Créé le</span><strong><?= e((string)($client['created_at'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Mis à jour</span><strong><?= e((string)($client['updated_at'] ?? '—')) ?></strong></div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
