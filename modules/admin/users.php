<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if (!function_exists('au_like')) {
    function au_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterRoleId = trim((string)($_GET['filter_role_id'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));
$filterLogin = trim((string)($_GET['filter_login'] ?? ''));

$roles = tableExists($pdo, 'roles')
    ? $pdo->query("SELECT id, code, label FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$sql = "
    SELECT u.*, r.label AS role_label, r.code AS role_code
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE 1=1
";
$params = [];

if ($filterSearch !== '') {
    $sql .= " AND (u.username LIKE ? OR COALESCE(r.label, '') LIKE ? OR COALESCE(r.code, '') LIKE ?) ";
    $params[] = au_like($filterSearch);
    $params[] = au_like($filterSearch);
    $params[] = au_like($filterSearch);
}

if ($filterRoleId !== '') {
    $sql .= " AND u.role_id = ? ";
    $params[] = (int)$filterRoleId;
}

if ($filterStatus === 'active') {
    $sql .= " AND COALESCE(u.is_active, 1) = 1 ";
} elseif ($filterStatus === 'inactive') {
    $sql .= " AND COALESCE(u.is_active, 1) = 0 ";
}

if ($filterLogin === 'logged') {
    $sql .= " AND u.last_login_at IS NOT NULL ";
} elseif ($filterLogin === 'never') {
    $sql .= " AND u.last_login_at IS NULL ";
}

$sql .= " ORDER BY u.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dashboard = [
    'total' => count($rows),
    'active' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'logged' => count(array_filter($rows, fn($r) => !empty($r['last_login_at']))),
    'never' => count(array_filter($rows, fn($r) => empty($r['last_login_at']))),
];

$pageTitle = 'Utilisateurs';
$pageSubtitle = 'Création, édition, activation et suivi des comptes utilisateurs.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="page-title">
            <div>
                <h2>Comptes utilisateurs</h2>
                <p class="muted">Gestion centralisée des comptes et de leurs rôles.</p>
            </div>

            <div class="btn-group">
                <a class="btn btn-primary" href="<?= e(APP_URL) ?>modules/admin/user_create.php">Créer un utilisateur</a>
            </div>
        </div>

        <div class="dashboard-grid-2" style="margin-bottom:20px;">
            <div class="card">
                <h3 class="section-title">Dashboard utilisateurs</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Total</span><strong><?= (int)$dashboard['total'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Actifs</span><strong><?= (int)$dashboard['active'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Inactifs</span><strong><?= (int)$dashboard['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Déjà connectés</span><strong><?= (int)$dashboard['logged'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Jamais connectés</span><strong><?= (int)$dashboard['never'] ?></strong></div>
                </div>
            </div>

            <div class="form-card">
                <h3 class="section-title">Filtres utilisateurs</h3>
                <form method="GET">
                    <div class="dashboard-grid-2">
                        <div>
                            <label>Recherche</label>
                            <input type="text" name="filter_search" value="<?= e($filterSearch) ?>" placeholder="Utilisateur ou rôle">
                        </div>

                        <div>
                            <label>Rôle</label>
                            <select name="filter_role_id">
                                <option value="">Tous</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>" <?= $filterRoleId === (string)$role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Statut</label>
                            <select name="filter_status">
                                <option value="">Tous</option>
                                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                            </select>
                        </div>

                        <div>
                            <label>Connexion</label>
                            <select name="filter_login">
                                <option value="">Toutes</option>
                                <option value="logged" <?= $filterLogin === 'logged' ? 'selected' : '' ?>>Déjà connectés</option>
                                <option value="never" <?= $filterLogin === 'never' ? 'selected' : '' ?>>Jamais connectés</option>
                            </select>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Filtrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Actif</th>
                        <th>Dernière connexion</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['username'] ?? '') ?></td>
                            <td><?= e($row['role_label'] ?? $row['role'] ?? '') ?></td>
                            <td><?= ((int)($row['is_active'] ?? 1) === 1) ? 'Oui' : 'Non' ?></td>
                            <td><?= e($row['last_login_at'] ?? '—') ?></td>
                            <td><?= e($row['created_at'] ?? '') ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin/user_edit.php?id=<?= (int)$row['id'] ?>">Modifier</a>
                                    <a class="btn btn-danger" href="<?= e(APP_URL) ?>modules/admin/user_delete.php?id=<?= (int)$row['id'] ?>">Archiver</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rows): ?>
                        <tr><td colspan="6">Aucun utilisateur.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>