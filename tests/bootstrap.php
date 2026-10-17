<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the real app bootstrap (autoloader, env, config helpers) then
 * redirects all DB usage to the scratch schema so integration tests can
 * never touch the production database. Unit tests never hit the DB, so
 * the schema check lives in DatabaseTestCase, not here.
 */

use App\Core\DB;

$app = require __DIR__ . '/../bootstrap/app.php';

// The app silences all PHP reporting when APP_DEBUG is off; tests must see
// deprecations and notices instead.
error_reporting(E_ALL);
ini_set('display_errors', '1');

// config('database.*') is resolved lazily, so overriding the env var here
// takes effect before the first DB::connect().
$_ENV['DB_DATABASE'] = getenv('TEST_DB_DATABASE') ?: 'glee_test';
