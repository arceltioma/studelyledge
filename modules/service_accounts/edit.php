<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'service_accounts_manage_page');
} else {
    enforcePagePermission($pdo, 'service_accounts_view');
}

if (!tableExists($pdo, 'service_accounts')) {
    exit('Table service_accounts introuvable.');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    exit('Compte de service invalide.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM service_accounts
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    exit('Compte de service introuvable.');
}

$pageTitle = 'Modifier un compte de service (706)';
$pageSubtitle = 'Mise à jour sécurisée du compte de produit';

$successMessage = '';
$errorMessage = '';

$formData = [
    'account_code' => $account['account_code'] ?? '',
    'account_label' => $account['account_label'] ?? '',
    'commercial_country_label' => $account['commercial_country_label'] ?? '',
    'destination_country_label' => $account['destination_country_label'] ?? '',
    'is_postable' => (int)($account['is_postable'] ?? 0),
    'is_active' => (int)($account['is_active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'account_code' => trim((string)($_POST['account_code'] ?? '')),
        'account_label' => trim((string)($_POST['account_label'] ?? '')),
        'commercial_country_label' => trim((string)($_POST['commercial_country_label'] ?? '')),
        'destination_country_label' => trim((string)($_POST['destination_country_label'] ?? '')),
        'is_postable' => isset($_POST['is_postable']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if ($formData['account_code'] === '') {
            throw new RuntimeException('Le code compte est obligatoire.');
        }

        if ($formData['account_label'] === '') {
            throw new RuntimeException('L’intitulé du compte est obligatoire.');
        }

        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM service_accounts
            WHERE account_code = ?
              AND id <> ?
        ");
        $stmtCheck->execute([$formData['account_code'], $id]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException('Un autre compte utilise déjà ce code.');
        }

        $before = $account;

        $fields = [];
        $params = [];

        if (columnExists($pdo, 'service_accounts', 'account_code')) {
            $fields[] = 'account_code = ?';
            $params[] = $formData['account_code'];
        }

        if (columnExists($pdo, 'service_accounts', 'account_label')) {
            $fields[] = 'account_label = ?';
            $params[] = $formData['account_label'];
        }

        if (columnExists($pdo, 'service_accounts', 'commercial_country_label')) {
            $fields[] = 'commercial_country_label = ?';
            $params[] = $formData['commercial_country_label'] !== '' ? $formData['commercial_country_label'] : null;
        }

        if (columnExists($pdo, 'service_accounts', 'destination_country_label')) {
            $fields[] = 'destination_country_label = ?';
            $params[] = $formData['destination_country_label'] !== '' ? $formData['destination_country_label'] : null;
        }

        if (columnExists($pdo, 'service_accounts', 'is_postable')) {
            $fields[] = 'is_postable = ?';
            $params[] = $formData['is_postable'];
        }

        if (columnExists($pdo, 'service_accounts', 'is_active')) {
            $fields[] = 'is_active = ?';
            $params[] = $formData['is_active'];
        }

        if (columnExists($pdo, 'service_accounts', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (!$fields) {
            throw new RuntimeException('Aucun champ modifiable disponible.');
        }

        $params[] = $id;

        $stmtUpdate = $pdo->prepare("
            UPDATE service_accounts
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmtUpdate->execute($params);

        $stmt = $pdo->prepare("
            SELECT *
            FROM service_accounts
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: $account;

        if (function_exists('auditEntityChanges') && isset($_SESSION['user_id'])) {
            auditEntityChanges($pdo, 'service_account', $id, $before, $account, (int)$_SESSION['user_id']);
        }

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'edit_service_account',
                'service_accounts',
                'service_account',
                $id,
                'Modification d’un compte de service 706'
            );
        }

        if (function_exists('sl_create_entity_notification') && isset($_SESSION['user_id'])) {
            sl_create_entity_notification(
                $pdo,
                'service_account_update',
                'Compte de service modifié : ' . $formData['account_code'],
                'warning',
                'service_account',
                $id,
                (int)$_SESSION['user_id']
            );
        }

        $successMessage = 'Compte de service mis à jour avec succès.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

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
                <h3>Édition du compte 706</h3>

                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$id ?>">

                    <div>
                        <label>Code compte</label>
                        <input type="text" name="account_code" value="<?= e((string)$formData['account_code']) ?>" required>
                    </div>

                    <div style="margin-top:14px;">
                        <label>Intitulé</label>
                        <input type="text" name="account_label" value="<?= e((string)$formData['account_label']) ?>" required>
                    </div>

                    <?php if (columnExists($pdo, 'service_accounts', 'commercial_country_label')): ?>
                        <div style="margin-top:14px;">
                            <label>Pays commercial</label>
                            <input type="text" name="commercial_country_label" value="<?= e((string)$formData['commercial_country_label']) ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (columnExists($pdo, 'service_accounts', 'destination_country_label')): ?>
                        <div style="margin-top:14px;">
                            <label>Pays destination</label>
                            <input type="text" name="destination_country_label" value="<?= e((string)$formData['destination_country_label']) ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (columnExists($pdo, 'service_accounts', 'is_postable')): ?>
                        <div style="margin-top:14px;">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="is_postable" value="1" <?= ((int)$formData['is_postable'] === 1) ? 'checked' : '' ?>>
                                Compte postable
                            </label>
                        </div>
                    <?php endif; ?>

                    <?php if (columnExists($pdo, 'service_accounts', 'is_active')): ?>
                        <div style="margin-top:10px;">
                            <label style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="is_active" value="1" <?= ((int)$formData['is_active'] === 1) ? 'checked' : '' ?>>
                                Compte actif
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)$id ?>" class="btn btn-secondary">Voir</a>
                        <a href="<?= e(APP_URL) ?>modules/service_accounts/index.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>État actuel</h3>

                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Code</span>
                        <strong><?= e((string)($account['account_code'] ?? '')) ?></strong>
                    </div>

                    <div class="sl-data-list__row">
                        <span>Intitulé</span>
                        <strong><?= e((string)($account['account_label'] ?? '')) ?></strong>
                    </div>

                    <?php if (array_key_exists('commercial_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays commercial</span>
                            <strong><?= e((string)($account['commercial_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('destination_country_label', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Pays destination</span>
                            <strong><?= e((string)($account['destination_country_label'] ?? '—')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('current_balance', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Solde courant</span>
                            <strong><?= e(number_format((float)($account['current_balance'] ?? 0), 2, ',', ' ')) ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if (array_key_exists('is_active', $account)): ?>
                        <div class="sl-data-list__row">
                            <span>Statut</span>
                            <strong><?= ((int)($account['is_active'] ?? 1) === 1) ? 'Actif' : 'Archivé' ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>