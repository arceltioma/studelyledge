<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'clients_view');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmtClient = $pdo->prepare("
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    WHERE c.id = ?
    LIMIT 1
");
$stmtClient->execute([$id]);
$client = $stmtClient->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$bankAccount = findPrimaryBankAccountForClient($pdo, $id);

$stmtOps = $pdo->prepare("
    SELECT *
    FROM operations
    WHERE client_id = ?
    ORDER BY operation_date DESC, id DESC
    LIMIT 50
");
$stmtOps->execute([$id]);
$operations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Fiche client';
$pageSubtitle = 'Vue complète du client, de son compte, de son rattachement financier et de son activité.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h2><?= e($client['full_name'] ?? '') ?></h2>
                <p class="muted">Code client : <?= e($client['client_code'] ?? '') ?></p>
            </div>

            <div class="btn-group">
                <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>">Modifier</a>
                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/archive_client.php?id=<?= (int)$id ?>">
                    <?= ((int)($client['is_active'] ?? 1) === 1) ? 'Archiver' : 'Réactiver' ?>
                </a>
                <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/statements/account_statements.php">Exporter relevé</a>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Identité</h3>
                <div class="stat-row"><span class="metric-label">Prénom</span><span class="metric-value"><?= e($client['first_name'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Nom</span><span class="metric-value"><?= e($client['last_name'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Nom complet</span><span class="metric-value"><?= e($client['full_name'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Email</span><span class="metric-value"><?= e($client['email'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Téléphone</span><span class="metric-value"><?= e($client['phone'] ?? '') ?></span></div>
            </div>

            <div class="card">
                <h3>Pays & cycle</h3>
                <div class="stat-row"><span class="metric-label">Pays origine</span><span class="metric-value"><?= e($client['country_origin'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Pays destination</span><span class="metric-value"><?= e($client['country_destination'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Pays commercial</span><span class="metric-value"><?= e($client['country_commercial'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Type client</span><span class="metric-value"><?= e($client['client_type'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Statut client</span><span class="metric-value"><?= e($client['client_status'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Devise</span><span class="metric-value"><?= e($client['currency'] ?? '') ?></span></div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="card">
                <h3>Rattachement financier</h3>
                <div class="stat-row">
                    <span class="metric-label">Compte interne</span>
                    <span class="metric-value"><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?></span>
                </div>
                <div class="stat-row">
                    <span class="metric-label">Compte client généré</span>
                    <span class="metric-value"><?= e($client['generated_client_account'] ?? '') ?></span>
                </div>
                <div class="stat-row">
                    <span class="metric-label">État</span>
                    <span class="metric-value"><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></span>
                </div>
            </div>

            <div class="card">
                <h3>Compte du client</h3>
                <div class="stat-row"><span class="metric-label">Numéro de compte</span><span class="metric-value"><?= e($bankAccount['account_number'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Banque</span><span class="metric-value"><?= e($bankAccount['bank_name'] ?? '') ?></span></div>
                <div class="stat-row"><span class="metric-label">Solde initial</span><span class="metric-value"><?= number_format((float)($bankAccount['initial_balance'] ?? 0), 2, ',', ' ') ?></span></div>
                <div class="stat-row"><span class="metric-label">Solde courant</span><span class="metric-value"><?= number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ') ?></span></div>
            </div>
        </div>

        <div class="table-card">
            <h3 class="section-title">Dernières opérations</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Montant</th>
                        <th>Référence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operations as $op): ?>
                        <tr>
                            <td><?= e($op['operation_date'] ?? '') ?></td>
                            <td><?= e($op['label'] ?? '') ?></td>
                            <td><?= e($op['debit_account_code'] ?? '') ?></td>
                            <td><?= e($op['credit_account_code'] ?? '') ?></td>
                            <td><?= number_format((float)($op['amount'] ?? 0), 2, ',', ' ') ?></td>
                            <td><?= e($op['reference'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$operations): ?>
                        <tr><td colspan="6">Aucune opération trouvée pour ce client.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>