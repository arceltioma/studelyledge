<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);
$pageTitle = 'Dashboard administration fonctionnelle';
$pageSubtitle = 'Pilotage des référentiels, des comptes 411 / 512 / 706 et des règles comptables.';

if (!function_exists('af_dashboard_money')) {
    function af_dashboard_money(float $value): string
    {
        return number_format($value, 2, ',', ' ') . ' €';
    }
}

$dashboard = [
    'stats' => [
        'clients_total' => 0,
        'clients_active' => 0,
        'accounts_411_total' => 0,
        'accounts_411_balance_total' => 0.0,
        'accounts_512_total' => 0,
        'accounts_512_balance_total' => 0.0,
        'accounts_706_total' => 0,
        'accounts_706_balance_total' => 0.0,
        'operation_types_total' => 0,
        'services_total' => 0,
        'rules_total' => 0,
        'rules_active' => 0,
        'rules_missing' => 0,
    ],
    'top411' => [],
    'top512' => [],
    'top706' => [],
    'recentRules' => [],
    'servicesWithoutRule' => [],
];

/**
 * On utilise le helper centralisé si présent.
 */
if (function_exists('sl_admin_functional_dashboard_get_data')) {
    $dashboard = sl_admin_functional_dashboard_get_data($pdo);
}

/**
 * Fallback robuste pour Top 411 si le helper ne fournit rien
 * ou si la jointure dans le helper est trop restrictive.
 */
