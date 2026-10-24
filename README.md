# StudelyLedger

Back-office de pilotage financier : clients et comptes 411, trésorerie 512, comptes de service 706,
opérations et règles comptables, imports, mensualités, débits dus, relevés PDF, journal d'audit,
notifications et gestion fine des droits par rôle.

PHP 8.2 · MariaDB/MySQL · Apache (XAMPP) · dompdf.

## Installation (XAMPP)

1. Placer le projet dans `C:\xampp\htdocs\StudelyLedge`.
2. Installer les dépendances : `composer install` (le dossier `vendor/` n'est pas versionné).
3. Créer la base `studelyledge` (interclassement `utf8mb4_unicode_ci`) puis importer, dans l'ordre :
   - `database/schema.sql` : structure complète ;
   - `database/seed_reference.sql` : rôles, permissions, statuts, devises, catalogues ;
   - `database/migrations/*.sql` : migrations postérieures (idempotentes).
4. Créer le premier compte super administrateur (le mot de passe est demandé au clavier) :
   `php tools/create_admin.php mon_identifiant`
5. Ouvrir `http://localhost/StudelyLedge/`.

## Configuration

Les valeurs par défaut conviennent à XAMPP. Pour un autre environnement, copier
`config/local.example.php` en `config/local.php` (non versionné) et y définir `APP_ENV`, `APP_URL`
et les accès `DB_*`. Les variables d'environnement du même nom sont aussi prises en compte.

En production : `APP_ENV = 'production'`, HTTPS obligatoire. Les erreurs ne sont alors plus
affichées mais journalisées dans `logs/php-error.log`.

## Sécurité

- **Authentification obligatoire** sur tout `modules/` (refus par défaut, appliqué dès `config/app.php`).
- **Droits** : chaque page est associée à une permission (`includes/permission_middleware.php`),
  résolue par `studelyAccessMap()` dans `includes/admin_functions.php`. Le compte est revalidé à
  chaque requête : désactivation ou changement de rôle pris en compte immédiatement.
- **Anti-escalade** : seul le super administrateur (rôle id 1) peut attribuer ce rôle ou modifier
  un compte super administrateur. Mots de passe : 10 caractères minimum, lettres et chiffres.
- **CSRF** : toute requête POST est vérifiée globalement ; le jeton est ajouté automatiquement
  aux formulaires POST. Les actions de suppression/archivage passent uniquement par POST.
- **Sessions** : cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, mode strict,
  régénération à la connexion, expiration après 2 h d'inactivité.
- **Connexion** : 5 échecs par identifiant (ou 20 par IP) en 15 min bloquent temporairement
  (`login_attempts`) ; anciens mots de passe en clair re-hachés automatiquement.
- **Fichiers** : `exports/`, `uploads/`, `logs/`, `database/`, `vendor/`, `config/`, `includes/`
  et `.git` sont inaccessibles depuis le web. Les PDF générés sont servis par
  `modules/statements/export_file.php` après contrôle d'accès.
- En-têtes HTTP de sécurité (X-Frame-Options, nosniff, Referrer-Policy, CSP frame-ancestors).

## Structure

| Dossier | Rôle |
|---|---|
| `config/` | Bootstrap (`app.php`), base de données, fonctions de sécurité |
| `includes/` | Fonctions métier, moteurs (règles, anomalies, débits dus), middleware, gabarits |
| `modules/` | Pages du back-office, par domaine |
| `pages/` | Pages publiques (contact, mentions, plan du site) |
| `assets/` | CSS, JS (Chart.js embarqué), images |
| `modeles/` | Modèles CSV d'import |
| `database/` | Schéma, données de référence, migrations (`database/local/` : sauvegardes non versionnées) |
| `tools/` | Scripts en ligne de commande |

## Évolutions de schéma

Ajouter un fichier `database/migrations/AAAA_MM_JJ_NN_description.sql` idempotent
(`CREATE TABLE IF NOT EXISTS`, …) qui enregistre sa version dans `sl_schema_versions`,
puis régénérer `database/schema.sql`.
