<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'dashboard_view_page');
} else {
    enforcePagePermission($pdo, 'dashboard_view');
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Pilotage global Studely Ledger : comptes, clients, opérations, mensualités et synthèses comptables';

$showTypes = isset($_GET['show_types']) ? 1 : 0;
$showServices = isset($_GET['show_services']) ? 1 : 0;
$showCountries = isset($_GET['show_countries']) ? 1 : 0;
$showAccounting = isset($_GET['show_accounting']) ? 1 : 0;

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));

$currentMonth = date('Y-m');
$periodFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : date('Y-m-01');
$periodTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : date('Y-m-d');

$cards = [
    'solde_411' => 0,
    'solde_512' => 0,
    'solde_706' => 0,
    'students_active' => 0,
    'students_inactive' => 0,
    'monthly_remaining' => 0,
    'monthly_done' => 0,
    'period_ops_count' => 0,
    'period_ops_amount' => 0,
    'low_balance_clients' => 0,
];

$typeRows = [];
$serviceRows = [];
$countryRows = [];
$accountingRows = [];
$lowBalanceRows = [];

if (tableExists($pdo, 'bank_accounts')) {
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(balance), 0)
        FROM bank_accounts
        WHERE account_number LIKE '411%'
    ");
    $cards['solde_411'] = (float)$stmt->fetchColumn();
}

if (tableExists($pdo, 'treasury_accounts')) {
    if (columnExists($pdo, 'treasury_accounts', 'current_balance')) {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(current_balance), 0)
            FROM treasury_accounts
            WHERE account_code LIKE '512%'
        ");
        $cards['solde_512'] = (float)$stmt->fetchColumn();
    } elseif (columnExists($pdo, 'treasury_accounts', 'opening_balance')) {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(opening_balance), 0)
            FROM treasury_accounts
            WHERE account_code LIKE '512%'
        ");
        $cards['solde_512'] = (float)$stmt->fetchColumn();
    }
}

if (tableExists($pdo, 'service_accounts')) {
    if (columnExists($pdo, 'service_accounts', 'current_balance')) {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(current_balance), 0)
            FROM service_accounts
            WHERE account_code LIKE '706%'
        ");
        $cards['solde_706'] = (float)$stmt->fetchColumn();
    }
}

