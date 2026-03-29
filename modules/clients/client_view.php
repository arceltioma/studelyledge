<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'clients_view');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit('Client invalide.');
}

$stmt = $pdo->prepare("
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label,
        ta.country_label AS treasury_country_label,
        s.name AS status_name
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    LEFT JOIN statuses s ON s.id = c.status_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

$bankAccount = findPrimaryBankAccountForClient($pdo, $id);

$recentOperations = [];
if (tableExists($pdo, 'operations')) {
    $stmtOps = $pdo->prepare("
        SELECT
            o.id,
            o.operation_date,
            o.operation_type_code,
            o.label,
            o.reference,
            o.amount,
            o.debit_account_code,
            o.credit_account_code
        FROM operations o
        WHERE o.client_id = ?
        ORDER BY o.operation_date DESC, o.id DESC
        LIMIT 20
    ");
    $stmtOps->execute([$id]);
    $recentOperations = $stmtOps->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Fiche client';
$pageSubtitle = 'Visualisation complète du client, du compte 411, du compte 512 lié et des soldes.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>
        <?php render_app_header_bar($pageTitle, $pageSubtitle); ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if (currentUserCan($pdo, 'clients_edit')): ?>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>" class="btn btn-secondary">Modifier</a>
                <?php endif; ?>
                <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Retour liste</a>
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <h3>Code client</h3>
                <div class="kpi"><?= e($client['client_code'] ?? '') ?></div>
            </div>

            <div class="card">
                <h3>Compte client 411</h3>
                <div class="kpi"><?= e($client['generated_client_account'] ?? '') ?></div>
            </div>

            <div class="card">
                <h3>Compte 512 lié</h3>
                <div class="kpi">
                    <?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?>
                </div>
            </div>

            <div class="card">
                <h3>Solde initial 411</h3>
                <div class="kpi"><?= number_format((float)($bankAccount['initial_balance'] ?? 0), 2, ',', ' ') ?></div>
            </div>

            <div class="card">
                <h3>Solde courant 411</h3>
                <div class="kpi"><?= number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ') ?></div>
            </div>

            <div class="card">
                <h3>Devise</h3>
                <div class="kpi"><?= e($client['currency'] ?? 'EUR') ?></div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="form-card">
                <h3 class="section-title">Informations générales</h3>

                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">Prénom</span>
                        <span class="detail-value"><?= e($client['first_name'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Nom</span>
                        <span class="detail-value"><?= e($client['last_name'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Nom complet</span>
                        <span class="detail-value"><?= e($client['full_name'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?= e($client['email'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Téléphone</span>
                        <span class="detail-value"><?= e($client['phone'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Type client</span>
                        <span class="detail-value"><?= e($client['client_type'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Statut client</span>
                        <span class="detail-value"><?= e($client['client_status'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Statut paramétré</span>
                        <span class="detail-value"><?= e($client['status_name'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Client actif</span>
                        <span class="detail-value"><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Oui' : 'Non' ?></span>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h3 class="section-title">Pays et rattachement comptable</h3>

                <div class="detail-grid">
                    <div class="detail-row">
                        <span class="detail-label">Pays d’origine</span>
                        <span class="detail-value"><?= e($client['country_origin'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Pays de destination</span>
                        <span class="detail-value"><?= e($client['country_destination'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Pays commercial</span>
                        <span class="detail-value"><?= e($client['country_commercial'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Compte 512</span>
                        <span class="detail-value"><?= e($client['treasury_account_code'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Libellé 512</span>
                        <span class="detail-value"><?= e($client['treasury_account_label'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Pays du 512</span>
                        <span class="detail-value"><?= e($client['treasury_country_label'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Compte 411</span>
                        <span class="detail-value"><?= e($client['generated_client_account'] ?? '') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Solde initial</span>
                        <span class="detail-value"><?= number_format((float)($bankAccount['initial_balance'] ?? 0), 2, ',', ' ') ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Solde courant</span>
                        <span class="detail-value"><?= number_format((float)($bankAccount['balance'] ?? 0), 2, ',', ' ') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <h3 class="section-title">Dernières opérations du client</h3>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Libellé</th>
                        <th>Référence</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOperations as $op): ?>
                        <tr>
                            <td><?= e($op['operation_date'] ?? '') ?></td>
                            <td><?= e($op['operation_type_code'] ?? '') ?></td>
                            <td><?= e($op['label'] ?? '') ?></td>
                            <td><?= e($op['reference'] ?? '') ?></td>
                            <td><?= e($op['debit_account_code'] ?? '') ?></td>
                            <td><?= e($op['credit_account_code'] ?? '') ?></td>
                            <td><?= number_format((float)($op['amount'] ?? 0), 2, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$recentOperations): ?>
                        <tr><td colspan="7">Aucune opération trouvée pour ce client.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>