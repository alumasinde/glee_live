#!/usr/bin/env php
<?php
/**
 * bin/audit-retention.php — PII retention sweep.
 *
 * Two passes, both chunked so a run never holds a long lock on a busy table:
 *
 *   1. audit_logs older than config('jobs.retention.audit_days') are
 *      ANONYMISED, not deleted. user_id, ip_address, message and the personal
 *      metadata payload go away; id, tenant_id, action, entity_type, entity_id
 *      and created_at stay, so the statistical history (what happened, where,
 *      when) survives while no longer naming a person.
 *
 *   2. notifications that are already read and older than
 *      config('jobs.retention.notify_days') are DELETED. Unread rows are never
 *      touched — an inbox item nobody has seen is not expired data.
 *
 * USAGE
 *   php bin/audit-retention.php [--audit-days=N] [--notify-days=N] [--tenant=ID]
 *                               [--max-chunks=N] [--dry-run] [--db=NAME] [--help]
 *
 * EXIT CODES
 *   0  ran cleanly (including "nothing to do")
 *   1  fatal: bad option, missing table/column, or a pass that could not run.
 *
 * CRONTAB (weekly, out of hours)
 *   30 2 * * 0 cd /var/www/glee_live && /usr/bin/php bin/audit-retention.php >> var/log/audit-retention.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    exit("audit-retention.php must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

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

function env(string $key, $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

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

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------------------------------------------------------------------
// Options
// ---------------------------------------------------------------------------

$retention = config('jobs.retention') ?? [];

$auditDays  = max(1, (int) ($retention['audit_days'] ?? 365));
$notifyDays = max(1, (int) ($retention['notify_days'] ?? 90));
$chunkSize  = min(10000, max(1, (int) ($retention['chunk_size'] ?? 1000)));
$maxChunks  = 1000;
$tenantId   = null;
$dryRun     = false;
$wantHelp   = false;

$positiveInt = static function (string $value, string $flag): int {
    if (!ctype_digit($value) || (int) $value < 1) {
        fwrite(STDERR, "$flag expects a positive integer.\n");
        exit(1);
    }

    return (int) $value;
};

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $wantHelp = true;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--db=')) {
        // consumed by the bootstrap
    } elseif (str_starts_with($arg, '--audit-days=')) {
        $auditDays = $positiveInt(substr($arg, 13), '--audit-days');
    } elseif (str_starts_with($arg, '--notify-days=')) {
        $notifyDays = $positiveInt(substr($arg, 14), '--notify-days');
    } elseif (str_starts_with($arg, '--chunk=')) {
        $chunkSize = min(10000, $positiveInt(substr($arg, 8), '--chunk'));
    } elseif (str_starts_with($arg, '--max-chunks=')) {
        $maxChunks = $positiveInt(substr($arg, 13), '--max-chunks');
    } elseif (str_starts_with($arg, '--tenant=')) {
        $tenantId = $positiveInt(substr($arg, 9), '--tenant');
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        usage(1);
    }
}

if ($wantHelp) {
    usage(0);
}

$messageMarker  = (string) ($retention['message_marker'] ?? 'anonymised (retention policy)');
$metadataMarker = json_encode(
    $retention['metadata_marker'] ?? ['anonymised' => true, 'reason' => 'retention_policy'],
    JSON_UNESCAPED_SLASHES
);

$exitCode = 0;

echo '[' . date('Y-m-d H:i:s') . "] audit-retention start (audit {$auditDays}d, notifications {$notifyDays}d, chunk {$chunkSize}"
    . ($tenantId ? ", tenant {$tenantId}" : ', all tenants')
    . ($dryRun ? ', dry-run' : '') . ")\n";

// ---------------------------------------------------------------------------
// Pass 1 — anonymise old audit entries
// ---------------------------------------------------------------------------

if (!tableExists($pdo, 'audit_logs')) {
    fwrite(STDERR, "audit_logs does not exist — run bin/migrate.php first.\n");
    exit(1);
}

$auditCutoff = date('Y-m-d H:i:s', strtotime("-{$auditDays} days"));
$auditWhere  = 'created_at < :cutoff' . ($tenantId ? ' AND tenant_id = :tenant_id' : '');

$counts = $pdo->prepare("
    SELECT COUNT(*) FROM audit_logs
    WHERE {$auditWhere}
      AND (user_id IS NOT NULL OR ip_address IS NOT NULL OR metadata IS NOT NULL)
");

$counts->bindValue(':cutoff', $auditCutoff);
if ($tenantId) {
    $counts->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
}

$counts->execute();
$auditDue = (int) $counts->fetchColumn();

printf("audit_logs: %d row(s) older than %s still carry personal data\n", $auditDue, $auditCutoff);

if ($auditDue > 0 && !$dryRun) {
    // Chunked LIMIT updates: MySQL has no SKIP LOCKED for UPDATE, so the batch
    // is bounded by the row count instead, and the anonymised marker takes each
    // row out of the candidate set on the next pass.
    $update = $pdo->prepare("
        UPDATE audit_logs
        SET user_id    = NULL,
            ip_address = NULL,
            message    = :marker,
            metadata   = :metadata
        WHERE {$auditWhere}
          AND (user_id IS NOT NULL OR ip_address IS NOT NULL OR metadata IS NOT NULL)
        LIMIT {$chunkSize}
    ");

    $anonymised = 0;

    for ($chunk = 0; $chunk < $maxChunks; $chunk++) {
        $update->bindValue(':marker', $messageMarker);
        $update->bindValue(':metadata', $metadataMarker);
        $update->bindValue(':cutoff', $auditCutoff);
        if ($tenantId) {
            $update->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        }

        $update->execute();
        $affected = $update->rowCount();
        $anonymised += $affected;

        if ($affected < $chunkSize) {
            break;
        }
    }

    printf("audit_logs: %d row(s) anonymised\n", $anonymised);
}

// ---------------------------------------------------------------------------
// Pass 2 — purge stale read notifications
// ---------------------------------------------------------------------------

if (tableExists($pdo, 'notifications')) {
    $notifyCutoff = date('Y-m-d H:i:s', strtotime("-{$notifyDays} days"));
    $notifyWhere  = 'read_at IS NOT NULL AND read_at < :cutoff'
        . ($tenantId ? ' AND tenant_id = :tenant_id' : '');

    $due = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE {$notifyWhere}");
    $due->bindValue(':cutoff', $notifyCutoff);
    if ($tenantId) {
        $due->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    }

    $due->execute();
    $notifyDue = (int) $due->fetchColumn();

    printf("notifications: %d read row(s) older than %s\n", $notifyDue, $notifyCutoff);

    if ($notifyDue > 0 && !$dryRun) {
        $delete = $pdo->prepare("DELETE FROM notifications WHERE {$notifyWhere} LIMIT {$chunkSize}");

        $deleted = 0;

        for ($chunk = 0; $chunk < $maxChunks; $chunk++) {
            $delete->bindValue(':cutoff', $notifyCutoff);
            if ($tenantId) {
                $delete->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            }

            $delete->execute();
            $affected = $delete->rowCount();
            $deleted += $affected;

            if ($affected < $chunkSize) {
                break;
            }
        }

        printf("notifications: %d row(s) deleted\n", $deleted);
    }
} else {
    echo "notifications: table missing (migration 0021 not applied) — skipped\n";
}

// ---------------------------------------------------------------------------
// Outbox backlog is NOT purged here: a provider that recovers tomorrow should
// still deliver yesterday's approval notice, and bin/notify-outbox.php already
// caps retries at Mailer::MAX_ATTEMPTS.

echo '[' . date('Y-m-d H:i:s') . '] audit-retention done'
    . ($dryRun ? ' (dry-run: nothing changed)' : '')
    . " (exit {$exitCode})\n";

exit($exitCode);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function usage(int $code): never
{
    echo <<<TXT
audit-retention.php — PII retention sweep

  php bin/audit-retention.php [--audit-days=N] [--notify-days=N] [--chunk=N]
                              [--tenant=ID] [--max-chunks=N] [--dry-run] [--db=NAME]

  --audit-days=N    Anonymise audit_logs older than N days. Default: config('jobs.retention.audit_days') (365).
  --notify-days=N   Delete read notifications older than N days. Default: config('jobs.retention.notify_days') (90).
  --chunk=N         Rows per statement. Default: config('jobs.retention.chunk_size') (1000).
  --tenant=ID       Only this tenant. Default: every tenant.
  --max-chunks=N    Stop after N statements per pass, as a runtime ceiling. Default 1000.
  --dry-run         Count what would change. Writes nothing.
  --db=NAME         Use this schema instead of DB_DATABASE from .env.

TXT;

    exit($code);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table
    ");

    $stmt->execute([':table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}
