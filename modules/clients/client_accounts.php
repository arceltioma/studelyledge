<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

$pageTitle = 'Comptes clients 411';
$pageSubtitle = 'Vue compacte et consolidée des comptes 411 rattachés aux clients';

$filters = sl_parse_common_list_filters($_GET);
$kpis = sl_client_accounts_get_kpis($pdo, $filters);
$data = sl_client_accounts_get_rows($pdo, $filters);

$rows = $data['rows'] ?? [];
$page = (int)($data['page'] ?? 1);
$pages = (int)($data['pages'] ?? 1);
$total = (int)($data['total'] ?? count($rows));
$perPage = (int)($data['per_page'] ?? ($filters['per_page'] ?? 20));
$from = (int)($data['from'] ?? 0);
$to = (int)($data['to'] ?? 0);

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="sl-kpi-grid">
            <div class="sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Nb comptes 411</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['accounts_count'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Total filtré</span>
                    <strong>Comptes</strong>
                </div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Total solde initial</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['initial_balance_total'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Base d'ouverture</span>
                    <strong>411</strong>
                </div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Total solde courant</div>
                <div class="sl-kpi-card__value"><?= e(number_format((float)$kpis['current_balance_total'], 2, ',', ' ')) ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Montant filtré</span>
                    <strong>En cours</strong>
                </div>
            </div>

            <div class="sl-kpi-card sl-kpi-card--rose">
                <div class="sl-kpi-card__label">Comptes négatifs</div>
                <div class="sl-kpi-card__value"><?= (int)$kpis['negative_accounts_count'] ?></div>
                <div class="sl-kpi-card__meta">
                    <span>À surveiller</span>
                    <strong>Alertes</strong>
                </div>
            </div>
        </div>

        <div class="sl-filter-card" style="margin-top:20px;">
            <form method="GET">
                <div class="dashboard-grid-4">
                    <div>
                        <label>Recherche</label>
                        <input
                            type="text"
                            name="q"
                            value="<?= e((string)$filters['q']) ?>"
                            placeholder="Compte 411, nom, code client"
                        >
                    </div>

                    <div>
                        <label>Statut client</label>
                        <select name="client_status">
                            <option value="">Tous</option>
                            <option value="active" <?= ($filters['client_status'] ?? '') === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="archived" <?= ($filters['client_status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archivés</option>
                        </select>
                    </div>

                    <div>
                        <label>Solde</label>
                        <select name="balance_filter">
                            <option value="">Tous</option>
                            <option value="positive" <?= ($filters['balance_filter'] ?? '') === 'positive' ? 'selected' : '' ?>>Positif</option>
                            <option value="negative" <?= ($filters['balance_filter'] ?? '') === 'negative' ? 'selected' : '' ?>>Négatif</option>
                            <option value="zero" <?= ($filters['balance_filter'] ?? '') === 'zero' ? 'selected' : '' ?>>À zéro</option>
                            <option value="non_zero" <?= ($filters['balance_filter'] ?? '') === 'non_zero' ? 'selected' : '' ?>>Non nul</option>
                        </select>
                    </div>

                    <div>
                        <label>Par page</label>
                        <select name="per_page">
                            <?php foreach ([10, 20, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= (int)$perPage === $size ? 'selected' : '' ?>>
                                    <?= $size ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="dashboard-grid-4" style="margin-top:16px;">
                    <div>
                        <label>Solde minimum</label>
                        <input
                            type="number"
                            step="0.01"
                            name="balance_min"
                            value="<?= e((string)($filters['balance_min'] ?? '')) ?>"
                            placeholder="Ex. 0"
                        >
                    </div>

                    <div>
                        <label>Solde maximum</label>
                        <input
                            type="number"
                            step="0.01"
                            name="balance_max"
                            value="<?= e((string)($filters['balance_max'] ?? '')) ?>"
                            placeholder="Ex. 5000"
                        >
                    </div>

                    <div>
                        <label>Tri</label>
                        <select name="sort">
                            <option value="account_number_asc" <?= ($filters['sort'] ?? '') === 'account_number_asc' ? 'selected' : '' ?>>Compte 411 croissant</option>
                            <option value="account_number_desc" <?= ($filters['sort'] ?? '') === 'account_number_desc' ? 'selected' : '' ?>>Compte 411 décroissant</option>
                            <option value="client_name_asc" <?= ($filters['sort'] ?? '') === 'client_name_asc' ? 'selected' : '' ?>>Client A → Z</option>
                            <option value="client_name_desc" <?= ($filters['sort'] ?? '') === 'client_name_desc' ? 'selected' : '' ?>>Client Z → A</option>
                            <option value="balance_asc" <?= ($filters['sort'] ?? '') === 'balance_asc' ? 'selected' : '' ?>>Solde croissant</option>
                            <option value="balance_desc" <?= ($filters['sort'] ?? '') === 'balance_desc' ? 'selected' : '' ?>>Solde décroissant</option>
                            <option value="initial_balance_desc" <?= ($filters['sort'] ?? '') === 'initial_balance_desc' ? 'selected' : '' ?>>Solde initial décroissant</option>
                        </select>
                    </div>

                    <div>
                        <label>&nbsp;</label>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-success">Filtrer</button>
                            <a href="<?= e(APP_URL) ?>modules/clients/client_accounts.php" class="btn btn-outline">Réinitialiser</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="sl-card-head">
                <div>
                    <h3>Liste des comptes clients 411</h3>
                    <p class="sl-card-head-subtitle">
                        <?= e((string)$from) ?> à <?= e((string)$to) ?> sur <?= e((string)$total) ?> ligne(s)
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table sl-modern-table">
                    <thead>
                        <tr>
                            <th>Compte 411</th>
                            <th>Nom du compte</th>
                            <th>Client</th>
                            <th>Statut client</th>
                            <th>Solde initial</th>
                            <th>Solde courant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $isActive = (int)($row['client_is_active'] ?? 1) === 1; ?>
                            <tr>
                                <td><?= e((string)($row['account_number'] ?? '')) ?></td>
                                <td><?= e((string)($row['account_name'] ?? '')) ?></td>
                                <td><?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($row['full_name'] ?? '')) ?: '—') ?></td>
                                <td>
                                    <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $isActive ? 'Actif' : 'Archivé' ?>
                                    </span>
                                </td>
                                <td><?= e(number_format((float)($row['initial_balance'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($row['balance'] ?? 0), 2, ',', ' ')) ?></td>
                                <td>
                                    <div class="btn-group btn-group--compact">
                                        <?php if (!empty($row['client_id'])): ?>
                                            <a href="<?= e(APP_URL) ?>modules/clients/client_account_view.php?id=<?= (int)$row['client_id'] ?>"class="btn btn-outline btn-sm">Voir</a>
                                            <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)$row['client_id'] ?>" class="btn btn-outline btn-sm">Client</a>
                                            <a href="<?= e(APP_URL) ?>modules/clients/client_edit.php?id=<?= (int)$row['client_id'] ?>" class="btn btn-success btn-sm">Modifier</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7">Aucun compte client trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="btn-group" style="margin-top:18px; justify-content:space-between; align-items:center;">
                    <div class="muted">
                        Page <?= (int)$page ?> / <?= (int)$pages ?>
                    </div>

                    <div class="btn-group">
                        <?php
                        $baseQuery = $_GET;

                        if ($page > 1):
                            $firstQuery = $baseQuery;
                            $firstQuery['page'] = 1;

                            $prevQuery = $baseQuery;
                            $prevQuery['page'] = $page - 1;
                        ?>
                            <a href="?<?= e(http_build_query($firstQuery)) ?>" class="btn btn-outline btn-sm">« Première</a>
                            <a href="?<?= e(http_build_query($prevQuery)) ?>" class="btn btn-outline btn-sm">‹ Précédente</a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($pages, $page + 2);

                        for ($i = $startPage; $i <= $endPage; $i++):
                            $query = $baseQuery;
                            $query['page'] = $i;
                        ?>
                            <a href="?<?= e(http_build_query($query)) ?>" class="btn <?= $i === $page ? 'btn-success' : 'btn-outline' ?> btn-sm">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        if ($page < $pages):
                            $nextQuery = $baseQuery;
                            $nextQuery['page'] = $page + 1;

                            $lastQuery = $baseQuery;
                            $lastQuery['page'] = $pages;
                        ?>
                            <a href="?<?= e(http_build_query($nextQuery)) ?>" class="btn btn-outline btn-sm">Suivante ›</a>
                            <a href="?<?= e(http_build_query($lastQuery)) ?>" class="btn btn-outline btn-sm">Dernière »</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>