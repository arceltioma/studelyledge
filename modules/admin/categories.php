<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

$categories = tableExists($pdo, 'categories')
    ? $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$pageTitle = 'Catégories';
$pageSubtitle = 'Ensemble des catégories des comptes';
require_once __DIR__ . '/../../includes/document_start.php';
?>
<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <div class="table-card">
            <table>
                <thead><tr><th>ID</th><th>Nom</th></tr></thead>
                <tbody>
                    <?php if (!$categories): ?>
                        <tr><td colspan="2">Aucune catégorie.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= (int)$category['id'] ?></td>
                            <td><?= e($category['name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>
