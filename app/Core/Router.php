<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur de l'application.
 * Gère l'enregistrement et la résolution des routes HTTP.
 */
class Router
{
    private array $routes = [];

    /**
     * Enregistre une route GET.
     */
    public function get(string $path, string $controller, string $action): self
    {
        $this->addRoute('GET', $path, $controller, $action);
        return $this;
    }

    /**
     * Enregistre une route POST.
     */
    public function post(string $path, string $controller, string $action): self
    {
        $this->addRoute('POST', $path, $controller, $action);
        return $this;
    }

    /**
     * Enregistre une route PUT.
     */
    public function put(string $path, string $controller, string $action): self
    {
        $this->addRoute('PUT', $path, $controller, $action);
        return $this;
    }

    /**
     * Enregistre une route DELETE.
     */
    public function delete(string $path, string $controller, string $action): self
    {
        $this->addRoute('DELETE', $path, $controller, $action);
        return $this;
    }

    /**
     * Ajoute une route au registre.
     */
    private function addRoute(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'controller' => $controller,
            'action'     => $action,
        ];
    }

    /**
     * Dispatche la requête HTTP vers le bon contrôleur.
     */
    public function dispatch(string $method, string $uri): void
    {
        // Nettoyer l'URI
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Convertir le pattern de route en regex
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                // Extraire les paramètres nommés
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $controllerClass = $route['controller'];
                $action = $route['action'];

                if (!class_exists($controllerClass)) {
                    $this->sendError(500, "Contrôleur introuvable : {$controllerClass}");
                    return;
                }

                $controllerInstance = new $controllerClass();

                if (!method_exists($controllerInstance, $action)) {
                    $this->sendError(500, "Action introuvable : {$action}");
                    return;
                }

                // Utiliser la réflexion pour mapper les paramètres
                $ref = new \ReflectionMethod($controllerClass, $action);
                $args = [];
                foreach ($ref->getParameters() as $param) {
                    $name = $param->getName();
                    if (isset($params[$name])) {
                        $args[] = $params[$name];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        $args[] = null;
                    }
                }

                call_user_func_array([$controllerInstance, $action], $args);
                return;
            }
        }

        // Aucune route trouvée
        $this->sendError(404, "Page introuvable");
    }

    /**
     * Affiche une page d'erreur.
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        $title = $code === 404 ? 'Page introuvable' : 'Erreur serveur';
        require __DIR__ . '/../Views/errors/error.php';
    }
}
