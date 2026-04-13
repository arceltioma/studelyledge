<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'pending_debits_list_page');
} else {
    enforcePagePermission($pdo, 'pending_debits_list');
}

if (!tableExists($pdo, 'pending_client_debits')) {
    exit('Table pending_client_debits introuvable.');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$clientFilter = trim((string)($_GET['client'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];

$selectParts = [
    'pd.*'
];

$joinClient = '';
if (tableExists($pdo, 'clients') && columnExists($pdo, 'pending_client_debits', 'client_id')) {
    $joinClient = 'LEFT JOIN clients c ON c.id = pd.client_id';
    $selectParts[] = 'c.client_code';
    $selectParts[] = 'c.full_name';
    $selectParts[] = 'c.generated_client_account';
}

if ($statusFilter !== '') {
    $where[] = 'pd.status = ?';
    $params[] = $statusFilter;
}

if ($clientFilter !== '' && ctype_digit($clientFilter)) {
    $where[] = 'pd.client_id = ?';
    $params[] = (int)$clientFilter;
}

if ($q !== '') {
    $where[] = '(
        pd.label LIKE ?
        OR pd.trigger_type LIKE ?
        OR pd.notes LIKE ?
        OR c.client_code LIKE ?
        OR c.full_name LIKE ?
        OR c.generated_client_account LIKE ?
    )';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
    SELECT " . implode(', ', $selectParts) . "
    FROM pending_client_debits pd
    {$joinClient}
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY
    CASE pd.status
        WHEN 'ready' THEN 1
        WHEN 'partial' THEN 2
        WHEN 'pending' THEN 3
        WHEN 'resolved' THEN 4
        WHEN 'cancelled' THEN 5
        ELSE 6
    END,
    pd.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$clients = [];
if (tableExists($pdo, 'clients')) {
    $stmtClients = $pdo->query("
        SELECT id, client_code, full_name
        FROM clients
        WHERE COALESCE(is_active, 1) = 1
        ORDER BY client_code ASC, full_name ASC
    ");
    $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function sl_pending_debit_badge_class(string $status): string
{
    return match ($status) {
        'ready' => 'success',
        'partial' => 'warning',
        'pending' => 'info',
        'resolved' => 'success',
        'cancelled' => 'danger',
        default => 'secondary',
    };
}

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

        <div class="card">
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
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/pending_debits/pending_debits_list.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Liste des débits dus</h3>

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
                            <?php $status = (string)($item['status'] ?? ''); ?>
                            <tr>
                                <td><?= (int)($item['id'] ?? 0) ?></td>
                                <td><?= e(trim((string)($item['client_code'] ?? '') . ' - ' . (string)($item['full_name'] ?? '')) ?: '—') ?></td>
                                <td><?= e((string)($item['generated_client_account'] ?? '—')) ?></td>
                                <td><?= e((string)($item['label'] ?? '')) ?></td>
                                <td><?= e((string)($item['trigger_type'] ?? '')) ?></td>
                                <td><?= e(number_format((float)($item['initial_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($item['executed_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e(number_format((float)($item['remaining_amount'] ?? 0), 2, ',', ' ')) ?></td>
                                <td><?= e((string)($item['currency_code'] ?? 'EUR')) ?></td>
                                <td>
                                    <span class="badge badge-<?= e(sl_pending_debit_badge_class($status)) ?>">
                                        <?= e($status !== '' ? $status : '—') ?>
                                    </span>
                                </td>
                                <td><?= e((string)($item['priority_level'] ?? '—')) ?></td>
                                <td><?= e((string)($item['created_at'] ?? '')) ?></td>
                                <td>
                                    <div class="btn-group">
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
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>