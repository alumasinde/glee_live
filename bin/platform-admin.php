<?php
/**
 * Platform user management (CLI).
 *
 *   php bin/platform-admin.php create --email=ops@example.com --name="Ops" --password="..."
 *   php bin/platform-admin.php list
 *
 * Deliberately separate from tenant users: platform staff never appear in a
 * tenant's user list and cannot be created from any tenant screen.
 */

if (PHP_SAPI !== 'cli') {
    exit("platform-admin.php must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

// config/database.php calls env(); bin scripts must provide the same helper
// the web bootstrap does (casting rules live in App\Core\Env).
function env(string $key, $default = null): mixed
{
    return \App\Core\Env::get($key, $default);
}

$args    = array_slice($argv, 1);
$command = $args[0] ?? 'help';
$options = [];

foreach ($args as $arg) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $m)) {
        $options[$m[1]] = $m[2];
    }
}

$config = require BASE_PATH . '/config/database.php';
$conn   = $config['connections'][$config['default']] ?? $config['connections']['mysql'];
$dsn    = $conn['dsn'];

// Same override the migration runner uses, so the demo schema can be seeded
// without editing .env: --db=glee_demo
if (!empty($options['db'])) {
    $dsn = preg_replace('/dbname=[^;]+/', 'dbname=' . $options['db'], $dsn);
}

try {
    $pdo = new PDO($dsn, $conn['username'], $conn['password'], $conn['options']);
} catch (PDOException $e) {
    exit("Cannot connect to database: {$e->getMessage()}\n");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = (bool) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_users'"
)->fetchColumn();

if (!$tableExists) {
    exit("platform_users is missing. Run: php bin/migrate.php up\n");
}

if ($command === 'create') {
    $email    = strtolower(trim((string) ($options['email'] ?? '')));
    $name     = trim((string) ($options['name'] ?? 'Platform Admin'));
    $password = (string) ($options['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        exit("A valid --email is required.\n");
    }

    if (strlen($password) < 12) {
        exit("The password must be at least 12 characters.\n");
    }

    $stmt = $pdo->prepare('SELECT 1 FROM platform_users WHERE email = ?');
    $stmt->execute([$email]);

    if ($stmt->fetchColumn()) {
        exit("A platform user with that email already exists.\n");
    }

    $stmt = $pdo->prepare(
        'INSERT INTO platform_users (name, email, password_hash, is_active, is_superadmin)
         VALUES (?, ?, ?, 1, 1)'
    );
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);

    echo "Created platform user #{$pdo->lastInsertId()} <{$email}>.\n";
    exit(0);
}

if ($command === 'list') {
    $rows = $pdo->query(
        'SELECT id, name, email, is_active, last_login_at FROM platform_users ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        echo "No platform users yet.\n";
        exit(0);
    }

    foreach ($rows as $row) {
        printf(
            "#%-4d %-28s %-32s %s%s\n",
            $row['id'],
            $row['name'],
            $row['email'],
            $row['is_active'] ? 'active' : 'disabled',
            $row['last_login_at'] ? ' last_login=' . $row['last_login_at'] : ''
        );
    }

    exit(0);
}

echo "Usage:\n";
echo "  php bin/platform-admin.php create --email=you@example.com --name=\"Your Name\" --password=\"<12+ chars>\"\n";
echo "  php bin/platform-admin.php list\n";
