<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function any(string $path, callable $handler): void
    {
        $this->routes['GET'][$path]  = $handler;
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = strtok($_SERVER['REQUEST_URI'], '?');
        // Strip base path
        $uri    = BASE_PATH !== ''
            ? (preg_replace('#^' . preg_quote(BASE_PATH, '#') . '#', '', $uri) ?: '/')
            : ($uri ?: '/');
            
        // Ignore trailing slashes for routing (e.g. /tracking/ matches /tracking)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        $handler = $this->routes[$method][$uri] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $handler();
    }
}
