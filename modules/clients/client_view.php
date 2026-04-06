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

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
";

if (tableExists($pdo, 'bank_accounts')) {
    if (columnExists($pdo, 'clients', 'bank_account_id')) {
        $sql .= ",
            ba.id AS linked_bank_account_id,
            ba.account_number AS bank_account_number,
            ba.account_code AS bank_account_code,
            ba.account_name AS bank_account_name,
            ba.account_label AS bank_account_label,
            ba.initial_balance,
            ba.balance,
            ba.currency AS bank_currency
        ";
        $sql .= "
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
            LEFT JOIN bank_accounts ba ON ba.id = c.bank_account_id
        ";
    } else {
        $sql .= "
            FROM clients c
            LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
        ";
    }
} else {
    $sql .= "
        FROM clients c
        LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
    ";
}

$sql .= " WHERE c.id = ? LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    exit('Client introuvable.');
}

if (!function_exists('sl_client_find_bank_account_by_number')) {
    function sl_client_find_bank_account_by_number(PDO $pdo, string $accountNumber): ?array
    {
        if ($accountNumber === '' || !tableExists($pdo, 'bank_accounts')) {
            return null;
        }

        $candidateColumns = ['account_number', 'account_code', 'rib'];
        foreach ($candidateColumns as $column) {
            if (!columnExists($pdo, 'bank_accounts', $column)) {
                continue;
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM bank_accounts
                WHERE {$column} = ?
                LIMIT 1
            ");
            $stmt->execute([$accountNumber]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

$resolved411 = trim((string)($client['generated_client_account'] ?? ''));
$bankData = null;

if (!empty($client['linked_bank_account_id'])) {
    $bankData = [
        'initial_balance' => $client['initial_balance'] ?? 0,
        'balance' => $client['balance'] ?? 0,
        'currency' => $client['bank_currency'] ?? ($client['currency'] ?? 'EUR'),
        'account_number' => $client['bank_account_number'] ?? '',
        'account_code' => $client['bank_account_code'] ?? '',
        'account_name' => $client['bank_account_name'] ?? '',
        'account_label' => $client['bank_account_label'] ?? '',
    ];
} elseif ($resolved411 !== '') {
    $bankData = sl_client_find_bank_account_by_number($pdo, $resolved411);
}

$currency = trim((string)($bankData['currency'] ?? ($client['currency'] ?? 'EUR')));
$initialBalance = (float)($bankData['initial_balance'] ?? 0);
$currentBalance = (float)($bankData['balance'] ?? 0);

$pageTitle = 'Fiche client';
$pageSubtitle = 'Consultation détaillée du client, du compte 411 et des rattachements.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2">
            <div class="card">
                <div class="sl-card-head">
                    <div>
                        <h3>Identité client</h3>
                        <p class="sl-card-head-subtitle">Données générales et statut</p>
                    </div>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Code client</span><strong><?= e((string)($client['client_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom complet</span><strong><?= e((string)($client['full_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Prénom</span><strong><?= e((string)($client['first_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Nom</span><strong><?= e((string)($client['last_name'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Email</span><strong><?= e((string)($client['email'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Téléphone</span><strong><?= e((string)($client['phone'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Adresse postale</span><strong><?= e((string)($client['postal_address'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Type client</span><strong><?= e((string)($client['client_type'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Statut</span><strong><?= ((int)($client['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong></div>
                </div>
            </div>

            <div class="card">
                <div class="sl-card-head">
                    <div>
                        <h3>Compte client 411</h3>
                        <p class="sl-card-head-subtitle">Compte généré et soldes issus de bank_accounts</p>
                    </div>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Compte 411</span><strong><?= e($resolved411) ?></strong></div>
                    <div class="sl-data-list__row"><span>Devise</span><strong><?= e($currency) ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde initial</span><strong><?= e(number_format($initialBalance, 2, ',', ' ') . ' ' . $currency) ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant</span><strong><?= e(number_format($currentBalance, 2, ',', ' ') . ' ' . $currency) ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte bancaire lié</span><strong><?= e((string)($bankData['account_number'] ?? $bankData['account_code'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Libellé compte</span><strong><?= e((string)($bankData['account_label'] ?? $bankData['account_name'] ?? '')) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <div class="sl-card-head">
                    <div>
                        <h3>Passeport</h3>
                        <p class="sl-card-head-subtitle">Informations documentaires</p>
                    </div>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Numéro</span><strong><?= e((string)($client['passport_number'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays de délivrance</span><strong><?= e((string)($client['passport_issue_country'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Date de délivrance</span><strong><?= e((string)($client['passport_issue_date'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Date d’expiration</span><strong><?= e((string)($client['passport_expiry_date'] ?? '')) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <div class="sl-card-head">
                    <div>
                        <h3>Rattachement métier</h3>
                        <p class="sl-card-head-subtitle">Pays et compte 512 lié</p>
                    </div>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Pays d'origine</span><strong><?= e((string)($client['country_origin'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays de destination</span><strong><?= e((string)($client['country_destination'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row"><span>Pays commercial</span><strong><?= e((string)($client['country_commercial'] ?? '')) ?></strong></div>
                    <div class="sl-data-list__row">
                        <span>Compte 512 lié</span>
                        <strong><?= e(trim((string)($client['treasury_account_code'] ?? '') . ' - ' . (string)($client['treasury_account_label'] ?? ''))) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="btn-group" style="margin-top:20px;">
            <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$id ?>">Modifier</a>
            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">Retour</a>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>