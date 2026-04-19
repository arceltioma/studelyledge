<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$pageTitle = 'Liste des clients';
$pageSubtitle = 'Vue consolidée des clients, comptes 411 et mensualités';

$filters = sl_parse_common_list_filters($_GET);
$kpis = sl_clients_list_get_kpis($pdo, $filters);
$data = sl_clients_list_get_rows($pdo, $filters);

$rows = $data['rows'];
$page = $data['page'];
$pages = $data['pages'];
$perPage = $data['per_page'];
$total = $data['total'];

$clientTypes = function_exists('studely_client_types') ? studely_client_types() : [];
$commercialCountries = function_exists('studely_commercial_countries') ? studely_commercial_countries() : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="sl-kpi-grid">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Clients</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['total_clients'] ?></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Actifs</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['active_clients'] ?></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--rose">
                <div class="sl-kpi-card__label">Archivés</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['inactive_clients'] ?></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Solde initial 411</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['initial_balance_total'], 2, ',', ' ')) ?></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Solde courant 411</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['current_balance_total'], 2, ',', ' ')) ?></div>
            </div>
            <div class="sl-kpi-card sl-kpi-card--soft">
                <div class="sl-kpi-card__label">Mensualités actives</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['monthly_amount_total'], 2, ',', ' ')) ?></div>
            </div>
        </div>

        <div class="sl-filter-card" style="margin-top:20px;">
            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Code, nom, email, compte 411">
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Archivés</option>
                        </select>
                    </div>

                    <div>
                        <label>Type client</label>
                        <select name="client_type">
                            <option value="">Tous</option>
                            <?php foreach ($clientTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($filters['client_type'] ?? '') === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Pays commercial</label>
                        <select name="country_commercial">
                            <option value="">Tous</option>
                            <?php foreach ($commercialCountries as $country): ?>
                                <option value="<?= e($country) ?>" <?= ($filters['country_commercial'] ?? '') === $country ? 'selected' : '' ?>>
                                    <?= e($country) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Lignes par page</label>
                        <select name="per_page">
                            <?php foreach ([10, 20, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= (int)$perPage === $size ? 'selected' : '' ?>>
                                    <?= $size ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:18px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/clients/clients_list.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/clients/client_create.php" class="btn btn-primary">Nouveau client</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="sl-card-head">
                <div>
                    <h3>Liste des clients</h3>
                    <p class="sl-card-head-subtitle">
                        <?= (int)$total ?> résultat(s) — page <?= (int)$page ?> / <?= (int)$pages ?>
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table sl-modern-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom complet</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Compte 411</th>
                            <th>Solde courant</th>
                            <th>512 principal</th>
                            <th>Mensualité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $isActive = (int)($row['is_active'] ?? 1) === 1; ?>
                            <tr>
                                <td><?= e((string)($row['client_code'] ?? '')) ?></td>
                                <td><?= e((string)($row['full_name'] ?? '')) ?></td>
                                <td><?= e((string)($row['client_type'] ?? '')) ?></td>
                                <td>
                                    <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $isActive ? 'Actif' : 'Archivé' ?>
                                    </span>
                                </td>
                                <td><?= e((string)($row['generated_client_account'] ?? '')) ?></td>
                                <td><?= e(number_format((float)($row['current_balance_411'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(trim((string)($row['treasury_account_code'] ?? '') . ' - ' . (string)($row['treasury_account_label'] ?? '')) ?: '—') ?></td>
                                <td><?= e(number_format((float)($row['monthly_amount'] ?? 0), 2, ',', ' ')) ?></td>
<td>
    <div class="btn-group btn-group--compact">
        <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
        <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-success btn-sm">Modifier</a>
        <a href="<?= e(APP_URL) ?>modules/clients/clients_archive.php?id=<?= (int)$row['id'] ?>&action=<?= $isActive ? 'archive' : 'restore' ?>" class="btn btn-warning btn-sm">
            <?= $isActive ? 'Archiver' : 'Réactiver' ?>
        </a>
        <a href="<?= e(APP_URL) ?>modules/clients/client_delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression ?');">Supprimer</a>
    </div>
</td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="9">Aucun client trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="btn-group" style="margin-top:18px;">
                    <?php
                    $prevQuery = $_GET;
                    $prevQuery['page'] = max(1, $page - 1);

                    $nextQuery = $_GET;
                    $nextQuery['page'] = min($pages, $page + 1);
                    ?>

                    <a href="?<?= e(http_build_query($prevQuery)) ?>" class="btn btn-outline <?= $page <= 1 ? 'disabled' : '' ?>">
                        Précédent
                    </a>

                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <?php
                        if (
                            $i === 1 ||
                            $i === $pages ||
                            abs($i - $page) <= 2
                        ):
                            $query = $_GET;
                            $query['page'] = $i;
                        ?>
                            <a href="?<?= e(http_build_query($query)) ?>" class="btn <?= $i === $page ? 'btn-success' : 'btn-outline' ?>">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i === 2 && $page > 4): ?>
                            <span class="btn btn-outline">...</span>
                        <?php elseif ($i === $pages - 1 && $page < $pages - 3): ?>
                            <span class="btn btn-outline">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <a href="?<?= e(http_build_query($nextQuery)) ?>" class="btn btn-outline <?= $page >= $pages ? 'disabled' : '' ?>">
                        Suivant
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>