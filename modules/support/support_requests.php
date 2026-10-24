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

if (!function_exists('sl_support_requests_fetch_all')) {
    function sl_support_requests_fetch_all(PDO $pdo): array
    {
        if (!tableExists($pdo, 'support_requests')) {
            return [];
        }

        return $pdo->query("
            SELECT *
            FROM support_requests
            ORDER BY created_at DESC, id DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('sl_support_badge_class_for_type')) {
    function sl_support_badge_class_for_type(string $type): string
    {
        return match ($type) {
            'bug' => 'status-danger',
            'access' => 'status-info',
            'question' => 'status-primary',
            default => 'status-neutral',
        };
    }
}

if (!function_exists('sl_support_badge_class_for_status')) {
    function sl_support_badge_class_for_status(string $status): string
    {
        return match ($status) {
            'open' => 'status-warning',
            'in_progress' => 'status-info',
            'resolved', 'closed' => 'status-success',
            'rejected', 'cancelled' => 'status-danger',
            default => 'status-neutral',
        };
    }
}

if (!function_exists('sl_support_badge_class_for_priority')) {
    function sl_support_badge_class_for_priority(string $priority): string
    {
        return match ($priority) {
            'high', 'urgent' => 'status-danger',
            'medium', 'normal' => 'status-warning',
            'low' => 'status-neutral',
            default => 'status-neutral',
        };
    }
}

if (!function_exists('sl_support_label_for_type')) {
    function sl_support_label_for_type(string $type): string
    {
        return match ($type) {
            'question' => 'Question',
            'bug' => 'Bug',
            'access' => 'Accès',
            default => ucfirst($type !== '' ? $type : 'Demande'),
        };
    }
}

if (!function_exists('sl_support_label_for_status')) {
    function sl_support_label_for_status(string $status): string
    {
        return match ($status) {
            'open' => 'Ouverte',
            'in_progress' => 'En cours',
            'resolved' => 'Résolue',
            'closed' => 'Clôturée',
            'rejected' => 'Rejetée',
            'cancelled' => 'Annulée',
            default => $status !== '' ? $status : '—',
        };
    }
}

if (!function_exists('sl_support_label_for_priority')) {
    function sl_support_label_for_priority(string $priority): string
    {
        return match ($priority) {
            'low' => 'Basse',
            'normal' => 'Normale',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'urgent' => 'Urgente',
            default => $priority !== '' ? $priority : '—',
        };
    }
}

if (!function_exists('sl_support_parse_requested_permissions_from_message')) {
    function sl_support_parse_requested_permissions_from_message(string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return [];
        }

        $items = [];
        $lines = preg_split('/\R/u', $message) ?: [];
        $capture = false;

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '') {
                if ($capture) {
                    continue;
                }
                continue;
            }

            if (!$capture && preg_match('/^Acc[eè]s demand[ée]s\s*:/iu', $line)) {
                $capture = true;
                continue;
            }

            if ($capture) {
                if (preg_match('/^\-\s*(.+)$/u', $line, $m)) {
                    $raw = trim((string)$m[1]);

                    $label = $raw;
                    $code = '';

                    if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $raw, $parts)) {
                        $label = trim((string)$parts[1]);
                        $code = trim((string)$parts[2]);
                    }

                    $items[] = [
                        'label' => $label !== '' ? $label : $raw,
                        'code' => $code,
                    ];
                    continue;
                }

                if (!str_starts_with($line, '-')) {
                    break;
                }
            }
        }

        return $items;
    }
}

