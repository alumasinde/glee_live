<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false; // serve the static file as-is
    }
}

use App\Core\App;

use App\Core\Request;
//use App\Core\Router;


require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$request = new Request();

$router = require base_path('routes/web.php');

$router->dispatch($request);
