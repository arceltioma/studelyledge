<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'operations_create');

require_once __DIR__ . '/../../includes/header.php';

$successMessage = '';
$errorMessage = '';

$editId = (int)($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$deleteId = (int)($_GET['delete'] ?? 0);

if ($deleteId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM ref_operation_types WHERE id = ? LIMIT 1");
        $stmt->execute([$deleteId]);
        header('Location: ' . APP_URL . 'modules/admin_functional/manage_operation_types.php?ok=deleted');
        exit;
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_operation_type'])) {
    try {
        $code = trim((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));

        if ($code === '' || $label === '') {
            throw new RuntimeException('Le code et le libellé sont obligatoires.');
        }

        $stmtDup = $pdo->prepare("
            SELECT id FROM ref_operation_types
            WHERE code = ?
            " . ($editId > 0 ? "AND id <> ?" : "") . "
            LIMIT 1
        ");
        $params = [$code];
        if ($editId > 0) $params[] = $editId;
        $stmtDup->execute($params);

        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce code existe déjà.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare("
                UPDATE ref_operation_types
                SET code = ?, label = ?, is_active = 1
                WHERE id = ?
            ");
            $stmt->execute([$code, $label, $editId]);
            $successMessage = 'Type d’opération mis à jour.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ref_operation_types (code, label, is_active, created_at)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$code, $label]);
            $successMessage = 'Type d’opération créé.';
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ref_operation_types WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rows = tableExists($pdo, 'ref_operation_types')
    ? $pdo->query("SELECT * FROM ref_operation_types ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];
?>

<div class="layout">
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main">
    <?php render_app_header_bar(
        'Types d’opérations',
        'Création, modification et suppression du référentiel des mouvements.'
    ); ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'deleted'): ?>
        <div class="success">Type d’opération supprimé.</div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>

    <div class="dashboard-grid-2">
        <div class="form-card">
            <h3 class="section-title"><?= $editItem ? 'Modifier un type d’opération' : 'Créer un type d’opération' ?></h3>

            <form method="POST">
                <?php if ($editItem): ?>
                    <input type="hidden" name="edit_id" value="<?= (int)$editItem['id'] ?>">
                <?php endif; ?>

                <div class="dashboard-grid-2">
                    <div>
                        <label>Code</label>
                        <input type="text" name="code" value="<?= e($editItem['code'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label>Libellé</label>
                        <input type="text" name="label" value="<?= e($editItem['label'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" name="save_operation_type" value="1" class="btn btn-success"><?= $editItem ? 'Enregistrer' : 'Créer' ?></button>
                    <?php if ($editItem): ?>
                        <a class="btn btn-outline" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="dashboard-panel">
            <h3 class="section-title">Lecture</h3>
            <div class="dashboard-note">
                Le type d’opération détermine la logique comptable utilisée ensuite dans les opérations.
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Libellé</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['code'] ?? '') ?></td>
                        <td><?= e($row['label'] ?? '') ?></td>
                        <td>
                            <div class="btn-group">
                                <a class="btn btn-secondary" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php?edit=<?= (int)$row['id'] ?>">Modifier</a>
                                <a class="btn btn-danger" href="<?= APP_URL ?>modules/admin_functional/manage_operation_types.php?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Supprimer ce type d’opération ?');">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="3">Aucun type d’opération.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
</div>