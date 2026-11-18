<?php
/**
 * ============================================================================
 * FRONT CONTROLLER - Point d'entrée unique de l'application MVC
 * ============================================================================
 * 
 * Infinit Registrar - Université Adventiste Zurcher
 * 
 * Toutes les requêtes HTTP sont routées ici par le .htaccess
 * Le Router dispatche vers le bon Controller qui charge la Vue appropriée
 * 
 * Architecture:
 *   core/         → Router, helpers (framework MVC)
 *   controllers/  → Contrôleurs (logique de routage)
 *   src/          → Vues principales de l'application
 *   landing/      → Vues publiques (landing page)
 *   inscription/  → Vues d'inscription
 *   data/         → Modèles, config, middleware (couche données)
 *   init/         → Composants de layout (head, topbar, menubar, footer)
 *   app/          → Actions CRUD, uploads, fichiers
 *   framework/    → Assets CSS/JS (Bootstrap, etc.)
 *   file/         → Fichiers statiques (logos, images)
 */

// ============================================================================
// INITIALISATION
// ============================================================================

// Répertoire racine de l'application
define('ROOT_DIR', __DIR__);
// Heure de Madagascar, avant toute date calculee (voir data/fuseau.php).
require_once ROOT_DIR . '/data/fuseau.php';
// Erreurs : toutes journalisées ; affichées dans la page seulement en local
// (registrar.test, localhost). En production, un avertissement PHP affiché
// dévoile des chemins du serveur et casse la mise en page.
$_hote = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$_en_local = $_hote === 'localhost' || $_hote === '127.0.0.1' || str_ends_with($_hote, '.test');
ini_set('display_errors', $_en_local ? '1' : '0');
ini_set('display_startup_errors', $_en_local ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
unset($_hote);

// Calculer le chemin de base (ex: "/a.registrar" en local, "" en production)
$_doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_app_root = rtrim(str_replace('\\', '/', __DIR__), '/');
define('APP_BASE', substr($_app_root, strlen($_doc_root)));

// ============================================================================
// CHARGEMENT DU FRAMEWORK MVC
// ============================================================================

require_once ROOT_DIR . '/core/helpers.php';
require_once ROOT_DIR . '/core/Router.php';
require_once ROOT_DIR . '/controllers/BaseController.php';

// ============================================================================
// ROUTAGE
// ============================================================================

// Créer le routeur
$router = new Router();

// Charger les définitions de routes
require_once ROOT_DIR . '/routes.php';

// Dispatcher la requête vers le bon contrôleur
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

