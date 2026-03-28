<?php
$dbStatus = 'Inconnu';
$connectedUsersCount = '—';
$openSupportCount = '—';
$importsInProgressCount = '—';

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $dbStatus = 'Connectée';

        if (
            function_exists('tableExists') &&
            function_exists('columnExists') &&
            tableExists($pdo, 'users')
        ) {
            if (columnExists($pdo, 'users', 'last_login_at') && columnExists($pdo, 'users', 'is_active')) {
                $stmtConnected = $pdo->query("
                    SELECT COUNT(*)
                    FROM users
                    WHERE is_active = 1
                      AND last_login_at IS NOT NULL
                      AND last_login_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ");
                $connectedUsersCount = (int)$stmtConnected->fetchColumn();
            } elseif (columnExists($pdo, 'users', 'is_active')) {
                $stmtConnected = $pdo->query("
                    SELECT COUNT(*)
                    FROM users
                    WHERE is_active = 1
                ");
                $connectedUsersCount = (int)$stmtConnected->fetchColumn();
            } else {
                $stmtConnected = $pdo->query("SELECT COUNT(*) FROM users");
                $connectedUsersCount = (int)$stmtConnected->fetchColumn();
            }
        }

        if (function_exists('tableExists') && tableExists($pdo, 'support_requests')) {
            if (function_exists('columnExists') && columnExists($pdo, 'support_requests', 'status')) {
                $stmtSupport = $pdo->query("
                    SELECT COUNT(*)
                    FROM support_requests
                    WHERE status IN ('open', 'in_progress')
                ");
                $openSupportCount = (int)$stmtSupport->fetchColumn();
            } else {
                $stmtSupport = $pdo->query("SELECT COUNT(*) FROM support_requests");
                $openSupportCount = (int)$stmtSupport->fetchColumn();
            }
        } else {
            $openSupportCount = 0;
        }

        if (function_exists('tableExists') && tableExists($pdo, 'import_batches')) {
            $stmtImports = $pdo->query("
                SELECT COUNT(*)
                FROM import_batches
                WHERE status IN ('processing', 'pending')
            ");
            $importsInProgressCount = (int)$stmtImports->fetchColumn();
        } elseif (function_exists('tableExists') && tableExists($pdo, 'imports')) {
            $stmtImports = $pdo->query("
                SELECT COUNT(*)
                FROM imports
                WHERE status IN ('processing', 'pending')
            ");
            $importsInProgressCount = (int)$stmtImports->fetchColumn();
        } else {
            $importsInProgressCount = 0;
        }
    }
} catch (Throwable $e) {
    $dbStatus = 'Erreur';
}
?>

<footer class="app-footer rich-footer">

    <div class="footer-top">
        <div class="footer-left">
            <strong><?= e(APP_NAME) ?></strong>
            <span class="footer-separator">•</span>
            <span>Plateforme de pilotage financier</span>
        </div>

        <div class="footer-center">
            <span>© <?= date('Y') ?></span>
            <span class="footer-separator">•</span>
            <span>Version <?= defined('APP_VERSION') ? e(APP_VERSION) : '1.0' ?></span>
        </div>

        <div class="footer-right">
            <span class="footer-user">
                Connecté : <strong><?= e($_SESSION['username'] ?? 'Utilisateur') ?></strong>
            </span>
        </div>
    </div>

    <div class="system-status-bar">
        <div class="status-item">
            <span class="status-label">Utilisateurs connectés</span>
            <span class="status-value"><?= e((string)$connectedUsersCount) ?></span>
        </div>

        <div class="status-item">
            <span class="status-label">Support en attente</span>
            <span class="status-value"><?= e((string)$openSupportCount) ?></span>
        </div>

        <div class="status-item">
            <span class="status-label">Imports en cours</span>
            <span class="status-value"><?= e((string)$importsInProgressCount) ?></span>
        </div>

        <div class="status-item">
            <span class="status-label">Base de données</span>
            <span class="status-value <?= $dbStatus === 'Connectée' ? 'status-ok' : ($dbStatus === 'Erreur' ? 'status-ko' : '') ?>">
                <?= e($dbStatus) ?>
            </span>
        </div>
    </div>

    <div class="footer-links">
        <a href="<?= e(APP_URL) ?>pages/sitemap.php" class="btn btn-outline">Sitemap</a>
        <a href="<?= e(APP_URL) ?>pages/copyright.php" class="btn btn-secondary">Copyright</a>
        <a href="<?= e(APP_URL) ?>pages/contact.php" class="btn btn-primary">Contacts</a>
    </div>

</footer>

<div id="cookieBanner" class="cookie-banner" style="display:none;">
    <div class="cookie-banner-content">
        <div>
            <strong>Cookies</strong>
            <p class="muted" style="margin:6px 0 0;">
                Ce site utilise des cookies essentiels et, si tu l’acceptes, des cookies de confort pour améliorer l’expérience.
            </p>
        </div>
        <div class="btn-group" style="margin-top:0;">
            <button id="acceptCookies" class="btn btn-success" type="button">Accepter</button>
            <button id="rejectCookies" class="btn btn-danger" type="button">Refuser</button>
            <a href="<?= e(APP_URL) ?>pages/cookies_policy.php" class="btn btn-outline">En savoir plus</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const banner = document.getElementById('cookieBanner');
    const acceptBtn = document.getElementById('acceptCookies');
    const rejectBtn = document.getElementById('rejectCookies');

    const consent = localStorage.getItem('studelyledger_cookie_consent');

    if (!consent && banner) {
        banner.style.display = 'block';
    }

    if (acceptBtn) {
        acceptBtn.addEventListener('click', function () {
            localStorage.setItem('studelyledger_cookie_consent', 'accepted');
            document.cookie = "studelyledger_cookie_consent=accepted; path=/; max-age=" + (60 * 60 * 24 * 180);
            if (banner) banner.style.display = 'none';
        });
    }

    if (rejectBtn) {
        rejectBtn.addEventListener('click', function () {
            localStorage.setItem('studelyledger_cookie_consent', 'rejected');
            document.cookie = "studelyledger_cookie_consent=rejected; path=/; max-age=" + (60 * 60 * 24 * 180);
            if (banner) banner.style.display = 'none';
        });
    }
});
</script>