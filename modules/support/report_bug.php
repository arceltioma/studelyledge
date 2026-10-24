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

        $hasRequestType = columnExists($pdo, 'support_requests', 'request_type');
        $hasStatus = columnExists($pdo, 'support_requests', 'status');

        if (!$hasRequestType) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM support_requests
            WHERE request_type = ?
        ";
        $params = [$type];

        if ($hasStatus) {
            $sql .= " AND status IN ('open', 'in_progress', 'pending')";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}

$errorMessage = '';
$successMessage = '';

$subjectValue = trim((string)($_POST['subject'] ?? ''));
$messageValue = trim((string)($_POST['message'] ?? ''));

$openQuestions = sl_support_open_requests_count($pdo, 'question');
$openBugs = sl_support_open_requests_count($pdo, 'bug');
$openAccess = sl_support_open_requests_count($pdo, 'access');

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

        $requestId = 0;

        if (tableExists($pdo, 'support_requests')) {
            $columns = [];
            $values = [];
            $params = [];

            $map = [
                'request_type' => 'bug',
                'subject' => $subject,
                'message' => $message,
                'status' => 'open',
                'priority' => 'high',
                'created_by' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            ];

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
                'support_bug_reported',
                'support',
                'support_request',
                $requestId > 0 ? $requestId : null,
                'Signalement d’un bug : ' . $subject,
                'support_bug_reported',
                'Nouveau bug signalé : ' . $subject,
                'warning',
                $requestId > 0 ? APP_URL . 'modules/support/support_requests.php' : null,
                null,
                true
            );
        }

        $successMessage = 'Bug signalé avec succès.';
        $subjectValue = '';
        $messageValue = '';

        $openQuestions = sl_support_open_requests_count($pdo, 'question');
        $openBugs = sl_support_open_requests_count($pdo, 'bug');
        $openAccess = sl_support_open_requests_count($pdo, 'access');
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Signaler un bug';
$pageSubtitle = 'Créer une demande de support de type bug.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sb-hero,
            .sb-form-card,
            .sb-side-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
            }

            .sb-hero {
                padding: 22px 24px;
                margin-bottom: 20px;
                border-radius: 24px;
                background: linear-gradient(135deg, rgba(127,29,29,1) 0%, rgba(220,38,38,0.95) 55%, rgba(251,191,36,0.95) 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(127, 29, 29, 0.20);
            }

            .sb-hero__top {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:18px;
                flex-wrap:wrap;
            }

            .sb-hero__title {
                margin:0 0 8px;
                font-size:28px;
                font-weight:800;
                letter-spacing:-0.02em;
            }

            .sb-hero__subtitle {
                margin:0;
                max-width:860px;
                color:rgba(255,255,255,0.88);
                line-height:1.55;
            }

            .sb-kpi-grid {
                display:grid;
                grid-template-columns:repeat(3, minmax(0, 1fr));
                gap:14px;
                margin-bottom:20px;
            }

            @media (max-width: 900px) {
                .sb-kpi-grid {
                    grid-template-columns:1fr;
                }
            }

            .sb-kpi {
                border-radius:18px;
                padding:16px;
                background:linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border:1px solid rgba(148, 163, 184, 0.14);
            }

            .sb-kpi__label {
                display:block;
                font-size:12px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                margin-bottom:8px;
            }

            .sb-kpi__value {
                display:block;
                font-size:28px;
                line-height:1;
                font-weight:800;
                color:#0f172a;
            }

            .sb-kpi__meta {
                margin-top:8px;
                color:#64748b;
                font-size:12px;
            }

            .sb-grid {
                display:grid;
                grid-template-columns:1.2fr .8fr;
                gap:20px;
            }

            @media (max-width: 980px) {
                .sb-grid {
                    grid-template-columns:1fr;
                }
            }

            .sb-form-card,
            .sb-side-card {
                padding:20px;
            }

            .sb-section-title {
                margin:0 0 8px;
                font-size:20px;
                font-weight:800;
                color:#0f172a;
            }

            .sb-subtitle {
                margin:0 0 18px;
                color:#64748b;
                line-height:1.55;
            }

            .sb-note {
                padding:14px 16px;
                border-radius:16px;
                background:rgba(239,68,68,0.06);
                border:1px solid rgba(239,68,68,0.14);
                color:#991b1b;
                line-height:1.55;
                margin-bottom:18px;
            }

            .sb-field-grid {
                display:grid;
                gap:16px;
            }

            .sb-preview-box {
                border-radius:18px;
                padding:16px;
                background:rgba(248,250,252,0.82);
                border:1px solid rgba(148,163,184,0.14);
            }

            .sb-preview-title {
                margin:0 0 12px;
                font-size:14px;
                font-weight:800;
                color:#334155;
            }

            .sb-preview-line {
                display:flex;
                justify-content:space-between;
                gap:12px;
                padding:10px 0;
                border-bottom:1px solid rgba(148,163,184,0.10);
            }

            .sb-preview-line:last-child {
                border-bottom:none;
            }

            .sb-preview-line span {
                color:#64748b;
                font-size:13px;
            }

            .sb-preview-line strong {
                color:#0f172a;
                font-size:13px;
                text-align:right;
            }

            .sb-help-list {
                display:grid;
                gap:12px;
                margin-top:16px;
            }

            .sb-help-item {
                border-radius:16px;
                padding:14px 16px;
                background:#fff;
                border:1px solid rgba(148,163,184,0.14);
            }

            .sb-help-item strong {
                display:block;
                margin-bottom:6px;
                color:#0f172a;
            }

            .sb-help-item span {
                color:#64748b;
                line-height:1.5;
                font-size:13px;
            }
        </style>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <section class="sb-hero">
            <div class="sb-hero__top">
                <div>
                    <h1 class="sb-hero__title">Signaler un bug</h1>
                    <p class="sb-hero__subtitle">
                        Déclare ici une anomalie, une erreur d’affichage, un dysfonctionnement ou un résultat incohérent.
                        Le signalement sera envoyé dans le centre support avec une priorité renforcée.
                    </p>
                </div>

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-outline">Voir le support</a>
                    <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="btn btn-secondary">Poser une question</a>
                    <a href="<?= e(APP_URL) ?>modules/support/request_access.php" class="btn btn-outline">Demander un accès</a>
                </div>
            </div>
        </section>

        <section class="sb-kpi-grid">
            <div class="sb-kpi">
                <span class="sb-kpi__label">Bugs ouverts</span>
                <span class="sb-kpi__value"><?= (int)$openBugs ?></span>
                <div class="sb-kpi__meta">Anomalies en attente de traitement</div>
            </div>

            <div class="sb-kpi">
                <span class="sb-kpi__label">Questions ouvertes</span>
                <span class="sb-kpi__value"><?= (int)$openQuestions ?></span>
                <div class="sb-kpi__meta">Demandes fonctionnelles en cours</div>
            </div>

            <div class="sb-kpi">
                <span class="sb-kpi__label">Accès ouverts</span>
                <span class="sb-kpi__value"><?= (int)$openAccess ?></span>
                <div class="sb-kpi__meta">Demandes de droits en attente</div>
            </div>
        </section>

        <div class="sb-grid">
            <section class="sb-form-card">
                <h3 class="sb-section-title">Nouveau signalement</h3>
                <p class="sb-subtitle">
                    Décris le problème observé, où il se produit, et ce que tu attendais.
                </p>

                <div class="sb-note">
                    Pour accélérer le diagnostic, précise le module concerné, le parcours exact, le message d’erreur éventuel et le résultat attendu.
                </div>

                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="sb-field-grid">
                        <div>
                            <label>Sujet</label>
                            <input type="text" name="subject" value="<?= e($subjectValue) ?>" required>
                        </div>

                        <div>
                            <label>Description</label>
                            <textarea name="message" rows="10" required><?= e($messageValue) ?></textarea>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-warning">Signaler le bug</button>
                        <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </section>

            <aside class="sb-side-card">
                <h3 class="sb-section-title">Prévisualisation</h3>
                <p class="sb-subtitle">Résumé du ticket avant envoi.</p>

                <div class="sb-preview-box">
                    <div class="sb-preview-title">Résumé</div>

                    <div class="sb-preview-line">
                        <span>Type</span>
                        <strong>Bug</strong>
                    </div>

                    <div class="sb-preview-line">
                        <span>Sujet</span>
                        <strong><?= e($subjectValue !== '' ? $subjectValue : '—') ?></strong>
                    </div>

                    <div class="sb-preview-line">
                        <span>Longueur description</span>
                        <strong><?= mb_strlen($messageValue, 'UTF-8') ?> caractère(s)</strong>
                    </div>

                    <div class="sb-preview-line">
                        <span>Priorité</span>
                        <strong>Haute</strong>
                    </div>

                    <div class="sb-preview-line">
                        <span>État initial</span>
                        <strong>Ouverte</strong>
                    </div>
                </div>

                <div class="sb-help-list">
                    <div class="sb-help-item">
                        <strong>Quand utiliser cette page ?</strong>
                        <span>Quand une action plante, affiche une incohérence, produit une erreur ou ne respecte pas le comportement attendu.</span>
                    </div>

                    <div class="sb-help-item">
                        <strong>Informations utiles à donner</strong>
                        <span>Page concernée, étape précise, données saisies, résultat obtenu, résultat attendu, et si possible message affiché.</span>
                    </div>

                    <div class="sb-help-item">
                        <strong>Et si ce n’est pas un bug ?</strong>
                        <span>Pour une simple question, utilise la page “Poser une question”. Pour un droit manquant, utilise “Demander un accès”.</span>
                    </div>
                </div>
            </aside>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>