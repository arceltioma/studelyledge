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

$filters = [
    'period_start' => trim((string)($_GET['period_start'] ?? date('Y-m-01'))),
    'period_end' => trim((string)($_GET['period_end'] ?? date('Y-m-t'))),
];

$showTypes = isset($_GET['show_types']) ? 1 : 0;
$showServices = isset($_GET['show_services']) ? 1 : 0;
$showCountries = isset($_GET['show_countries']) ? 1 : 0;
$showIndicators = isset($_GET['show_indicators']) ? 1 : 0;
$showPendingDebits = isset($_GET['show_pending_debits']) ? 1 : 0;
$showLowBalances = isset($_GET['show_low_balances']) ? 1 : 0;
$showPendingDebitsHistory = isset($_GET['show_pending_debits_history']) ? 1 : 0;

$dashboard = function_exists('sl_dashboard_get_global_kpis')
    ? sl_dashboard_get_global_kpis($pdo, $filters)
    : [];

$extraCounters = function_exists('sl_dashboard_get_extra_counters')
    ? sl_dashboard_get_extra_counters($pdo)
    : [
        'monthly_active_count' => 0,
        'monthly_pending_count' => 0,
        'manual_operations_count' => 0,
    ];

$balances = $dashboard['balances'] ?? [];
$students = $dashboard['students'] ?? [];
$monthly = $dashboard['monthly'] ?? [];
$operations = $dashboard['operations'] ?? [];
$lowBalance = $dashboard['low_balance_clients'] ?? [];
$pendingDebits = $dashboard['pending_debits'] ?? [];

$typesRows = $dashboard['types_rows'] ?? [];
$servicesRows = $dashboard['services_rows'] ?? [];
$commercialCountriesRows = $dashboard['commercial_countries_rows'] ?? [];
$accountingIndicatorsRows = $dashboard['accounting_indicators_rows'] ?? [];
$pendingDebitRows = $pendingDebits['rows'] ?? [];
$pendingDebitsHistoryRows = $dashboard['pending_debits_history_rows'] ?? [];

$lowBalanceRows = function_exists('sl_dashboard_get_low_balance_clients')
    ? sl_dashboard_get_low_balance_clients($pdo, 1000, 12)
    : [];

$pageTitle = 'Dashboard';
$pageSubtitle = 'Vue consolidée des indicateurs clients, opérations, comptes 411 / 512 / 706 et débits dus.';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="sl-dashboard-shell">
            <div class="sl-toolbar-card">
                <form method="get" class="sl-toolbar-form">
                    <div class="sl-toolbar-grid">
                        <div>
                            <label for="period_start">Période du</label>
                            <input type="date" id="period_start" name="period_start" value="<?= e($filters['period_start']) ?>">
                        </div>

                        <div>
                            <label for="period_end">au</label>
                            <input type="date" id="period_end" name="period_end" value="<?= e($filters['period_end']) ?>">
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-success">Actualiser</button>
                            <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Réinitialiser</a>
                        </div>
                    </div>

                    <div>
                        <label>Affichages complémentaires</label>
                        <div class="sl-check-grid">
                            <label class="sl-check-item">
                                <input type="checkbox" name="show_types" value="1" <?= $showTypes ? 'checked' : '' ?>>
                                <span>Types d’opérations</span>
                            </label>

                            <label class="sl-check-item">
                                <input type="checkbox" name="show_services" value="1" <?= $showServices ? 'checked' : '' ?>>
                                <span>Services</span>
                            </label>

                            <label class="sl-check-item">
                                <input type="checkbox" name="show_countries" value="1" <?= $showCountries ? 'checked' : '' ?>>
                                <span>Répartition par pays commercial</span>
                            </label>

                            <label class="sl-check-item">
                                <input type="checkbox" name="show_indicators" value="1" <?= $showIndicators ? 'checked' : '' ?>>
                                <span>Indicateurs comptables</span>
                            </label>

                            <label class="sl-check-item">
                                <input type="checkbox" name="show_pending_debits" value="1" <?= $showPendingDebits ? 'checked' : '' ?>>
                                <span>Détails des débits dus</span>
                            </label>

                            <label class="sl-check-item">
                                <input type="checkbox" name="show_low_balances" value="1" <?= $showLowBalances ? 'checked' : '' ?>>
                                <span>Clients avec solde 411 bas</span>
                            </label>

                            <label class="sl-check-item">
                                <input type="checkbox" name="show_pending_debits_history" value="1" <?= $showPendingDebitsHistory ? 'checked' : '' ?>>
                                <span>Historique clients avec débit dû</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

