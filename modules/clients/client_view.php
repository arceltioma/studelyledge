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
    SELECT c.*, ta.account_code AS treasury_account_code, ta.account_label AS treasury_account_label
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$operations = tableExists($pdo, 'operations')
    ? (function () use ($pdo, $id) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM operations
            WHERE client_id = ?
            ORDER BY operation_date DESC, id DESC
            LIMIT 20
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    })()
    : [];

$timeline = function_exists('getEntityTimeline') ? getEntityTimeline($pdo, 'client', $id, 20) : [];

$pageTitle = 'Fiche client';
$pageSubtitle = 'Vue détaillée, identité, rattachement financier, passeport et historique';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Informations générales</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Email</span><strong><?= e((string)($client['email'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Téléphone</span><strong><?= e((string)($client['phone'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Adresse</span><strong><?= e((string)($client['postal_address'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Type de client</span><strong><?= e((string)($client['client_type'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Devise</span><strong><?= e((string)($client['currency'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Rattachement géographique et financier</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Pays d’origine</span><strong><?= e((string)($client['country_origin'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays de destination</span><strong><?= e((string)($client['country_destination'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays commercial</span><strong><?= e((string)($client['country_commercial'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte 411 généré</span><strong><?= e((string)($client['generated_client_account'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte 512 lié</span><strong><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?: '—' ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Informations passeport</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Numéro de passport</span><strong><?= e((string)($client['passport_number'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Lieu de délivrance</span><strong><?= e((string)($client['passport_issue_country'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Date de délivrance</span><strong><?= e((string)($client['passport_issue_date'] ?? '—')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Date d’expiration</span><strong><?= e((string)($client['passport_expiry_date'] ?? '—')) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3>Actions rapides</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>" class="btn btn-outline">Modifier</a>
                    <a href="<?= e(APP_URL) ?>modules/statements/client_profiles.php?client_ids[]=<?= (int)$id ?>" class="btn btn-secondary">Exporter fiche PDF</a>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour liste</a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Dernières opérations</h3>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Référence</th>
                                <th>Montant</th>
                                <th>Débit</th>
                                <th>Crédit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($operations): ?>
                                <?php foreach ($operations as $op): ?>
                                    <tr>
                                        <td><?= e((string)($op['operation_date'] ?? '')) ?></td>
                                        <td><?= e((string)(($op['reference'] ?? '') !== '' ? $op['reference'] : ($op['label'] ?? ''))) ?></td>
                                        <td><?= e(number_format((float)($op['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                        <td><?= e((string)($op['debit_account_code'] ?? '')) ?></td>
                                        <td><?= e((string)($op['credit_account_code'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">Aucune opération trouvée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3>Historique</h3>
                <div class="sl-anomaly-list">
                    <?php if ($timeline): ?>
                        <?php foreach ($timeline as $item): ?>
                            <div class="sl-anomaly-list__item">
                                <div>
                                    <strong><?= e((string)($item['title'] ?? 'Événement')) ?></strong>
                                    <div class="muted"><?= e((string)($item['created_at'] ?? '')) ?></div>
                                    <?php if (!empty($item['details'])): ?>
                                        <div class="muted"><?= e((string)$item['details']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">Aucun historique disponible.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>