<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

enforcePagePermission($pdo, 'admin_roles_manage');

require_once __DIR__ . '/../../includes/header.php';

$roles = [
    [
        'code' => 'admin',
        'label' => 'Administrateur',
        'description' => 'Accès complet à la plateforme, administration technique et fonctionnelle.'
    ],
    [
        'code' => 'user',
        'label' => 'Utilisateur',
        'description' => 'Accès standard aux modules métier et aux opérations autorisées.'
    ],
];
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php render_app_header_bar(
            'Rôles',
            'Lecture simple des rôles applicatifs actuellement en place.'
        ); ?>

        <div class="table-card">
            <h3 class="section-title">Catalogue des rôles</h3>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?= e($role['code']) ?></td>
                            <td><?= e($role['label']) ?></td>
                            <td><?= e($role['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="dashboard-grid-2" style="margin-top:20px;">
            <div class="card">
                <h3>Note d’architecture</h3>
                <div class="dashboard-note">
                    Cette version stabilisée utilise un système de rôles simple.
                    La matrice d’autorisations fine pourra être réintroduite plus tard sans casser la base métier.
                </div>
            </div>

            <div class="card">
                <h3>Bon usage</h3>
                <div class="dashboard-note">
                    Le rôle <strong>admin</strong> sert à l’administration technique et fonctionnelle.
                    Le rôle <strong>user</strong> couvre l’usage courant des modules opérationnels.
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>