<?php

namespace App\Core;

class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

   public function uri(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove index.php if present
    if ($uri === '/index.php') {
        $uri = '/';
    }

    // Remove script directory if app is in subfolder
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);

    if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
        $uri = substr($uri, strlen($scriptName));
    }

    return rtrim($uri, '/') ?: '/';
}

    public function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function header(string $key)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? null;
    }

}