if (tableExists($pdo, 'clients')) {
    $studentStmt = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN COALESCE(is_active,1)=1 AND client_type = 'Etudiant' THEN 1 ELSE 0 END), 0) AS active_students,
            COALESCE(SUM(CASE WHEN COALESCE(is_active,1)=0 AND client_type = 'Etudiant' THEN 1 ELSE 0 END), 0) AS inactive_students
        FROM clients
    ");
    $studentStats = $studentStmt->fetch(PDO::FETCH_ASSOC);
    if ($studentStats) {
        $cards['students_active'] = (int)($studentStats['active_students'] ?? 0);
        $cards['students_inactive'] = (int)($studentStats['inactive_students'] ?? 0);
    }

    $stmtLow = $pdo->query("
        SELECT
            c.id,
            c.client_code,
            c.full_name,
            c.generated_client_account,
            COALESCE(ba.balance, 0) AS balance
        FROM clients c
        LEFT JOIN (
            SELECT
                cba.client_id,
                ba.balance
            FROM client_bank_accounts cba
            INNER JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            INNER JOIN (
                SELECT client_id, MIN(id) AS first_link_id
                FROM client_bank_accounts
                GROUP BY client_id
            ) x ON x.client_id = cba.client_id AND x.first_link_id = cba.id
        ) ba ON ba.client_id = c.id
        WHERE COALESCE(c.is_active,1)=1
          AND COALESCE(ba.balance,0) < 1000
        ORDER BY ba.balance ASC, c.client_code ASC
        LIMIT 12
    ");
    $lowBalanceRows = $stmtLow->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $cards['low_balance_clients'] = count($lowBalanceRows);

    $stmtRemaining = $pdo->prepare("
        SELECT COALESCE(SUM(monthly_amount),0) AS total_remaining
        FROM clients
        WHERE COALESCE(is_active,1)=1
          AND COALESCE(monthly_enabled,0)=1
          AND COALESCE(monthly_amount,0) > 0
          AND (
                monthly_last_generated_at IS NULL
                OR DATE_FORMAT(monthly_last_generated_at, '%Y-%m') <> ?
          )
    ");
    $stmtRemaining->execute([$currentMonth]);
    $cards['monthly_remaining'] = (float)$stmtRemaining->fetchColumn();
}

if (tableExists($pdo, 'operations')) {
    $stmtDone = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) AS total_done
        FROM operations
        WHERE DATE_FORMAT(operation_date, '%Y-%m') = ?
          AND (
                operation_type_code = 'VIREMENT_MENSUEL'
                OR operation_kind = 'monthly_run'
                OR source_type = 'monthly_import'
                OR source_type = 'system_monthly'
          )
    ");
    $stmtDone->execute([$currentMonth]);
    $cards['monthly_done'] = (float)$stmtDone->fetchColumn();

    $stmtOpsPeriod = $pdo->prepare("
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(amount),0) AS total_amount
        FROM operations
        WHERE operation_date >= ?
          AND operation_date <= ?
    ");
    $stmtOpsPeriod->execute([$periodFrom, $periodTo]);
    $opsStats = $stmtOpsPeriod->fetch(PDO::FETCH_ASSOC);
    if ($opsStats) {
        $cards['period_ops_count'] = (int)($opsStats['total_count'] ?? 0);
        $cards['period_ops_amount'] = (float)($opsStats['total_amount'] ?? 0);
    }

    if ($showTypes) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(operation_type_code, 'Non renseigné') AS type_code,
                COUNT(*) AS total_count,
                COALESCE(SUM(amount),0) AS total_amount
            FROM operations
            WHERE operation_date >= ?
              AND operation_date <= ?
            GROUP BY operation_type_code
            ORDER BY total_amount DESC, total_count DESC
        ");
        $stmt->execute([$periodFrom, $periodTo]);
        $typeRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($showServices) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(rs.label, o.service_account_code, 'Non renseigné') AS service_label,
                COUNT(*) AS total_count,
                COALESCE(SUM(o.amount),0) AS total_amount
            FROM operations o
            LEFT JOIN ref_services rs ON rs.id = o.service_id
            WHERE o.operation_date >= ?
              AND o.operation_date <= ?
            GROUP BY rs.label, o.service_account_code
            ORDER BY total_amount DESC, total_count DESC
        ");
        $stmt->execute([$periodFrom, $periodTo]);
        $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($showAccounting) {
        $stmt = $pdo->prepare("
            SELECT
                'Compte débité 411' AS label,
                COALESCE(SUM(CASE WHEN debit_account_code LIKE '411%' THEN amount ELSE 0 END),0) AS amount
            FROM operations
            WHERE operation_date >= ?
              AND operation_date <= ?

            UNION ALL

            SELECT
                'Compte crédité 411' AS label,
                COALESCE(SUM(CASE WHEN credit_account_code LIKE '411%' THEN amount ELSE 0 END),0) AS amount
            FROM operations
            WHERE operation_date >= ?
              AND operation_date <= ?

            UNION ALL

            SELECT
                'Compte débité 512' AS label,
                COALESCE(SUM(CASE WHEN debit_account_code LIKE '512%' THEN amount ELSE 0 END),0) AS amount
            FROM operations
            WHERE operation_date >= ?
              AND operation_date <= ?

            UNION ALL

            SELECT
                'Compte crédité 512' AS label,
                COALESCE(SUM(CASE WHEN credit_account_code LIKE '512%' THEN amount ELSE 0 END),0) AS amount
            FROM operations
            WHERE operation_date >= ?
              AND operation_date <= ?

            UNION ALL

            SELECT
                'Compte crédité 706' AS label,
                COALESCE(SUM(CASE WHEN credit_account_code LIKE '706%' THEN amount ELSE 0 END),0) AS amount
            FROM operations
            WHERE operation_date >= ?
              AND operation_date <= ?
        ");
        $stmt->execute([
            $periodFrom, $periodTo,
            $periodFrom, $periodTo,
            $periodFrom, $periodTo,
            $periodFrom, $periodTo,
            $periodFrom, $periodTo
        ]);
        $accountingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if ($showCountries && tableExists($pdo, 'clients')) {
    $stmt = $pdo->query("
        SELECT
            COALESCE(country_commercial, 'Non renseigné') AS country_commercial,
            COUNT(*) AS total_clients,
            COALESCE(SUM(CASE WHEN COALESCE(monthly_enabled,0)=1 THEN COALESCE(monthly_amount,0) ELSE 0 END),0) AS total_monthly_amount
        FROM clients
        GROUP BY country_commercial
        ORDER BY total_clients DESC, country_commercial ASC
    ");
    $countryRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-dashboard-hero">
            <div class="sl-dashboard-hero__content">
                <div class="sl-dashboard-hero__eyebrow">Pilotage global</div>
                <h1>Dashboard Studely Ledger</h1>
                <p>
                    Vision consolidée des comptes 411 / 512 / 706, des étudiants actifs,
                    des mensualités, des opérations et des signaux d’alerte clients.
                </p>
            </div>

            <div class="sl-dashboard-hero__meta">
                <div class="sl-hero-pill">
                    <span>Période</span>
                    <strong><?= e($periodFrom) ?> → <?= e($periodTo) ?></strong>
                </div>
                <div class="sl-hero-pill">
                    <span>Mois en cours</span>
                    <strong><?= e(date('m/Y')) ?></strong>
                </div>
            </div>
        </section>

        <div class="sl-filter-card">
            <div class="sl-filter-card__head">
                <div>
                    <h3>Filtres & affichages avancés</h3>
                    <p>Personnalise la vue avec les périodes et les synthèses complémentaires.</p>
                </div>
            </div>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Date du</label>
                        <input type="date" name="date_from" value="<?= e($periodFrom) ?>">
                    </div>

                    <div>
                        <label>au</label>
                        <input type="date" name="date_to" value="<?= e($periodTo) ?>">
                    </div>
                </div>

                <div class="sl-toggle-grid">
                    <label class="sl-toggle-card">
                        <input type="checkbox" name="show_types" value="1" <?= $showTypes ? 'checked' : '' ?>>
                        <span class="sl-toggle-card__title">Types d’opérations</span>
                        <span class="sl-toggle-card__desc">Vue tabulaire consolidée par type</span>
                    </label>

                    <label class="sl-toggle-card">
                        <input type="checkbox" name="show_services" value="1" <?= $showServices ? 'checked' : '' ?>>
                        <span class="sl-toggle-card__title">Services</span>
                        <span class="sl-toggle-card__desc">Synthèse par service rattaché</span>
                    </label>

                    <label class="sl-toggle-card">
                        <input type="checkbox" name="show_countries" value="1" <?= $showCountries ? 'checked' : '' ?>>
                        <span class="sl-toggle-card__title">Pays commerciaux</span>
                        <span class="sl-toggle-card__desc">Répartition consolidée par zone</span>
                    </label>

                    <label class="sl-toggle-card">
                        <input type="checkbox" name="show_accounting" value="1" <?= $showAccounting ? 'checked' : '' ?>>
                        <span class="sl-toggle-card__title">Indicateurs comptables</span>
                        <span class="sl-toggle-card__desc">Synthèse 411 / 512 / 706</span>
                    </label>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Actualiser</button>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="sl-kpi-grid sl-kpi-grid--primary">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Solde global 411</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$cards['solde_411'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__hint">Comptes clients</div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Solde global 512</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$cards['solde_512'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__hint">Trésorerie</div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Solde global 706</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$cards['solde_706'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__hint">Produits / services</div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Clients solde bas</div>
                <div class="sl-kpi-card__value"><?= (int)$cards['low_balance_clients'] ?></div>
                <div class="sl-kpi-card__hint">Solde 411 inférieur à 1000</div>
            </div>
        </div>

        <div class="sl-kpi-grid">
            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Étudiants actifs</div>
                <div class="sl-kpi-card__value"><?= (int)$cards['students_active'] ?></div>
                <div class="sl-kpi-card__hint">Population active</div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Étudiants inactifs</div>
                <div class="sl-kpi-card__value"><?= (int)$cards['students_inactive'] ?></div>
                <div class="sl-kpi-card__hint">Archivés / inactifs</div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Mensualités restantes</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$cards['monthly_remaining'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__hint">À exécuter ce mois</div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Mensualités effectuées</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$cards['monthly_done'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__hint">Déjà passées sur le mois</div>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-bottom:22px;">
            <div class="sl-panel-card">
                <div class="sl-panel-card__head">
                    <div>
                        <h3>Activité sur la période</h3>
                        <p>Volume et montant cumulé des opérations</p>
                    </div>
                </div>

                <div class="sl-metric-split">
                    <div class="sl-metric-box">
                        <span>Nombre d’opérations</span>
                        <strong><?= (int)$cards['period_ops_count'] ?></strong>
                    </div>
                    <div class="sl-metric-box">
                        <span>Montant cumulé</span>
                        <strong><?= e(number_format((float)$cards['period_ops_amount'], 2, ',', ' ')) ?></strong>
                    </div>
                </div>

                <div class="dashboard-note" style="margin-top:14px;">
                    Période analysée : <strong><?= e($periodFrom) ?></strong> à <strong><?= e($periodTo) ?></strong>.
                </div>
            </div>

            <div class="sl-panel-card">
                <div class="sl-panel-card__head">
                    <div>
                        <h3>Accès rapides</h3>
                        <p>Raccourcis vers les modules les plus utilisés</p>
                    </div>
                </div>

                <div class="sl-quick-links">
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="sl-quick-link">Clients</a>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="sl-quick-link">Comptes clients</a>
                    <a href="<?= e(APP_URL) ?>modules/operations/operations_list.php" class="sl-quick-link">Opérations</a>
                    <a href="<?= e(APP_URL) ?>modules/operations/operation_create.php" class="sl-quick-link sl-quick-link--primary">Nouvelle opération</a>
                    <a href="<?= e(APP_URL) ?>modules/monthly_payments/import_monthly_payments.php" class="sl-quick-link">Import mensualités</a>
                    <a href="<?= e(APP_URL) ?>modules/treasury/index.php" class="sl-quick-link">Trésorerie</a>
                </div>
            </div>
        </div>

        <div class="sl-panel-card" style="margin-bottom:22px;">
            <div class="sl-panel-card__head">
                <div>
                    <h3>Clients à solde bas</h3>
                    <p>Clients actifs dont le solde 411 est inférieur à 1000</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table sl-modern-table">
                    <thead>
                        <tr>
                            <th>Code client</th>
                            <th>Nom complet</th>
                            <th>Compte 411</th>
                            <th>Solde courant</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowBalanceRows as $row): ?>
                            <tr>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '')) ?></td>
                                <td><?= e((string)($row['generated_client_account'] ?? '')) ?></td>
                                <td>
                                    <span class="sl-amount sl-amount--alert">
                                        <?= e(number_format((float)($row['balance'] ?? 0), 2, ',', ' ')) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$lowBalanceRows): ?>
                            <tr>
                                <td colspan="5">Aucun client avec solde bas pour le moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($showTypes): ?>
            <div class="sl-panel-card" style="margin-bottom:22px;">
                <div class="sl-panel-card__head">
                    <div>
                        <h3>Types d’opérations</h3>
                        <p>Répartition détaillée sur la période</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table sl-modern-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Nombre</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($typeRows as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['type_code'] ?? 'Non renseigné')) ?></td>
                                    <td><?= (int)($row['total_count'] ?? 0) ?></td>
                                    <td><?= e(number_format((float)($row['total_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$typeRows): ?>
                                <tr><td colspan="3">Aucune donnée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showServices): ?>
            <div class="sl-panel-card" style="margin-bottom:22px;">
                <div class="sl-panel-card__head">
                    <div>
                        <h3>Services</h3>
                        <p>Vue consolidée des montants par service</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table sl-modern-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Nombre</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($serviceRows as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['service_label'] ?? 'Non renseigné')) ?></td>
                                    <td><?= (int)($row['total_count'] ?? 0) ?></td>
                                    <td><?= e(number_format((float)($row['total_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$serviceRows): ?>
                                <tr><td colspan="3">Aucune donnée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showCountries): ?>
            <div class="sl-panel-card" style="margin-bottom:22px;">
                <div class="sl-panel-card__head">
                    <div>
                        <h3>Répartition par pays commercial</h3>
                        <p>Vue consolidée des clients et mensualités actives</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table sl-modern-table">
                        <thead>
                            <tr>
                                <th>Pays commercial</th>
                                <th>Nb clients</th>
                                <th>Mensualités actives</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($countryRows as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['country_commercial'] ?? 'Non renseigné')) ?></td>
                                    <td><?= (int)($row['total_clients'] ?? 0) ?></td>
                                    <td><?= e(number_format((float)($row['total_monthly_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$countryRows): ?>
                                <tr><td colspan="3">Aucune donnée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showAccounting): ?>
            <div class="sl-panel-card" style="margin-bottom:22px;">
                <div class="sl-panel-card__head">
                    <div>
                        <h3>Indicateurs comptables</h3>
                        <p>Synthèse des mouvements principaux sur la période</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table sl-modern-table">
                        <thead>
                            <tr>
                                <th>Indicateur</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accountingRows as $row): ?>
                                <tr>
                                    <td><?= e((string)($row['label'] ?? '')) ?></td>
                                    <td><?= e(number_format((float)($row['amount'] ?? 0), 2, ',', ' ')) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$accountingRows): ?>
                                <tr><td colspan="2">Aucune donnée.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <style>
            .sl-dashboard-hero {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 20px;
                padding: 26px 28px;
                margin-bottom: 22px;
                border-radius: 24px;
                background:
                    radial-gradient(circle at top right, rgba(59,130,246,0.20), transparent 32%),
                    radial-gradient(circle at bottom left, rgba(16,185,129,0.16), transparent 30%),
                    linear-gradient(135deg, #0f172a 0%, #162033 48%, #1e293b 100%);
                color: #fff;
                box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
            }

            .sl-dashboard-hero__content h1 {
                margin: 6px 0 10px;
                font-size: 2rem;
                line-height: 1.1;
                color: #fff;
            }

            .sl-dashboard-hero__content p {
                margin: 0;
                max-width: 760px;
                color: rgba(255,255,255,0.82);
                font-size: 0.98rem;
                line-height: 1.6;
            }

            .sl-dashboard-hero__eyebrow {
                display: inline-flex;
                align-items: center;
                padding: 6px 12px;
                border-radius: 999px;
                background: rgba(255,255,255,0.12);
                color: #dbeafe;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .04em;
                text-transform: uppercase;
            }

            .sl-dashboard-hero__meta {
                display: grid;
                gap: 12px;
                min-width: 220px;
            }

            .sl-hero-pill {
                padding: 14px 16px;
                border-radius: 18px;
                background: rgba(255,255,255,0.10);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(255,255,255,0.10);
            }

            .sl-hero-pill span {
                display: block;
                font-size: 12px;
                color: rgba(255,255,255,0.70);
                margin-bottom: 6px;
            }

            .sl-hero-pill strong {
                display: block;
                color: #fff;
                font-size: 1rem;
                font-weight: 700;
            }

            .sl-filter-card,
            .sl-panel-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 22px;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
                padding: 20px;
            }

            .sl-filter-card {
                margin-bottom: 22px;
            }

            .sl-filter-card__head,
            .sl-panel-card__head {
                display: flex;
                justify-content: space-between;
                align-items: start;
                gap: 16px;
                margin-bottom: 16px;
            }

            .sl-filter-card__head h3,
            .sl-panel-card__head h3 {
                margin: 0 0 4px;
                font-size: 1.1rem;
                color: #0f172a;
            }

            .sl-filter-card__head p,
            .sl-panel-card__head p {
                margin: 0;
                color: #64748b;
                font-size: 0.92rem;
            }

            .sl-toggle-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 12px;
                margin-top: 18px;
            }

            .sl-toggle-card {
                position: relative;
                display: grid;
                gap: 6px;
                padding: 16px 16px 16px 44px;
                border: 1px solid #dbeafe;
                border-radius: 18px;
                background: linear-gradient(180deg, #f8fbff 0%, #f1f7ff 100%);
                cursor: pointer;
                transition: all .18s ease;
            }

            .sl-toggle-card:hover {
                transform: translateY(-1px);
                box-shadow: 0 8px 18px rgba(59,130,246,0.10);
            }

            .sl-toggle-card input {
                position: absolute;
                left: 16px;
                top: 18px;
            }

            .sl-toggle-card__title {
                font-weight: 700;
                color: #0f172a;
            }

            .sl-toggle-card__desc {
                font-size: 0.88rem;
                color: #64748b;
            }

            .sl-kpi-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 16px;
                margin-bottom: 18px;
            }

            .sl-kpi-grid--primary {
                margin-bottom: 16px;
            }

            .sl-kpi-card {
                position: relative;
                overflow: hidden;
                border-radius: 22px;
                padding: 20px;
                color: #0f172a;
                box-shadow: 0 12px 26px rgba(15, 23, 42, 0.07);
                border: 1px solid #e2e8f0;
                background: #fff;
            }

            .sl-kpi-card::after {
                content: "";
                position: absolute;
                right: -18px;
                top: -18px;
                width: 86px;
                height: 86px;
                border-radius: 50%;
                background: rgba(255,255,255,0.15);
            }

            .sl-kpi-card--blue {
                background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
                color: #fff;
                border: none;
            }

            .sl-kpi-card--emerald {
                background: linear-gradient(135deg, #059669 0%, #10b981 100%);
                color: #fff;
                border: none;
            }

            .sl-kpi-card--violet {
                background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
                color: #fff;
                border: none;
            }

            .sl-kpi-card--amber {
                background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
                color: #fff;
                border: none;
            }

            .sl-kpi-card--soft {
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }

            .sl-kpi-card__label {
                font-size: 0.9rem;
                font-weight: 600;
                opacity: 0.92;
                margin-bottom: 10px;
            }

            .sl-kpi-card__value {
                font-size: 1.75rem;
                line-height: 1.1;
                font-weight: 800;
                margin-bottom: 8px;
            }

            .sl-kpi-card__hint {
                font-size: 0.85rem;
                opacity: 0.82;
            }

            .sl-metric-split {
                display: grid;
                grid-template-columns: repeat(2, minmax(0,1fr));
                gap: 14px;
            }

            .sl-metric-box {
                padding: 16px;
                border-radius: 18px;
                background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
            }

            .sl-metric-box span {
                display: block;
                font-size: 0.87rem;
                color: #64748b;
                margin-bottom: 8px;
            }

            .sl-metric-box strong {
                display: block;
                font-size: 1.4rem;
                line-height: 1.1;
                color: #0f172a;
            }

            .sl-quick-links {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 10px;
            }

            .sl-quick-link {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 48px;
                text-decoration: none;
                border-radius: 16px;
                border: 1px solid #dbeafe;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                color: #1e3a8a;
                font-weight: 700;
                transition: all .18s ease;
            }

            .sl-quick-link:hover {
                transform: translateY(-1px);
                box-shadow: 0 10px 20px rgba(59,130,246,0.10);
            }

            .sl-quick-link--primary {
                background: linear-gradient(135deg, #10b981 0%, #22c55e 100%);
                color: #fff;
                border: none;
            }

            .sl-modern-table th,
            .sl-modern-table td {
                padding-top: 10px;
                padding-bottom: 10px;
                vertical-align: middle;
            }

            .sl-modern-table thead th {
                background: #f8fafc;
                color: #334155;
                font-size: 0.83rem;
                text-transform: uppercase;
                letter-spacing: .03em;
            }

            .sl-modern-table tbody tr:hover {
                background: #f8fbff;
            }

            .sl-amount {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                font-weight: 700;
                font-size: 0.88rem;
            }

            .sl-amount--alert {
                background: #fef2f2;
                color: #b91c1c;
            }

            @media (max-width: 980px) {
                .sl-dashboard-hero {
                    flex-direction: column;
                }

                .sl-dashboard-hero__meta {
                    width: 100%;
                    grid-template-columns: repeat(2, minmax(0,1fr));
                }

                .sl-metric-split {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .sl-dashboard-hero {
                    padding: 20px;
                    border-radius: 20px;
                }

                .sl-dashboard-hero__content h1 {
                    font-size: 1.55rem;
                }

                .sl-dashboard-hero__meta {
                    grid-template-columns: 1fr;
                }

                .sl-kpi-card__value {
                    font-size: 1.45rem;
                }
            }
        </style>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>