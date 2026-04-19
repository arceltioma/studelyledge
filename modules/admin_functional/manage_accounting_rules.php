<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

studelyEnforceCurrentPageAccess($pdo);

if (!function_exists('mar_table_name')) {
    function mar_table_name(PDO $pdo, string $preferred, string $fallback): string
    {
        if (tableExists($pdo, $preferred)) {
            return $preferred;
        }
        return $fallback;
    }
}

if (!function_exists('mar_like')) {
    function mar_like(string $value): string
    {
        return '%' . $value . '%';
    }
}

$operationTypeTable = mar_table_name($pdo, 'ref_operation_types', 'operation_types');
$serviceTable = mar_table_name($pdo, 'ref_services', 'services');

$pageTitle = 'Règles comptables';
$pageSubtitle = 'Pilotage des règles de résolution débit / crédit, contrôle des contraintes et maintenance du référentiel.';

$successMessage = '';
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $successMessage = 'La règle comptable a bien été supprimée.';
} elseif (isset($_GET['created']) && $_GET['created'] === '1') {
    $successMessage = 'La règle comptable a bien été créée.';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = 'La règle comptable a bien été mise à jour.';
}

$filterSearch = trim((string)($_GET['filter_search'] ?? ''));
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));
$filterOperationTypeId = trim((string)($_GET['filter_operation_type_id'] ?? ''));
$filterServiceId = trim((string)($_GET['filter_service_id'] ?? ''));
$filterDebitMode = trim((string)($_GET['filter_debit_mode'] ?? ''));
$filterCreditMode = trim((string)($_GET['filter_credit_mode'] ?? ''));

