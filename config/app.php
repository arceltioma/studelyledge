<?php

/*
|--------------------------------------------------------------------------
| Surcharge locale (non versionnée)
|--------------------------------------------------------------------------
| Copier config/local.example.php en config/local.php pour définir
| APP_ENV, APP_URL, APP_DEBUG, DB_* propres à chaque environnement.
|--------------------------------------------------------------------------
*/
$slLocalConfig = __DIR__ . '/local.php';
if (is_file($slLocalConfig)) {
    require_once $slLocalConfig;
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'StudelyLedger');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.1.0');
}

/*
|--------------------------------------------------------------------------
| APP_URL
|--------------------------------------------------------------------------
| URL racine du projet sous XAMPP
| Exemple : http://localhost/StudelyLedge/
|--------------------------------------------------------------------------
*/
if (!defined('APP_URL')) {
    define('APP_URL', getenv('APP_URL') ?: 'http://localhost/StudelyLedge/');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'local');
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', APP_ENV === 'local');
}

/*
| Durée d'inactivité maximale d'une session connectée (secondes)
*/
if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', 7200);
}

date_default_timezone_set('Europe/Paris');

/*
|--------------------------------------------------------------------------
| Debug PHP
|--------------------------------------------------------------------------
| En production, les erreurs ne sont jamais affichées mais journalisées
| dans logs/php-error.log (dossier inaccessible depuis le web).
|--------------------------------------------------------------------------
*/
error_reporting(E_ALL);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');

    $slLogDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
    if (is_dir($slLogDir) || @mkdir($slLogDir, 0775, true)) {
        ini_set('error_log', $slLogDir . DIRECTORY_SEPARATOR . 'php-error.log');
    }
}

require_once __DIR__ . '/security.php';

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $slIsHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    $slCookiePath = (string)(parse_url(APP_URL, PHP_URL_PATH) ?: '/');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('STUDELYSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $slCookiePath,
        'secure' => $slIsHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Helpers globaux
|--------------------------------------------------------------------------
*/
if (!function_exists('app_asset')) {
    function app_asset(string $path): string
    {
        return APP_URL . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = dirname(__DIR__);
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = app_path('uploads');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

if (!function_exists('models_path')) {
    function models_path(string $path = ''): string
    {
        $base = app_path('modeles');
        return $path !== '' ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}

if (!function_exists('app_current_script')) {
    /**
     * Chemin du script exécuté, relatif à la racine du projet (ex: modules/clients/clients_list.php).
     */
    function app_current_script(): string
    {
        $script = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($script === false) {
            return '';
        }

        $script = str_replace('\\', '/', $script);
        $root = str_replace('\\', '/', dirname(__DIR__));

        return str_starts_with($script, $root . '/') ? substr($script, strlen($root) + 1) : '';
    }
}

/*
|--------------------------------------------------------------------------
| Protections HTTP globales (hors CLI)
|--------------------------------------------------------------------------
*/
if (PHP_SAPI !== 'cli' && !defined('SL_HTTP_GUARDS_APPLIED')) {
    define('SL_HTTP_GUARDS_APPLIED', true);

    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    }

    $slCurrentScript = app_current_script();
    $slIsLoggedIn = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

    // Expiration de session après inactivité
    if ($slIsLoggedIn) {
        $slLastActivity = (int)($_SESSION['last_activity'] ?? 0);

        if ($slLastActivity > 0 && (time() - $slLastActivity) > SESSION_IDLE_TIMEOUT) {
            $_SESSION = [];
            session_regenerate_id(true);
            $slIsLoggedIn = false;
            $_SESSION['session_expired'] = 1;
        } else {
            $_SESSION['last_activity'] = time();
        }
    }

    // Authentification obligatoire pour tout le back-office (refus par défaut)
    if (!$slIsLoggedIn && str_starts_with($slCurrentScript, 'modules/')) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_SERVER['REQUEST_URI'])) {
            $_SESSION['intended_url'] = (string)$_SERVER['REQUEST_URI'];
        }

        header('Location: ' . APP_URL . 'login.php');
        exit;
    }

    // Jeton CSRF disponible dès le démarrage, puis vérifié sur toute requête POST
    csrf_token();
    csrf_enforce_on_post();

    // Ajout automatique du jeton CSRF dans tous les formulaires POST rendus en HTML
    ob_start('csrf_inject_into_forms');
}

/*
|--------------------------------------------------------------------------
| Préparation minimale de l'arborescence utile
|--------------------------------------------------------------------------
*/
$requiredDirectories = [
    app_path('uploads'),
    app_path('uploads/imports'),
    app_path('uploads/exports'),
    app_path('modeles'),
];

foreach ($requiredDirectories as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
}