<div class="sl-kpi-grid">
    <div class="sl-kpi-card is-info">
        <div class="sl-kpi-label">Solde 411</div>
        <div class="sl-kpi-value"><?= number_format((float)($balances['accounts_411'] ?? 0), 2, ',', ' ') ?></div>
        <div class="sl-kpi-sub">Comptes clients</div>
    </div>

    <div class="sl-kpi-card is-dark">
        <div class="sl-kpi-label">Solde 512</div>
        <div class="sl-kpi-value"><?= number_format((float)($balances['accounts_512'] ?? 0), 2, ',', ' ') ?></div>
        <div class="sl-kpi-sub">Trésorerie</div>
    </div>

    <div class="sl-kpi-card is-success">
        <div class="sl-kpi-label">Solde 706</div>
        <div class="sl-kpi-value"><?= number_format((float)($balances['accounts_706'] ?? 0), 2, ',', ' ') ?></div>
        <div class="sl-kpi-sub">Produits / services</div>
    </div>

    <div class="sl-kpi-card is-violet">
        <div class="sl-kpi-label">Total global</div>
        <div class="sl-kpi-value"><?= number_format((float)($balances['global_total'] ?? 0), 2, ',', ' ') ?></div>
        <div class="sl-kpi-sub">411 + 512 + 706</div>
    </div>

    <div class="sl-kpi-card is-success">
        <div class="sl-kpi-label">Étudiants actifs</div>
        <div class="sl-kpi-value"><?= (int)($students['active'] ?? 0) ?></div>
        <div class="sl-kpi-sub">Dossiers en cours</div>
    </div>

    <div class="sl-kpi-card is-danger">
        <div class="sl-kpi-label">Étudiants inactifs</div>
        <div class="sl-kpi-value"><?= (int)($students['inactive'] ?? 0) ?></div>
        <div class="sl-kpi-sub">Archivés / inactifs</div>
    </div>

    <div class="sl-kpi-card is-warning">
        <div class="sl-kpi-label">Mensualités restantes</div>
        <div class="sl-kpi-value"><?= number_format((float)($monthly['remaining_current_month'] ?? 0), 2, ',', ' ') ?></div>
        <div class="sl-kpi-sub">À faire ce mois</div>
    </div>

    <div class="sl-kpi-card is-success">
        <div class="sl-kpi-label">Mensualités effectuées</div>
        <div class="sl-kpi-value"><?= number_format((float)($monthly['done_current_month'] ?? 0), 2, ',', ' ') ?></div>
        <div class="sl-kpi-sub">Déjà passées</div>
    </div>

    <div class="sl-kpi-card is-info">
        <div class="sl-kpi-label">Opérations période</div>
        <div class="sl-kpi-value"><?= (int)($operations['count'] ?? 0) ?></div>
        <div class="sl-kpi-sub"><?= number_format((float)($operations['amount'] ?? 0), 2, ',', ' ') ?></div>
    </div>

    <div class="sl-kpi-card is-warning">
        <div class="sl-kpi-label">Débits dus ouverts</div>
        <div class="sl-kpi-value"><?= (int)($pendingDebits['count'] ?? 0) ?></div>
        <div class="sl-kpi-sub"><?= number_format((float)($pendingDebits['amount'] ?? 0), 2, ',', ' ') ?></div>
    </div>

    <div class="sl-kpi-card is-dark">
        <div class="sl-kpi-label">Mensualités actives</div>
        <div class="sl-kpi-value"><?= (int)($extraCounters['monthly_active_count'] ?? 0) ?></div>
        <div class="sl-kpi-sub">Clients mensualisés</div>
    </div>

    <div class="sl-kpi-card is-danger">
        <div class="sl-kpi-label">Clients solde bas</div>
        <div class="sl-kpi-value"><?= (int)($lowBalance['count'] ?? 0) ?></div>
        <div class="sl-kpi-sub">411 &lt; 1 000</div>
    </div>
