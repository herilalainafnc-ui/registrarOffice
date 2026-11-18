<?php
/**
 * Routeur pour le serveur interne de PHP (php -S), utilise UNIQUEMENT par la
 * version desktop (Electron). Apache n'est pas present dans l'app desktop ;
 * ce fichier rejoue donc les regles de .htaccess dont l'application depend.
 *
 * Il n'a aucun effet en production web : Apache lit .htaccess, pas ce fichier.
 *
 * Lancement (voir desktop/main.js) :
 *     php -S 127.0.0.1:<port> -t <racine> router.php
 *
 * Regles reproduites depuis .htaccess :
 *   - PAS de redirection HTTP->HTTPS : le desktop sert en http sur 127.0.0.1,
 *     que Chromium traite deja comme "contexte securise" (la geolocalisation
 *     et les autres API sensibles fonctionnent donc sans TLS).
 *   - fichiers statiques existants -> servis tels quels ;
 *   - /chemin correspondant a /chemin.php existant -> on execute le .php
 *     (compatibilite arriere) ;
 *   - tout le reste -> index.php (front controller MVC).
 *
 * Subtilite importante : sous Apache, un script execute a son repertoire pour
 * dossier de travail, ce dont dependent les require() relatifs du type
 * require('backdb.php'). php -S garde lui la racine comme dossier de travail.
 * On fait donc chdir() vers le dossier du script avant de l'inclure, pour
 * reproduire fidelement le comportement d'Apache.
 */

/* Hors de php -S, ce fichier n'a rien a faire. Le serveur web de production
   l'executait pourtant a l'adresse /router.php : il s'incluait alors lui-meme
   sans fin, et chaque appel bloquait un processus PHP plusieurs secondes,
   jusqu'a epuiser sa memoire (constate le 26/10/2026). */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$racine = __DIR__;
$uri    = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Empeche toute remontee hors de la racine (../ dans l'URL).
$cible = realpath($racine . $uri);
$dansRacine = ($cible !== false && strpos($cible, realpath($racine)) === 0);
// /router.php lui-meme : ni execute ni servi, il passe au front controller.
if ($cible !== false && $cible === realpath(__FILE__)) {
    $dansRacine = false;
}

// 1. Fichier reel existant dans la racine.
if ($uri !== '/' && $dansRacine && is_file($cible)) {
    if (strtolower(substr($cible, -4)) === '.php') {
        // Acces direct a un .php : on l'execute nous-memes, avec le bon CWD.
        chdir(dirname($cible));
        require $cible;
        return true;
    }
    // Actif statique (css, js, image, police...) : laisser php -S le servir.
    return false;
}

// 2. Compatibilite arriere : /chemin -> /chemin.php s'il existe.
if ($uri !== '/') {
    $php = realpath($racine . $uri . '.php');
    if ($php !== false && strpos($php, realpath($racine)) === 0 && is_file($php)) {
        chdir(dirname($php));
        require $php;
        return true;
    }
}

// 3. Front controller : tout le reste passe par index.php, a la racine.
chdir($racine);
require $racine . '/index.php';
return true;
