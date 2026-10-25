<?php
/**
 * Serve the demo/feedback schema locally without touching .env.
 *
 * Dotenv is immutable and PHP's built-in server does not put shell variables
 * into $_ENV, so DB_DATABASE must be set there BEFORE bootstrap/app.php loads
 * .env — the same trick bin/seed-demo.php uses with --db=.
 *
 * Usage (from the project root):
 *   php -S 127.0.0.1:8123 bin/serve-demo.php                    serve glee_feedback
 *   DEMO_DB=glee_other php -S 127.0.0.1:8123 bin/serve-demo.php
 *
 * Then open http://demo.gpms.local:8123 (hosts entry: 127.0.0.1 demo.gpms.local).
 * The first host label is the tenant code — see LoginController::resolveTenantId().
 * No -t flag is needed: this router delivers public/ assets itself.
 */

if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
    exit("bin/serve-demo.php is a router for the PHP built-in server, not a web entry point.\n");
}

if (PHP_SAPI === 'cli') {
    exit("Run it as a router: php -S 127.0.0.1:8123 bin/serve-demo.php\n");
}

$schema = getenv('DEMO_DB') ?: 'glee_feedback';

$_ENV['DB_DATABASE']    = $schema;
$_SERVER['DB_DATABASE'] = $schema;
putenv('DB_DATABASE=' . $schema);

error_log('[serve-demo] schema: ' . $schema);

$publicDir = realpath(dirname(__DIR__) . '/public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$candidate = $publicDir === false ? false : realpath($publicDir . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($path)));

if ($candidate !== false && is_file($candidate) && str_starts_with($candidate, $publicDir)
    && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php') {
    $types = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'map'   => 'application/json',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'txt'   => 'text/plain',
    ];

    header('Content-Type: ' . ($types[strtolower(pathinfo($candidate, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($candidate));
    readfile($candidate);
    exit;
}

require dirname(__DIR__) . '/public/index.php';
