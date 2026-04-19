<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$pageTitle = 'Audit de cohérence des soldes';
$pageSubtitle = 'Contrôle entre soldes stockés et soldes théoriques recalculés depuis les opérations.';

if (!function_exists('aba_money')) {
    function aba_money(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}

$filterSection = trim((string)($_GET['filter_section'] ?? ''));
$filterOnlyDiff = trim((string)($_GET['filter_only_diff'] ?? '1'));

$clientRows = [];
$serviceRows = [];
$treasuryRows = [];

/* =========================
   AUDIT 411
========================= */
if (
    ($filterSection === '' || $filterSection === '411')
    && tableExists($pdo, 'bank_accounts')
    && tableExists($pdo, 'client_bank_accounts')
    && tableExists($pdo, 'clients')
    && tableExists($pdo, 'operations')
) {
    $clientRows = $pdo->query("
        SELECT
            ba.id AS bank_account_id,
            ba.account_number,
            ba.account_name,
            ba.initial_balance,
            ba.balance AS stored_balance,
            ba.is_active,
            c.id AS client_id,
            c.client_code,
            c.full_name,
            c.currency,
            COALESCE(SUM(CASE WHEN o.credit_account_code = ba.account_number THEN o.amount ELSE 0 END), 0) AS total_credit,
            COALESCE(SUM(CASE WHEN o.debit_account_code = ba.account_number THEN o.amount ELSE 0 END), 0) AS total_debit
        FROM bank_accounts ba
        INNER JOIN client_bank_accounts cba ON cba.bank_account_id = ba.id
        INNER JOIN clients c ON c.id = cba.client_id
        LEFT JOIN operations o
            ON o.credit_account_code = ba.account_number
            OR o.debit_account_code = ba.account_number
        GROUP BY
            ba.id, ba.account_number, ba.account_name, ba.initial_balance, ba.balance, ba.is_active,
            c.id, c.client_code, c.full_name, c.currency
        ORDER BY c.client_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($clientRows as &$row) {
        $theoretical = (float)($row['initial_balance'] ?? 0)
            + (float)($row['total_credit'] ?? 0)
            - (float)($row['total_debit'] ?? 0);

        $stored = (float)($row['stored_balance'] ?? 0);
        $diff = round($stored - $theoretical, 2);

        $row['theoretical_balance'] = $theoretical;
        $row['diff_balance'] = $diff;
        $row['has_diff'] = abs($diff) > 0.009 ? 1 : 0;
    }
    unset($row);

    if ($filterOnlyDiff === '1') {
        $clientRows = array_values(array_filter($clientRows, fn($r) => (int)($r['has_diff'] ?? 0) === 1));
    }
}

/* =========================
   AUDIT 706
========================= */
if (
    ($filterSection === '' || $filterSection === '706')
    && tableExists($pdo, 'service_accounts')
    && tableExists($pdo, 'operations')
) {
    $serviceRows = $pdo->query("
        SELECT
            sa.id,
            sa.account_code,
            sa.account_label,
            sa.current_balance AS stored_balance,
            sa.is_active,
            COALESCE(SUM(CASE WHEN o.credit_account_code = sa.account_code THEN o.amount ELSE 0 END), 0) AS total_credit,
            COALESCE(SUM(CASE WHEN o.debit_account_code = sa.account_code THEN o.amount ELSE 0 END), 0) AS total_debit
        FROM service_accounts sa
        LEFT JOIN operations o
            ON o.credit_account_code = sa.account_code
            OR o.debit_account_code = sa.account_code
        GROUP BY sa.id, sa.account_code, sa.account_label, sa.current_balance, sa.is_active
        ORDER BY sa.account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($serviceRows as &$row) {
        $theoretical = (float)($row['total_credit'] ?? 0) - (float)($row['total_debit'] ?? 0);
        $stored = (float)($row['stored_balance'] ?? 0);
        $diff = round($stored - $theoretical, 2);

        $row['theoretical_balance'] = $theoretical;
        $row['diff_balance'] = $diff;
        $row['has_diff'] = abs($diff) > 0.009 ? 1 : 0;
    }
    unset($row);

    if ($filterOnlyDiff === '1') {
        $serviceRows = array_values(array_filter($serviceRows, fn($r) => (int)($r['has_diff'] ?? 0) === 1));
    }
}

/* =========================
   AUDIT 512
========================= */
if (
    ($filterSection === '' || $filterSection === '512')
    && tableExists($pdo, 'treasury_accounts')
) {
    $treasuryRows = $pdo->query("
        SELECT
            ta.id,
            ta.account_code,
            ta.account_label,
            ta.currency_code,
            ta.opening_balance,
            ta.current_balance AS stored_balance,
            ta.is_active,
            COALESCE((
                SELECT SUM(CASE WHEN o.credit_account_code = ta.account_code THEN o.amount ELSE 0 END)
                FROM operations o
            ), 0) AS total_credit_ops,
            COALESCE((
                SELECT SUM(CASE WHEN o.debit_account_code = ta.account_code THEN o.amount ELSE 0 END)
                FROM operations o
            ), 0) AS total_debit_ops,
            COALESCE((
                SELECT SUM(tm.amount)
                FROM treasury_movements tm
                WHERE tm.target_treasury_account_id = ta.id
            ), 0) AS total_in_movements,
            COALESCE((
                SELECT SUM(tm.amount)
                FROM treasury_movements tm
                WHERE tm.source_treasury_account_id = ta.id
            ), 0) AS total_out_movements
        FROM treasury_accounts ta
        ORDER BY ta.account_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($treasuryRows as &$row) {
        $theoretical = (float)($row['opening_balance'] ?? 0)
            - (float)($row['total_debit_ops'] ?? 0)
            + (float)($row['total_credit_ops'] ?? 0)
            + (float)($row['total_in_movements'] ?? 0)
            - (float)($row['total_out_movements'] ?? 0);

        $stored = (float)($row['stored_balance'] ?? 0);
        $diff = round($stored - $theoretical, 2);

        $row['theoretical_balance'] = $theoretical;
        $row['diff_balance'] = $diff;
        $row['has_diff'] = abs($diff) > 0.009 ? 1 : 0;
    }
    unset($row);

    if ($filterOnlyDiff === '1') {
        $treasuryRows = array_values(array_filter($treasuryRows, fn($r) => (int)($r['has_diff'] ?? 0) === 1));
    }
}

$dashboard = [
    'diff_411' => count(array_filter($clientRows, fn($r) => (int)($r['has_diff'] ?? 0) === 1)),
    'diff_706' => count(array_filter($serviceRows, fn($r) => (int)($r['has_diff'] ?? 0) === 1)),
    'diff_512' => count(array_filter($treasuryRows, fn($r) => (int)($r['has_diff'] ?? 0) === 1)),
    'rows_411' => count($clientRows),
    'rows_706' => count($serviceRows),
    'rows_512' => count($treasuryRows),
];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Écarts 411</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['diff_411'] ?></div>
                <div class="sl-kpi-card__meta"><strong><?= (int)$dashboard['rows_411'] ?> ligne(s)</strong></div>
            </div>

            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Écarts 706</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['diff_706'] ?></div>
                <div class="sl-kpi-card__meta"><strong><?= (int)$dashboard['rows_706'] ?> ligne(s)</strong></div>
            </div>

            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Écarts 512</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['diff_512'] ?></div>
                <div class="sl-kpi-card__meta"><strong><?= (int)$dashboard['rows_512'] ?> ligne(s)</strong></div>
            </div>

            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Total écarts</div>
                <div class="sl-kpi-card__value"><?= (int)($dashboard['diff_411'] + $dashboard['diff_706'] + $dashboard['diff_512']) ?></div>
                <div class="sl-kpi-card__meta"><strong>Audit multi-comptes</strong></div>
            </div>
        </div>

        <div class="form-card" style="margin-bottom:20px;">
            <h3 class="section-title">Filtres audit</h3>

            <form method="GET">
                <div class="dashboard-grid-2">
                    <div>
                        <label>Section</label>
                        <select name="filter_section">
                            <option value="">Toutes</option>
                            <option value="411" <?= $filterSection === '411' ? 'selected' : '' ?>>Comptes clients 411</option>
                            <option value="706" <?= $filterSection === '706' ? 'selected' : '' ?>>Comptes 706</option>
                            <option value="512" <?= $filterSection === '512' ? 'selected' : '' ?>>Comptes 512</option>
                        </select>
                    </div>

                    <div>
                        <label>Affichage</label>
                        <select name="filter_only_diff">
                            <option value="1" <?= $filterOnlyDiff === '1' ? 'selected' : '' ?>>Seulement les écarts</option>
                            <option value="0" <?= $filterOnlyDiff === '0' ? 'selected' : '' ?>>Toutes les lignes</option>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-secondary">Lancer l’audit</button>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_balance_audit.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php" class="btn btn-outline">Voir les comptes</a>
                </div>
            </form>
        </div>

        <?php if ($filterSection === '' || $filterSection === '411'): ?>
            <div class="table-card" style="margin-bottom:20px;">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Audit comptes clients 411</h3>
                        <p class="muted">Théorique = initial + crédits - débits</p>
                    </div>
                </div>

                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Compte 411</th>
                            <th>Initial</th>
                            <th>Crédits</th>
                            <th>Débits</th>
                            <th>Théorique</th>
                            <th>Stocké</th>
                            <th>Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientRows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e((string)($row['client_code'] ?? '')) ?></strong>
                                    <div class="muted"><?= e((string)($row['full_name'] ?? '')) ?></div>
                                </td>
                                <td><?= e((string)($row['account_number'] ?? '')) ?></td>
                                <td><?= aba_money((float)($row['initial_balance'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_credit'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_debit'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['theoretical_balance'] ?? 0)) ?></td>
                                <td><strong><?= aba_money((float)($row['stored_balance'] ?? 0)) ?></strong></td>
                                <td>
                                    <span class="status-pill <?= abs((float)($row['diff_balance'] ?? 0)) > 0.009 ? 'status-danger' : 'status-success' ?>">
                                        <?= aba_money((float)($row['diff_balance'] ?? 0)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$clientRows): ?>
                            <tr><td colspan="8">Aucun écart 411 détecté.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($filterSection === '' || $filterSection === '706'): ?>
            <div class="table-card" style="margin-bottom:20px;">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Audit comptes 706</h3>
                        <p class="muted">Théorique = crédits - débits</p>
                    </div>
                </div>

                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Libellé</th>
                            <th>Crédits</th>
                            <th>Débits</th>
                            <th>Théorique</th>
                            <th>Stocké</th>
                            <th>Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviceRows as $row): ?>
                            <tr>
                                <td><?= e((string)($row['account_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                <td><?= aba_money((float)($row['total_credit'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_debit'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['theoretical_balance'] ?? 0)) ?></td>
                                <td><strong><?= aba_money((float)($row['stored_balance'] ?? 0)) ?></strong></td>
                                <td>
                                    <span class="status-pill <?= abs((float)($row['diff_balance'] ?? 0)) > 0.009 ? 'status-danger' : 'status-success' ?>">
                                        <?= aba_money((float)($row['diff_balance'] ?? 0)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$serviceRows): ?>
                            <tr><td colspan="7">Aucun écart 706 détecté.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($filterSection === '' || $filterSection === '512'): ?>
            <div class="table-card">
                <div class="page-title page-title-inline">
                    <div>
                        <h3 class="section-title">Audit comptes 512</h3>
                        <p class="muted">Théorique = ouverture - débits op + crédits op + entrées TM - sorties TM</p>
                    </div>
                </div>

                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Ouverture</th>
                            <th>Crédits op</th>
                            <th>Débits op</th>
                            <th>Entrées TM</th>
                            <th>Sorties TM</th>
                            <th>Théorique</th>
                            <th>Stocké</th>
                            <th>Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treasuryRows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e((string)($row['account_code'] ?? '')) ?></strong>
                                    <div class="muted"><?= e((string)($row['account_label'] ?? '')) ?></div>
                                </td>
                                <td><?= aba_money((float)($row['opening_balance'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_credit_ops'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_debit_ops'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_in_movements'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['total_out_movements'] ?? 0)) ?></td>
                                <td><?= aba_money((float)($row['theoretical_balance'] ?? 0)) ?></td>
                                <td><strong><?= aba_money((float)($row['stored_balance'] ?? 0)) ?></strong></td>
                                <td>
                                    <span class="status-pill <?= abs((float)($row['diff_balance'] ?? 0)) > 0.009 ? 'status-danger' : 'status-success' ?>">
                                        <?= aba_money((float)($row['diff_balance'] ?? 0)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$treasuryRows): ?>
                            <tr><td colspan="9">Aucun écart 512 détecté.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>