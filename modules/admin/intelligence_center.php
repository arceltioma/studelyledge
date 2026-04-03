<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';

if (function_exists('studelyEnforceAccess')) {
    studelyEnforceAccess($pdo, 'intelligence_center_page');
} else {
    enforcePagePermission($pdo, 'admin_dashboard_view');
}

$pageTitle = 'Centre d’intelligence';
$pageSubtitle = 'Règles métier, cohérence comptable, détection d’anomalies et outils d’analyse';

$totalNotificationsUnread = function_exists('countUnreadNotifications')
    ? countUnreadNotifications($pdo)
    : 0;

$totalLogs = tableExists($pdo, 'user_logs')
    ? (int)$pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn()
    : 0;

$totalAuditTrail = tableExists($pdo, 'audit_trail')
    ? (int)$pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn()
    : 0;

$totalOperations = tableExists($pdo, 'operations')
    ? (int)$pdo->query("SELECT COUNT(*) FROM operations")->fetchColumn()
    : 0;

$sampleRules = [
    sl_get_operation_rules_summary('VERSEMENT', 'VERSEMENT'),
    sl_get_operation_rules_summary('VIREMENT', 'INTERNE'),
    sl_get_operation_rules_summary('FRAIS_SERVICE', 'AVI', 'Mexique', 'Allemagne'),
    sl_get_operation_rules_summary('FRAIS_GESTION', 'GESTION', 'Congo Brazzaville', null),
];

$sampleAnomalies = sl_get_operation_anomalies([
    'amount' => 0,
    'currency_code' => 'EUR',
    'client_id' => 0,
    'operation_type_code' => 'FRAIS_SERVICE',
    'service_code' => 'AVI',
    'manual_debit_account_code' => '',
    'manual_credit_account_code' => '',
    'reference' => '',
    'country_commercial' => 'Mexique',
    'country_destination' => 'Allemagne',
]);

$recentNotifications = function_exists('getUnreadNotifications')
    ? getUnreadNotifications($pdo, 6)
    : [];

require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <section class="sl-grid sl-grid-4 sl-stable-block" style="margin-bottom:20px;">
            <div class="sl-card sl-kpi-card sl-kpi-card--blue">
                <div class="sl-kpi-card__label">Opérations</div>
                <div class="sl-kpi-card__value"><?= (int)$totalOperations ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Périmètre analysable</span>
                    <strong>Base active</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--emerald">
                <div class="sl-kpi-card__label">Notifications non lues</div>
                <div class="sl-kpi-card__value"><?= (int)$totalNotificationsUnread ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Signal faible / fort</span>
                    <strong>Supervision</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--green">
                <div class="sl-kpi-card__label">Logs</div>
                <div class="sl-kpi-card__value"><?= (int)$totalLogs ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Journal système</span>
                    <strong>Traçabilité</strong>
                </div>
            </div>

            <div class="sl-card sl-kpi-card sl-kpi-card--violet">
                <div class="sl-kpi-card__label">Audit trail</div>
                <div class="sl-kpi-card__value"><?= (int)$totalAuditTrail ?></div>
                <div class="sl-kpi-card__meta">
                    <span>Historique détaillé</span>
                    <strong>Contrôle</strong>
                </div>
            </div>
        </section>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Accès rapides intelligence</h3>
                <div class="btn-group btn-group-vertical">
                    <a href="<?= e(APP_URL) ?>modules/admin/dashboard_admin.php" class="btn btn-outline">Dashboard admin</a>
                    <a href="<?= e(APP_URL) ?>modules/admin/audit_logs.php" class="btn btn-outline">Audit & traçabilité</a>
                    <a href="<?= e(APP_URL) ?>modules/notifications/notifications.php" class="btn btn-outline">Notifications</a>
                    <a href="<?= e(APP_URL) ?>modules/search/global_search.php" class="btn btn-outline">Recherche globale</a>
                    <a href="<?= e(APP_URL) ?>modules/dashboard/dashboard.php" class="btn btn-outline">Dashboard financier</a>
                </div>
            </div>

            <div class="card">
                <h3>Lecture</h3>
                <div class="dashboard-note">
                    Ce centre regroupe les briques intelligentes du projet : règles comptables, détection d’anomalies, supervision des imports, notifications et cohérence globale du système.
                </div>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Échantillon de règles métier</h3>

                <div class="sl-anomaly-list">
                    <?php foreach ($sampleRules as $index => $rule): ?>
                        <div class="sl-anomaly-list__item">
                            <span class="sl-anomaly-list__label">
                                Règle <?= $index + 1 ?> —
                                client <?= !empty($rule['requires_client']) ? 'oui' : 'non' ?>,
                                compte lié <?= !empty($rule['requires_linked_bank']) ? 'oui' : 'non' ?>,
                                manuel <?= !empty($rule['requires_manual_accounts']) ? 'oui' : 'non' ?>
                            </span>
                            <strong class="sl-anomaly-list__value">
                                <?= e((string)($rule['service_account_search_text'] ?? '—')) ?>
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h3>Échantillon d’anomalies détectées</h3>

                <?php if ($sampleAnomalies): ?>
                    <div class="sl-anomaly-list">
                        <?php foreach ($sampleAnomalies as $anomaly): ?>
                            <div class="sl-anomaly-list__item">
                                <span class="sl-anomaly-list__label">
                                    <?= e((string)($anomaly['message'] ?? 'Anomalie')) ?>
                                </span>
                                <strong class="sl-anomaly-list__value">
                                    <?= e((string)($anomaly['level'] ?? 'info')) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucune anomalie exemple détectée.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid-2 dashboard-section-spacing">
            <div class="card">
                <h3>Notifications récentes</h3>

                <?php if ($recentNotifications): ?>
                    <div class="sl-anomaly-list">
                        <?php foreach ($recentNotifications as $item): ?>
                            <div class="sl-anomaly-list__item">
                                <span class="sl-anomaly-list__label">
                                    <?= e((string)($item['message'] ?? '')) ?>
                                </span>
                                <strong class="sl-anomaly-list__value">
                                    <?= e((string)($item['level'] ?? 'info')) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Aucune notification non lue.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Vision LOT 2</h3>
                <div class="sl-data-list">
                    <div class="sl-data-list__row">
                        <span>Règles métier centralisées</span>
                        <strong>Actif</strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Anomaly engine</span>
                        <strong><?= function_exists('sl_get_operation_anomalies') ? 'Disponible' : 'Non' ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Rules engine</span>
                        <strong><?= function_exists('sl_get_operation_rules_summary') ? 'Disponible' : 'Non' ?></strong>
                    </div>
                    <div class="sl-data-list__row">
                        <span>Import mapper</span>
                        <strong><?= function_exists('sl_get_import_mapping_suggestions') ? 'Disponible' : 'Non' ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>