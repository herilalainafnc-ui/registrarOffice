<?php
/**
 * helpers.php - Fonctions utilitaires globales pour le framework MVC
 */

if (!function_exists('url')) {
    /**
     * Générer une URL complète à partir d'un chemin relatif
     * @param string $path Le chemin (ex: '/login', '/dashboard')
     * @return string L'URL complète avec le chemin de base
     */
    function url($path = '') {
        $base = defined('APP_BASE') ? APP_BASE : '';
        if ($path === '' || $path === '/') {
            return $base ?: '/';
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Générer l'URL d'un asset statique
     * @param string $path Chemin vers l'asset
     * @return string URL complète
     */
    function asset($path) {
        return url($path);
    }
}

if (!function_exists('currentPath')) {
    /**
     * Obtenir le chemin de la requête actuelle (sans le chemin de base)
     * @return string Le chemin relatif actuel
     */
    function currentPath() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base = defined('APP_BASE') ? APP_BASE : '';
        if ($base && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        return '/' . ltrim($uri, '/');
    }
}

if (!function_exists('isCurrentRoute')) {
    /**
     * Vérifier si le chemin actuel correspond à un chemin donné
     * @param string $path Le chemin à vérifier
     * @return bool
     */
    function isCurrentRoute($path) {
        $current = currentPath();
        return $current === $path || $current === $path . '/';
    }
}

if (!function_exists('redirect')) {
    /**
     * Rediriger vers une URL
     * @param string $url URL de destination
     * @param int $code Code HTTP (302 par défaut)
     */
    function redirect($url, $code = 302) {
        header("Location: $url", true, $code);
        exit;
    }
}

if (!function_exists('redirectTo')) {
    /**
     * Rediriger vers une route de l'application
     * @param string $path Chemin de la route (ex: '/login')
     * @param int $code Code HTTP
     */
    function redirectTo($path, $code = 302) {
        redirect(url($path), $code);
    }
}

if (!function_exists('isRoute')) {
    /**
     * Vérifier si la route actuelle correspond à un ou plusieurs chemins
     * @param string|array $paths Chemin(s) à vérifier
     * @return bool
     */
    function isRoute($paths) {
        $current = currentPath();
        if (is_array($paths)) {
            foreach ($paths as $path) {
                if ($current === $path || $current === $path . '/' || strpos($current, $path . '?') === 0) {
                    return true;
                }
            }
            return false;
        }
        return $current === $paths || $current === $paths . '/' || strpos($current, $paths . '?') === 0;
    }
}
