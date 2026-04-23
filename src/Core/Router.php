<?php

namespace App\Core;

use App\Core\Response;


class Router
{
    private array $routes = [];

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

            // Run middleware
            foreach ($route['middleware'] as $middleware) {
                App::make($middleware)->handle($request);
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
                return ctype_digit($v) ? (int) $v : $v;
            }, $matches);

            return $controller->$action($request, ...$matches);
        }
    }

    Response::abort(404);
}
}