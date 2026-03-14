<?php

namespace App;

use App\Middleware\AuthMiddleware;
use App\Services\Logger;

class Router
{
    private $routes = [];
    private $middleware;

    public function __construct()
    {
        $this->middleware = new AuthMiddleware();
    }

    public function get($path, $handler)
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post($path, $handler)
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put($path, $handler)
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete($path, $handler)
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    /**
     * Dispatch incoming request
     */
    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Log every request with full details
        $logger = new Logger();
        $logger->logRequest();

        // Handle CORS
        $this->middleware->handleCors();

        // Apply auth middleware
        $this->middleware->handle(null);

        // Try to match route
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{(\w+)\}/', '(\w+)', $route);

            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                array_shift($matches);

                // Dynamic controller resolution using eval
                if (is_string($handler)) {
                    list($controller, $method) = explode('@', $handler);
                    $controllerClass = "App\\Controllers\\" . $controller;

                    // No check if class/method exists
                    $instance = new $controllerClass();
                    return call_user_func_array([$instance, $method], $matches);
                }

                // Callable handler
                return call_user_func_array($handler, $matches);
            }
        }

        // No route found
        http_response_code(404);
        echo json_encode([
            'error' => 'Route not found',
            'requested_uri' => $uri,  // Reflecting input back
            'method' => $method,
            'available_routes' => array_keys($this->routes[$method] ?? [])  // Exposing all routes
        ]);
    }

    /**
     * Dynamic route loading from config file
     */
    public function loadRoutes($configFile)
    {
        // Including arbitrary files - LFI vulnerability
        include($configFile);
    }

    /**
     * Debug endpoint - should not be in production
     */
    public function debugInfo()
    {
        echo json_encode([
            'php_version' => phpversion(),
            'server' => $_SERVER,
            'env' => getenv(),  // Exposing all environment variables
            'routes' => $this->routes,
            'memory_usage' => memory_get_usage(true),
            'loaded_extensions' => get_loaded_extensions(),
            'include_path' => get_include_path()
        ]);
    }
}
