<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = getPDO();

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/admin_functions.php';
require_once __DIR__ . '/../../includes/permission_middleware.php';
require_once __DIR__ . '/../../config/security.php';

studelyEnforceCurrentPageAccess($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    studelyEnforceActionAccess($pdo, 'support_request_create');
}

if (!function_exists('sl_support_open_requests_count')) {
    function sl_support_open_requests_count(PDO $pdo, string $type): int
    {
        if (!tableExists($pdo, 'support_requests')) {
            return 0;
        }

        if (!columnExists($pdo, 'support_requests', 'request_type')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM support_requests
            WHERE request_type = ?
        ";
        $params = [$type];

        if (columnExists($pdo, 'support_requests', 'status')) {
            $sql .= " AND status IN ('open', 'in_progress', 'pending')";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('sl_support_permission_group_key')) {
    function sl_support_permission_group_key(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return 'other';
        }

        $parts = explode('_', $code);
        return $parts[0] !== '' ? $parts[0] : 'other';
    }
}

if (!function_exists('sl_support_permission_group_label')) {
    function sl_support_permission_group_label(string $group): string
    {
        $map = [
            'dashboard' => 'Dashboard',
            'analytics' => 'Analytics',
            'global' => 'Recherche globale',
            'clients' => 'Clients',
            'client' => 'Clients',
            'operations' => 'Opérations',
            'operation' => 'Opérations',
            'manual' => 'Actions manuelles',
            'imports' => 'Imports',
            'monthly' => 'Mensualités',
            'pending' => 'Débits dus',
            'treasury' => 'Trésorerie',
            'bank' => 'Banques',
            'service' => 'Services / Comptes 706',
            'statements' => 'Relevés / Exports',
            'account' => 'Comptes / États',
            'bulk' => 'Exports de masse',
            'generate' => 'Génération',
            'notifications' => 'Notifications',
            'support' => 'Support',
            'admin' => 'Administration technique',
            'roles' => 'Rôles',
            'users' => 'Utilisateurs',
            'user' => 'Utilisateurs / Logs',
            'audit' => 'Audit',
            'intelligence' => 'Centre d’intelligence',
            'settings' => 'Paramètres',
            'statuses' => 'Statuts',
            'categories' => 'Catégories',
            'services' => 'Services',
            'catalogs' => 'Catalogues',
            'accounts' => 'Comptes fonctionnels',
            'accounting' => 'Règles comptables',
            'access' => 'Matrice d’accès',
            'other' => 'Autres',
        ];

        return $map[$group] ?? ucfirst($group);
    }
}

if (!function_exists('sl_support_available_permissions_catalog')) {
    function sl_support_available_permissions_catalog(PDO $pdo): array
    {
        $catalog = [];

        if (function_exists('studely_defined_permissions')) {
            $raw = studely_defined_permissions();
            if (is_array($raw)) {
                $catalog = $raw;
            }
        }

        if (!$catalog && tableExists($pdo, 'permissions')) {
            $stmt = $pdo->query("
                SELECT code, label
                FROM permissions
                ORDER BY code ASC
            ");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

            foreach ($rows as $row) {
                $code = trim((string)($row['code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $catalog[$code] = (string)($row['label'] ?? $code);
            }
        }

        $filtered = [];
        foreach ($catalog as $code => $label) {
            $code = trim((string)$code);
            $label = trim((string)$label);

            if ($code === '') {
                continue;
            }

            if (str_ends_with($code, '_page')) {
                $filtered[$code] = $label !== '' ? $label : $code;
                continue;
            }

            if (
                str_contains($code, 'view')
                || str_contains($code, 'create')
                || str_contains($code, 'edit')
                || str_contains($code, 'delete')
                || str_contains($code, 'archive')
                || str_contains($code, 'manage')
                || str_contains($code, 'execute')
                || str_contains($code, 'import')
                || str_contains($code, 'export')
                || str_contains($code, 'validate')
            ) {
                $filtered[$code] = $label !== '' ? $label : $code;
            }
        }

        ksort($filtered);

        return $filtered;
    }
}

if (!function_exists('sl_support_group_permissions_catalog')) {
    function sl_support_group_permissions_catalog(array $catalog): array
    {
        $grouped = [];

        foreach ($catalog as $code => $label) {
            $group = sl_support_permission_group_key((string)$code);

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = [
                'code' => (string)$code,
                'label' => (string)$label,
            ];
        }

        uksort($grouped, function (string $a, string $b): int {
            return strcmp(sl_support_permission_group_label($a), sl_support_permission_group_label($b));
        });

        foreach ($grouped as $group => $items) {
            usort($items, function (array $a, array $b): int {
                return strcmp((string)$a['label'], (string)$b['label']);
            });
            $grouped[$group] = $items;
        }

        return $grouped;
    }
}

$errorMessage = '';
$successMessage = '';

$subjectValue = trim((string)($_POST['subject'] ?? ''));
$messageValue = trim((string)($_POST['message'] ?? ''));
$selectedPermissionCodes = array_values(array_unique(array_filter(
    array_map('strval', $_POST['requested_permission_codes'] ?? []),
    static fn($v) => trim($v) !== ''
)));

$openQuestions = sl_support_open_requests_count($pdo, 'question');
$openBugs = sl_support_open_requests_count($pdo, 'bug');
$openAccess = sl_support_open_requests_count($pdo, 'access');

$permissionsCatalog = sl_support_available_permissions_catalog($pdo);
$groupedPermissions = sl_support_group_permissions_catalog($permissionsCatalog);

$currentUserPermissionCodes = [];
foreach ($permissionsCatalog as $permissionCode => $permissionLabel) {
    if (function_exists('currentUserCan') && currentUserCan($pdo, (string)$permissionCode)) {
        $currentUserPermissionCodes[] = (string)$permissionCode;
    }
}
$currentUserPermissionCodes = array_values(array_unique($currentUserPermissionCodes));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? null)) {
            throw new RuntimeException('Jeton CSRF invalide.');
        }

        $subject = $subjectValue;
        $message = $messageValue;

        if ($subject === '' || $message === '') {
            throw new RuntimeException('Merci de compléter tous les champs.');
        }

        $validRequestedCodes = [];
        foreach ($selectedPermissionCodes as $code) {
            if (isset($permissionsCatalog[$code])) {
                $validRequestedCodes[] = $code;
            }
        }
        $selectedPermissionCodes = $validRequestedCodes;

        $requestedPermissionLabels = [];
        foreach ($selectedPermissionCodes as $code) {
            $requestedPermissionLabels[] = [
                'code' => $code,
                'label' => (string)($permissionsCatalog[$code] ?? $code),
            ];
        }

        $messageForStorage = $message;
        if ($requestedPermissionLabels) {
            $messageForStorage .= "\n\nAccès demandés :\n";
            foreach ($requestedPermissionLabels as $item) {
                $messageForStorage .= '- ' . $item['label'] . ' (' . $item['code'] . ')' . "\n";
            }
        }

        $structuredAccessPayload = [
            'requested_permissions' => $requestedPermissionLabels,
            'requested_permission_codes' => $selectedPermissionCodes,
            'requested_at' => date('Y-m-d H:i:s'),
        ];

        $requestId = 0;

        if (tableExists($pdo, 'support_requests')) {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'request_type' => 'access',
                'subject' => $subject,
                'message' => $messageForStorage,
                'status' => 'open',
                'priority' => 'normal',
                'created_by' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            ];

            if (columnExists($pdo, 'support_requests', 'requested_permissions_json')) {
                $map['requested_permissions_json'] = json_encode($requestedPermissionLabels, JSON_UNESCAPED_UNICODE);
            }

            if (columnExists($pdo, 'support_requests', 'details_json')) {
                $map['details_json'] = json_encode($structuredAccessPayload, JSON_UNESCAPED_UNICODE);
            }

            if (columnExists($pdo, 'support_requests', 'metadata_json')) {
                $map['metadata_json'] = json_encode($structuredAccessPayload, JSON_UNESCAPED_UNICODE);
            }

            foreach ($map as $column => $value) {
                if (columnExists($pdo, 'support_requests', $column)) {
                    $columns[] = $column;
                    $values[] = '?';
                    $params[] = $value;
                }
            }

            if (columnExists($pdo, 'support_requests', 'created_at')) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }

            if (columnExists($pdo, 'support_requests', 'updated_at')) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            if ($columns) {
                $stmt = $pdo->prepare("
                    INSERT INTO support_requests (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $values) . ")
                ");
                $stmt->execute($params);
                $requestId = (int)$pdo->lastInsertId();
            }
        }

        if (function_exists('sl_audit_and_notify_action') && isset($_SESSION['user_id'])) {
            sl_audit_and_notify_action(
                $pdo,
                (int)$_SESSION['user_id'],
                'request_support_access',
                'support',
                'support_request',
                $requestId > 0 ? $requestId : null,
                [
                    'subject' => $subject,
                    'requested_permissions' => $requestedPermissionLabels,
                ],
                'support_access_requested',
                'Nouvelle demande d’accès : ' . $subject,
                'info',
                $requestId > 0 ? APP_URL . 'modules/support/support_requests.php' : null,
                null,
                true
            );
        }

        $successMessage = 'Demande d’accès enregistrée.';
        $subjectValue = '';
        $messageValue = '';
        $selectedPermissionCodes = [];

        $openQuestions = sl_support_open_requests_count($pdo, 'question');
        $openBugs = sl_support_open_requests_count($pdo, 'bug');
        $openAccess = sl_support_open_requests_count($pdo, 'access');
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Demander un accès';
$pageSubtitle = 'Créer une demande d’accès ou d’évolution de droits.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sa-hero,
            .sa-form-card,
            .sa-side-card,
            .sa-access-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
            }

            .sa-hero {
                padding: 22px 24px;
                margin-bottom: 20px;
                border-radius: 24px;
                background: linear-gradient(135deg, rgba(49,46,129,1) 0%, rgba(79,70,229,0.95) 55%, rgba(56,189,248,0.95) 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(49, 46, 129, 0.20);
            }

            .sa-hero__top {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:18px;
                flex-wrap:wrap;
            }

            .sa-hero__title {
                margin:0 0 8px;
                font-size:28px;
                font-weight:800;
                letter-spacing:-0.02em;
            }

            .sa-hero__subtitle {
                margin:0;
                max-width:860px;
                color:rgba(255,255,255,0.88);
                line-height:1.55;
            }

            .sa-kpi-grid {
                display:grid;
                grid-template-columns:repeat(3, minmax(0, 1fr));
                gap:14px;
                margin-bottom:20px;
            }

            @media (max-width: 900px) {
                .sa-kpi-grid {
                    grid-template-columns:1fr;
                }
            }

            .sa-kpi {
                border-radius:18px;
                padding:16px;
                background:linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border:1px solid rgba(148, 163, 184, 0.14);
            }

            .sa-kpi__label {
                display:block;
                font-size:12px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                margin-bottom:8px;
            }

            .sa-kpi__value {
                display:block;
                font-size:28px;
                line-height:1;
                font-weight:800;
                color:#0f172a;
            }

            .sa-kpi__meta {
                margin-top:8px;
                color:#64748b;
                font-size:12px;
            }

            .sa-grid {
                display:grid;
                grid-template-columns:1.3fr .7fr;
                gap:20px;
            }

            @media (max-width: 1100px) {
                .sa-grid {
                    grid-template-columns:1fr;
                }
            }

            .sa-form-card,
            .sa-side-card {
                padding:20px;
            }

            .sa-section-title {
                margin:0 0 8px;
                font-size:20px;
                font-weight:800;
                color:#0f172a;
            }

            .sa-subtitle {
                margin:0 0 18px;
                color:#64748b;
                line-height:1.55;
            }

            .sa-note {
                padding:14px 16px;
                border-radius:16px;
                background:rgba(79,70,229,0.06);
                border:1px solid rgba(79,70,229,0.14);
                color:#3730a3;
                line-height:1.55;
                margin-bottom:18px;
            }

            .sa-field-grid {
                display:grid;
                gap:16px;
            }

            .sa-access-grid {
                display:grid;
                gap:16px;
                margin-top:18px;
            }

            .sa-access-card {
                overflow:hidden;
            }

            .sa-access-head {
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:12px;
                padding:14px 16px;
                background:linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border-bottom:1px solid rgba(148,163,184,0.12);
            }

            .sa-access-title {
                margin:0;
                font-size:15px;
                font-weight:800;
                color:#0f172a;
            }

            .sa-access-count {
                display:inline-flex;
                align-items:center;
                justify-content:center;
                min-width:32px;
                padding:4px 10px;
                border-radius:999px;
                background:rgba(79,70,229,0.08);
                color:#4338ca;
                font-size:12px;
                font-weight:800;
            }

            .sa-access-body {
                padding:16px;
            }

            .sa-permissions-grid {
                display:grid;
                grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
                gap:10px;
            }

            .sa-permission-item label {
                min-height:72px;
                display:flex;
                gap:12px;
                align-items:flex-start;
                padding:12px 14px;
                border-radius:14px;
                border:1px solid rgba(148,163,184,0.18);
                background:#fff;
                cursor:pointer;
                transition:all .18s ease;
            }

            .sa-permission-item label:hover {
                border-color:rgba(79,70,229,0.35);
                box-shadow:0 8px 18px rgba(79,70,229,0.08);
                transform:translateY(-1px);
            }

            .sa-permission-item input[type="checkbox"] {
                width:17px;
                height:17px;
                margin-top:3px;
                accent-color:#4f46e5;
            }

            .sa-permission-item.is-checked label {
                background:rgba(79,70,229,0.05);
                border-color:rgba(79,70,229,0.28);
            }

            .sa-permission-text {
                min-width:0;
            }

            .sa-permission-name {
                display:block;
                font-size:13px;
                font-weight:700;
                color:#0f172a;
                line-height:1.35;
            }

            .sa-permission-code {
                display:inline-block;
                margin-top:4px;
                font-size:11px;
                color:#64748b;
                word-break:break-word;
            }

            .sa-current-badge {
                display:inline-flex;
                align-items:center;
                gap:6px;
                margin-top:8px;
                padding:4px 8px;
                border-radius:999px;
                background:rgba(16,185,129,0.08);
                color:#047857;
                font-size:11px;
                font-weight:800;
            }

            .sa-preview-box {
                border-radius:18px;
                padding:16px;
                background:rgba(248,250,252,0.82);
                border:1px solid rgba(148,163,184,0.14);
            }

            .sa-preview-title {
                margin:0 0 12px;
                font-size:14px;
                font-weight:800;
                color:#334155;
            }

            .sa-preview-line {
                display:flex;
                justify-content:space-between;
                gap:12px;
                padding:10px 0;
                border-bottom:1px solid rgba(148,163,184,0.10);
            }

            .sa-preview-line:last-child {
                border-bottom:none;
            }

            .sa-preview-line span {
                color:#64748b;
                font-size:13px;
            }

            .sa-preview-line strong {
                color:#0f172a;
                font-size:13px;
                text-align:right;
            }

            .sa-preview-list {
                margin-top:14px;
                display:grid;
                gap:8px;
            }

            .sa-preview-pill {
                display:flex;
                justify-content:space-between;
                gap:12px;
                padding:10px 12px;
                border-radius:12px;
                background:#fff;
                border:1px solid rgba(148,163,184,0.14);
            }

            .sa-preview-pill span {
                color:#0f172a;
                font-size:13px;
                font-weight:700;
            }

            .sa-preview-pill small {
                color:#64748b;
                font-size:11px;
            }
        </style>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <section class="sa-hero">
            <div class="sa-hero__top">
                <div>
                    <h1 class="sa-hero__title">Demander un accès</h1>
                    <p class="sa-hero__subtitle">
                        Utilise ce formulaire pour demander de nouveaux droits applicatifs ou une évolution de ton périmètre.
                        Tu peux décrire le besoin métier puis sélectionner directement les accès souhaités.
                    </p>
                </div>

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-outline">Voir le support</a>
                    <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="btn btn-secondary">Poser une question</a>
                    <a href="<?= e(APP_URL) ?>modules/support/report_bug.php" class="btn btn-warning">Signaler un bug</a>
                </div>
            </div>
        </section>

        <section class="sa-kpi-grid">
            <div class="sa-kpi">
                <span class="sa-kpi__label">Demandes d’accès ouvertes</span>
                <span class="sa-kpi__value"><?= (int)$openAccess ?></span>
                <div class="sa-kpi__meta">Droits en attente d’analyse</div>
            </div>

            <div class="sa-kpi">
                <span class="sa-kpi__label">Questions ouvertes</span>
                <span class="sa-kpi__value"><?= (int)$openQuestions ?></span>
                <div class="sa-kpi__meta">Flux support fonctionnel</div>
            </div>

            <div class="sa-kpi">
                <span class="sa-kpi__label">Bugs ouverts</span>
                <span class="sa-kpi__value"><?= (int)$openBugs ?></span>
                <div class="sa-kpi__meta">Flux support technique</div>
            </div>
        </section>

        <div class="sa-grid">
            <section class="sa-form-card">
                <h3 class="sa-section-title">Nouvelle demande d’accès</h3>
                <p class="sa-subtitle">
                    Décris le contexte puis coche les accès nécessaires. La demande sera centralisée dans le support pour validation ou arbitrage.
                </p>

                <div class="sa-note">
                    Bon réflexe : demande uniquement les accès utiles à ton rôle réel. Plus la demande est précise, plus elle sera rapide à traiter.
                </div>

                <form method="POST" id="requestAccessForm">
                    <?= csrf_input() ?>

                    <div class="sa-field-grid">
                        <div>
                            <label>Sujet</label>
                            <input type="text" name="subject" value="<?= e($subjectValue) ?>" required>
                        </div>

                        <div>
                            <label>Détails</label>
                            <textarea name="message" rows="7" required><?= e($messageValue) ?></textarea>
                        </div>
                    </div>

                    <?php if ($groupedPermissions): ?>
                        <div class="sa-access-grid">
                            <?php foreach ($groupedPermissions as $group => $items): ?>
                                <div class="sa-access-card">
                                    <div class="sa-access-head">
                                        <h4 class="sa-access-title"><?= e(sl_support_permission_group_label((string)$group)) ?></h4>
                                        <span class="sa-access-count"><?= count($items) ?></span>
                                    </div>

                                    <div class="sa-access-body">
                                        <div class="sa-permissions-grid">
                                            <?php foreach ($items as $item): ?>
                                                <?php
                                                $code = (string)($item['code'] ?? '');
                                                $label = (string)($item['label'] ?? $code);
                                                $checked = in_array($code, $selectedPermissionCodes, true);
                                                $alreadyHas = in_array($code, $currentUserPermissionCodes, true);
                                                ?>
                                                <div class="sa-permission-item <?= $checked ? 'is-checked' : '' ?>">
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            name="requested_permission_codes[]"
                                                            value="<?= e($code) ?>"
                                                            <?= $checked ? 'checked' : '' ?>
                                                        >
                                                        <span class="sa-permission-text">
                                                            <span class="sa-permission-name"><?= e($label) ?></span>
                                                            <span class="sa-permission-code"><?= e($code) ?></span>

                                                            <?php if ($alreadyHas): ?>
                                                                <span class="sa-current-badge">Déjà accordé</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-outline">Envoyer la demande</button>
                        <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-secondary">Retour</a>
                    </div>
                </form>
            </section>

            <aside class="sa-side-card">
                <h3 class="sa-section-title">Prévisualisation</h3>
                <p class="sa-subtitle">Résumé de la demande avant envoi.</p>

                <div class="sa-preview-box">
                    <div class="sa-preview-title">Résumé</div>

                    <div class="sa-preview-line">
                        <span>Type</span>
                        <strong>Demande d’accès</strong>
                    </div>

                    <div class="sa-preview-line">
                        <span>Sujet</span>
                        <strong><?= e($subjectValue !== '' ? $subjectValue : '—') ?></strong>
                    </div>

                    <div class="sa-preview-line">
                        <span>Nombre d’accès cochés</span>
                        <strong><?= count($selectedPermissionCodes) ?></strong>
                    </div>

                    <div class="sa-preview-line">
                        <span>État initial</span>
                        <strong>Ouverte</strong>
                    </div>
                </div>

                <div class="sa-preview-list">
                    <?php if ($selectedPermissionCodes): ?>
                        <?php foreach ($selectedPermissionCodes as $code): ?>
                            <div class="sa-preview-pill">
                                <span><?= e((string)($permissionsCatalog[$code] ?? $code)) ?></span>
                                <small><?= e($code) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dashboard-note" style="margin-top:14px;">
                            Aucun accès coché pour le moment. Tu peux quand même envoyer une demande textuelle, mais la sélection explicite facilite fortement le traitement.
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            function refreshCheckedState() {
                document.querySelectorAll('.sa-permission-item').forEach(function (item) {
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;

                    if (checkbox.checked) {
                        item.classList.add('is-checked');
                    } else {
                        item.classList.remove('is-checked');
                    }
                });
            }

            document.querySelectorAll('.sa-permission-item input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.addEventListener('change', refreshCheckedState);
            });

            refreshCheckedState();
        });
        </script>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>