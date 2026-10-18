<?php

namespace App\Core;

use App\Core\Response;


class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    public function add(string $method, string $path, $handler, array $middleware = [])
    {
        $this->routes[$method][$path] = compact('handler', 'middleware');
    }

    public function get($path, $handler, $middleware = [])
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = [])
    {
        $this->add('POST', $path, $handler, $middleware);
    }


    // Dispatch function
    public function dispatch(Request $request)
{
    $method = $request->method();
    $uri    = rtrim($request->uri(), '/') ?: '/';

    $routes = $this->routes[$method] ?? [];

    foreach ($routes as $path => $route) {
        if ($path === '/') {
            $pattern = '#^/$#';
        } else {
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $path);
            $pattern = '#^' . $pattern . '$#';
        }

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);

            // Run global middleware first
            foreach ($this->globalMiddleware as $middleware) {
                if (is_string($middleware) && str_contains($middleware, ':')) {
                    $parts = explode(':', $middleware);
                    $class = array_shift($parts);
                    App::make($class)->handle($request, ...$parts);
                } else {
                    App::make($middleware)->handle($request);
                }
            }

            // Run route-specific middleware ("Class:arg1:arg2:arg3" passes arguments)
            foreach ($route['middleware'] as $middleware) {
                if (is_string($middleware) && str_contains($middleware, ':')) {
                    $parts = explode(':', $middleware);
                    $class = array_shift($parts);
                    App::make($class)->handle($request, ...$parts);
                } else {
                    App::make($middleware)->handle($request);
                }
            }

            $handler = $route['handler'];

            if (is_array($handler)) {
                [$class, $action] = $handler;
            } else {
                [$class, $action] = explode('@', $handler);
            }

            $controller = App::make($class);

            // -------------------------
            // CAST PARAMETERS
            // -------------------------
            $matches = array_map(function($v) {
                // Cast only realistic ids; long digit strings (64-hex scan
                // tokens that are all digits) must stay intact as strings.
                return ctype_digit($v) && strlen($v) <= 15 ? (int) $v : $v;
            }, $matches);

            return $controller->$action($request, ...$matches);
        }
    }

    Response::abort(404);
}
}