if (!function_exists('sl_support_extract_requested_permissions')) {
    function sl_support_extract_requested_permissions(array $request): array
    {
        $normalized = [];

        $pushItem = static function (array &$bucket, array $item): void {
            $label = trim((string)($item['label'] ?? ''));
            $code = trim((string)($item['code'] ?? ''));

            if ($label === '' && $code === '') {
                return;
            }

            $bucket[] = [
                'label' => $label !== '' ? $label : $code,
                'code' => $code,
            ];
        };

        $normalizeAndDeduplicate = static function (array $items): array {
            $out = [];
            $seen = [];

            foreach ($items as $item) {
                $label = trim((string)($item['label'] ?? ''));
                $code = trim((string)($item['code'] ?? ''));

                if ($label === '' && $code === '') {
                    continue;
                }

                $key = mb_strtolower($label . '|' . $code, 'UTF-8');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $out[] = [
                    'label' => $label !== '' ? $label : $code,
                    'code' => $code,
                ];
            }

            return $out;
        };

        /*
         * PRIORITE 1 : requested_permissions_json
         */
        $rawRequestedPermissionsJson = trim((string)($request['requested_permissions_json'] ?? ''));
        if ($rawRequestedPermissionsJson !== '') {
            $decoded = json_decode($rawRequestedPermissionsJson, true);

            if (is_array($decoded)) {
                foreach ($decoded as $row) {
                    if (is_array($row)) {
                        $pushItem($normalized, [
                            'label' => (string)($row['label'] ?? $row['code'] ?? ''),
                            'code'  => (string)($row['code'] ?? ''),
                        ]);
                    } elseif (is_string($row)) {
                        $pushItem($normalized, [
                            'label' => $row,
                            'code'  => '',
                        ]);
                    }
                }

                $normalized = $normalizeAndDeduplicate($normalized);
                if ($normalized) {
                    return $normalized;
                }
            }
        }

        /*
         * PRIORITE 2 : details_json
         */
        $rawDetailsJson = trim((string)($request['details_json'] ?? ''));
        if ($rawDetailsJson !== '') {
            $decoded = json_decode($rawDetailsJson, true);

            if (is_array($decoded)) {
                $nested = $decoded['requested_permissions'] ?? null;

                if (is_array($nested)) {
                    foreach ($nested as $row) {
                        if (is_array($row)) {
                            $pushItem($normalized, [
                                'label' => (string)($row['label'] ?? $row['code'] ?? ''),
                                'code'  => (string)($row['code'] ?? ''),
                            ]);
                        } elseif (is_string($row)) {
                            $pushItem($normalized, [
                                'label' => $row,
                                'code'  => '',
                            ]);
                        }
                    }

                    $normalized = $normalizeAndDeduplicate($normalized);
                    if ($normalized) {
                        return $normalized;
                    }
                }
            }
        }

        /*
         * PRIORITE 3 : metadata_json
         */
        $rawMetadataJson = trim((string)($request['metadata_json'] ?? ''));
        if ($rawMetadataJson !== '') {
            $decoded = json_decode($rawMetadataJson, true);

            if (is_array($decoded)) {
                $nested = $decoded['requested_permissions'] ?? null;

                if (is_array($nested)) {
                    foreach ($nested as $row) {
                        if (is_array($row)) {
                            $pushItem($normalized, [
                                'label' => (string)($row['label'] ?? $row['code'] ?? ''),
                                'code'  => (string)($row['code'] ?? ''),
                            ]);
                        } elseif (is_string($row)) {
                            $pushItem($normalized, [
                                'label' => $row,
                                'code'  => '',
                            ]);
                        }
                    }

                    $normalized = $normalizeAndDeduplicate($normalized);
                    if ($normalized) {
                        return $normalized;
                    }
                }
            }
        }

        /*
         * PRIORITE 4 : parsing du message historique
         */
        $parsedFromMessage = sl_support_parse_requested_permissions_from_message((string)($request['message'] ?? ''));
        return $normalizeAndDeduplicate($parsedFromMessage);
    }
}

if (!function_exists('sl_support_clean_message_body')) {
    function sl_support_clean_message_body(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        $lines = preg_split('/\R/u', $message) ?: [];
        $clean = [];
        $capture = false;

        foreach ($lines as $line) {
            $trimmed = trim((string)$line);

            if (!$capture && preg_match('/^Acc[eè]s demand[ée]s\s*:/iu', $trimmed)) {
                $capture = true;
                continue;
            }

            if ($capture) {
                if ($trimmed === '') {
                    continue;
                }

                if (preg_match('/^\-\s*(.+)$/u', $trimmed)) {
                    continue;
                }

                $capture = false;
            }

            if (!$capture) {
                $clean[] = $line;
            }
        }

        return trim(implode("\n", $clean));
    }
}

$requests = sl_support_requests_fetch_all($pdo);

$totalRequests = count($requests);
$totalOpen = 0;
$totalInProgress = 0;
$totalAccess = 0;
$totalBugs = 0;
$totalQuestions = 0;

foreach ($requests as $request) {
    $type = (string)($request['request_type'] ?? '');
    $status = (string)($request['status'] ?? '');

    if ($status === 'open') {
        $totalOpen++;
    }
    if ($status === 'in_progress') {
        $totalInProgress++;
    }

    if ($type === 'access') {
        $totalAccess++;
    } elseif ($type === 'bug') {
        $totalBugs++;
    } elseif ($type === 'question') {
        $totalQuestions++;
    }
}

$pageTitle = 'Support';
$pageSubtitle = 'Historique, suivi et mise à jour des demandes support.';
require_once __DIR__ . '/../../includes/document_start.php';
?>