</div>

            <div class="sl-dashboard-grid-2">
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Débits dus non réglés</h3>
                            <p>Suivi rapide des clients ayant encore un montant restant dû.</p>
                        </div>
                    </div>

                    <?php if (!empty($pendingDebitRows)): ?>
                        <div class="sl-table-wrap">
                            <table class="sl-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Libellé</th>
                                        <th>Montant dû</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingDebitRows as $row): ?>
                                        <?php
                                        $status = strtolower((string)($row['status'] ?? 'pending'));
                                        $badgeClass = 'sl-badge-warning';
                                        if (in_array($status, ['settled', 'resolved'], true)) {
                                            $badgeClass = 'sl-badge-success';
                                        } elseif (in_array($status, ['cancelled', 'closed'], true)) {
                                            $badgeClass = 'sl-badge-danger';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= e(trim((($row['client_code'] ?? '') !== '' ? ($row['client_code'] . ' - ') : '') . ($row['full_name'] ?? 'Client'))) ?></td>
                                            <td><?= e((string)($row['label'] ?? 'Débit dû')) ?></td>
                                            <td><strong><?= number_format((float)($row['remaining_amount'] ?? 0), 2, ',', ' ') ?></strong></td>
                                            <td><span class="sl-badge <?= $badgeClass ?>"><?= e((string)($row['status'] ?? 'pending')) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="sl-alert-soft">Aucun débit dû non réglé actuellement.</div>
                    <?php endif; ?>
                </div>

                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Accès rapides</h3>
                            <p>Raccourcis vers les zones les plus utilisées.</p>
                        </div>
                    </div>

                    <div class="sl-quick-links">
                        <a class="sl-quick-link" href="<?= e(APP_URL) ?>modules/clients/clients_list.php">
                            <strong>Clients</strong>
                            <small>Consulter et gérer les clients</small>
                        </a>

                        <a class="sl-quick-link" href="<?= e(APP_URL) ?>modules/operations/operations_list.php">
                            <strong>Opérations</strong>
                            <small>Historique et suivi comptable</small>
                        </a>

                        <a class="sl-quick-link" href="<?= e(APP_URL) ?>modules/operations/operation_create.php">
                            <strong>Nouvelle opération</strong>
                            <small>Créer une opération manuelle</small>
                        </a>

                        <a class="sl-quick-link" href="<?= e(APP_URL) ?>modules/treasury/index.php">
                            <strong>Trésorerie</strong>
                            <small>Vue sur les comptes 512</small>
                        </a>

                        <a class="sl-quick-link" href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php">
                            <strong>Débits dus</strong>
                            <small>Suivre les reliquats clients</small>
                        </a>

                        <a class="sl-quick-link" href="<?= e(APP_URL) ?>modules/clients/client_accounts.php">
                            <strong>Comptes 411</strong>
                            <small>Suivi des soldes clients</small>
                        </a>
                    </div>

                    <div class="sl-mini-list" style="margin-top:16px;">
                        <div class="sl-mini-row">
                            <span>Clients sous seuil 411</span>
                            <strong><?= (int)($lowBalance['count'] ?? 0) ?></strong>
                        </div>
                        <div class="sl-mini-row">
                            <span>Total global 411 + 512 + 706</span>
                            <strong><?= number_format((float)($balances['global_total'] ?? 0), 2, ',', ' ') ?></strong>
                        </div>
                        <div class="sl-mini-row">
                            <span>Débits dus ouverts</span>
                            <strong><?= number_format((float)($pendingDebits['amount'] ?? 0), 2, ',', ' ') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($showLowBalances): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Clients avec solde 411 bas</h3>
                            <p>Clients dont le solde du compte 411 est inférieur à 1 000.</p>
                        </div>
                    </div>

                    <?php if (!empty($lowBalanceRows)): ?>
                        <div class="sl-table-wrap">
                            <table class="sl-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Compte 411</th>
                                        <th>Solde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowBalanceRows as $row): ?>
                                        <tr>
                                            <td><?= e((string)(($row['client_code'] ?? '') . ' - ' . ($row['full_name'] ?? ''))) ?></td>
                                            <td><?= e((string)($row['generated_client_account'] ?? '')) ?></td>
                                            <td><strong><?= number_format((float)($row['balance'] ?? 0), 2, ',', ' ') ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="sl-alert-soft">Aucun client sous le seuil défini.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($showPendingDebits): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Détail des débits dus</h3>
                            <p>Affichage détaillé des reliquats encore non soldés.</p>
                        </div>
                    </div>

                    <?php if (!empty($pendingDebitRows)): ?>
                        <div class="sl-table-wrap">
                            <table class="sl-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Libellé</th>
                                        <th>Montant restant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingDebitRows as $row): ?>
                                        <tr>
                                            <td><?= e(trim((($row['client_code'] ?? '') !== '' ? ($row['client_code'] . ' - ') : '') . ($row['full_name'] ?? 'Client'))) ?></td>
                                            <td><?= e((string)($row['label'] ?? 'Débit dû')) ?></td>
                                            <td><?= number_format((float)($row['remaining_amount'] ?? 0), 2, ',', ' ') ?></td>
                                            <td><?= e((string)($row['status'] ?? 'pending')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="sl-alert-soft">Aucune ligne de débit dû à afficher.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($showPendingDebitsHistory): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Historique des clients ayant déjà eu un débit dû</h3>
                            <p>Nombre de fois, montant cumulé et temps moyen de règlement par client.</p>
                        </div>
                    </div>

                    <?php if (!empty($pendingDebitsHistoryRows)): ?>
                        <div class="sl-table-wrap">
                            <table class="sl-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Compte 411</th>
                                        <th>Nb de fois</th>
                                        <th>Montant cumulé</th>
                                        <th>Nb réglés</th>
                                        <th>Délai moyen</th>
                                        <th>Dernier débit dû</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingDebitsHistoryRows as $row): ?>
                                        <tr>
                                            <td><?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($row['full_name'] ?? ''))) ?></td>
                                            <td><?= e((string)($row['generated_client_account'] ?? '—')) ?></td>
                                            <td><?= (int)($row['total_pending_debits'] ?? 0) ?></td>
                                            <td><?= number_format((float)($row['total_pending_amount'] ?? 0), 2, ',', ' ') ?></td>
                                            <td><?= (int)($row['resolved_count'] ?? 0) ?></td>
                                            <td>
                                                <?php
                                                $avgHours = (float)($row['avg_resolution_hours'] ?? 0);
                                                if ($avgHours <= 0) {
                                                    echo '—';
                                                } elseif ($avgHours < 24) {
                                                    echo e(number_format($avgHours, 1, ',', ' ')) . ' h';
                                                } else {
                                                    echo e(number_format($avgHours / 24, 1, ',', ' ')) . ' j';
                                                }
                                                ?>
                                            </td>
                                            <td><?= e((string)($row['last_pending_created_at'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="sl-alert-soft">Aucun historique de débit dû trouvé.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($showTypes): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Types d’opérations</h3>
                            <p>Répartition des opérations par type.</p>
                        </div>
                    </div>

                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Nombre</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($typesRows as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['operation_type_code'] ?? 'N/A')) ?></td>
                                        <td><?= (int)($row['total_count'] ?? 0) ?></td>
                                        <td><?= number_format((float)($row['total_amount'] ?? 0), 2, ',', ' ') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showServices): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Services</h3>
                            <p>Répartition des montants par service.</p>
                        </div>
                    </div>

                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Nombre</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servicesRows as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['service_label'] ?? 'N/A')) ?></td>
                                        <td><?= (int)($row['total_count'] ?? 0) ?></td>
                                        <td><?= number_format((float)($row['total_amount'] ?? 0), 2, ',', ' ') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showCountries): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Répartition par pays commercial</h3>
                            <p>Vue consolidée clients / mensualités par zone commerciale.</p>
                        </div>
                    </div>

                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Pays commercial</th>
                                    <th>Nb clients</th>
                                    <th>Mensualités</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commercialCountriesRows as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['country_commercial'] ?? 'N/A')) ?></td>
                                        <td><?= (int)($row['total_clients'] ?? 0) ?></td>
                                        <td><?= number_format((float)($row['total_monthly_amount'] ?? 0), 2, ',', ' ') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showIndicators): ?>
                <div class="sl-panel">
                    <div class="sl-panel-head">
                        <div>
                            <h3>Indicateurs comptables</h3>
                            <p>Synthèse rapide des principaux indicateurs 411 / 512 / 706.</p>
                        </div>
                    </div>

                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Indicateur</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accountingIndicatorsRows as $row): ?>
                                    <tr>
                                        <td><?= e((string)($row['label'] ?? '')) ?></td>
                                        <td><?= number_format((float)($row['amount'] ?? 0), 2, ',', ' ') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>