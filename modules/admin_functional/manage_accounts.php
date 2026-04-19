<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$pageTitle = 'Comptes fonctionnels';
$pageSubtitle = 'Lecture croisée des comptes 411, 512 et 706 avec recalcul des mouvements sur période.';

$filters = sl_manage_accounts_parse_filters($_GET);

$baseData = sl_manage_accounts_load_base_data($pdo);
$clientAccounts = $baseData['client_accounts'];
$treasuryAccounts = $baseData['treasury_accounts'];
$serviceAccounts = $baseData['service_accounts'];

$movementMaps = sl_manage_accounts_load_movement_maps(
    $pdo,
    $filters['from'],
    $filters['to'],
    $treasuryAccounts
);

$filteredClientAccounts = sl_manage_accounts_filter_client_rows($clientAccounts, $filters);
$filteredTreasuryAccounts = sl_manage_accounts_filter_treasury_rows($treasuryAccounts, $filters);
$filteredServiceAccounts = sl_manage_accounts_filter_service_rows($serviceAccounts, $filters);

$summary = sl_manage_accounts_build_summary(
    $filteredClientAccounts,
    $filteredTreasuryAccounts,
    $filteredServiceAccounts,
    $movementMaps
);

$filterOptions = sl_manage_accounts_build_filter_options(
    $clientAccounts,
    $treasuryAccounts,
    $serviceAccounts
);

$clientPagination = sl_manage_accounts_paginate_array(
    $filteredClientAccounts,
    (int)$filters['client_page'],
    (int)$filters['per_page']
);

$treasuryPagination = sl_manage_accounts_paginate_array(
    $filteredTreasuryAccounts,
    (int)$filters['treasury_page'],
    (int)$filters['per_page']
);

$servicePagination = sl_manage_accounts_paginate_array(
    $filteredServiceAccounts,
    (int)$filters['service_page'],
    (int)$filters['per_page']
);

