<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'pending_debits_view_page');
} else {
    enforcePagePermission($pdo, 'pending_debits_view');
}

if (!tableExists($pdo, 'pending_client_debits')) {
    exit('Table pending_client_debits introuvable.');
}

$filters = function_exists('sl_pending_debits_list_parse_filters')
    ? sl_pending_debits_list_parse_filters($_GET)
    : [
        'q' => trim((string)($_GET['q'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'client' => trim((string)($_GET['client'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => 25,
    ];

$listData = function_exists('sl_pending_debits_list_get_rows')
    ? sl_pending_debits_list_get_rows($pdo, $filters)
    : [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => 25,
        'pages' => 1,
    ];

$kpis = function_exists('sl_pending_debits_list_get_kpis')
    ? sl_pending_debits_list_get_kpis($pdo, $filters)
    : [
        'total_count' => 0,
        'pending_count' => 0,
        'ready_count' => 0,
        'partial_count' => 0,
        'resolved_count' => 0,
        'cancelled_count' => 0,
        'initial_amount_total' => 0.0,
        'executed_amount_total' => 0.0,
        'remaining_amount_total' => 0.0,
    ];

$clients = function_exists('sl_pending_debits_list_get_clients')
    ? sl_pending_debits_list_get_clients($pdo)
    : [];

$items = $listData['rows'] ?? [];
$total = (int)($listData['total'] ?? 0);
$page = (int)($listData['page'] ?? 1);
$perPage = (int)($listData['per_page'] ?? 25);
$pages = (int)($listData['pages'] ?? 1);

$q = (string)($filters['q'] ?? '');
$statusFilter = (string)($filters['status'] ?? '');
$clientFilter = (string)($filters['client'] ?? '');

$pageTitle = 'Débits dus';
$pageSubtitle = 'Suivi des débits clients 411 en attente, partiels, prêts ou soldés';

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if (isset($_GET['executed'])): ?>
            <div class="success">Le débit dû a bien été exécuté.</div>
        <?php endif; ?>

        <div class="sl-kpi-grid sl-kpi-grid--compact">
            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Total débits dus</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['total_count'] ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">En attente</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['pending_count'] ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Prêts</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['ready_count'] ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Partiels</span>
                <strong class="sl-kpi-card__value"><?= (int)$kpis['partial_count'] ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Montant initial</span>
                <strong class="sl-kpi-card__value"><?= e(number_format((float)$kpis['initial_amount_total'], 2, ',', ' ')) ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Déjà exécuté</span>
                <strong class="sl-kpi-card__value"><?= e(number_format((float)$kpis['executed_amount_total'], 2, ',', ' ')) ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Restant dû</span>
                <strong class="sl-kpi-card__value"><?= e(number_format((float)$kpis['remaining_amount_total'], 2, ',', ' ')) ?></strong>
            </div>

            <div class="sl-kpi-card">
                <span class="sl-kpi-card__label">Résolus / annulés</span>
                <strong class="sl-kpi-card__value">
                    <?= (int)$kpis['resolved_count'] ?> / <?= (int)$kpis['cancelled_count'] ?>
                </strong>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-2">
                    <div>
                        <label>Recherche libre</label>
                        <input
                            type="text"
                            name="q"
                            value="<?= e($q) ?>"
                            placeholder="Client, compte 411, libellé, notes..."
                        >
                    </div>

                    <div>
                        <label>Statut</label>
                        <select name="status">
                            <option value="">Tous</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>pending</option>
                            <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>partial</option>
                            <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>ready</option>
                            <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>resolved</option>
                            <option value="settled" <?= $statusFilter === 'settled' ? 'selected' : '' ?>>settled</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label>Client</label>
                        <select name="client">
                            <option value="">Tous</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int)$client['id'] ?>" <?= $clientFilter === (string)$client['id'] ? 'selected' : '' ?>>
                                    <?= e((string)($client['client_code'] ?? '') . ' - ' . (string)($client['full_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Résultats par page</label>
                        <select name="per_page">
                            <?php foreach ([10, 25, 50, 100] as $size): ?>
                                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="sl-card-head">
                <div>
                    <h3>Liste des débits dus</h3>
                    <p class="muted"><?= (int)$total ?> résultat(s)</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Compte 411</th>
                            <th>Libellé</th>
                            <th>Déclencheur</th>
                            <th>Initial</th>
                            <th>Exécuté</th>
                            <th>Restant</th>
                            <th>Devise</th>
                            <th>Statut</th>
                            <th>Priorité</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $status = strtolower(trim((string)($item['status'] ?? ''))); ?>
                            <tr>
                                <td><?= (int)($item['id'] ?? 0) ?></td>
                                <td><?= e(trim((string)($item['client_code'] ?? '') . ' - ' . (string)($item['full_name'] ?? '')) ?: '—') ?></td>
                                <td><?= e((string)($item['generated_client_account'] ?? '—')) ?></td>
                                <td><?= e((string)($item['label'] ?? '')) ?></td>
                                <td><?= e((string)($item['trigger_type'] ?? '')) ?></td>
                                <td><?= e(number_format((float)($item['initial_amount'] ?? $item['amount_due'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($item['executed_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($item['remaining_amount'] ?? $item['amount_due'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e((string)($item['currency_code'] ?? 'EUR')) ?></td>
                                <td>
                                    <span class="badge badge-<?= e(function_exists('sl_pending_debit_badge_class') ? sl_pending_debit_badge_class($status) : 'secondary') ?>">
                                        <?= e($status !== '' ? $status : '—') ?>
                                    </span>
                                </td>
                                <td><?= e((string)($item['priority_level'] ?? '—')) ?></td>
                                <td><?= e((string)($item['created_at'] ?? '')) ?></td>
                                <td>
                                    <div class="btn-group btn-group--compact">
                                        <a
                                            href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_view.php?id=<?= (int)$item['id'] ?>"
                                            class="btn btn-outline"
                                        >
                                            Voir
                                        </a>

                                        <?php if (in_array($status, ['pending', 'partial', 'ready'], true)): ?>
                                            <a
                                                href="<?= e(APP_URL) ?>modules/pending_debits/pending_debit_execute.php?id=<?= (int)$item['id'] ?>"
                                                class="btn btn-success"
                                            >
                                                Initier
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr>
                                <td colspan="13">Aucun débit dû trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="btn-group" style="margin-top:18px;">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <a
                            class="btn <?= $p === $page ? 'btn-success' : 'btn-outline' ?>"
                            href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php?<?= http_build_query([
                                'q' => $q,
                                'status' => $statusFilter,
                                'client' => $clientFilter,
                                'per_page' => $perPage,
                                'page' => $p,
                            ]) ?>"
                        >
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>