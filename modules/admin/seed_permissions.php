<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

/**
 * Accès page
 */
if (function_exists('studelyEnforceCurrentPageAccess')) {
    studelyEnforceCurrentPageAccess($pdo);
} else {
    if (function_exists('studelyEnforceAccess')) {
        studelyEnforceAccess($pdo, 'access_matrix_manage_page');
    } else {
        enforcePagePermission($pdo, 'access_matrix_manage_page');
    }
}

/**
 * Accès action
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('studelyEnforceActionAccess')) {
        studelyEnforceActionAccess($pdo, 'access_matrix_manage');
    } else {
        if (function_exists('studelyEnforceAccess')) {
            studelyEnforceAccess($pdo, 'access_matrix_manage');
        } else {
            enforcePagePermission($pdo, 'access_matrix_manage');
        }
    }
}

if (!function_exists('studely_permissions_table_exists')) {
    function studely_permissions_table_exists(PDO $pdo): bool
    {
        return function_exists('tableExists') ? tableExists($pdo, 'permissions') : true;
    }
}

if (!function_exists('studely_permissions_table_is_usable')) {
    function studely_permissions_table_is_usable(PDO $pdo): bool
    {
        if (!studely_permissions_table_exists($pdo)) {
            return false;
        }

        if (function_exists('columnExists')) {
            return columnExists($pdo, 'permissions', 'code')
                && columnExists($pdo, 'permissions', 'label');
        }

        return true;
    }
}

if (!function_exists('studely_permissions_current_snapshot')) {
    function studely_permissions_current_snapshot(PDO $pdo): array
    {
        if (!studely_permissions_table_is_usable($pdo)) {
            return [];
        }

        $stmt = $pdo->query("
            SELECT id, code, label, created_at
            FROM permissions
            ORDER BY code ASC
        ");

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
}

$pageTitle = 'Seed permissions';
$pageSubtitle = 'Synchronisation centralisée des permissions applicatives';

$successMessage = '';
$errorMessage = '';
$seedResult = null;
$catalog = function_exists('studely_defined_permissions') ? studely_defined_permissions() : [];
$currentPermissions = studely_permissions_current_snapshot($pdo);

$existingByCode = [];
foreach ($currentPermissions as $row) {
    $existingByCode[(string)($row['code'] ?? '')] = $row;
}

$missingCodes = [];
$labelDiffs = [];

foreach ($catalog as $code => $label) {
    if (!isset($existingByCode[$code])) {
        $missingCodes[$code] = $label;
        continue;
    }

    $existingLabel = (string)($existingByCode[$code]['label'] ?? '');
    if ($existingLabel !== $label) {
        $labelDiffs[$code] = [
            'current' => $existingLabel,
            'expected' => $label,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        if (!function_exists('studely_seed_permissions')) {
            throw new RuntimeException('Le helper studely_seed_permissions() est introuvable.');
        }

        if (!studely_permissions_table_is_usable($pdo)) {
            throw new RuntimeException('La table permissions est absente ou incomplète.');
        }

        $updateLabels = isset($_POST['update_labels']) && (string)$_POST['update_labels'] === '1';

        $pdo->beginTransaction();

        $seedResult = studely_seed_permissions($pdo, $updateLabels);

        if (function_exists('logUserAction') && isset($_SESSION['user_id'])) {
            logUserAction(
                $pdo,
                (int)$_SESSION['user_id'],
                'seed_permissions',
                'admin',
                'permissions',
                0,
                'Synchronisation des permissions. Insertées: '
                . (int)($seedResult['inserted'] ?? 0)
                . ', mises à jour: '
                . (int)($seedResult['updated'] ?? 0)
            );
        }

        $pdo->commit();

        $successMessage = 'Synchronisation terminée. '
            . (int)($seedResult['inserted'] ?? 0)
            . ' permission(s) insérée(s), '
            . (int)($seedResult['updated'] ?? 0)
            . ' mise(s) à jour.';

        $currentPermissions = studely_permissions_current_snapshot($pdo);
        $existingByCode = [];
        foreach ($currentPermissions as $row) {
            $existingByCode[(string)($row['code'] ?? '')] = $row;
        }

        $missingCodes = [];
        $labelDiffs = [];
        foreach ($catalog as $code => $label) {
            if (!isset($existingByCode[$code])) {
                $missingCodes[$code] = $label;
                continue;
            }

            $existingLabel = (string)($existingByCode[$code]['label'] ?? '');
            if ($existingLabel !== $label) {
                $labelDiffs[$code] = [
                    'current' => $existingLabel,
                    'expected' => $label,
                ];
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = $e->getMessage();
    }
}

$totalCatalog = count($catalog);
$totalExisting = count($currentPermissions);
$totalMissing = count($missingCodes);
$totalLabelDiffs = count($labelDiffs);

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

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Catalogue central</div>
                <div class="sl-kpi-card__value"><?= (int)$totalCatalog ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Permissions attendues</span>
                    <strong>Référence</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">En base</div>
                <div class="sl-kpi-card__value"><?= (int)$totalExisting ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Permissions présentes</span>
                    <strong>Table SQL</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--amber">
                <div class="sl-kpi-card__label">Manquantes</div>
                <div class="sl-kpi-card__value"><?= (int)$totalMissing ?></div>
                <div class="sl-kpi-card__meta">
                    <span>À insérer</span>
                    <strong>Synchronisation</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Libellés divergents</div>
                <div class="sl-kpi-card__value"><?= (int)$totalLabelDiffs ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Écarts de label</span>
                    <strong>Maintenance</strong>
                </div>
            </div>
        </section>

        <section class="sl-card sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card-head">
                <div>
                    <h3>Synchroniser les permissions</h3>
                    <p class="sl-card-head-subtitle">Injection automatique des permissions manquantes depuis le catalogue central</p>
                </div>
            </div>

            <form method="POST">
                <?= csrf_input() ?>

                <div class="dashboard-grid-2">
                    <div class="dashboard-note">
                        Le seed lit le catalogue central des permissions défini dans le code, puis compare avec la table SQL `permissions`.
                        Il ajoute uniquement les codes manquants, et peut aussi réaligner les libellés.
                    </div>

                    <div>
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="checkbox" name="update_labels" value="1" checked>
                            Mettre aussi à jour les libellés existants si le catalogue a changé
                        </label>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-success">Lancer la synchronisation</button>
                    <a href="<?= e(APP_URL) ?>modules/admin/access_matrix.php" class="btn btn-outline">Retour matrice d’accès</a>
                </div>
            </form>
        </section>

        <div class="dashboard-grid-2">
            <section class="sl-card sl-stable-block">
                <div class="sl-card-head">
                    <div>
                        <h3>Permissions manquantes</h3>
                        <p class="sl-card-head-subtitle"><?= (int)$totalMissing ?> élément(s) non trouvés en base</p>
                    </div>
                </div>

                <?php if ($missingCodes): ?>
                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Libellé attendu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($missingCodes as $code => $label): ?>
                                    <tr>
                                        <td><?= e((string)$code) ?></td>
                                        <td><?= e((string)$label) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">Aucune permission manquante.</div>
                <?php endif; ?>
            </section>

            <section class="sl-card sl-stable-block">
                <div class="sl-card-head">
                    <div>
                        <h3>Libellés divergents</h3>
                        <p class="sl-card-head-subtitle"><?= (int)$totalLabelDiffs ?> différence(s) détectée(s)</p>
                    </div>
                </div>

                <?php if ($labelDiffs): ?>
                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Libellé actuel</th>
                                    <th>Libellé catalogue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($labelDiffs as $code => $diff): ?>
                                    <tr>
                                        <td><?= e((string)$code) ?></td>
                                        <td><?= e((string)($diff['current'] ?? '')) ?></td>
                                        <td><?= e((string)($diff['expected'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="dashboard-note">Aucun libellé divergent.</div>
                <?php endif; ?>
            </section>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>