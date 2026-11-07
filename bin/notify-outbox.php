#!/usr/bin/env php
<?php
/**
 * bin/notify-outbox.php — drain the outbound notification queues.
 *
 * Two queues, one worker:
 *   1. email_outbox        failed SMTP sends, retried IN PLACE by Mailer::retry()
 *                          (one row per message, exponential backoff, capped at
 *                          App\Core\Mailer::MAX_ATTEMPTS attempts).
 *   2. notification_outbox queued / retry-window SMS and push rows, dispatched by
 *                          NotificationDispatcher::dispatchOutbox().
 *
 * Emails are retried first: they are the cheaper queue and a backlog there means
 * the mail config is broken, which the operator should see before the SMS/push
 * numbers scroll past.
 *
 * Delivery must never happen inside a web request, so this is the only caller.
 *
 * USAGE
 *   php bin/notify-outbox.php [--limit=N] [--tenant=ID] [--dry-run] [--db=NAME] [--help]
 *
 * OPTIONS
 *   --limit=N    Rows per queue per run. Default: config('jobs.outbox')
 *                (email_limit=50, dispatch_limit=100); when set, N applies to both.
 *   --tenant=ID  Only this tenant. Default: every active tenant, one pass each.
 *   --dry-run    Count what is due and exit. Sends nothing, changes nothing.
 *   --db=NAME    Use this schema instead of DB_DATABASE from .env.
 *   --help       This message.
 *
 * EXIT CODES
 *   0  ran cleanly (including "nothing due")
 *   1  fatal: bad option, unknown tenant, missing queue table/column, or a queue
 *      that could not be drained.
 *
 * CRONTAB (every 5 minutes; retry windows are at least 15 minutes, so a tick can
 * never pile onto one that is still waiting on a slow provider. The minutes are
 * spelled out because the shorthand form would close this block comment.)
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * * cd /var/www/glee_live && /usr/bin/php bin/notify-outbox.php --limit=100 >> var/log/notify-outbox.log 2>&1
 *
 * Non-interactive by design: no stdin, no session, no HTTP headers. Safe to run
 * by hand while debugging:
 *   php bin/notify-outbox.php --dry-run
 *   php bin/notify-outbox.php --tenant=1 --limit=5
 */

if (PHP_SAPI !== 'cli') {
    exit("notify-outbox.php must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Bootstrap — same shape as bin/migrate.php.
// --db= is applied BEFORE Dotenv (which is immutable, so a value already in the
// environment wins) because the App\Core classes used below open their own
// connection through DB::connect() / config('database.default'), and that must
// resolve to the same schema this script is working on.
// ---------------------------------------------------------------------------

$dbOverride = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $dbOverride = substr($arg, 5);
    }
}

if ($dbOverride !== null && $dbOverride !== '') {
    $_ENV['DB_DATABASE']    = $dbOverride;
    $_SERVER['DB_DATABASE'] = $dbOverride;
    putenv('DB_DATABASE=' . $dbOverride);
}

Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

// Delegates to the single implementation used by the web application, so
// string values such as "false" are cast consistently.
function env(string $key, $default = null): mixed
{
    return \App\Core\Env::get($key, $default);
}

/**
 * base_path() / config() live in bootstrap/app.php, which a CLI job must not
 * require (it starts a session and installs an HTTP exception handler). These
 * are the CLI-safe subset of it: Mailer, Audit and Notifier all call
 * config('database.*') internally, so they have to exist.
 */
if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        $parts = explode('.', $key);
        $file  = array_shift($parts);

        if (!isset($cache[$file])) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            if (!is_file($path)) {
                return $default;
            }
            $cache[$file] = require $path;
        }

        $value = $cache[$file];

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}

date_default_timezone_set((string) (config('app.timezone') ?? 'UTC'));

$dbConfig = require BASE_PATH . '/config/database.php';
$conn     = $dbConfig['connections'][$dbConfig['default']] ?? $dbConfig['connections']['mysql'];

$dsn = $conn['dsn'];

if ($dbOverride !== null && $dbOverride !== '') {
    $dsn = preg_replace('/dbname=[^;]+/', 'dbname=' . $dbOverride, $dsn);
} elseif (env('DB_DATABASE') === null || env('DB_DATABASE') === '') {
    exit("DB_DATABASE is not set in .env (or pass --db=name).\n");
}

