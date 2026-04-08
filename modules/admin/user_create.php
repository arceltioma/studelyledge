<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'admin_users_manage');

if (!function_exists('auc_get_roles')) {
    function auc_get_roles(PDO $pdo): array
    {
        return function_exists('getRoleOptions')
            ? getRoleOptions($pdo)
            : $pdo->query("SELECT id, code, label FROM roles ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('auc_find_role')) {
    function auc_find_role(PDO $pdo, int $roleId): ?array
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

$roles = auc_get_roles($pdo);

$errorMessage = '';
$successMessage = '';
$previewMode = false;
$previewData = null;

$formData = [
    'username' => '',
    'role_id' => '',
    'password' => '',
    'is_active' => 1,
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
        $password = $formData['password'];
        $roleId = $formData['role_id'] !== '' ? (int)$formData['role_id'] : 0;
        $isActive = $formData['is_active'];

        if ($username === '' || $password === '') {
            throw new RuntimeException('Le nom utilisateur et le mot de passe sont obligatoires.');
        }

        if ($roleId <= 0) {
            throw new RuntimeException('Le rôle est obligatoire.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmtDup->execute([$username]);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce nom utilisateur existe déjà.');
        }

        $role = auc_find_role($pdo, $roleId);
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
                'username' => $username,
                'role' => $role,
                'is_active' => $isActive,
                'password_length' => strlen($password),
                'permission_count' => $permissionCount,
            ];
        } elseif ($action === 'save') {
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username,
                    password,
                    role,
                    role_id,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $role['code'],
                $roleId,
                $isActive
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                logUserAction(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'create_user',
                    'admin',
                    'user',
                    $newUserId,
                    'Création d’un utilisateur ' . $username
                );
            }

            header('Location: ' . APP_URL . 'modules/admin/users.php');
            exit;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Créer un utilisateur';
$pageSubtitle = 'Création d’un nouveau compte applicatif.';
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

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Nom utilisateur</label>
                            <input type="text" name="username" value="<?= e($formData['username']) ?>" required>
                        </div>

                        <div>
                            <label>Rôle</label>
                            <select name="role_id" required>
                                <option value="">Choisir</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['id'] ?>" <?= (string)$formData['role_id'] === (string)$role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['label']) ?> (<?= e($role['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Mot de passe</label>
                            <input type="password" name="password" value="<?= e($formData['password']) ?>" required>
                        </div>

                        <div style="display:flex;align-items:end;">
                            <label style="display:flex;gap:8px;align-items:center;">
                                <input type="checkbox" name="is_active" <?= (int)$formData['is_active'] === 1 ? 'checked' : '' ?>>
                                Utilisateur actif
                            </label>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="form_action" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="form_action" value="save" class="btn btn-success">Créer</button>
                        <a href="<?= e(APP_URL) ?>modules/admin/users.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Nom utilisateur</span>
                            <strong><?= e($previewData['username']) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Rôle</span>
                            <strong><?= e(($previewData['role']['label'] ?? '') . ' (' . ($previewData['role']['code'] ?? '') . ')') ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Utilisateur actif</span>
                            <strong><?= (int)$previewData['is_active'] === 1 ? 'Oui' : 'Non' ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Longueur mot de passe</span>
                            <strong><?= (int)$previewData['password_length'] ?> caractères</strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Permissions héritées</span>
                            <strong><?= (int)$previewData['permission_count'] ?></strong>
                        </div>
                    </div>

                    <div class="dashboard-note" style="margin-top:14px;">
                        Le mot de passe sera hashé à la création et l’utilisateur héritera des permissions du rôle sélectionné.
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Le compte créé héritera des permissions du rôle sélectionné via la matrice d’accès. Le mot de passe est hashé à la création.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>