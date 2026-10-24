<?php
/*
|--------------------------------------------------------------------------
| Configuration locale - modèle
|--------------------------------------------------------------------------
| Copier ce fichier en config/local.php (non versionné) et l'adapter.
| Toute constante non définie ici garde sa valeur par défaut
| (voir config/app.php et config/database.php).
|--------------------------------------------------------------------------
*/

// Environnement : 'local' (erreurs affichées) ou 'production' (erreurs journalisées dans logs/)
define('APP_ENV', 'local');
// define('APP_DEBUG', false);

// URL racine de l'application, avec la barre oblique finale
define('APP_URL', 'http://localhost/StudelyLedge/');

// Déconnexion automatique après inactivité (secondes)
// define('SESSION_IDLE_TIMEOUT', 7200);

// Base de données
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'studelyledge');
define('DB_USER', 'root');
define('DB_PASS', '');