$clientAccountsPageRows = $clientPagination['rows'];
$treasuryAccountsPageRows = $treasuryPagination['rows'];
$serviceAccountsPageRows = $servicePagination['rows'];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="card" style="margin-bottom:20px;">
            <h3 class="section-title">Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Du</label>
                        <input type="date" name="from" value="<?= e($filters['from']) ?>">
                    </div>

                    <div>
                        <label>Au</label>
                        <input type="date" name="to" value="<?= e($filters['to']) ?>">
                    </div>

                    <div>
                        <label>Recherche</label>
                        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Code, libellé, client, pays...">
                    </div>

                    <div>
                        <label>Famille</label>
                        <select name="family">
                            <option value="">Toutes</option>
                            <option value="411" <?= $filters['family'] === '411' ? 'selected' : '' ?>>411</option>
                            <option value="512" <?= $filters['family'] === '512' ? 'selected' : '' ?>>512</option>
                            <option value="706" <?= $filters['family'] === '706' ? 'selected' : '' ?>>706</option>
                        </select>
                    </div>

                    <div>
                        <label>Statut actif / archivé</label>
                        <select name="active">
                            <option value="">Tous</option>
                            <option value="active" <?= $filters['active'] === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="inactive" <?= $filters['active'] === 'inactive' ? 'selected' : '' ?>>Archivés</option>
                        </select>
                    </div>

                    <div>
                        <label>Statut client</label>
                        <select name="client_status">
                            <option value="">Tous</option>
                            <option value="active" <?= $filters['client_status'] === 'active' ? 'selected' : '' ?>>Clients actifs</option>
                            <option value="inactive" <?= $filters['client_status'] === 'inactive' ? 'selected' : '' ?>>Clients archivés</option>
                        </select>
                    </div>

                    <div>
                        <label>Type client</label>
                        <select name="client_type">
                            <option value="">Tous</option>
                            <?php foreach ($filterOptions['client_types'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= $filters['client_type'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Type 706</label>
                        <select name="postable">
                            <option value="">Tous</option>
                            <option value="postable" <?= $filters['postable'] === 'postable' ? 'selected' : '' ?>>Postables</option>
                            <option value="structure" <?= $filters['postable'] === 'structure' ? 'selected' : '' ?>>Structures</option>
                        </select>
                    </div>

                    <div>
                        <label>Pays commercial</label>
                        <select name="country_commercial">
                            <option value="">Tous</option>
                            <?php foreach ($filterOptions['commercial_countries'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= $filters['country_commercial'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays destination</label>
                        <select name="country_destination">
                            <option value="">Tous</option>
                            <?php foreach ($filterOptions['destination_countries'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= $filters['country_destination'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Banque</label>
                        <select name="bank">
                            <option value="">Toutes</option>
                            <?php foreach ($filterOptions['banks'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= $filters['bank'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Devise</label>
                        <select name="currency">
                            <option value="">Toutes</option>
                            <?php foreach ($filterOptions['currencies'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= $filters['currency'] === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Lignes par page</label>
                        <select name="per_page">
                            <?php foreach ($filters['allowed_per_page'] as $value): ?>
                                <option value="<?= (int)$value ?>" <?= (int)$filters['per_page'] === (int)$value ? 'selected' : '' ?>><?= (int)$value ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-secondary">Actualiser</button>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="dashboard-grid-3" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Comptes 411</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Nombre</span><strong><?= (int)$summary['411']['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= sl_manage_accounts_money((float)$summary['411']['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédits période</span><strong><?= sl_manage_accounts_money((float)$summary['411']['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits période</span><strong><?= sl_manage_accounts_money((float)$summary['411']['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= sl_manage_accounts_money((float)$summary['411']['period_net']) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 512</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Nombre</span><strong><?= (int)$summary['512']['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= sl_manage_accounts_money((float)$summary['512']['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédits période</span><strong><?= sl_manage_accounts_money((float)$summary['512']['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits période</span><strong><?= sl_manage_accounts_money((float)$summary['512']['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= sl_manage_accounts_money((float)$summary['512']['period_net']) ?></strong></div>
                </div>
            </div>

            <div class="card">
                <h3 class="section-title">Comptes 706</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Nombre</span><strong><?= (int)$summary['706']['count'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Solde courant cumulé</span><strong><?= sl_manage_accounts_money((float)$summary['706']['current_balance']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Crédits période</span><strong><?= sl_manage_accounts_money((float)$summary['706']['period_credit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Débits période</span><strong><?= sl_manage_accounts_money((float)$summary['706']['period_debit']) ?></strong></div>
                    <div class="sl-data-list__row"><span>Net période</span><strong><?= sl_manage_accounts_money((float)$summary['706']['period_net']) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="page-title page-title-inline">
                <div>
                    <h3 class="section-title">Comptes clients 411</h3>
                    <p class="muted">Mouvements recalculés sur la période sélectionnée.</p>
                </div>
                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="btn btn-outline">Voir les comptes clients</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Compte 411</th>
                            <th>Client</th>
                            <th>Pays</th>
                            <th>Type</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Crédit période</th>
                            <th>Débit période</th>
                            <th>Net période</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientAccountsPageRows as $row): ?>
                            <?php
                            $code = (string)($row['account_number'] ?? '');
                            $mv = $movementMaps['client'][$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];
                            ?>
                            <tr>
                                <td><?= e($code) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '—')) ?></td>
                                <td><?= e((string)($row['country_commercial'] ?? '—')) ?></td>
                                <td><?= e((string)($row['client_type'] ?? '—')) ?></td>
                                <td><?= sl_manage_accounts_money((float)($row['initial_balance'] ?? 0)) ?></td>
                                <td><?= sl_manage_accounts_money((float)($row['balance'] ?? 0)) ?></td>
                                <td><?= sl_manage_accounts_money((float)$mv['credit']) ?></td>
                                <td><?= sl_manage_accounts_money((float)$mv['debit']) ?></td>
                                <td><?= sl_manage_accounts_money((float)$mv['net']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$clientAccountsPageRows): ?>
                            <tr><td colspan="9">Aucun compte 411.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($clientPagination['pages'] > 1): ?>
                <div class="btn-group" style="margin-top:18px;">
                    <?php for ($i = 1; $i <= $clientPagination['pages']; $i++): ?>
                        <?php $query = sl_manage_accounts_build_page_query($_GET, 'client_page', $i); ?>
                        <a href="?<?= e(http_build_query($query)) ?>" class="btn <?= $i === $clientPagination['page'] ? 'btn-success' : 'btn-outline' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid-1" style="display:grid; grid-template-columns:1fr; gap:20px;">
    <div class="table-card">
        <div class="page-title page-title-inline">
            <div>
                <h3 class="section-title">Comptes 706</h3>
            </div>
            <div class="btn-group">
                <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Gérer les 706</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Type op</th>
                        <th>Destination</th>
                        <th>Commercial</th>
                        <th>Solde courant</th>
                        <th>Crédit période</th>
                        <th>Débit période</th>
                        <th>Net période</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serviceAccountsPageRows as $row): ?>
                        <?php
                        $code = (string)($row['account_code'] ?? '');
                        $mv = $movementMaps['service'][$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];
                        ?>
                        <tr>
                            <td><?= e($code) ?></td>
                            <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                            <td><?= e((string)($row['operation_type_label'] ?? '')) ?></td>
                            <td><?= e((string)($row['destination_country_label'] ?? '')) ?></td>
                            <td><?= e((string)($row['commercial_country_label'] ?? '')) ?></td>
                            <td><?= sl_manage_accounts_money((float)($row['current_balance'] ?? 0)) ?></td>
                            <td><?= sl_manage_accounts_money((float)$mv['credit']) ?></td>
                            <td><?= sl_manage_accounts_money((float)$mv['debit']) ?></td>
                            <td><?= sl_manage_accounts_money((float)$mv['net']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$serviceAccountsPageRows): ?>
                        <tr><td colspan="9">Aucun compte 706.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($servicePagination['pages'] > 1): ?>
            <div class="btn-group" style="margin-top:18px;">
                <?php for ($i = 1; $i <= $servicePagination['pages']; $i++): ?>
                    <?php $query = sl_manage_accounts_build_page_query($_GET, 'service_page', $i); ?>
                    <a href="?<?= e(http_build_query($query)) ?>" class="btn <?= $i === $servicePagination['page'] ? 'btn-success' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
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

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Banque</th>
                        <th>Pays</th>
                        <th>Devise</th>
                        <th>Solde courant</th>
                        <th>Crédit période</th>
                        <th>Débit période</th>
                        <th>Net période</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($treasuryAccountsPageRows as $row): ?>
                        <?php
                        $code = (string)($row['account_code'] ?? '');
                        $mv = $movementMaps['treasury'][$code] ?? ['credit' => 0.0, 'debit' => 0.0, 'net' => 0.0];
                        ?>
                        <tr>
                            <td><?= e($code) ?></td>
                            <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                            <td><?= e((string)($row['bank_name'] ?? '')) ?></td>
                            <td><?= e((string)($row['country_label'] ?? '')) ?></td>
                            <td><?= e((string)($row['currency_code'] ?? '')) ?></td>
                            <td><?= sl_manage_accounts_money((float)($row['current_balance'] ?? 0)) ?></td>
                            <td><?= sl_manage_accounts_money((float)$mv['credit']) ?></td>
                            <td><?= sl_manage_accounts_money((float)$mv['debit']) ?></td>
                            <td><?= sl_manage_accounts_money((float)$mv['net']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$treasuryAccountsPageRows): ?>
                        <tr><td colspan="9">Aucun compte 512.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($treasuryPagination['pages'] > 1): ?>
            <div class="btn-group" style="margin-top:18px;">
                <?php for ($i = 1; $i <= $treasuryPagination['pages']; $i++): ?>
                    <?php $query = sl_manage_accounts_build_page_query($_GET, 'treasury_page', $i); ?>
                    <a href="?<?= e(http_build_query($query)) ?>" class="btn <?= $i === $treasuryPagination['page'] ? 'btn-success' : 'btn-outline' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>