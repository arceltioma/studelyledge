<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_users_manage');

$rows = $pdo->query("
    SELECT u.*, r.label AS role_label, r.code AS role_code
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

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