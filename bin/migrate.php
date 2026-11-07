<?php
/**
 * Database migration runner.
 *
 * Usage (from the project root):
 *   php bin/migrate.php status
 *   php bin/migrate.php up [N]      apply all pending (or next N) migrations
 *   php bin/migrate.php down [N|all] roll back last N (default 1) or everything
 *   php bin/migrate.php --db=name   override DB_DATABASE from .env
 */

if (PHP_SAPI !== 'cli') {
    exit("migrate.php must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

// Delegates to the single implementation used by the web application, so
// string values such as "false" are cast consistently.
function env(string $key, $default = null): mixed
{
    return \App\Core\Env::get($key, $default);
}

$migrationsDir = BASE_PATH . '/database/migrations';
$args = array_slice($argv, 1);
$command = 'status';
$argument = null;
$dbOverride = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbOverride = substr($arg, 5);
    } elseif ($command === 'status' && !str_starts_with($arg, '--') && in_array($arg, ['up', 'down', 'status'], true)) {
        $command = $arg;
    } elseif ($command !== 'status') {
        $argument = $arg;
    }
}

$config = require BASE_PATH . '/config/database.php';
$conn = $config['connections'][$config['default']] ?? $config['connections']['mysql'];

$dsn = $conn['dsn'];
if ($dbOverride !== null) {
    $dsn = preg_replace('/dbname=[^;]+/', 'dbname=' . $dbOverride, $dsn);
} elseif (env('DB_DATABASE') === null || env('DB_DATABASE') === '') {
    exit("DB_DATABASE is not set in .env (or pass --db=name).\n");
}

preg_match('/dbname=([^;]+)/', $dsn, $m);
$dbName = $m[1];

// Connect without dbname first so the schema can be created if missing.
$serverDsn = preg_replace('/dbname=[^;]+;?/', '', $dsn);
try {
    $pdo = new PDO($serverDsn, $conn['username'], $conn['password'], $conn['options']);
} catch (PDOException $e) {
    exit("Cannot connect to database server: {$e->getMessage()}\n");
}

$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4");
$pdo->exec("USE `$dbName`");

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS migrations (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  filename varchar(255) NOT NULL,
  checksum char(64) NOT NULL,
  applied_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL);

function findMigrations(string $dir): array
{
    $files = glob($dir . '/*.sql');
    sort($files);
    return $files;
}

function parseSection(string $sql, string $direction): string
{
    if (!preg_match('/--\s*\+migrate\s+' . $direction . '\s*\r?\n(.*?)(?=--\s*\+migrate\s+(?:UP|DOWN)|\z)/is', $sql, $m)) {
        return '';
    }
    return trim($m[1]);
}

// Split a SQL blob into statements, respecting quotes and comments.
function splitStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $quote = null;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($quote !== null) {
            $current .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $len) {
                $current .= $sql[++$i];
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if (in_array($ch, ["'", '"', '`'], true)) {
            $quote = $ch;
            $current .= $ch;
            continue;
        }

        if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            $eol = strpos($sql, "\n", $i);
            $eol = $eol === false ? $len : $eol;
            $current .= substr($sql, $i, $eol - $i);
            $i = $eol - 1;
            continue;
        }

        if ($ch === '/' && ($sql[$i + 1] ?? '') === '*') {
            $end = strpos($sql, '*/', $i);
            $end = $end === false ? $len : $end + 2;
            $current .= substr($sql, $i, $end - $i);
            $i = $end - 1;
            continue;
        }

        if ($ch === '#') {
            $eol = strpos($sql, "\n", $i);
            $eol = $eol === false ? $len : $eol;
            $i = $eol - 1;
            continue;
        }

        if ($ch === ';') {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    if (trim($current) !== '') {
        $statements[] = trim($current);
    }

    return $statements;
}

function runMigration(PDO $pdo, string $file, string $direction): void
{
    $sql = file_get_contents($file);
    $section = parseSection($sql, $direction);
    if ($section === '') {
        echo basename($file) . ": no $direction section, skipped\n";
        return;
    }

    foreach (splitStatements($section) as $statement) {
        try {
            // query(), not exec(): exec() cannot drain a statement that returns
            // rows, and "EXECUTE s" from the conditional-ALTER guard blocks does
            // exactly that, leaving an active cursor that makes the following
            // DEALLOCATE fail with MySQL error 2014. query() also sends the
            // statement as a plain COM_QUERY, so nested PREPARE/EXECUTE inside a
            // migration is legal (a server-side prepared statement cannot
            // contain them). closeCursor() frees the result either way.
            $result = $pdo->query($statement);

            if ($result !== false) {
                $result->closeCursor();
            }
        } catch (PDOException $e) {
            exit(sprintf(
                "\nError in %s (%s): %s\nSQL: %s\n",
                basename($file),
                $direction,
                $e->getMessage(),
                substr($statement, 0, 200)
            ));
        }
    }
}

$files = findMigrations($migrationsDir);
$applied = $pdo->query("SELECT filename, checksum FROM migrations ORDER BY id")->fetchAll();
$appliedMap = array_combine(array_column($applied, 'filename'), array_column($applied, 'checksum'));
$appliedNames = array_column($applied, 'filename');

if ($command === 'status') {
    printf("%-32s %-10s %s\n", 'MIGRATION', 'STATUS', 'CHECKSUM');
    foreach ($files as $file) {
        $name = basename($file);
        $checksum = hash('sha256', file_get_contents($file));
        if (isset($appliedMap[$name])) {
            $status = $appliedMap[$name] === $checksum ? 'applied' : 'applied (DRIFT!)';
        } else {
            $status = 'pending';
        }
        printf("%-32s %-10s %s\n", $name, $status, substr($checksum, 0, 12));
    }
    exit(0);
}

if ($command === 'up') {
    $pending = array_values(array_filter($files, fn($f) => !isset($appliedMap[basename($f)])));
    if ($argument !== null && ctype_digit($argument)) {
        $pending = array_slice($pending, 0, (int) $argument);
    }
    if (!$pending) {
        echo "Nothing to migrate. Database is up to date.\n";
        exit(0);
    }
    foreach ($pending as $file) {
        $name = basename($file);
        echo "Applying $name ... ";
        runMigration($pdo, $file, 'UP');
        $stmt = $pdo->prepare('INSERT INTO migrations (filename, checksum) VALUES (?, ?)');
        $stmt->execute([$name, hash('sha256', file_get_contents($file))]);
        echo "ok\n";
    }
    printf("Applied %d migration(s).\n", count($pending));
    exit(0);
}

// down
$toRollback = array_reverse($appliedNames);
if (strtolower((string) $argument) === 'all') {
    // everything
} elseif ($argument !== null && ctype_digit($argument)) {
    $toRollback = array_slice($toRollback, 0, (int) $argument);
} else {
    $toRollback = array_slice($toRollback, 0, 1);
}
if (!$toRollback) {
    echo "Nothing to roll back.\n";
    exit(0);
}
foreach ($toRollback as $name) {
    $file = $migrationsDir . '/' . $name;
    if (!is_file($file)) {
        exit("Migration file missing for rollback: $name\n");
    }
    echo "Rolling back $name ... ";
    runMigration($pdo, $file, 'DOWN');
    $stmt = $pdo->prepare('DELETE FROM migrations WHERE filename = ?');
    $stmt->execute([$name]);
    echo "ok\n";
}
printf("Rolled back %d migration(s).\n", count($toRollback));