$operationTypes = [];
if (tableExists($pdo, $operationTypeTable)) {
    $stmt = $pdo->query("
        SELECT id, code, label
        FROM {$operationTypeTable}
        ORDER BY label ASC
    ");
    $operationTypes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$services = [];
if (tableExists($pdo, $serviceTable)) {
    $stmt = $pdo->query("
        SELECT id, code, label
        FROM {$serviceTable}
        ORDER BY label ASC
    ");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$debitModes = [];
$creditModes = [];
if (tableExists($pdo, 'accounting_rules')) {
    if (columnExists($pdo, 'accounting_rules', 'debit_mode')) {
        $debitModes = $pdo->query("
            SELECT DISTINCT debit_mode
            FROM accounting_rules
            WHERE COALESCE(debit_mode,'') <> ''
            ORDER BY debit_mode ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    if (columnExists($pdo, 'accounting_rules', 'credit_mode')) {
        $creditModes = $pdo->query("
            SELECT DISTINCT credit_mode
            FROM accounting_rules
            WHERE COALESCE(credit_mode,'') <> ''
            ORDER BY credit_mode ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

$rows = [];
if (tableExists($pdo, 'accounting_rules')) {
    $sql = "
        SELECT
            ar.*,
            ot.code AS operation_type_code,
            ot.label AS operation_type_label,
            ot.direction AS operation_type_direction,
            s.code AS service_code,
            s.label AS service_label,
            sa.account_code AS linked_service_account_code,
            sa.account_label AS linked_service_account_label,
            ta.account_code AS linked_treasury_account_code,
            ta.account_label AS linked_treasury_account_label
        FROM accounting_rules ar
        LEFT JOIN {$operationTypeTable} ot ON ot.id = ar.operation_type_id
        LEFT JOIN {$serviceTable} s ON s.id = ar.service_id
        LEFT JOIN service_accounts sa ON sa.id = s.service_account_id
        LEFT JOIN treasury_accounts ta ON ta.id = s.treasury_account_id
        WHERE 1=1
    ";
    $params = [];

    if ($filterSearch !== '') {
        $sql .= "
            AND (
                COALESCE(ar.rule_code,'') LIKE ?
                OR COALESCE(ar.rule_label,'') LIKE ?
                OR COALESCE(ar.debit_mode,'') LIKE ?
                OR COALESCE(ar.credit_mode,'') LIKE ?
                OR COALESCE(ar.label_pattern,'') LIKE ?
                OR COALESCE(ot.code,'') LIKE ?
                OR COALESCE(ot.label,'') LIKE ?
                OR COALESCE(s.code,'') LIKE ?
                OR COALESCE(s.label,'') LIKE ?
            )
        ";
        for ($i = 0; $i < 9; $i++) {
            $params[] = mar_like($filterSearch);
        }
    }

    if ($filterStatus === 'active') {
        $sql .= " AND COALESCE(ar.is_active,1) = 1 ";
    } elseif ($filterStatus === 'inactive') {
        $sql .= " AND COALESCE(ar.is_active,1) <> 1 ";
    } elseif ($filterStatus === 'requires_client') {
        $sql .= " AND COALESCE(ar.requires_client,0) = 1 ";
    } elseif ($filterStatus === 'requires_manual') {
        $sql .= " AND COALESCE(ar.requires_manual_accounts,0) = 1 ";
    } elseif ($filterStatus === 'requires_bank') {
        $sql .= " AND COALESCE(ar.requires_linked_bank,0) = 1 ";
    } elseif ($filterStatus === 'fixed_accounts') {
        $sql .= " AND (
            COALESCE(ar.debit_fixed_account_code,'') <> ''
            OR COALESCE(ar.credit_fixed_account_code,'') <> ''
        ) ";
    }

    if ($filterOperationTypeId !== '') {
        $sql .= " AND ar.operation_type_id = ? ";
        $params[] = (int)$filterOperationTypeId;
    }

    if ($filterServiceId !== '') {
        $sql .= " AND ar.service_id = ? ";
        $params[] = (int)$filterServiceId;
    }

    if ($filterDebitMode !== '') {
        $sql .= " AND COALESCE(ar.debit_mode,'') = ? ";
        $params[] = $filterDebitMode;
    }

    if ($filterCreditMode !== '') {
        $sql .= " AND COALESCE(ar.credit_mode,'') = ? ";
        $params[] = $filterCreditMode;
    }

    $sql .= " ORDER BY COALESCE(ar.updated_at, ar.created_at) DESC, ar.id DESC ";

    $stmtRows = $pdo->prepare($sql);
    $stmtRows->execute($params);
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$dashboard = [
    'total' => count($rows),
    'active' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) === 1)),
    'inactive' => count(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) !== 1)),
    'requires_client' => count(array_filter($rows, fn($r) => (int)($r['requires_client'] ?? 0) === 1)),
    'requires_manual' => count(array_filter($rows, fn($r) => (int)($r['requires_manual_accounts'] ?? 0) === 1)),
    'requires_bank' => count(array_filter($rows, fn($r) => (int)($r['requires_linked_bank'] ?? 0) === 1)),
    'fixed_accounts' => count(array_filter($rows, fn($r) => trim((string)($r['debit_fixed_account_code'] ?? '')) !== '' || trim((string)($r['credit_fixed_account_code'] ?? '')) !== '')),
];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid-4" style="margin-bottom:20px;">
            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Règles</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['total'] ?></div>
                <div class="sl-kpi-card__meta"><strong>Total paramétré</strong></div>
            </div>

            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Actives</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['active'] ?></div>
                <div class="sl-kpi-card__meta"><strong>En production</strong></div>
            </div>

            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Client requis</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['requires_client'] ?></div>
                <div class="sl-kpi-card__meta"><strong>Règles conditionnées</strong></div>
            </div>

            <div class="sl-card sl-premium-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Comptes manuels</div>
                <div class="sl-kpi-card__value"><?= (int)$dashboard['requires_manual'] ?></div>
                <div class="sl-kpi-card__meta"><strong>Cas sensibles</strong></div>
            </div>
        </div>

        <div class="dashboard-grid-3" style="margin-bottom:20px;">
            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Vue pilotage</h3>
                        <p class="sl-card-head-subtitle">Résumé rapide du moteur paramétrique</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Synthèse</span>
                </div>

                <div class="sl-data-list">
                    <div class="sl-data-list__row"><span>Inactives</span><strong><?= (int)$dashboard['inactive'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Compte lié requis</span><strong><?= (int)$dashboard['requires_bank'] ?></strong></div>
                    <div class="sl-data-list__row"><span>Comptes fixes</span><strong><?= (int)$dashboard['fixed_accounts'] ?></strong></div>
                </div>
            </div>

            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Actions rapides</h3>
                        <p class="sl-card-head-subtitle">Raccourcis vers les objets liés</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Navigation</span>
                </div>

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_create.php" class="btn btn-success">➕ Nouvelle règle</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php" class="btn btn-secondary">🧠 Types d’opérations</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php" class="btn btn-secondary">🧩 Services</a>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_balance_audit.php" class="btn btn-outline">🧾 Audit soldes</a>
                </div>
            </div>

            <div class="sl-card sl-premium-card">
                <div class="sl-card-head">
                    <div>
                        <h3>Lecture fonctionnelle</h3>
                        <p class="sl-card-head-subtitle">Aide rapide sur les modes les plus utilisés</p>
                    </div>
                    <span class="sl-pill sl-pill-soft">Référence</span>
                </div>

                <div class="sl-anomaly-list">
                    <div class="sl-anomaly-list__item">
                        <span class="sl-anomaly-list__label">CLIENT_411</span>
                        <strong class="sl-anomaly-list__value">Compte client</strong>
                    </div>
                    <div class="sl-anomaly-list__item">
                        <span class="sl-anomaly-list__label">CLIENT_512</span>
                        <strong class="sl-anomaly-list__value">Trésorerie client</strong>
                    </div>
                    <div class="sl-anomaly-list__item">
                        <span class="sl-anomaly-list__label">SERVICE_706</span>
                        <strong class="sl-anomaly-list__value">Produit / service</strong>
                    </div>
                    <div class="sl-anomaly-list__item">
                        <span class="sl-anomaly-list__label">SOURCE_512 / TARGET_512</span>
                        <strong class="sl-anomaly-list__value">Flux internes</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card" style="margin-bottom:20px;">
            <h3 class="section-title">Filtres</h3>

            <form method="GET">
                <div class="dashboard-grid-3">
                    <div>
                        <label>Recherche</label>
                        <input type="text" name="filter_search" value="<?= e($filterSearch) ?>" placeholder="Code, libellé, type, service, mode...">
                    </div>

                    <div>
                        <label>Statut / contrainte</label>
                        <select name="filter_status">
                            <option value="">Tous</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actives</option>
                            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactives</option>
                            <option value="requires_client" <?= $filterStatus === 'requires_client' ? 'selected' : '' ?>>Client requis</option>
                            <option value="requires_manual" <?= $filterStatus === 'requires_manual' ? 'selected' : '' ?>>Comptes manuels requis</option>
                            <option value="requires_bank" <?= $filterStatus === 'requires_bank' ? 'selected' : '' ?>>Compte lié requis</option>
                            <option value="fixed_accounts" <?= $filterStatus === 'fixed_accounts' ? 'selected' : '' ?>>Avec comptes fixes</option>
                        </select>
                    </div>

                    <div>
                        <label>Type d’opération</label>
                        <select name="filter_operation_type_id">
                            <option value="">Tous</option>
                            <?php foreach ($operationTypes as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= $filterOperationTypeId === (string)$item['id'] ? 'selected' : '' ?>>
                                    <?= e(trim((string)($item['label'] ?? '') . ' (' . (string)($item['code'] ?? '') . ')')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Service</label>
                        <select name="filter_service_id">
                            <option value="">Tous</option>
                            <?php foreach ($services as $item): ?>
                                <option value="<?= (int)$item['id'] ?>" <?= $filterServiceId === (string)$item['id'] ? 'selected' : '' ?>>
                                    <?= e(trim((string)($item['label'] ?? '') . ' (' . (string)($item['code'] ?? '') . ')')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Mode débit</label>
                        <select name="filter_debit_mode">
                            <option value="">Tous</option>
                            <?php foreach ($debitModes as $mode): ?>
                                <option value="<?= e((string)$mode) ?>" <?= $filterDebitMode === (string)$mode ? 'selected' : '' ?>>
                                    <?= e((string)$mode) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Mode crédit</label>
                        <select name="filter_credit_mode">
                            <option value="">Tous</option>
                            <?php foreach ($creditModes as $mode): ?>
                                <option value="<?= e((string)$mode) ?>" <?= $filterCreditMode === (string)$mode ? 'selected' : '' ?>>
                                    <?= e((string)$mode) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px;">
                    <button type="submit" class="btn btn-secondary">Filtrer</button>
                    <a href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php" class="btn btn-outline">Réinitialiser</a>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="page-title page-title-inline">
                <div>
                    <h3 class="section-title">Liste des règles</h3>
                    <p class="muted"><?= count($rows) ?> règle(s) trouvée(s)</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code règle</th>
                            <th>Type d’opération</th>
                            <th>Service</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Contraintes</th>
                            <th>Statut</th>
                            <th>Dernière mise à jour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $constraints = [];
                            if ((int)($row['requires_client'] ?? 0) === 1) {
                                $constraints[] = 'Client';
                            }
                            if ((int)($row['requires_linked_bank'] ?? 0) === 1) {
                                $constraints[] = 'Banque liée';
                            }
                            if ((int)($row['requires_manual_accounts'] ?? 0) === 1) {
                                $constraints[] = 'Manuel';
                            }
                            if (trim((string)($row['debit_fixed_account_code'] ?? '')) !== '' || trim((string)($row['credit_fixed_account_code'] ?? '')) !== '') {
                                $constraints[] = 'Compte fixe';
                            }
                            ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td>
                                    <strong><?= e((string)($row['rule_code'] ?? '')) ?></strong>
                                    <?php if (!empty($row['rule_label'])): ?>
                                        <div class="muted"><?= e((string)$row['rule_label']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e(trim((string)($row['operation_type_label'] ?? '') . ' (' . (string)($row['operation_type_code'] ?? '') . ')')) ?>
                                </td>
                                <td>
                                    <?= e(trim((string)($row['service_label'] ?? '') . ' (' . (string)($row['service_code'] ?? '') . ')')) ?>
                                </td>
                                <td>
                                    <strong><?= e((string)($row['debit_mode'] ?? '')) ?></strong>
                                    <?php if (!empty($row['debit_fixed_account_code'])): ?>
                                        <div class="muted">Fixe : <?= e((string)$row['debit_fixed_account_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e((string)($row['credit_mode'] ?? '')) ?></strong>
                                    <?php if (!empty($row['credit_fixed_account_code'])): ?>
                                        <div class="muted">Fixe : <?= e((string)$row['credit_fixed_account_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($constraints): ?>
                                        <div class="btn-group">
                                            <?php foreach ($constraints as $badge): ?>
                                                <span class="sl-pill sl-pill-soft"><?= e($badge) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">Aucune</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?= (int)($row['is_active'] ?? 1) === 1 ? 'status-success' : 'status-danger' ?>">
                                        <?= (int)($row['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e((string)($row['updated_at'] ?? $row['created_at'] ?? '—')) ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_view.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline">Voir</a>
                                        <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-secondary">Modifier</a>
                                        <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-danger">Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="10">Aucune règle trouvée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>