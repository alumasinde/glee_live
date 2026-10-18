<?php

use App\Core\App;
use App\Core\Container;
use App\Core\DB;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use Dotenv\Dotenv;

function base_path($path = '')
{
    return dirname(__DIR__) . ($path ? '/' . $path : '');
}

require base_path('vendor/autoload.php');

$envFile = '.env';

$dotenv = Dotenv::createImmutable(base_path(), $envFile);
$dotenv->load();

/**
 * Read an environment value.
 * Dotenv returns everything as strings, so "false" would be truthy in PHP.
 * Cast the common literals to real booleans/null.
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

    if ($value === null) {
        return $default;
    }

    if (!is_string($value)) {
        return $value;
    }

    return match (strtolower($value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        'empty', '(empty)' => '',
        default            => $value,
    };
}

if (env('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set(config('app.timezone') ?? 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Strict'
    ]);

    // Use Redis for sessions only if enabled AND Redis is actually reachable.
    // Otherwise fall back to PHP's default file sessions.
    if (env('REDIS_SESSION_ENABLED', false) && \App\Core\Redis::connect() !== null) {
        $sessionHandler = new \App\Core\RedisSessionHandler(
            (int) env('SESSION_LIFETIME', 120) * 60, // Convert minutes to seconds
            (string) env('REDIS_SESSION_PREFIX', 'session:')
        );
        session_set_save_handler($sessionHandler, true);
    }

    session_start();
}

$container = new Container();
App::setContainer($container);

$container->singleton(DB::class, function () {
    return DB::connect();
});

$container->bind(Request::class, function () {
    return new Request();
});

$container->singleton(Router::class, function () {
    return new Router();
});

set_exception_handler(function ($e) {

    error_log('[Uncaught] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());

    $code = $e->getCode();

    if (!is_int($code) || $code < 400 || $code > 599) {
        $code = 500;
    }

    if (env('APP_DEBUG', false)) {
        http_response_code($code);
        echo "<pre>";
        echo "Error: " . $e->getMessage() . "\n\n";
        echo $e->getTraceAsString();
        echo "</pre>";
        exit;
    }

    Response::abort($code, 'Server Error');
});

function config(string $key): mixed
{
    static $cache = [];

    $parts = explode('.', $key);
    $file  = array_shift($parts);

    if (!isset($cache[$file])) {
        $path = base_path("config/{$file}.php");
        if (!file_exists($path)) {
            return null;
        }
        $cache[$file] = require $path;
    }

    $config = $cache[$file];

    foreach ($parts as $part) {
        if (!isset($config[$part])) {
            return null;
        }
        $config = $config[$part];
    }

    return $config;
}

/**
 * Permission check for views and services (cached per request).
 */
function can(string $permission): bool
{
    return (new \App\Core\Permission(\App\Core\DB::connect()))->can($permission);
}

/**
 * Display dates in lists: "17 Oct 2026, 20:56" (or "—" when empty).
 */
function format_date(?string $value, bool $withTime = true): string
{
    if ($value === null || trim($value) === '' || str_starts_with($value, '0000')) {
        return '—';
    }

    $ts = strtotime($value);

    if ($ts === false) {
        return $value;
    }

    return date($withTime ? 'j M Y, H:i' : 'j M Y', $ts);
}

return $container;