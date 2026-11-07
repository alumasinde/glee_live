<?php

use App\Core\App;
use App\Core\Container;
use App\Core\DB;
use App\Core\Env;
use App\Core\Permission;
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
 * The casting rules live in App\Core\Env so the CLI scripts in bin/ cannot
 * drift from the web application (they used to read the raw string, making
 * {"false"} truthy).
 */
function env(string $key, $default = null)
{
    return Env::get($key, $default);
}

// Always record errors, including warnings that error_reporting(0) used to
// discard silently; only their display depends on the environment.
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (env('APP_DEBUG', false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

date_default_timezone_set(config('app.timezone') ?? 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    // isset($_SERVER['HTTPS']) is false behind every TLS-terminating proxy and
    // true for the literal value "off", so derive the scheme instead.
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $https || (bool) env('SESSION_SECURE', false),
        'samesite' => 'Strict',
        'path'     => '/',
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

function config(string $key, mixed $default = null): mixed
{
    static $cache = [];

    $parts = explode('.', $key);
    $file  = array_shift($parts);

    if (!isset($cache[$file])) {
        $path = base_path("config/{$file}.php");
        if (!file_exists($path)) {
            return $default;
        }
        $cache[$file] = require $path;
    }

    $config = $cache[$file];

    foreach ($parts as $part) {
        // array_key_exists, not isset: a configured null/false/0 is a value,
        // not a missing key.
        if (!is_array($config) || !array_key_exists($part, $config)) {
            return $default;
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
    return (new Permission(DB::connect()))->can($permission);
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