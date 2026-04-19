<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if (!function_exists('aue_get_roles')) {
    function aue_get_roles(PDO $pdo): array
    {
        return function_exists('getRoleOptions')
            ? getRoleOptions($pdo)
            : $pdo->query("SELECT id, code, label FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('aue_find_user')) {
    function aue_find_user(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, username, role_id, is_active, role
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('aue_find_role')) {
    function aue_find_role(PDO $pdo, int $roleId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, code, label
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($userId <= 0) {
    exit('Utilisateur invalide.');
}

$user = aue_find_user($pdo, $userId);
if (!$user) {
    exit('Utilisateur introuvable.');
}

$roles = aue_get_roles($pdo);

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$formData = [
    'username' => $user['username'] ?? '',
    'role_id' => (string)($user['role_id'] ?? ''),
    'password' => '',
    'is_active' => (int)($user['is_active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'username' => trim((string)($_POST['username'] ?? '')),
        'role_id' => ($_POST['role_id'] ?? '') !== '' ? (string)(int)$_POST['role_id'] : '',
        'password' => (string)($_POST['password'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $action = (string)($_POST['form_action'] ?? '');

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $username = $formData['username'];
        $roleId = $formData['role_id'] !== '' ? (int)$formData['role_id'] : 0;
        $isActive = $formData['is_active'];
        $password = $formData['password'];

        if ($username === '') {
            throw new RuntimeException('Le nom utilisateur est obligatoire.');
        }

        if ($roleId <= 0) {
            throw new RuntimeException('Le rôle est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtDup->execute([$username, $userId]);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce nom utilisateur est déjà utilisé.');
        }

        $role = aue_find_role($pdo, $roleId);
        if (!$role) {
            throw new RuntimeException('Rôle invalide.');
        }

        if ($action === 'preview') {
            $permissionCount = 0;
            if (tableExists($pdo, 'role_permissions')) {
                $stmtPerms = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ?");
                $stmtPerms->execute([$roleId]);
                $permissionCount = (int)$stmtPerms->fetchColumn();
            }

            $previewMode = true;
            $previewData = [
                'current_user' => $user,
                'username' => $username,
                'role' => $role,
                'is_active' => $isActive,
                'password_changed' => $password !== '',
                'permission_count' => $permissionCount,
            ];
        } elseif ($action === 'save') {
            if ($password !== '') {
                $stmtUpdate = $pdo->prepare("
                    UPDATE users
                    SET
                        username = :username,
                        role = :role,
                        role_id = :role_id,
                        is_active = :is_active,
                        password = :password,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':username' => $username,
                    ':role' => $role['code'],
                    ':role_id' => $roleId,
                    ':is_active' => $isActive,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $userId,
                ]);
            } else {
                $stmtUpdate = $pdo->prepare("
                    UPDATE users
                    SET
                        username = :username,
                        role = :role,
                        role_id = :role_id,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':username' => $username,
                    ':role' => $role['code'],
                    ':role_id' => $roleId,
                    ':is_active' => $isActive,
                    ':id' => $userId,
                ]);
            }

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'edit_user',
                    'admin',
                    'user',
                    $userId,
                    "Modification de l'utilisateur {$username}"
                );
            }

            $successMessage = 'Utilisateur mis à jour avec succès.';
            $user = aue_find_user($pdo, $userId);

            $formData = [
                'username' => $user['username'] ?? '',
                'role_id' => (string)($user['role_id'] ?? ''),
                'password' => '',
                'is_active' => (int)($user['is_active'] ?? 1),
            ];

            $previewMode = false;
            $previewData = null;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Modifier un utilisateur';
$pageSubtitle = 'Ajuster un compte sans casser les permissions.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$userId ?>">

                    <div class="dashboard-grid-2">
                        <div>
                            <label for="username">Nom utilisateur</label>
                            <input type="text" id="username" name="username" value="<?= e($formData['username']) ?>" required>
                        </div>

                        <div>
                            <label for="role_id">Rôle</label>
                            <select id="role_id" name="role_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>" <?= (string)$formData['role_id'] === (string)$role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="password">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password" value="<?= e($formData['password']) ?>" placeholder="Laisser vide pour conserver l’actuel">
                        </div>

                        <div style="display:flex;align-items:flex-end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                                Utilisateur actif
                            </label>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="form_action" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="form_action" value="save" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Utilisateur actuel</span>
                            <strong><?= e($previewData['current_user']['username'] ?? '') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Nouveau nom</span>
                            <strong><?= e($previewData['username']) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Nouveau rôle</span>
                            <strong><?= e(($previewData['role']['label'] ?? '') . ' (' . ($previewData['role']['code'] ?? '') . ')') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Actif</span>
                            <strong><?= (int)$previewData['is_active'] === 1 ? 'Oui' : 'Non' ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Mot de passe modifié</span>
                            <strong><?= !empty($previewData['password_changed']) ? 'Oui' : 'Non' ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Permissions héritées</span>
                            <strong><?= (int)$previewData['permission_count'] ?></strong>
                        </div>
                    </div>

                    <div class="dashboard-note" style="margin-top:14px;">
                        Le mot de passe n’est modifié que si une nouvelle valeur est saisie.
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Le rôle sélectionné pilote les permissions via la matrice d’accès. Le mot de passe n’est modifié que si un nouveau mot de passe est saisi.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>