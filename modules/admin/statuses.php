<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '') {
            throw new RuntimeException('Le nom du statut est obligatoire.');
        }

        $stmtDup = $pdo->prepare("SELECT id FROM statuses WHERE name = ? LIMIT 1");
        $stmtDup->execute([$name]);
        if ($stmtDup->fetch()) {
            throw new RuntimeException('Ce statut existe déjà.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO statuses (name, sort_order, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$name, $sortOrder]);

        $successMessage = 'Statut ajouté avec succès.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM statuses WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: ' . APP_URL . 'modules/admin/statuses.php?success=deleted');
            exit;
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$statuses = tableExists($pdo, 'statuses')
    ? $pdo->query("
        SELECT *
        FROM statuses
        ORDER BY sort_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

if (isset($_GET['success']) && $_GET['success'] === 'deleted') {
    $successMessage = 'Statut supprimé.';
}

$pageTitle = 'Gestion des statuts';
$pageSubtitle = 'Gérer les statuts métier utilisés dans la plateforme.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-2">
            <div class="form-card">
                <h3 class="section-title">Ajouter un statut</h3>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="create">

                    <label>Nom</label>
                    <input type="text" name="name" placeholder="Ex: Étudiant actif" required>

                    <label>Ordre de tri</label>
                    <input type="number" name="sort_order" value="0">

                    <div class="btn-group">
                        <button type="submit" class="btn btn-success">Ajouter</button>
                    </div>
                </form>
            </div>

            <div class="table-card">
                <h3 class="section-title">Liste des statuts</h3>

                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Ordre</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$statuses): ?>
                            <tr>
                                <td colspan="3">Aucun statut trouvé.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($statuses as $status): ?>
                                <tr>
                                    <td><?= e($status['name']) ?></td>
                                    <td><?= (int)$status['sort_order'] ?></td>
                                    <td>
                                        <a
                                            href="<?= e(APP_URL) ?>modules/admin/statuses.php?delete=<?= (int)$status['id'] ?>"
                                            class="btn btn-danger"
                                            onclick="return confirm('Supprimer ce statut ?')"
                                        >
                                            Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>