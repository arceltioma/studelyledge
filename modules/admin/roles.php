<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

enforcePagePermission($pdo, 'admin_roles_manage');

if (!function_exists('ar_fetch_roles')) {
    function ar_fetch_roles(PDO $pdo): array
    {
        return $pdo->query("
            SELECT *
            FROM roles
            ORDER BY label ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('ar_fetch_role_by_id')) {
    function ar_fetch_role_by_id(PDO $pdo, int $roleId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT *
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('ar_normalize_role_payload')) {
    function ar_normalize_role_payload(array $source): array
    {
        return [
            'code'  => trim((string)($source['code'] ?? '')),
            'label' => trim((string)($source['label'] ?? '')),
        ];
    }
}

if (!function_exists('ar_validate_role_payload')) {
    function ar_validate_role_payload(PDO $pdo, array $payload, int $editId = 0): void
    {
        if ($payload['code'] === '' || $payload['label'] === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $sqlDup = "SELECT id FROM roles WHERE code = ?";
        $paramsDup = [$payload['code']];

        if ($editId > 0) {
            $sqlDup .= " AND id <> ?";
            $paramsDup[] = $editId;
        }

        $sqlDup .= " LIMIT 1";

        $stmtDup = $pdo->prepare($sqlDup);
        $stmtDup->execute($paramsDup);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code rôle existe déjà.');
        }
    }
}

if (!function_exists('ar_preview_summary')) {
    function ar_preview_summary(PDO $pdo, array $payload, int $editId = 0): array
    {
        $linkedUsers = 0;
        $linkedPermissions = 0;
        $currentRole = null;

        if ($editId > 0) {
            $currentRole = ar_fetch_role_by_id($pdo, $editId);

            if (tableExists($pdo, 'users')) {
                $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
                $stmtUsers->execute([$editId]);
                $linkedUsers = (int)$stmtUsers->fetchColumn();
            }

            if (tableExists($pdo, 'role_permissions')) {
                $stmtPermissions = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ?");
                $stmtPermissions->execute([$editId]);
                $linkedPermissions = (int)$stmtPermissions->fetchColumn();
            }
        }

        return [
            'mode' => $editId > 0 ? 'edit' : 'create',
            'current_role' => $currentRole,
            'code' => $payload['code'],
            'label' => $payload['label'],
            'linked_users' => $linkedUsers,
            'linked_permissions' => $linkedPermissions,
        ];
    }
}

$successMessage = '';
$errorMessage = '';
$previewMode = false;
$previewData = null;

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$editRole = $editId > 0 ? ar_fetch_role_by_id($pdo, $editId) : null;

$formData = [
    'code' => $editRole['code'] ?? '',
    'label' => $editRole['label'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = ar_normalize_role_payload($_POST);
    $action = (string)($_POST['form_action'] ?? '');

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        ar_validate_role_payload($pdo, $formData, $editId);

        if ($action === 'preview') {
            $previewMode = true;
            $previewData = ar_preview_summary($pdo, $formData, $editId);
        } elseif ($action === 'save_role') {
            if ($editId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE roles
                    SET code = ?, label = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $formData['code'],
                    $formData['label'],
                    $editId
                ]);

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'edit_role',
                        'admin',
                        'role',
                        $editId,
                        'Modification du rôle ' . $formData['code']
                    );
                }

                $successMessage = 'Rôle mis à jour.';
                $editRole = ar_fetch_role_by_id($pdo, $editId);
                $formData = [
                    'code' => $editRole['code'] ?? '',
                    'label' => $editRole['label'] ?? '',
                ];
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO roles (code, label, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([
                    $formData['code'],
                    $formData['label']
                ]);

                $newRoleId = (int)$pdo->lastInsertId();

                if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
                    logUserAction(
                        $pdo,
                        (int)$_SESSION['user_id'],
                        'create_role',
                        'admin',
                        'role',
                        $newRoleId,
                        'Création du rôle ' . $formData['code']
                    );
                }

                $successMessage = 'Rôle créé.';
                $formData = ['code' => '', 'label' => ''];
                $editId = 0;
                $editRole = null;
            }

            $previewMode = false;
            $previewData = null;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$roles = ar_fetch_roles($pdo);

$pageTitle = 'Rôles';
$pageSubtitle = 'Gestion centralisée des rôles applicatifs.';
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
                <h3 class="section-title"><?= $editId > 0 ? 'Modifier un rôle' : 'Créer un rôle' ?></h3>

                <form method="POST">
                    <?= csrf_input() ?>
                    <?php if ($editId > 0): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$editId ?>">
                    <?php endif; ?>

                    <div class="dashboard-grid-2">
                        <div>
                            <label>Code</label>
                            <input type="text" name="code" value="<?= e($formData['code']) ?>" required>
                        </div>

                        <div>
                            <label>Libellé</label>
                            <input type="text" name="label" value="<?= e($formData['label']) ?>" required>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" name="form_action" value="preview" class="btn btn-secondary">Prévisualiser</button>
                        <button type="submit" name="form_action" value="save_role" class="btn btn-success">
                            <?= $editId > 0 ? 'Enregistrer' : 'Créer' ?>
                        </button>

                        <?php if ($editId > 0): ?>
                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin/roles.php">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="dashboard-panel">
                <h3 class="section-title">Prévisualisation</h3>

                <?php if ($previewMode && $previewData): ?>
                    <div class="sl-data-list">
                        <div class="sl-data-list__row">
                            <span>Mode</span>
                            <strong><?= $previewData['mode'] === 'edit' ? 'Modification' : 'Création' ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Code</span>
                            <strong><?= e($previewData['code']) ?></strong>
                        </div>
                        <div class="sl-data-list__row">
                            <span>Libellé</span>
                            <strong><?= e($previewData['label']) ?></strong>
                        </div>

                        <?php if ($previewData['mode'] === 'edit'): ?>
                            <div class="sl-data-list__row">
                                <span>Utilisateurs liés</span>
                                <strong><?= (int)$previewData['linked_users'] ?></strong>
                            </div>
                            <div class="sl-data-list__row">
                                <span>Permissions liées</span>
                                <strong><?= (int)$previewData['linked_permissions'] ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dashboard-note" style="margin-top:14px;">
                        Vérifie le code avant validation. Toute modification d’un rôle impacte les utilisateurs déjà rattachés à ce rôle.
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">
                        Les rôles structurent les droits. Les permissions réelles sont ensuite pilotées depuis la matrice d’accès.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= e($role['code'] ?? '') ?></td>
                            <td><?= e($role['label'] ?? '') ?></td>
                            <td><?= e($role['created_at'] ?? '') ?></td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin/roles.php?edit=<?= (int)$role['id'] ?>">Modifier</a>
                                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin/access_matrix.php?role_id=<?= (int)$role['id'] ?>">Permissions</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$roles): ?>
                        <tr><td colspan="4">Aucun rôle.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>