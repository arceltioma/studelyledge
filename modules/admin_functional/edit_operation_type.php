<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

$pagePermission = 'operations_create';
enforcePagePermission($pdo, $pagePermission);

require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    die('Type d’opération invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM ref_operation_types WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$type = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$type) {
    die('Type d’opération introuvable.');
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $code = trim((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $stmt = $pdo->prepare("
            UPDATE ref_operation_types
            SET code = ?, label = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$code, $label, $isActive, $id]);

        $successMessage = 'Type d’opération mis à jour.';
        $stmt->execute([$code, $label, $isActive, $id]);

        $stmtReload = $pdo->prepare("SELECT * FROM ref_operation_types WHERE id = ? LIMIT 1");
        $stmtReload->execute([$id]);
        $type = $stmtReload->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar('Modifier un type d’opération', 'Ajuster un type sans briser la suite logique.'); ?>

        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div>
                    <label for="code">Code</label>
                    <input type="text" id="code" name="code" value="<?= e($type['code'] ?? '') ?>" required>
                </div>

                <div style="margin-top:16px;">
                    <label for="label">Libellé</label>
                    <input type="text" id="label" name="label" value="<?= e($type['label'] ?? '') ?>" required>
                </div>

                <div style="margin-top:16px;">
                    <label style="display:flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="is_active" <?= (int)($type['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                        Actif
                    </label>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                    <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php">Retour</a>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>