<div class="layout">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <?php require_once __DIR__ . '/../../includes/header.php'; ?>

        <style>
            .sr-hero,
            .sr-kpi,
            .sr-card {
                border-radius: 22px;
                background: #fff;
                border: 1px solid rgba(148,163,184,0.16);
                box-shadow: 0 14px 32px rgba(15,23,42,0.06);
            }

            .sr-hero {
                padding: 22px 24px;
                margin-bottom: 20px;
                background: linear-gradient(135deg, rgba(15,23,42,1) 0%, rgba(30,41,59,0.97) 55%, rgba(79,70,229,0.95) 100%);
                color: #fff;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            }

            .sr-hero__top {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:18px;
                flex-wrap:wrap;
            }

            .sr-hero__title {
                margin:0 0 8px;
                font-size:28px;
                font-weight:800;
                letter-spacing:-0.02em;
            }

            .sr-hero__subtitle {
                margin:0;
                color:rgba(255,255,255,0.86);
                line-height:1.55;
                max-width:860px;
            }

            .sr-kpi-grid {
                display:grid;
                grid-template-columns:repeat(5, minmax(0, 1fr));
                gap:14px;
                margin-bottom:20px;
            }

            @media (max-width: 1100px) {
                .sr-kpi-grid {
                    grid-template-columns:repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 680px) {
                .sr-kpi-grid {
                    grid-template-columns:1fr;
                }
            }

            .sr-kpi {
                padding:16px;
                background:linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
            }

            .sr-kpi__label {
                display:block;
                font-size:12px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.04em;
                color:#64748b;
                margin-bottom:8px;
            }

            .sr-kpi__value {
                display:block;
                font-size:28px;
                line-height:1;
                font-weight:800;
                color:#0f172a;
            }

            .sr-kpi__meta {
                margin-top:8px;
                color:#64748b;
                font-size:12px;
            }

            .sr-list {
                display:grid;
                gap:16px;
            }

            .sr-card {
                padding:20px;
            }

            .sr-card__top {
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:16px;
                flex-wrap:wrap;
                margin-bottom:14px;
            }

            .sr-card__title {
                margin:0 0 6px;
                font-size:20px;
                font-weight:800;
                color:#0f172a;
            }

            .sr-card__meta {
                color:#64748b;
                font-size:13px;
            }

            .sr-badges {
                display:flex;
                gap:8px;
                flex-wrap:wrap;
            }

            .sr-section-grid {
                display:grid;
                grid-template-columns:1.1fr .9fr;
                gap:18px;
            }

            @media (max-width: 960px) {
                .sr-section-grid {
                    grid-template-columns:1fr;
                }
            }

            .sr-box {
                border-radius:18px;
                border:1px solid rgba(148,163,184,0.14);
                background:rgba(248,250,252,0.72);
                padding:16px;
            }

            .sr-box h4 {
                margin:0 0 10px;
                font-size:14px;
                font-weight:800;
                color:#334155;
            }

            .sr-message {
                color:#0f172a;
                line-height:1.65;
                white-space:pre-line;
            }

            .sr-access-grid {
                display:grid;
                grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
                gap:10px;
            }

            .sr-access-pill {
                display:flex;
                justify-content:space-between;
                gap:10px;
                align-items:flex-start;
                padding:10px 12px;
                border-radius:14px;
                background:#fff;
                border:1px solid rgba(79,70,229,0.14);
            }

            .sr-access-pill__label {
                display:block;
                font-size:13px;
                font-weight:700;
                color:#0f172a;
                line-height:1.35;
            }

            .sr-access-pill__code {
                display:block;
                margin-top:4px;
                font-size:11px;
                color:#64748b;
                word-break:break-word;
            }

            .sr-empty {
                padding:14px 16px;
                border-radius:16px;
                background:rgba(248,250,252,0.8);
                border:1px dashed rgba(148,163,184,0.35);
                color:#64748b;
            }
        </style>

        <section class="sr-hero">
            <div class="sr-hero__top">
                <div>
                    <h1 class="sr-hero__title">Centre support</h1>
                    <p class="sr-hero__subtitle">
                        Suivi consolidé des questions, bugs et demandes d’accès.
                        Les demandes d’accès affichent maintenant proprement les permissions demandées, même quand elles ont été stockées dans le message historique.
                    </p>
                </div>

                <div class="btn-group">
                    <a href="<?= e(APP_URL) ?>modules/support/ask_question.php" class="btn btn-secondary">Nouvelle question</a>
                    <a href="<?= e(APP_URL) ?>modules/support/report_bug.php" class="btn btn-warning">Nouveau bug</a>
                    <a href="<?= e(APP_URL) ?>modules/support/request_access.php" class="btn btn-outline">Nouvel accès</a>
                </div>
            </div>
        </section>

        <section class="sr-kpi-grid">
            <div class="sr-kpi">
                <span class="sr-kpi__label">Total demandes</span>
                <span class="sr-kpi__value"><?= (int)$totalRequests ?></span>
                <div class="sr-kpi__meta">Historique complet</div>
            </div>

            <div class="sr-kpi">
                <span class="sr-kpi__label">Ouvertes</span>
                <span class="sr-kpi__value"><?= (int)$totalOpen ?></span>
                <div class="sr-kpi__meta">À traiter</div>
            </div>

            <div class="sr-kpi">
                <span class="sr-kpi__label">En cours</span>
                <span class="sr-kpi__value"><?= (int)$totalInProgress ?></span>
                <div class="sr-kpi__meta">Déjà prises en charge</div>
            </div>

            <div class="sr-kpi">
                <span class="sr-kpi__label">Demandes d’accès</span>
                <span class="sr-kpi__value"><?= (int)$totalAccess ?></span>
                <div class="sr-kpi__meta">Droits et habilitations</div>
            </div>

            <div class="sr-kpi">
                <span class="sr-kpi__label">Questions + bugs</span>
                <span class="sr-kpi__value"><?= (int)($totalQuestions + $totalBugs) ?></span>
                <div class="sr-kpi__meta">Support fonctionnel et technique</div>
            </div>
        </section>

        <div class="sr-list">
            <?php if (!$requests): ?>
                <div class="sr-empty">Aucune demande support enregistrée.</div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $type = (string)($request['request_type'] ?? '');
                    $status = (string)($request['status'] ?? '');
                    $priority = (string)($request['priority'] ?? 'normal');

                    $requestedPermissions = $type === 'access'
                        ? sl_support_extract_requested_permissions($request)
                        : [];

                    $cleanMessage = $type === 'access'
                        ? sl_support_clean_message_body((string)($request['message'] ?? ''))
                        : (string)($request['message'] ?? '');
                    ?>
                    <section class="sr-card">
                        <div class="sr-card__top">
                            <div>
                                <h3 class="sr-card__title"><?= e((string)($request['subject'] ?? 'Sans objet')) ?></h3>
                                <div class="sr-card__meta">
                                    Créé le <?= e((string)($request['created_at'] ?? '')) ?>
                                    <?php if (!empty($request['updated_at'])): ?>
                                        • Mis à jour le <?= e((string)$request['updated_at']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="sr-badges">
                                <span class="status-pill <?= e(sl_support_badge_class_for_type($type)) ?>">
                                    <?= e(sl_support_label_for_type($type)) ?>
                                </span>

                                <span class="status-pill <?= e(sl_support_badge_class_for_priority($priority)) ?>">
                                    <?= e(sl_support_label_for_priority($priority)) ?>
                                </span>

                                <span class="status-pill <?= e(sl_support_badge_class_for_status($status)) ?>">
                                    <?= e(sl_support_label_for_status($status)) ?>
                                </span>
                            </div>
                        </div>

                        <div class="sr-section-grid">
                            <div class="sr-box">
                                <h4>Message</h4>
                                <div class="sr-message">
                                    <?= nl2br(e($cleanMessage !== '' ? $cleanMessage : '—')) ?>
                                </div>
                            </div>

                            <div class="sr-box">
                                <?php if ($type === 'access'): ?>
                                    <h4>Accès demandés</h4>

                                    <?php if ($requestedPermissions): ?>
                                        <div class="sr-access-grid">
                                            <?php foreach ($requestedPermissions as $permission): ?>
                                                <div class="sr-access-pill">
                                                    <div>
                                                        <span class="sr-access-pill__label"><?= e((string)($permission['label'] ?? '')) ?></span>
                                                        <?php if (trim((string)($permission['code'] ?? '')) !== ''): ?>
                                                            <span class="sr-access-pill__code"><?= e((string)$permission['code']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="sr-empty">
                                            Aucun accès structuré détecté pour cette demande.
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <h4>Résumé</h4>
                                    <div class="sl-data-list">
                                        <div class="sl-data-list__row">
                                            <span>Type</span>
                                            <strong><?= e(sl_support_label_for_type($type)) ?></strong>
                                        </div>
                                        <div class="sl-data-list__row">
                                            <span>Statut</span>
                                            <strong><?= e(sl_support_label_for_status($status)) ?></strong>
                                        </div>
                                        <div class="sl-data-list__row">
                                            <span>Priorité</span>
                                            <strong><?= e(sl_support_label_for_priority($priority)) ?></strong>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/document_end.php'; ?>