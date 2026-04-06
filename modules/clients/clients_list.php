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

$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if (!function_exists('sl_client_list_find_bank_account_by_number')) {
    function sl_client_list_find_bank_account_by_number(PDO $pdo, string $accountNumber): ?array
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

$sql = "
    SELECT
        c.*,
        ta.account_code AS treasury_account_code,
        ta.account_label AS treasury_account_label
";

$hasBankAccounts = tableExists($pdo, 'bank_accounts');
$hasClientBankLink = columnExists($pdo, 'clients', 'bank_account_id');

if ($hasBankAccounts && $hasClientBankLink) {
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
}

$sql .= "
    FROM clients c
    LEFT JOIN treasury_accounts ta ON ta.id = c.initial_treasury_account_id
";

if ($hasBankAccounts && $hasClientBankLink) {
    $sql .= "
        LEFT JOIN bank_accounts ba ON ba.id = c.bank_account_id
    ";
}

$sql .= "
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (
        c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR c.email LIKE ?
        OR c.phone LIKE ?
        OR c.generated_client_account LIKE ?";

    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like, $like, $like];

    if (columnExists($pdo, 'clients', 'postal_address')) {
        $sql .= " OR c.postal_address LIKE ?";
        $params[] = $like;
    }

    if (columnExists($pdo, 'clients', 'passport_number')) {
        $sql .= " OR c.passport_number LIKE ?";
        $params[] = $like;
    }

    $sql .= ")";
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && columnExists($pdo, 'clients', 'created_at')) {
    $sql .= " AND DATE(c.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) && columnExists($pdo, 'clients', 'created_at')) {
    $sql .= " AND DATE(c.created_at) <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY c.client_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $resolvedBank = null;

    if (!empty($row['linked_bank_account_id'])) {
        $resolvedBank = [
            'initial_balance' => $row['initial_balance'] ?? 0,
            'balance' => $row['balance'] ?? 0,
            'currency' => $row['bank_currency'] ?? ($row['currency'] ?? 'EUR'),
            'account_number' => $row['bank_account_number'] ?? '',
            'account_code' => $row['bank_account_code'] ?? '',
            'account_name' => $row['bank_account_name'] ?? '',
            'account_label' => $row['bank_account_label'] ?? '',
        ];
    } else {
        $generated411 = trim((string)($row['generated_client_account'] ?? ''));
        if ($generated411 !== '') {
            $bankRow = sl_client_list_find_bank_account_by_number($pdo, $generated411);
            if ($bankRow) {
                $resolvedBank = $bankRow;
            }
        }
    }

    $row['resolved_initial_balance'] = (float)($resolvedBank['initial_balance'] ?? 0);
    $row['resolved_current_balance'] = (float)($resolvedBank['balance'] ?? 0);
    $row['resolved_currency'] = (string)($resolvedBank['currency'] ?? ($row['currency'] ?? 'EUR'));
}
unset($row);

$pageTitle = 'Clients';
$pageSubtitle = 'Recherche, lecture rapide et gestion homogène des clients.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div class="btn-group">
                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_create_page') : currentUserCan($pdo, 'clients_create')): ?>
                    <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/clients/client_create.php">Nouveau client</a>
                <?php endif; ?>

                <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'imports_upload_page') : currentUserCan($pdo, 'clients_create')): ?>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/clients/import_clients_csv.php">Import CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-card">
            <form method="GET" class="inline-form">
                <div>
                    <label>Recherche</label>
                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="code, nom, email, téléphone, adresse, passport, compte 411..."
                    >
                </div>

                <div>
                    <label>Date création du</label>
                    <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div>
                    <label>Au</label>
                    <input type="date" name="date_to" value="<?= e($dateTo) ?>">
                </div>

                <div class="btn-group" style="margin-top:26px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Code client</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Adresse postale</th>
                        <th>Compte 411</th>
                        <th>Solde initial</th>
                        <th>Solde courant</th>
                        <th>Compte 512 lié</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $isActive = ((int)($row['is_active'] ?? 1) === 1); ?>
                        <tr>
                            <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                            <td><?= e((string)($row['full_name'] ?? '')) ?></td>
                            <td><?= e((string)($row['email'] ?? '')) ?></td>
                            <td><?= e((string)($row['phone'] ?? '')) ?></td>
                            <td><?= columnExists($pdo, 'clients', 'postal_address') ? e((string)($row['postal_address'] ?? '')) : '' ?></td>
                            <td><?= e((string)($row['generated_client_account'] ?? '')) ?></td>
                            <td><?= e(number_format((float)($row['resolved_initial_balance'] ?? 0), 2, ',', ' ') . ' ' . (string)($row['resolved_currency'] ?? 'EUR')) ?></td>
                            <td><?= e(number_format((float)($row['resolved_current_balance'] ?? 0), 2, ',', ' ') . ' ' . (string)($row['resolved_currency'] ?? 'EUR')) ?></td>
                            <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? ''))) ?></td>
                            <td>
                                <span class="status-pill <?= $isActive ? 'status-success' : 'status-warning' ?>">
                                    <?= $isActive ? 'Actif' : 'Archivé' ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>">Voir</a>

                                    <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_edit_page') : currentUserCan($pdo, 'clients_edit')): ?>
                                        <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    <?php endif; ?>

                                    <?php if (function_exists('studelyCanAccess') ? studelyCanAccess($pdo, 'clients_archive_page') : currentUserCan($pdo, 'clients_edit')): ?>
                                        <?php if ($isActive): ?>
                                            <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=archive">Archiver</a>
                                        <?php else: ?>
                                            <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=restore">Réactiver</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="11">Aucun client trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>