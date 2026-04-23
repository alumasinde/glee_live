<?php

use App\Core\App;
use App\Core\Container;
use App\Core\DB;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use Dotenv\Dotenv;

//Detect errors in Development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require base_path('vendor/autoload.php');

// Load environment variables
$dotenv = Dotenv::createImmutable(base_path());
$dotenv->load();


// Helper to access environment variables
function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}


date_default_timezone_set(config('app.timezone'));

// Start session with secure settings

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Strict'
    ]);

    session_start();
}

// Initialize IoC Container

$container = new Container();

App::setContainer($container);


//Bind core services

$container->singleton(DB::class, function () {
    return DB::connect();
});



$container->bind(Request::class, function () {
    return new Request();
});

$container->singleton(Router::class, function () {
    return new Router();
});

/*
|--------------------------------------------------------------------------
| Error Handling (Production Safe)
|--------------------------------------------------------------------------
*/

set_exception_handler(function ($e) {

    $code = $e->getCode();

    // If invalid HTTP code → force 500
    if (!is_int($code) || $code < 400 || $code > 599) {
        $code = 500;
    }

    if (config('app.debug')) {
        http_response_code($code);
        echo "<pre>";
        echo "Error: " . $e->getMessage() . "\n\n";
        echo $e->getTraceAsString();
        echo "</pre>";
        exit;
    }

    Response::abort($code ?: 500, $e->getMessage());
});


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function base_path($path = '')
{
    return dirname(__DIR__) . '/' . $path;
}

function config(string $key): mixed
{
    static $cache = [];

    $parts = explode('.', $key);
    $file  = array_shift($parts);

    if (!isset($cache[$file])) {
        $cache[$file] = require base_path("config/{$file}.php");
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


return $container;
