<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$statuses = $pdo->query("SELECT * FROM statuses ORDER BY sort_order ASC")->fetchAll();
?>
<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php render_app_header_bar('Statuts', 'Etat des comptes'); ?>

        <div class="table-card">
            <table>
                <thead><tr><th>ID</th><th>Nom</th><th>Ordre</th></tr></thead>
                <tbody>
                    <?php foreach ($statuses as $status): ?>
                        <tr>
                            <td><?= (int)$status['id'] ?></td>
                            <td><?= e($status['name']) ?></td>
                            <td><?= (int)$status['sort_order'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>