try {
    $pdo = new PDO($dsn, $conn['username'], $conn['password'], $conn['options']);
} catch (PDOException $e) {
    exit("Cannot connect to database: {$e->getMessage()}\n");
}

// ---------------------------------------------------------------------------
// Options
// ---------------------------------------------------------------------------

$positiveInt = static function (?string $value, string $flag): int {
    if ($value === null || $value === '' || !ctype_digit($value) || (int) $value < 1) {
        fwrite(STDERR, "$flag expects a positive integer.\n");
        exit(1);
    }

    return (int) $value;
};

$tenantId  = null;
$limit     = null;
$dryRun    = false;
$wantHelp  = false;
$exitCode  = 0;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $wantHelp = true;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--db=')) {
        // consumed by the bootstrap
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = $positiveInt(substr($arg, 8), '--limit=');
    } elseif (str_starts_with($arg, '--tenant=')) {
        $tenantId = $positiveInt(substr($arg, 9), '--tenant=');
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        usage(1);
    }
}

if ($wantHelp) {
    usage(0);
}

$emailLimit    = $limit ?? (int) (config('jobs.outbox.email_limit') ?? 50);
$dispatchLimit = $limit ?? (int) (config('jobs.outbox.dispatch_limit') ?? 100);

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

$tenants = activeTenantIds($pdo, $tenantId);

if ($tenants === []) {
    fwrite(STDERR, $tenantId === null
        ? "No active tenants found — nothing to drain.\n"
        : "Tenant {$tenantId} does not exist or is not active.\n");
    exit(1);
}

$maxAttempts = \App\Core\Mailer::MAX_ATTEMPTS;

echo '[' . date('Y-m-d H:i:s') . "] notify-outbox start (email limit {$emailLimit}, dispatch limit {$dispatchLimit}"
    . ($dryRun ? ', dry-run' : '') . ")\n";

if ($dryRun) {
    // Read-only: report what each queue WOULD take, touch nothing.
    foreach ($tenants as $tid) {
        $email  = dueEmailCount($pdo, $tid, $maxAttempts);
        $outbox = dueOutboxCount($pdo, $tid, $maxAttempts);

        printf(
            "tenant %d | email queue: %s (limit %d)%s\n",
            $tid,
            $email['due'] === null ? 'unavailable' : $email['due'] . ' due',
            $emailLimit,
            $email['note'] === '' ? '' : ' — ' . $email['note']
        );

        printf(
            "tenant %d | sms/push queue: %s (limit %d)%s\n",
            $tid,
            $outbox['due'] === null ? 'unavailable' : $outbox['due'] . ' due',
            $dispatchLimit,
            $outbox['note'] === '' ? '' : ' — ' . $outbox['note']
        );
    }

    echo "dry-run: nothing sent.\n";
    exit(0);
}

$dispatcher = new \App\Core\Notification\NotificationDispatcher(
    new \App\Core\Notification\NotificationRepository()
);

foreach ($tenants as $tid) {
    // 1. failed emails, retried in place.
    try {
        $email = $dispatcher->retryEmails($emailLimit, $tid);
        printf("tenant %d | email queue: sent=%d failed=%d (limit %d)\n", $tid, $email['sent'], $email['failed'], $emailLimit);
    } catch (Throwable $e) {
        $exitCode = 1;
        printf("tenant %d | email queue: FAILED — %s\n", $tid, $e->getMessage());
    }

    // 2. queued / retry-window SMS + push.
    try {
        $outbox = $dispatcher->dispatchOutbox($dispatchLimit, $tid);
        printf(
            "tenant %d | sms/push queue: sent=%d failed=%d skipped=%d (limit %d)\n",
            $tid,
            $outbox['sent'],
            $outbox['failed'],
            $outbox['skipped'],
            $dispatchLimit
        );
    } catch (Throwable $e) {
        $exitCode = 1;
        printf("tenant %d | sms/push queue: FAILED — %s\n", $tid, $e->getMessage());
    }

    // 3. what is left waiting for the next tick.
    try {
        printf("tenant %d | remaining pending: %d\n", $tid, $dispatcher->pendingCount($tid));
    } catch (Throwable $e) {
        printf("tenant %d | remaining pending: unavailable (%s)\n", $tid, shortError($e));
    }
}

