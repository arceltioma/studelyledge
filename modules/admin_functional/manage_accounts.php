<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

if (!function_exists('ma_like')) {
    function ma_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

$search706 = trim((string)($_GET['search_706'] ?? ''));
$search512 = trim((string)($_GET['search_512'] ?? ''));
$filter706Type = trim((string)($_GET['filter_706_type'] ?? ''));
$filter706Destination = trim((string)($_GET['filter_706_destination'] ?? ''));
$filter706Commercial = trim((string)($_GET['filter_706_commercial'] ?? ''));
$filter706Status = trim((string)($_GET['filter_706_status'] ?? ''));
$filter512Country = trim((string)($_GET['filter_512_country'] ?? ''));
$filter512Currency = trim((string)($_GET['filter_512_currency'] ?? ''));
$filter512Bank = trim((string)($_GET['filter_512_bank'] ?? ''));
$filter512Status = trim((string)($_GET['filter_512_status'] ?? ''));

$serviceAccounts = [];
$treasuryAccounts = [];

if (tableExists($pdo, 'service_accounts')) {
    $sql706 = "
        SELECT *
        FROM service_accounts
        WHERE 1=1
    ";
    $params706 = [];

    if ($search706 !== '') {
        $sql706 .= "
            AND (
                account_code LIKE ?
                OR account_label LIKE ?
                OR COALESCE(operation_type_label, '') LIKE ?
                OR COALESCE(destination_country_label, '') LIKE ?
                OR COALESCE(commercial_country_label, '') LIKE ?
            )
        ";
        $params706[] = ma_like($search706);
        $params706[] = ma_like($search706);
        $params706[] = ma_like($search706);
        $params706[] = ma_like($search706);
        $params706[] = ma_like($search706);
    }

    if ($filter706Type !== '') {
        $sql706 .= " AND COALESCE(operation_type_label, '') = ? ";
        $params706[] = $filter706Type;
    }

    if ($filter706Destination !== '') {
        $sql706 .= " AND COALESCE(destination_country_label, '') = ? ";
        $params706[] = $filter706Destination;
    }

    if ($filter706Commercial !== '') {
        $sql706 .= " AND COALESCE(commercial_country_label, '') = ? ";
        $params706[] = $filter706Commercial;
    }

    if ($filter706Status === 'active') {
        $sql706 .= " AND COALESCE(is_active, 1) = 1 ";
    } elseif ($filter706Status === 'inactive') {
        $sql706 .= " AND COALESCE(is_active, 1) = 0 ";
    } elseif ($filter706Status === 'postable') {
        $sql706 .= " AND COALESCE(is_postable, 0) = 1 ";
    } elseif ($filter706Status === 'structure') {
        $sql706 .= " AND COALESCE(is_postable, 0) = 0 ";
    }

    $sql706 .= " ORDER BY account_code ASC ";

    $stmt706 = $pdo->prepare($sql706);
    $stmt706->execute($params706);
    $serviceAccounts = $stmt706->fetchAll(PDO::FETCH_ASSOC);
}

if (tableExists($pdo, 'treasury_accounts')) {
    $sql512 = "
        SELECT *
        FROM treasury_accounts
        WHERE 1=1
    ";
    $params512 = [];

    if ($search512 !== '') {
        $sql512 .= "
            AND (
                account_code LIKE ?
                OR account_label LIKE ?
                OR COALESCE(bank_name, '') LIKE ?
                OR COALESCE(country_label, '') LIKE ?
                OR COALESCE(currency_code, '') LIKE ?
            )
        ";
        $params512[] = ma_like($search512);
        $params512[] = ma_like($search512);
        $params512[] = ma_like($search512);
        $params512[] = ma_like($search512);
        $params512[] = ma_like($search512);
    }

    if ($filter512Country !== '') {
        $sql512 .= " AND COALESCE(country_label, '') = ? ";
        $params512[] = $filter512Country;
    }

    if ($filter512Currency !== '') {
        $sql512 .= " AND COALESCE(currency_code, '') = ? ";
        $params512[] = $filter512Currency;
    }

    if ($filter512Bank !== '') {
        $sql512 .= " AND COALESCE(bank_name, '') = ? ";
        $params512[] = $filter512Bank;
    }

    if ($filter512Status === 'active') {
        $sql512 .= " AND COALESCE(is_active, 1) = 1 ";
    } elseif ($filter512Status === 'inactive') {
        $sql512 .= " AND COALESCE(is_active, 1) = 0 ";
    }

    $sql512 .= " ORDER BY account_code ASC ";

    $stmt512 = $pdo->prepare($sql512);
    $stmt512->execute($params512);
    $treasuryAccounts = $stmt512->fetchAll(PDO::FETCH_ASSOC);
}

$serviceTypeOptions = [];
$serviceDestinationOptions = [];
$serviceCommercialOptions = [];
$treasuryCountryOptions = [];
$treasuryCurrencyOptions = [];
$treasuryBankOptions = [];

if (tableExists($pdo, 'service_accounts')) {
    $serviceTypeOptions = $pdo->query("
        SELECT DISTINCT COALESCE(operation_type_label, '') AS value
        FROM service_accounts
        WHERE COALESCE(operation_type_label, '') <> ''
        ORDER BY value ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $serviceDestinationOptions = $pdo->query("
        SELECT DISTINCT COALESCE(destination_country_label, '') AS value
        FROM service_accounts
        WHERE COALESCE(destination_country_label, '') <> ''
        ORDER BY value ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $serviceCommercialOptions = $pdo->query("
        SELECT DISTINCT COALESCE(commercial_country_label, '') AS value
        FROM service_accounts
        WHERE COALESCE(commercial_country_label, '') <> ''
        ORDER BY value ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

if (tableExists($pdo, 'treasury_accounts')) {
    $treasuryCountryOptions = $pdo->query("
        SELECT DISTINCT COALESCE(country_label, '') AS value
        FROM treasury_accounts
        WHERE COALESCE(country_label, '') <> ''
        ORDER BY value ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $treasuryCurrencyOptions = $pdo->query("
        SELECT DISTINCT COALESCE(currency_code, '') AS value
        FROM treasury_accounts
        WHERE COALESCE(currency_code, '') <> ''
        ORDER BY value ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $treasuryBankOptions = $pdo->query("
        SELECT DISTINCT COALESCE(bank_name, '') AS value
        FROM treasury_accounts
        WHERE COALESCE(bank_name, '') <> ''
        ORDER BY value ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

$dashboard706 = [
    'total' => count($serviceAccounts),
    'active' => count(array_filter($serviceAccounts, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($serviceAccounts, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'postable' => count(array_filter($serviceAccounts, fn($r) => (int)($r['is_postable'] ?? 0) === 1)),
    'structure' => count(array_filter($serviceAccounts, fn($r) => (int)($r['is_postable'] ?? 0) !== 1)),
    'balance' => array_reduce($serviceAccounts, fn($c, $r) => $c + (float)($r['current_balance'] ?? 0), 0.0),
];

$dashboard512 = [
    'total' => count($treasuryAccounts),
    'active' => count(array_filter($treasuryAccounts, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($treasuryAccounts, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'positive' => count(array_filter($treasuryAccounts, fn($r) => (float)($r['current_balance'] ?? 0) > 0)),
    'negative' => count(array_filter($treasuryAccounts, fn($r) => (float)($r['current_balance'] ?? 0) < 0)),
    'balance' => array_reduce($treasuryAccounts, fn($c, $r) => $c + (float)($r['current_balance'] ?? 0), 0.0),
];

$pageTitle = 'Comptes fonctionnels';
$pageSubtitle = 'Lecture croisée des comptes 706 et 512 utilisés par le moteur métier.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Dashboard 706</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard706['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actifs</span><strong><?= (int)$dashboard706['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactifs</span><strong><?= (int)$dashboard706['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Postables</span><strong><?= (int)$dashboard706['postable'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Structure</span><strong><?= (int)$dashboard706['structure'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde total</span><strong><?= number_format((float)$dashboard706['balance'], 2, ',', ' ') ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Dashboard 512</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard512['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actifs</span><strong><?= (int)$dashboard512['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactifs</span><strong><?= (int)$dashboard512['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Soldes positifs</span><strong><?= (int)$dashboard512['positive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Soldes négatifs</span><strong><?= (int)$dashboard512['negative'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde total</span><strong><?= number_format((float)$dashboard512['balance'], 2, ',', ' ') ?></strong></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2">
            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Comptes 706</h3>
                    </div>
                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/treasury/service_accounts.php" class="btn btn-outline">Gérer les 706</a>
                    </div>
                </div>

                <form method="GET" class="form-card" style="margin-bottom:16px;">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche 706</label>
                            <input type="text" name="search_706" value="<?= e($search706) ?>" placeholder="Code, libellé, pays, type...">
                        </div>
                        <div>
                            <label>Type op</label>
                            <select name="filter_706_type">
                                <option value="">Tous</option>
                                <?php foreach ($serviceTypeOptions as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $filter706Type === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Destination</label>
                            <select name="filter_706_destination">
                                <option value="">Toutes</option>
                                <?php foreach ($serviceDestinationOptions as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $filter706Destination === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Commercial</label>
                            <select name="filter_706_commercial">
                                <option value="">Tous</option>
                                <?php foreach ($serviceCommercialOptions as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $filter706Commercial === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Statut / nature</label>
                            <select name="filter_706_status">
                                <option value="">Tous</option>
                                <option value="active" <?= $filter706Status === 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="inactive" <?= $filter706Status === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                                <option value="postable" <?= $filter706Status === 'postable' ? 'selected' : '' ?>>Postables</option>
                                <option value="structure" <?= $filter706Status === 'structure' ? 'selected' : '' ?>>Structure</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Type op</th>
                            <th>Destination</th>
                            <th>Commercial</th>
                            <th>Statut</th>
                            <th>Solde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviceAccounts as $row): ?>
                            <tr>
                                <td><?= e($row['account_code'] ?? '') ?></td>
                                <td><?= e($row['account_label'] ?? '') ?></td>
                                <td><?= e($row['operation_type_label'] ?? '') ?></td>
                                <td><?= e($row['destination_country_label'] ?? '') ?></td>
                                <td><?= e($row['commercial_country_label'] ?? '') ?></td>
                                <td>
                                    <?=
                                        ((int)($row['is_active'] ?? 1) === 1 ? 'Actif' : 'Inactif')
                                        . ' / '
                                        . ((int)($row['is_postable'] ?? 0) === 1 ? 'Postable' : 'Structure')
                                    ?>
                                </td>
                                <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$serviceAccounts): ?>
                            <tr><td colspan="7">Aucun compte 706.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Comptes 512</h3>
                    </div>
                    <div class="btn-group">
                        <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="btn btn-outline">Gérer les 512</a>
                    </div>
                </div>

                <form method="GET" class="form-card" style="margin-bottom:16px;">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche 512</label>
                            <input type="text" name="search_512" value="<?= e($search512) ?>" placeholder="Code, libellé, banque, pays...">
                        </div>
                        <div>
                            <label>Pays</label>
                            <select name="filter_512_country">
                                <option value="">Tous</option>
                                <?php foreach ($treasuryCountryOptions as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $filter512Country === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Devise</label>
                            <select name="filter_512_currency">
                                <option value="">Toutes</option>
                                <?php foreach ($treasuryCurrencyOptions as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $filter512Currency === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Banque</label>
                            <select name="filter_512_bank">
                                <option value="">Toutes</option>
                                <?php foreach ($treasuryBankOptions as $item): ?>
                                    <option value="<?= e($item) ?>" <?= $filter512Bank === (string)$item ? 'selected' : '' ?>><?= e($item) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Statut</label>
                            <select name="filter_512_status">
                                <option value="">Tous</option>
                                <option value="active" <?= $filter512Status === 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="inactive" <?= $filter512Status === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Libellé</th>
                            <th>Banque</th>
                            <th>Pays</th>
                            <th>Devise</th>
                            <th>Statut</th>
                            <th>Solde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treasuryAccounts as $row): ?>
                            <tr>
                                <td><?= e($row['account_code'] ?? '') ?></td>
                                <td><?= e($row['account_label'] ?? '') ?></td>
                                <td><?= e($row['bank_name'] ?? '') ?></td>
                                <td><?= e($row['country_label'] ?? '') ?></td>
                                <td><?= e($row['currency_code'] ?? '') ?></td>
                                <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Actif' : 'Inactif' ?></td>
                                <td><?= number_format((float)($row['current_balance'] ?? 0), 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$treasuryAccounts): ?>
                            <tr><td colspan="7">Aucun compte 512.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>