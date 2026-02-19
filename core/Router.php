<?php
/**
 * Router - Routeur PHP simple pour l'architecture MVC
 * Gère la correspondance entre les URLs et les contrôleurs
 */
class Router {
    private $routes = [];
    private $notFoundCallback;

    /**
     * Enregistrer une route GET
     */
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Enregistrer une route POST
     */
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Enregistrer une route pour GET et POST
     */
    public function any($path, $handler) {
        $this->addRoute('GET', $path, $handler);
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Ajouter une route avec conversion en regex
     */
    private function addRoute($method, $path, $handler) {
        // Convertir les paramètres {param} en groupes regex nommés
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'path'    => $path,
            'handler' => $handler
        ];
    }

    /**
     * Définir le callback pour les routes non trouvées (404)
     */
    public function notFound($callback) {
        $this->notFoundCallback = $callback;
    }

    /**
     * Dispatcher la requête vers le bon contrôleur
     */
    public function dispatch($uri, $method) {
        // Supprimer la query string
        $uri = parse_url($uri, PHP_URL_PATH);

        // Supprimer le chemin de base de l'application
        $basePath = defined('APP_BASE') ? APP_BASE : '';
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        // Assurer le slash initial
        $uri = '/' . ltrim($uri, '/');

        // Supprimer le slash final (sauf pour la racine)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        // Chercher une correspondance parmi les routes
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $this->callHandler($route['handler'], $params);
            }
        }

        // Aucune route trouvée → 404
        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        } else {
            http_response_code(404);
            echo '404 - Page non trouvée';
        }
    }

    /**
     * Appeler le handler d'une route (Controller@method ou callable)
     */
    private function callHandler($handler, $params = []) {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerName, $method] = explode('@', $handler);
            $controllerFile = ROOT_DIR . '/controllers/' . $controllerName . '.php';

            if (!file_exists($controllerFile)) {
                throw new \Exception("Fichier contrôleur introuvable: $controllerFile");
            }

            require_once $controllerFile;
            $controller = new $controllerName();
            return call_user_func_array([$controller, $method], $params);
        }

        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        throw new \Exception("Handler de route invalide");
    }
}