if (empty($dashboard['top411']) && tableExists($pdo, 'clients') && tableExists($pdo, 'bank_accounts')) {
    if (tableExists($pdo, 'client_bank_accounts')) {
        $stmtTop411 = $pdo->query("
            SELECT
                c.id,
                c.client_code,
                c.full_name,
                c.generated_client_account,
                c.country_commercial,
                COALESCE(ba.balance, 0) AS balance,
                COALESCE(ba.initial_balance, 0) AS initial_balance
            FROM clients c
            LEFT JOIN client_bank_accounts cba ON cba.client_id = c.id
            LEFT JOIN bank_accounts ba ON ba.id = cba.bank_account_id
            WHERE COALESCE(c.is_active,1)=1
              AND (
                    ba.account_number LIKE '411%'
                    OR c.generated_client_account LIKE '411%'
                  )
            GROUP BY
                c.id, c.client_code, c.full_name, c.generated_client_account,
                c.country_commercial, ba.balance, ba.initial_balance
            ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
            LIMIT 8
        ");
        $dashboard['top411'] = $stmtTop411->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (empty($dashboard['top411'])) {
        $stmtTop411 = $pdo->query("
            SELECT
                c.id,
                c.client_code,
                c.full_name,
                c.generated_client_account,
                c.country_commercial,
                COALESCE(ba.balance, 0) AS balance,
                COALESCE(ba.initial_balance, 0) AS initial_balance
            FROM clients c
            LEFT JOIN bank_accounts ba
                ON ba.account_number = c.generated_client_account
            WHERE COALESCE(c.is_active,1)=1
              AND COALESCE(c.generated_client_account,'') LIKE '411%'
            ORDER BY COALESCE(ba.balance, 0) DESC, c.full_name ASC
            LIMIT 8
        ");
        $dashboard['top411'] = $stmtTop411->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

/**
 * Fallback Top 512
 */
if (empty($dashboard['top512']) && tableExists($pdo, 'treasury_accounts')) {
    $balanceColumn512 = columnExists($pdo, 'treasury_accounts', 'current_balance')
        ? 'current_balance'
        : (columnExists($pdo, 'treasury_accounts', 'opening_balance') ? 'opening_balance' : '0');

    $stmtTop512 = $pdo->query("
        SELECT
            id,
            account_code,
            account_label,
            COALESCE({$balanceColumn512}, 0) AS current_balance,
            COALESCE(currency_code, 'EUR') AS currency_code,
            COALESCE(country_label, '') AS country_label
        FROM treasury_accounts
        WHERE COALESCE(is_active,1)=1
        ORDER BY COALESCE({$balanceColumn512}, 0) DESC, account_code ASC
        LIMIT 8
    ");
    $dashboard['top512'] = $stmtTop512->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Fallback Top 706
 */
if (empty($dashboard['top706']) && tableExists($pdo, 'service_accounts')) {
    $stmtTop706 = $pdo->query("
        SELECT
            id,
            account_code,
            account_label,
            COALESCE(current_balance, 0) AS current_balance,
            COALESCE(commercial_country_label, '') AS commercial_country_label,
            COALESCE(destination_country_label, '') AS destination_country_label
        FROM service_accounts
        WHERE COALESCE(is_active,1)=1
          AND (
                COALESCE(is_postable,0)=1
                OR account_code LIKE '706%'
              )
        ORDER BY COALESCE(current_balance, 0) DESC, account_code ASC
        LIMIT 8
    ");
    $dashboard['top706'] = $stmtTop706->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$stats = is_array($dashboard['stats'] ?? null) ? $dashboard['stats'] : [];
$top411 = is_array($dashboard['top411'] ?? null) ? $dashboard['top411'] : [];
$top512 = is_array($dashboard['top512'] ?? null) ? $dashboard['top512'] : [];
$top706 = is_array($dashboard['top706'] ?? null) ? $dashboard['top706'] : [];
$recentRules = is_array($dashboard['recentRules'] ?? null) ? $dashboard['recentRules'] : [];
$servicesWithoutRule = is_array($dashboard['servicesWithoutRule'] ?? null) ? $dashboard['servicesWithoutRule'] : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="afdash">
            <div class="afdash-hero">
                <div class="afdash-hero__left">
                    <span class="afdash-hero__eyebrow">Administration fonctionnelle</span>
                    <h2 class="afdash-hero__title"><?= e($pageTitle) ?></h2>
                    <p class="afdash-hero__subtitle"><?= e($pageSubtitle) ?></p>

                    <div class="afdash-hero__chips">
                        <span class="afdash-chip">Référentiels</span>
                        <span class="afdash-chip">Comptes 411</span>
                        <span class="afdash-chip">Comptes 512</span>
                        <span class="afdash-chip">Comptes 706</span>
                        <span class="afdash-chip">Règles de résolution</span>
                    </div>
                </div>

                <div class="afdash-hero__right">
                    <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php">Règles</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_operation_types.php">Types</a>
                    <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/admin_functional/manage_services.php">Services</a>
                    <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">Comptes</a>
                </div>
            </div>

            <div class="afdash-kpis">
                <article class="afdash-kpi afdash-kpi--blue">
                    <div class="afdash-kpi__label">Clients</div>
                    <div class="afdash-kpi__value"><?= (int)($stats['clients_total'] ?? 0) ?></div>
                    <div class="afdash-kpi__meta">
                        <span>Actifs</span>
                        <strong><?= (int)($stats['clients_active'] ?? 0) ?></strong>
                    </div>
                </article>

                <article class="afdash-kpi afdash-kpi--emerald">
                    <div class="afdash-kpi__label">Comptes 411</div>
                    <div class="afdash-kpi__value"><?= (int)($stats['accounts_411_total'] ?? 0) ?></div>
                    <div class="afdash-kpi__meta">
                        <span>Encours total</span>
                        <strong><?= e(af_dashboard_money((float)($stats['accounts_411_balance_total'] ?? 0))) ?></strong>
                    </div>
                </article>

                <article class="afdash-kpi afdash-kpi--violet">
                    <div class="afdash-kpi__label">Comptes 512</div>
                    <div class="afdash-kpi__value"><?= (int)($stats['accounts_512_total'] ?? 0) ?></div>
                    <div class="afdash-kpi__meta">
                        <span>Solde total</span>
                        <strong><?= e(af_dashboard_money((float)($stats['accounts_512_balance_total'] ?? 0))) ?></strong>
                    </div>
                </article>

                <article class="afdash-kpi afdash-kpi--amber">
                    <div class="afdash-kpi__label">Comptes 706</div>
                    <div class="afdash-kpi__value"><?= (int)($stats['accounts_706_total'] ?? 0) ?></div>
                    <div class="afdash-kpi__meta">
                        <span>Produit cumulé</span>
                        <strong><?= e(af_dashboard_money((float)($stats['accounts_706_balance_total'] ?? 0))) ?></strong>
                    </div>
                </article>

                <article class="afdash-kpi afdash-kpi--rose">
                    <div class="afdash-kpi__label">Règles comptables</div>
                    <div class="afdash-kpi__value"><?= (int)($stats['rules_total'] ?? 0) ?></div>
                    <div class="afdash-kpi__meta">
                        <span>Actives</span>
                        <strong><?= (int)($stats['rules_active'] ?? 0) ?></strong>
                    </div>
                </article>

                <article class="afdash-kpi afdash-kpi--slate">
                    <div class="afdash-kpi__label">Services sans règle</div>
                    <div class="afdash-kpi__value"><?= (int)($stats['rules_missing'] ?? 0) ?></div>
                    <div class="afdash-kpi__meta">
                        <span>Services</span>
                        <strong><?= (int)($stats['services_total'] ?? 0) ?></strong>
                    </div>
                </article>
            </div>

            <div class="afdash-grid afdash-grid--main">
                <section class="afdash-panel">
                    <div class="afdash-panel__head">
                        <div>
                            <h3>Top comptes clients 411</h3>
                            <p>Vue directe des principaux soldes clients actifs.</p>
                        </div>
                        <a class="btn btn-outline btn-sm" href="<?= e(APP_URL) ?>modules/clients/client_accounts.php">Voir tout</a>
                    </div>

                    <div class="afdash-table-wrap">
                        <table class="afdash-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Compte</th>
                                    <th>Pays</th>
                                    <th>Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top411): ?>
                                    <?php foreach ($top411 as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= e(APP_URL) ?>modules/clients/client_view.php?id=<?= (int)($row['id'] ?? 0) ?>">
                                                    <?= e(trim((string)($row['client_code'] ?? '') . ' - ' . (string)($row['full_name'] ?? ''))) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string)($row['generated_client_account'] ?? '—')) ?></td>
                                            <td><?= e((string)($row['country_commercial'] ?? '—')) ?></td>
                                            <td class="afdash-amount"><?= e(af_dashboard_money((float)($row['balance'] ?? 0))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"><div class="afdash-empty">Aucun compte client 411 trouvé.</div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="afdash-side">
                    <section class="afdash-panel afdash-panel--compact">
                        <div class="afdash-panel__head">
                            <div>
                                <h3>Contrôle fonctionnel</h3>
                                <p>Vue synthétique du référentiel.</p>
                            </div>
                        </div>

                        <div class="afdash-metrics">
                            <div class="afdash-metric">
                                <span>Services sans règle active</span>
                                <strong><?= (int)($stats['rules_missing'] ?? 0) ?></strong>
                            </div>
                            <div class="afdash-metric">
                                <span>Règles actives</span>
                                <strong><?= (int)($stats['rules_active'] ?? 0) ?></strong>
                            </div>
                            <div class="afdash-metric">
                                <span>Référentiel services</span>
                                <strong><?= (int)($stats['services_total'] ?? 0) ?></strong>
                            </div>
                            <div class="afdash-metric">
                                <span>Référentiel types</span>
                                <strong><?= (int)($stats['operation_types_total'] ?? 0) ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="afdash-panel afdash-panel--compact">
                        <div class="afdash-panel__head">
                            <div>
                                <h3>Accès rapides</h3>
                                <p>Navigation directe par zone métier.</p>
                            </div>
                        </div>

                        <div class="afdash-links">
                            <a class="btn btn-success" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounts.php">Voir tous les comptes</a>
                            <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/clients/client_accounts.php">Comptes 411</a>
                            <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/treasury/index.php">Comptes 512</a>
                            <a class="btn btn-secondary" href="<?= e(APP_URL) ?>modules/service_accounts/index.php">Comptes 706</a>
                            <a class="btn btn-outline" href="<?= e(APP_URL) ?>modules/admin_functional/manage_accounting_rules.php">Pilotage des règles</a>
                        </div>
                    </section>
                </aside>
            </div>

            <div class="afdash-grid afdash-grid--triple">
                <section class="afdash-panel">
                    <div class="afdash-panel__head">
                        <div>
                            <h3>Top comptes 512</h3>
                            <p>Comptes de trésorerie les plus élevés.</p>
                        </div>
                        <a class="btn btn-outline btn-sm" href="<?= e(APP_URL) ?>modules/treasury/index.php">Voir tout</a>
                    </div>

                    <div class="afdash-table-wrap">
                        <table class="afdash-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Libellé</th>
                                    <th>Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top512): ?>
                                    <?php foreach ($top512 as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= e(APP_URL) ?>modules/treasury/treasury_view.php?id=<?= (int)($row['id'] ?? 0) ?>">
                                                    <?= e((string)($row['account_code'] ?? '')) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                            <td class="afdash-amount"><?= e(af_dashboard_money((float)($row['current_balance'] ?? 0))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3"><div class="afdash-empty">Aucun compte 512 trouvé.</div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="afdash-panel">
                    <div class="afdash-panel__head">
                        <div>
                            <h3>Top comptes 706</h3>
                            <p>Comptes postables les plus alimentés.</p>
                        </div>
                        <a class="btn btn-outline btn-sm" href="<?= e(APP_URL) ?>modules/service_accounts/index.php">Voir tout</a>
                    </div>

                    <div class="afdash-table-wrap">
                        <table class="afdash-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Libellé</th>
                                    <th>Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top706): ?>
                                    <?php foreach ($top706 as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= e(APP_URL) ?>modules/service_accounts/view.php?id=<?= (int)($row['id'] ?? 0) ?>">
                                                    <?= e((string)($row['account_code'] ?? '')) ?>
                                                </a>
                                            </td>
                                            <td><?= e((string)($row['account_label'] ?? '')) ?></td>
                                            <td class="afdash-amount"><?= e(af_dashboard_money((float)($row['current_balance'] ?? 0))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3"><div class="afdash-empty">Aucun compte 706 trouvé.</div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="afdash-panel">
                    <div class="afdash-panel__head">
                        <div>
                            <h3>Règles récentes</h3>
                            <p>Dernières règles créées ou mises à jour.</p>
                        </div>
                    </div>

                    <div class="afdash-table-wrap">
                        <table class="afdash-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type / Service</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentRules): ?>
                                    <?php foreach ($recentRules as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_view.php?id=<?= (int)($row['id'] ?? 0) ?>">
                                                    <?= e((string)($row['rule_code'] ?? '')) ?>
                                                </a>
                                            </td>
                                            <td><?= e(trim((string)($row['operation_type_label'] ?? '') . ' / ' . (string)($row['service_label'] ?? ''))) ?></td>
                                            <td>
                                                <span class="afdash-status <?= !empty($row['is_active']) ? 'is-active' : 'is-inactive' ?>">
                                                    <?= !empty($row['is_active']) ? 'Actif' : 'Inactif' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3"><div class="afdash-empty">Aucune règle comptable.</div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="afdash-grid" style="grid-template-columns: 1fr; margin-top: 0;">
                <section class="afdash-panel">
                    <div class="afdash-panel__head">
                        <div>
                            <h3>Services sans règle active</h3>
                            <p>Points à traiter prioritairement.</p>
                        </div>
                    </div>

                    <div class="afdash-table-wrap">
                        <table class="afdash-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($servicesWithoutRule): ?>
                                    <?php foreach ($servicesWithoutRule as $row): ?>
                                        <tr>
                                            <td><?= e((string)($row['code'] ?? '') . ' - ' . (string)($row['label'] ?? '')) ?></td>
                                            <td><?= e((string)($row['operation_type_label'] ?? '—')) ?></td>
                                            <td>
                                                <a
                                                    class="btn btn-secondary btn-sm"
                                                    href="<?= e(APP_URL) ?>modules/admin_functional/accounting_rule_create.php?operation_type_id=<?= (int)($row['operation_type_id'] ?? 0) ?>&service_id=<?= (int)($row['id'] ?? 0) ?>"
                                                >
                                                    Créer
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3"><div class="afdash-empty">Tous les services affichés ont une règle active.</div></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>