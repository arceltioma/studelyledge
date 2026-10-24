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
                'request_type' => 'question',
                'subject' => $subject,
                'message' => $message,
                'status' => 'open',
                'priority' => 'normal',
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
                'support_question_created',
                'support',
                'support_request',
                $requestId > 0 ? $requestId : null,
                'Création d’une question support : ' . $subject,
                'support_question_created',
                'Nouvelle question support : ' . $subject,
                'info',
                $requestId > 0 ? APP_URL . 'modules/support/support_requests.php' : null,
                null,
                true
            );
        }

        $successMessage = 'Question enregistrée avec succès.';
        $subjectValue = '';
        $messageValue = '';

        $openQuestions = sl_support_open_requests_count($pdo, 'question');
        $openBugs = sl_support_open_requests_count($pdo, 'bug');
        $openAccess = sl_support_open_requests_count($pdo, 'access');
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$pageTitle = 'Poser une question';
$pageSubtitle = 'Créer une demande de support de type question.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sq-hero,
            .sq-card,
            .sq-form-card,
            .sq-side-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148, 163, 184, 0.16);
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
            }

            .sq-hero {
                padding: 22px 24px;
                margin-bottom: 20px;
                border-radius: 24px;
                background: linear-gradient(135deg, rgba(30,41,59,1) 0%, rgba(14,165,233,0.96) 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            }

            .sq-hero__top {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:18px;
                flex-wrap:wrap;
            }

            .sq-hero__title {
                margin:0 0 8px;
                font-size:28px;
                font-weight:800;
                letter-spacing:-0.02em;
            }

            .sq-hero__subtitle {
                margin:0;
                max-width:860px;
                color:rgba(255,255,255,0.86);
                line-height:1.55;
            }

            .sq-kpi-grid {
                display:grid;
                grid-template-columns:repeat(3, minmax(0, 1fr));
                gap:14px;
                margin-bottom:20px;
            }

            @media (max-width: 900px) {
                .sq-kpi-grid {
                    grid-template-columns:1fr;
                }
            }

            .sq-kpi {
                border-radius:18px;
                padding:16px;
                background:linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
                border:1px solid rgba(148, 163, 184, 0.14);
            }

            .sq-kpi__label {
                display:block;
                font-size:12px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                margin-bottom:8px;
            }

            .sq-kpi__value {
                display:block;
                font-size:28px;
                line-height:1;
                font-weight:800;
                color:#0f172a;
            }

            .sq-kpi__meta {
                margin-top:8px;
                color:#64748b;
                font-size:12px;
            }

            .sq-grid {
                display:grid;
                grid-template-columns:1.2fr .8fr;
                gap:20px;
            }

            @media (max-width: 980px) {
                .sq-grid {
                    grid-template-columns:1fr;
                }
            }

            .sq-form-card,
            .sq-side-card {
                padding:20px;
            }

            .sq-section-title {
                margin:0 0 8px;
                font-size:20px;
                font-weight:800;
                color:#0f172a;
            }

            .sq-subtitle {
                margin:0 0 18px;
                color:#64748b;
                line-height:1.55;
            }

            .sq-note {
                padding:14px 16px;
                border-radius:16px;
                background:rgba(59,130,246,0.06);
                border:1px solid rgba(59,130,246,0.14);
                color:#1e3a8a;
                line-height:1.55;
                margin-bottom:18px;
            }

            .sq-field-grid {
                display:grid;
                gap:16px;
            }

            .sq-preview-box {
                border-radius:18px;
                padding:16px;
                background:rgba(248,250,252,0.82);
                border:1px solid rgba(148,163,184,0.14);
            }

            .sq-preview-title {
                margin:0 0 12px;
                font-size:14px;
                font-weight:800;
                color:#334155;
            }

            .sq-preview-line {
                display:flex;
                justify-content:space-between;
                gap:12px;
                padding:10px 0;
                border-bottom:1px solid rgba(148,163,184,0.10);
            }

            .sq-preview-line:last-child {
                border-bottom:none;
            }

            .sq-preview-line span {
                color:#64748b;
                font-size:13px;
            }

            .sq-preview-line strong {
                color:#0f172a;
                font-size:13px;
                text-align:right;
            }

            .sq-help-list {
                display:grid;
                gap:12px;
                margin-top:16px;
            }

            .sq-help-item {
                border-radius:16px;
                padding:14px 16px;
                background:#fff;
                border:1px solid rgba(148,163,184,0.14);
            }

            .sq-help-item strong {
                display:block;
                margin-bottom:6px;
                color:#0f172a;
            }

            .sq-help-item span {
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

        <section class="sq-hero">
            <div class="sq-hero__top">
                <div>
                    <h1 class="sq-hero__title">Poser une question</h1>
                    <p class="sq-hero__subtitle">
                        Utilise ce formulaire pour transmettre une question fonctionnelle, métier ou d’utilisation.
                        La demande sera enregistrée proprement dans le centre support et pourra être suivie ensuite.
                    </p>
                </div>

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-outline">Voir le support</a>
                    <a href="<?= e(APP_URL) ?>modules/support/report_bug.php" class="btn btn-warning">Signaler un bug</a>
                    <a href="<?= e(APP_URL) ?>modules/support/request_access.php" class="btn btn-secondary">Demander un accès</a>
                </div>
            </div>
        </section>

        <section class="sq-kpi-grid">
            <div class="sq-kpi">
                <span class="sq-kpi__label">Questions ouvertes</span>
                <span class="sq-kpi__value"><?= (int)$openQuestions ?></span>
                <div class="sq-kpi__meta">Demandes de type question en attente</div>
            </div>

            <div class="sq-kpi">
                <span class="sq-kpi__label">Bugs ouverts</span>
                <span class="sq-kpi__value"><?= (int)$openBugs ?></span>
                <div class="sq-kpi__meta">Incidents actuellement signalés</div>
            </div>

            <div class="sq-kpi">
                <span class="sq-kpi__label">Accès ouverts</span>
                <span class="sq-kpi__value"><?= (int)$openAccess ?></span>
                <div class="sq-kpi__meta">Demandes de droits à traiter</div>
            </div>
        </section>

        <div class="sq-grid">
            <section class="sq-form-card">
                <h3 class="sq-section-title">Nouvelle question</h3>
                <p class="sq-subtitle">
                    Décris clairement le besoin, le contexte, et ce que tu attends comme réponse.
                </p>

                <div class="sq-note">
                    Conseil : indique le module concerné, le résultat attendu, et le bloc précis si ta question porte sur une page existante.
                </div>

                <form method="POST">
                    <?= csrf_input() ?>

                    <div class="sq-field-grid">
                        <div>
                            <label>Sujet</label>
                            <input type="text" name="subject" value="<?= e($subjectValue) ?>" required>
                        </div>

                        <div>
                            <label>Message</label>
                            <textarea name="message" rows="10" required><?= e($messageValue) ?></textarea>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top:20px;">
                        <button type="submit" class="btn btn-secondary">Envoyer la question</button>
                        <a href="<?= e(APP_URL) ?>modules/support/support_requests.php" class="btn btn-outline">Retour</a>
                    </div>
                </form>
            </section>

            <aside class="sq-side-card">
                <h3 class="sq-section-title">Prévisualisation</h3>
                <p class="sq-subtitle">Aperçu de la demande avant envoi.</p>

                <div class="sq-preview-box">
                    <div class="sq-preview-title">Résumé</div>

                    <div class="sq-preview-line">
                        <span>Type</span>
                        <strong>Question</strong>
                    </div>

                    <div class="sq-preview-line">
                        <span>Sujet</span>
                        <strong><?= e($subjectValue !== '' ? $subjectValue : '—') ?></strong>
                    </div>

                    <div class="sq-preview-line">
                        <span>Longueur message</span>
                        <strong><?= mb_strlen($messageValue, 'UTF-8') ?> caractère(s)</strong>
                    </div>

                    <div class="sq-preview-line">
                        <span>État initial</span>
                        <strong>Ouverte</strong>
                    </div>
                </div>

                <div class="sq-help-list">
                    <div class="sq-help-item">
                        <strong>Quand utiliser cette page ?</strong>
                        <span>Pour une question fonctionnelle, un doute métier, ou une demande d’explication sur un écran, une règle ou un comportement.</span>
                    </div>

                    <div class="sq-help-item">
                        <strong>Quand utiliser plutôt “Bug” ?</strong>
                        <span>Quand quelque chose ne fonctionne pas comme prévu, génère une erreur ou affiche un résultat incohérent.</span>
                    </div>

                    <div class="sq-help-item">
                        <strong>Quand utiliser plutôt “Demande d’accès” ?</strong>
                        <span>Quand tu souhaites obtenir de nouveaux droits ou faire évoluer les permissions de ton rôle.</span>
                    </div>
                </div>
            </aside>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>