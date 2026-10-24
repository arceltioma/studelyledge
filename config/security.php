<?php

if (!function_exists('clean_input')) {
    /**
     * Normalise une saisie texte. L'échappement se fait à l'affichage via e().
     */
    function clean_input(?string $value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('json_for_script')) {
    /**
     * Encode une valeur pour l'insérer sans risque dans un bloc <script>.
     */
    function json_for_script(mixed $value): string
    {
        return (string)json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['_csrf_token'])
            && is_string($_SESSION['_csrf_token'])
            && $_SESSION['_csrf_token'] !== ''
            && hash_equals($_SESSION['_csrf_token'], (string)$token);
    }
}

if (!function_exists('csrf_enforce_on_post')) {
    /**
     * Refuse toute requête POST sans jeton CSRF valide (champ _csrf_token ou en-tête X-CSRF-Token).
     */
    function csrf_enforce_on_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (is_string($token) && verify_csrf_token($token)) {
            return;
        }

        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $tooLarge = $contentLength > 0 && empty($_POST) && empty($_FILES);

        $message = $tooLarge
            ? 'Le fichier ou le formulaire envoyé dépasse la taille maximale autorisée par le serveur.'
            : 'Votre session a expiré ou le formulaire n’est plus valide. Merci de recharger la page et de recommencer.';

        $back = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $appUrl = defined('APP_URL') ? APP_URL : '/';
        if ($back === '' || !str_starts_with($back, $appUrl)) {
            $back = $appUrl;
        }

        http_response_code($tooLarge ? 413 : 403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Requête refusée</title></head>'
            . '<body style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:0 16px;">'
            . '<h2>Requête refusée</h2><p>' . e($message) . '</p>'
            . '<p><a href="' . e($back) . '">Revenir à la page précédente</a></p></body></html>';
        exit;
    }
}

if (!function_exists('csrf_inject_into_forms')) {
    /**
     * Callback de tampon de sortie : ajoute le jeton CSRF à chaque formulaire POST d'une page HTML.
     */
    function csrf_inject_into_forms(string $buffer): string
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0 && stripos($header, 'text/html') === false) {
                return $buffer;
            }
        }

        if (stripos($buffer, '<form') === false || empty($_SESSION['_csrf_token'])) {
            return $buffer;
        }

        $field = '<input type="hidden" name="_csrf_token" value="'
            . htmlspecialchars((string)$_SESSION['_csrf_token'], ENT_QUOTES, 'UTF-8') . '">';

        $result = preg_replace_callback(
            '/(<form\b[^>]*>)(.*?)(<\/form>)/is',
            static function (array $m) use ($field): string {
                if (!preg_match('/\bmethod\s*=\s*["\']?post\b/i', $m[1])
                    || stripos($m[2], 'name="_csrf_token"') !== false) {
                    return $m[0];
                }

                return $m[1] . $field . $m[2] . $m[3];
            },
            $buffer
        );

        if ($result === null) {
            // Limite PCRE atteinte sur une très grosse page : injection simple après chaque balise <form method="post">
            $result = preg_replace_callback(
                '/<form\b[^>]*>/i',
                static fn(array $m): string => preg_match('/\bmethod\s*=\s*["\']?post\b/i', $m[0]) ? $m[0] . $field : $m[0],
                $buffer
            );
        }

        return $result ?? $buffer;
    }
}

if (!function_exists('safe_redirect_target')) {
    /**
     * Ne garde une URL de redirection que si elle reste dans l'application.
     */
    function safe_redirect_target(?string $url, string $fallback): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return $fallback;
        }

        $appUrl = defined('APP_URL') ? APP_URL : '/';
        $appPath = (string)(parse_url($appUrl, PHP_URL_PATH) ?: '/');

        if (str_starts_with($url, $appUrl)) {
            return $url;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')
            && str_starts_with($url, $appPath)) {
            return $url;
        }

        return $fallback;
    }
}
