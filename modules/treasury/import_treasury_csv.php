<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceAccess($pdo, 'treasury_import_page');

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (empty($_FILES['csv_file']['name'])) {
            throw new RuntimeException('Aucun fichier sélectionné.');
        }

        $successMessage = 'Fichier reçu. Intègre ici ta logique actuelle d’import trésorerie.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Import trésorerie';
$pageSubtitle = 'Chargement des comptes internes par CSV.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($errorMessage !== ''): ?><div class="error"><?= e($errorMessage) ?></div><?php endif; ?>
        <?php if ($successMessage !== ''): ?><div class="success"><?= e($successMessage) ?></div><?php endif; ?>

        <div class="form-card">
            <h1>Importer les comptes internes</h1>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input() ?>

                <div>
                    <label>Fichier CSV</label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Importer</button>
                </div>
            </form>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>