echo '[' . date('Y-m-d H:i:s') . "] notify-outbox done (exit {$exitCode})\n";
exit($exitCode);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function usage(int $code): never
{
    echo <<<TXT
    notify-outbox.php — drain the email and SMS/push outbox queues (cron worker).

    Usage:
      php bin/notify-outbox.php [--limit=N] [--tenant=ID] [--dry-run] [--db=NAME] [--help]

      --limit=N     rows per queue per run (default config('jobs.outbox'): email 50, sms/push 100)
      --tenant=ID   only this tenant id (default: every active tenant)
      --dry-run     report what is due, send nothing
      --db=NAME     override DB_DATABASE from .env
      --help        this message

    Crontab (every 5 minutes; spelled out because the */5 shorthand would break
    this script's own block comment):
      0,5,10,15,20,25,30,35,40,45,50,55 * * * * cd /var/www/glee_live && /usr/bin/php bin/notify-outbox.php --limit=100 >> var/log/notify-outbox.log 2>&1

    Exit 0 = ran cleanly, exit 1 = fatal.

    TXT;

    exit($code);
}

/**
 * Tenant list for one pass.
 *
 * @return list<int> empty means "nothing to do / unknown tenant"
 */
function activeTenantIds(PDO $pdo, ?int $tenantId): array
{
    if ($tenantId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $tenantId]);

        return $stmt->fetchColumn() === false ? [] : [$tenantId];
    }

    return array_map('intval', $pdo->query('SELECT id FROM tenants WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
}

function tableExists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (!array_key_exists($table, $cache)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
        $stmt->execute([':t' => $table]);
        $cache[$table] = (int) $stmt->fetchColumn() > 0;
    }

    return $cache[$table];
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;

    if (!array_key_exists($key, $cache)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
        $stmt->execute([':t' => $table, ':c' => $column]);
        $cache[$key] = (int) $stmt->fetchColumn() > 0;
    }

    return $cache[$key];
}

function shortError(Throwable $e): string
{
    return preg_replace('/\s+/', ' ', substr($e->getMessage(), 0, 160)) ?? substr($e->getMessage(), 0, 160);
}

/**
 * Failed email rows whose retry window has elapsed — the same predicate
 * NotificationRepository::dueEmailIds() uses, but read-only and tolerant of an
 * un-migrated schema so --dry-run works on a database that has not had
 * migration 0021 applied yet.
 *
 * @return array{due: int|null, note: string}
 */
function dueEmailCount(PDO $pdo, int $tenantId, int $maxAttempts): array
{
    if (!tableExists($pdo, 'email_outbox')) {
        return ['due' => null, 'note' => 'table email_outbox missing (migration 0014 not applied)'];
    }

    // next_retry_at is migration 0021; older databases call it next_attempt_at.
    $retryColumn = columnExists($pdo, 'email_outbox', 'next_retry_at') ? 'next_retry_at'
        : (columnExists($pdo, 'email_outbox', 'next_attempt_at') ? 'next_attempt_at' : null);

    if ($retryColumn === null) {
        return ['due' => null, 'note' => 'email_outbox has no retry-window column (run: php bin/migrate.php up)'];
    }

    $sql = "
        SELECT COUNT(*)
        FROM email_outbox
        WHERE tenant_id   = :tenant_id
          AND status      = 'failed'
          AND ($retryColumn IS NULL OR $retryColumn <= NOW())
    ";
    $params = [':tenant_id' => $tenantId];

    if (columnExists($pdo, 'email_outbox', 'attempts')) {
        $sql .= ' AND attempts < :max_attempts';
        $params[':max_attempts'] = $maxAttempts;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ['due' => (int) $stmt->fetchColumn(), 'note' => ''];
}

/**
 * Rows in notification_outbox ready to dispatch (queued, or failed inside its window).
 *
 * @return array{due: int|null, note: string}
 */
function dueOutboxCount(PDO $pdo, int $tenantId, int $maxAttempts): array
{
    if (!tableExists($pdo, 'notification_outbox')) {
        return ['due' => null, 'note' => 'table notification_outbox missing (migration 0021 not applied)'];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notification_outbox
        WHERE tenant_id = :tenant_id
          AND (
                status = 'queued'
             OR (status = 'failed' AND attempts < :max_attempts
                 AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
              )
    ");

    $stmt->execute([':tenant_id' => $tenantId, ':max_attempts' => $maxAttempts]);

    return ['due' => (int) $stmt->fetchColumn(), 'note' => ''];
}
