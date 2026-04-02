<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'imports_journal_page');
} else {
    enforcePagePermission($pdo, 'imports_journal');
}

$pageTitle = 'Journal des imports';
$pageSubtitle = 'Suivi des imports, erreurs, doublons et opérations créées';

$search = trim((string)($_GET['search'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$dateFrom = trim((string)($_GET['from'] ?? ''));
$dateTo = trim((string)($_GET['to'] ?? ''));

$flashSuccess = $_SESSION['success_message'] ?? '';
$flashError = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$logs = [];
$canUseLogs = tableExists($pdo, 'user_logs');

if ($canUseLogs) {
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = "(COALESCE(l.action,'') LIKE ? OR COALESCE(l.module,'') LIKE ? OR COALESCE(l.entity_type,'') LIKE ? OR COALESCE(l.details,'') LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($module !== '' && columnExists($pdo, 'user_logs', 'module')) {
        $where[] = 'l.module = ?';
        $params[] = $module;
    }

    if ($action !== '' && columnExists($pdo, 'user_logs', 'action')) {
        $where[] = 'l.action = ?';
        $params[] = $action;
    }

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && columnExists($pdo, 'user_logs', 'created_at')) {
        $where[] = 'DATE(l.created_at) >= ?';
        $params[] = $dateFrom;
    }

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) && columnExists($pdo, 'user_logs', 'created_at')) {
        $where[] = 'DATE(l.created_at) <= ?';
        $params[] = $dateTo;
    }

    $userJoin = '';
    $selectUser = 'NULL AS username';
    if (tableExists($pdo, 'users') && columnExists($pdo, 'user_logs', 'user_id')) {
        $userJoin = 'LEFT JOIN users u ON u.id = l.user_id';
        $selectUser = columnExists($pdo, 'users', 'username') ? 'u.username AS username' : 'NULL AS username';
    }

    $orderBy = columnExists($pdo, 'user_logs', 'created_at') ? 'l.created_at DESC' : 'l.id DESC';

    $sql = "
        SELECT
            l.*,
            {$selectUser}
        FROM user_logs l
        {$userJoin}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderBy}
        LIMIT 300
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$availableModules = [];
$availableActions = [];

if ($canUseLogs && columnExists($pdo, 'user_logs', 'module')) {
    $availableModules = $pdo->query("
        SELECT DISTINCT module
        FROM user_logs
        WHERE COALESCE(module,'') <> ''
        ORDER BY module ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

if ($canUseLogs && columnExists($pdo, 'user_logs', 'action')) {
    $availableActions = $pdo->query("
        SELECT DISTINCT action
        FROM user_logs
        WHERE COALESCE(action,'') <> ''
        ORDER BY action ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
}

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($flashSuccess !== ''): ?>
            <div class="success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError !== ''): ?>
            <div class="error"><?= e($flashError) ?></div>
        <?php endif; ?>

        <section class="sl-card sl-stable-block" style="margin-bottom:20px;">
            <form method="GET" class="sl-grid sl-grid-5">
                <div>
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Mot-clé, détail, action...">
                </div>

                <div>
                    <label for="module">Module</label>
                    <select id="module" name="module">
                        <option value="">Tous</option>
                        <?php foreach ($availableModules as $moduleOption): ?>
                            <option value="<?= e((string)$moduleOption) ?>" <?= $module === (string)$moduleOption ? 'selected' : '' ?>>
                                <?= e((string)$moduleOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="action">Action</label>
                    <select id="action" name="action">
                        <option value="">Toutes</option>
                        <?php foreach ($availableActions as $actionOption): ?>
                            <option value="<?= e((string)$actionOption) ?>" <?= $action === (string)$actionOption ? 'selected' : '' ?>>
                                <?= e((string)$actionOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="from">Du</label>
                    <input type="date" id="from" name="from" value="<?= e($dateFrom) ?>">
                </div>

                <div>
                    <label for="to">Au</label>
                    <input type="date" id="to" name="to" value="<?= e($dateTo) ?>">
                </div>

                <div style="grid-column:1 / -1; display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-success">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_journal.php" class="btn btn-outline">Réinitialiser</a>
                    <a href="<?= e(APP_URL) ?>modules/imports/import_upload.php" class="btn btn-secondary">Nouvel import</a>
                </div>
            </form>
        </section>

        <section class="sl-card sl-stable-block">
            <div class="sl-card-head">
                <div>
                    <h3>Historique des imports et actions liées</h3>
                    <p class="sl-card-head-subtitle">Vision centralisée des imports et opérations journalisées</p>
                </div>
            </div>

            <?php if (!$canUseLogs): ?>
                <div class="warning">La table <strong>user_logs</strong> est absente. Le journal ne peut pas être affiché.</div>
            <?php else: ?>
                <div class="sl-table-wrap">
                    <table class="sl-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Type entité</th>
                                <th>ID entité</th>
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= e((string)($log['created_at'] ?? '')) ?></td>
                                        <td><?= e((string)($log['username'] ?? ($log['user_id'] ?? ''))) ?></td>
                                        <td><?= e((string)($log['action'] ?? '')) ?></td>
                                        <td><?= e((string)($log['module'] ?? '')) ?></td>
                                        <td><?= e((string)($log['entity_type'] ?? '')) ?></td>
                                        <td><?= e((string)($log['entity_id'] ?? '')) ?></td>
                                        <td><?= e((string)($log['details'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Aucune ligne trouvée pour les filtres actuels.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>