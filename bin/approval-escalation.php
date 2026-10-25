#!/usr/bin/env php
<?php
/**
 * bin/approval-escalation.php — aging sweep for approvals that sit pending forever.
 *
 * A gatepass stops at whichever workflow step nobody signs. This job finds the
 * stuck ones and applies graduated pressure:
 *
 *   age >= reminder_hours (default 24)  re-notify the approver on
 *        Events::APPROVAL_ESCALATED, then stamp escalated_at = NOW() and
 *        increment gatepass_approvals.escalation_count. escalated_at is also the
 *        quiet period: a row nudged less than reminder_hours ago is left alone,
 *        and escalation_count caps the whole thing at max_escalations (default 3)
 *        so a row nobody acts on cannot mail its approver forever.
 *
 *   age >= admin_hours (default 48)     additionally notify every active tenant
 *        user holding approval.reassign (resolved through
 *        role_permissions -> permissions -> modules/actions -> user_roles -> users)
 *        that this specific gatepass is stuck, and write one
 *        Audit::system(…, 'approval.escalated_admin', …) entry per workflow
 *        instance. Admins get the notice on the runs where the approver was also
 *        nudged, so the cadence is bounded by the same escalation budget.
 *
 * "Stuck" is precise, not "anything pending": only rows on an in_progress
 * instance whose workflow_steps.step_order equals the instance's
 * current_step_order, i.e. the step the gatepass is actually waiting on.
 *
 * Age is measured on the database clock (MySQL NOW()), against
 * gatepass_approvals.created_at — falling back to
 * gatepass_workflow_instances.started_at on schemas that predate it — so a job
 * host in another timezone cannot misjudge an approval.
 *
 * USAGE
 *   php bin/approval-escalation.php [--tenant=ID] [--limit=N] [--reminder-hours=H]
 *                                   [--admin-hours=H] [--max-escalations=N]
 *                                   [--dry-run] [--db=NAME] [--help]
 *
 * OPTIONS
 *   --tenant=ID           Only this tenant. Default: every active tenant.
 *   --limit=N             Candidate rows per tenant per run. Default config('jobs.escalation_limit').
 *   --reminder-hours=H    Age before the approver is nudged. Default config('jobs.reminder_hours').
 *   --admin-hours=H       Age before approval.reassign holders are told. Default config('jobs.admin_hours').
 *   --max-escalations=N   Nudge cap per approval row. Default config('jobs.max_escalations').
 *   --dry-run             Print the rows that WOULD be escalated and what would be
 *                         sent to them; validate the write statements against the
 *                         live schema. Changes nothing, sends nothing.
 *   --db=NAME             Use this schema instead of DB_DATABASE from .env.
 *   --help                This message.
 *
 * EXIT CODES
 *   0  ran cleanly (including "nothing overdue")
 *   1  fatal: bad option, unknown tenant, escalation bookkeeping columns missing
 *      (gatepass_approvals.escalated_at / .escalation_count come from migration
 *      0021 and a real run cannot be idempotent without them), or a row whose
 *      delivery blew up.
 *
 * CRONTAB (once an hour, comfortably outside the reminder window)
 *   15 * * * * cd /var/www/glee_live && /usr/bin/php bin/approval-escalation.php --limit=200 >> var/log/approval-escalation.log 2>&1
 *
 * Non-interactive: no stdin, no session. CLI has no $_SESSION['user'], so every
 * audit entry goes through Audit::system() (Audit::log() would silently no-op).
 */

if (PHP_SAPI !== 'cli') {
    exit("approval-escalation.php must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Bootstrap — same shape as bin/migrate.php. --db= is applied BEFORE Dotenv
// (immutable: a value already in the environment wins) because Mailer, Notifier
// and Audit open their own connection through DB::connect() /
// config('database.default'), and that must resolve to the same schema.
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

function env(string $key, $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

/**
 * base_path() / config() live in bootstrap/app.php, which a CLI job must not
 * require (it starts a session and installs an HTTP exception handler). This is
 * the CLI-safe subset: config('app.url') and config('jobs.*') are read below and
 * the App\Core classes read config('database.*') internally.
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

/**
 * config() with an explicit fallback, because a value deleted from
 * config/jobs.php must not turn into "0 hours" and escalate everything at once.
 */
function jobs_value(string $key, int $fallback): int
{
    $value = config('jobs.' . $key);

    return is_numeric($value) && (int) $value > 0 ? (int) $value : $fallback;
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

/**
 * Server-side parse + identifier check without executing anything: with
 * ATTR_EMULATE_PREPARES off, PDO::prepare() sends a real PREPARE, so MySQL
 * resolves every table and column name. Used by --dry-run to prove the writes it
 * is declining to perform would actually compile.
 */
function lintSql(PDO $pdo, string $label, string $sql): bool
{
    try {
        $pdo->prepare($sql);
        printf("  lint %-22s ok (statement compiles against the live schema)\n", $label);

        return true;
    } catch (Throwable $e) {
        printf("  lint %-22s FAILED — %s\n", $label, preg_replace('/\s+/', ' ', $e->getMessage()));

        return false;
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

function positiveIntArg(?string $value, string $flag): int
{
    if ($value === null || $value === '' || !ctype_digit($value) || (int) $value < 1) {
        fwrite(STDERR, "$flag expects a positive integer.\n");
        exit(1);
    }

    return (int) $value;
}

$tenantId        = null;
$limit           = null;
$reminderHours   = null;
$adminHours      = null;
$maxEscalations  = null;
$dryRun          = false;
$wantHelp        = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $wantHelp = true;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--db=')) {
        // consumed by the bootstrap
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = positiveIntArg(substr($arg, 8), '--limit=');
    } elseif (str_starts_with($arg, '--tenant=')) {
        $tenantId = positiveIntArg(substr($arg, 9), '--tenant=');
    } elseif (str_starts_with($arg, '--reminder-hours=')) {
        $reminderHours = positiveIntArg(substr($arg, 17), '--reminder-hours=');
    } elseif (str_starts_with($arg, '--admin-hours=')) {
        $adminHours = positiveIntArg(substr($arg, 14), '--admin-hours=');
    } elseif (str_starts_with($arg, '--max-escalations=')) {
        $maxEscalations = positiveIntArg(substr($arg, 18), '--max-escalations=');
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        usage(1);
    }
}

if ($wantHelp) {
    usage(0);
}

$limit          = $limit          ?? jobs_value('escalation_limit', 200);
$reminderHours  = $reminderHours  ?? jobs_value('reminder_hours', 24);
$adminHours     = $adminHours     ?? jobs_value('admin_hours', 48);
$maxEscalations = $maxEscalations ?? jobs_value('max_escalations', 3);

if ($adminHours < $reminderHours) {
    fwrite(STDERR, "--admin-hours must be >= the reminder threshold (admins are the escalation after the nudge).\n");
    exit(1);
}

$baseUrl = rtrim((string) (config('app.url') ?? 'http://localhost'), '/');

// ---------------------------------------------------------------------------
// Schema check: the escalation bookkeeping comes from migration 0021.
// ---------------------------------------------------------------------------

$approvalsTable = tableExists($pdo, 'gatepass_approvals');

if (!$approvalsTable || !tableExists($pdo, 'gatepass_workflow_instances') || !tableExists($pdo, 'workflow_steps')) {
    fwrite(STDERR, "Approval tables are missing (migrations 0008/0009 not applied) — cannot sweep.\n");
    exit(1);
}

$hasEscalationCols = columnExists($pdo, 'gatepass_approvals', 'escalated_at')
    && columnExists($pdo, 'gatepass_approvals', 'escalation_count');

// Age source, oldest reliable timestamp that actually exists on this schema.
$ageColumn = columnExists($pdo, 'gatepass_approvals', 'created_at')
    ? 'ga.created_at'
    : 'gwi.started_at';

if (!$hasEscalationCols && !$dryRun) {
    fwrite(STDERR, <<<'TXT'
gatepass_approvals.escalated_at / .escalation_count are missing (migration 0021
not applied). Without them this job cannot keep its promise not to re-nudge the
same approver on every run, so it refuses. Apply the migration
(php bin/migrate.php up) or re-run with --dry-run to only see the candidates.

TXT);
    exit(1);
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

$tenants = activeTenantIds($pdo, $tenantId);

if ($tenants === []) {
    fwrite(STDERR, $tenantId === null
        ? "No active tenants found — nothing to sweep.\n"
        : "Tenant {$tenantId} does not exist or is not active.\n");
    exit(1);
}

// Dry-run sends nothing, so the notifier is only built for a real pass.
$notifier = $dryRun ? null : \App\Core\Notification\Notifier::make();

$totals = ['nudged' => 0, 'admin_instances' => 0, 'admin_recipients' => 0, 'exhausted' => 0, 'failed' => 0];

echo '[' . date('Y-m-d H:i:s') . "] approval-escalation start"
    . ($dryRun ? ' [DRY RUN — nothing will be sent or written]' : '') . "\n";
printf(
    "thresholds: reminder %dh, admin %dh, max escalations %d, limit %d per tenant; age column %s%s\n",
    $reminderHours,
    $adminHours,
    $maxEscalations,
    $limit,
    $ageColumn,
    $hasEscalationCols ? '' : ' (escalation bookkeeping unavailable)'
);

if ($dryRun) {
    echo "validating the statements this run declines to execute:\n";
    lintSql($pdo, 'candidate select', candidateSql($hasEscalationCols, $ageColumn));
    lintSql($pdo, 'admin recipients', adminHoldersSql());

    if ($hasEscalationCols) {
        lintSql($pdo, 'escalation stamp', escalationStampSql());
    }
}

foreach ($tenants as $tid) {
    $rows = fetchCandidates($pdo, $tid, $reminderHours, $limit, $hasEscalationCols, $ageColumn);

    if ($rows === []) {
        printf("tenant %d: no approvals past the %dh reminder threshold on the current step.\n", $tid, $reminderHours);

        continue;
    }

    $adminStage = [];

    foreach ($rows as $row) {
        $ageHours  = (int) $row['age_hours'];
        $escalated = (int) $row['escalation_count'];

        if ($escalated >= $maxEscalations) {
            $totals['exhausted']++;
            printf(
                "  - approval #%d (tenant %d, gatepass %s, step %s, approver #%s) is %dh old but has used its escalation budget (%d/%d) — left alone\n",
                $row['id'], $tid, $row['gatepass_number'], $row['step_name'],
                $row['approver_user_id'], $ageHours, $escalated, $maxEscalations
            );

            continue;
        }

        $needsAdmin = $ageHours >= $adminHours;

        if ($dryRun) {
            $totals['nudged']++;
            printf(
                "  WOULD nudge approval #%d (tenant %d, gatepass %s, step %s, approver #%d, %dh old, escalated %d/%d)%s\n",
                $row['id'], $tid, $row['gatepass_number'], $row['step_name'],
                $row['approver_user_id'], $ageHours, $escalated, $maxEscalations,
                $needsAdmin ? ' + admin escalation' : ''
            );

            if ($needsAdmin) {
                $adminStage[(int) $row['workflow_instance_id']] = $row;
            }

            continue;
        }

        try {
            $outcomes = $notifier->notify($tid, (int) $row['approver_user_id'], approverMessage($row, $baseUrl));

            stampEscalation($pdo, (int) $row['id'], $tid);

            \App\Core\Audit::system($tid, 'approval.escalated_reminder', 'gatepass_approval', (int) $row['id'], [
                'target_label'       => $row['gatepass_number'],
                'gatepass_number'    => $row['gatepass_number'],
                'gatepass_id'        => (int) $row['gatepass_id'],
                'instance_id'        => (int) $row['workflow_instance_id'],
                'step_name'          => $row['step_name'],
                'approver_user_id'   => (int) $row['approver_user_id'],
                'age_hours'          => $ageHours,
                'escalation_count'   => $escalated + 1,
                'channels'           => $outcomes,
            ]);

            $totals['nudged']++;

            if ($outcomes === []) {
                printf(
                    "  - approval #%d: approver #%d has no reachable channel (inactive or deleted); escalation budget consumed so the admin path can take over\n",
                    $row['id'], $row['approver_user_id']
                );
            }

            if ($needsAdmin) {
                $adminStage[(int) $row['workflow_instance_id']] = $row;
            }
        } catch (Throwable $e) {
            // escalated_at is deliberately not stamped on failure: the next run
            // gets another shot instead of losing the reminder.
            $totals['failed']++;
            printf("  - approval #%d FAILED: %s\n", $row['id'], preg_replace('/\s+/', ' ', $e->getMessage()));
        }
    }

    // One admin notice per stuck instance, not per pending row.
    foreach ($adminStage as $instanceId => $row) {
        $holders = reassignHolders($pdo, $tid);

        if ($holders === []) {
            printf(
                "  - instance %d (gatepass %s) is %dh old but tenant %d has no active user with approval.reassign — nobody to escalate to\n",
                $instanceId, $row['gatepass_number'], (int) $row['age_hours'], $tid
            );

            continue;
        }

        $totals['admin_instances']++;
        $totals['admin_recipients'] += count($holders);

        if ($dryRun) {
            printf(
                "  WOULD escalate instance %d (gatepass %s) to %d approval.reassign user(s): %s\n",
                $instanceId,
                $row['gatepass_number'],
                count($holders),
                implode(', ', array_column($holders, 'email'))
            );

            continue;
        }

        try {
            $counts = $notifier->deliverMany(
                $tid,
                array_column($holders, 'id'),
                adminMessage($row, $baseUrl)
            );

            \App\Core\Audit::system($tid, 'approval.escalated_admin', 'gatepass_workflow_instance', $instanceId, [
                'gatepass_number'  => $row['gatepass_number'],
                'gatepass_id'      => (int) $row['gatepass_id'],
                'approval_id'      => (int) $row['id'],
                'step_name'        => $row['step_name'],
                'approver_user_id' => (int) $row['approver_user_id'],
                'age_hours'        => (int) $row['age_hours'],
                'recipients'       => array_column($holders, 'email'),
                'outcomes'         => $counts,
            ]);

            printf(
                "  escalated instance %d (gatepass %s) to %d approval.reassign user(s)\n",
                $instanceId, $row['gatepass_number'], count($holders)
            );
        } catch (Throwable $e) {
            $totals['failed']++;
            printf("  - instance %d admin escalation FAILED: %s\n", $instanceId, preg_replace('/\s+/', ' ', $e->getMessage()));
        }
    }
}

printf(
    "done: %d approver nudge(s), %d instance(s) escalated to %d admin recipient(s), %d over budget, %d failed.\n",
    $totals['nudged'],
    $totals['admin_instances'],
    $totals['admin_recipients'],
    $totals['exhausted'],
    $totals['failed']
);

if ($dryRun) {
    echo "dry-run: nothing sent, nothing written.\n";
}

exit($totals['failed'] > 0 ? 1 : 0);

// ---------------------------------------------------------------------------
// Queries
// ---------------------------------------------------------------------------

function usage(int $code): never
{
    echo <<<TXT
    approval-escalation.php — remind approvers of overdue approvals and escalate the
    hopeless ones to tenants' approval.reassign holders (cron worker).

    Usage:
      php bin/approval-escalation.php [--tenant=ID] [--limit=N] [--reminder-hours=H]
                                      [--admin-hours=H] [--max-escalations=N] [--dry-run]
                                      [--db=NAME] [--help]

      --tenant=ID           only this tenant id (default: every active tenant)
      --limit=N             candidate rows per tenant per run (default config('jobs.escalation_limit'))
      --reminder-hours=H    age before the approver is re-notified (default config('jobs.reminder_hours'))
      --admin-hours=H       age before approval.reassign holders are told (default config('jobs.admin_hours'))
      --max-escalations=N   nudge cap per approval row (default config('jobs.max_escalations'))
      --dry-run             show what would be escalated, validate the write SQL, change nothing
      --db=NAME             override DB_DATABASE from .env
      --help                this message

    Crontab:
      15 * * * * cd /var/www/glee_live && /usr/bin/php bin/approval-escalation.php --limit=200 >> var/log/approval-escalation.log 2>&1

    Exit 0 = ran cleanly, exit 1 = fatal.

    TXT;

    exit($code);
}

/** @return list<int> empty means "nothing to do / unknown tenant" */
function activeTenantIds(PDO $pdo, ?int $tenantId): array
{
    if ($tenantId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute([':id' => $tenantId]);

        return $stmt->fetchColumn() === false ? [] : [$tenantId];
    }

    return array_map('intval', $pdo->query('SELECT id FROM tenants WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * The candidate query.
 *
 * Every threshold arrives as a bound integer; no CLI/config value is ever
 * interpolated into the SQL text.
 *
 * @param bool   $withEscalation add escalated_at / escalation_count and the quiet period
 * @param string $ageColumn      the age timestamp that exists on this schema
 */
function candidateSql(bool $withEscalation, string $ageColumn): string
{
    $escalationColumns = $withEscalation
        ? 'ga.escalated_at, ga.escalation_count,'
        : 'NULL AS escalated_at, 0 AS escalation_count,';

    // Quiet period: a row nudged inside reminder_hours is left alone. This is the
    // whole point of escalated_at — without it every cron tick re-mails the approver.
    $quietPeriod = $withEscalation
        ? 'AND (ga.escalated_at IS NULL OR TIMESTAMPDIFF(HOUR, ga.escalated_at, NOW()) >= :quiet_hours)'
        : '';

    $orderBy = $withEscalation
        ? 'ga.escalation_count ASC, age_hours DESC, ga.id ASC'
        : 'age_hours DESC, ga.id ASC';

    return "
        SELECT
            ga.id,
            ga.tenant_id,
            ga.workflow_instance_id,
            ga.workflow_step_id,
            ga.approver_user_id,
            $ageColumn AS age_basis,
            $escalationColumns
            gwi.current_step_order,
            TIMESTAMPDIFF(HOUR, $ageColumn, NOW()) AS age_hours,
            ws.step_order,
            COALESCE(NULLIF(ws.name, ''), CONCAT('Step ', ws.step_order)) AS step_name,
            gp.id AS gatepass_id,
            gp.gatepass_number,
            gp.purpose,
            CONCAT(u.first_name, ' ', u.last_name) AS approver_name,
            u.email AS approver_email,
            CONCAT(r.first_name, ' ', r.last_name) AS requested_by_name
        FROM gatepass_approvals ga
        INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id AND gwi.tenant_id = ga.tenant_id
        INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id AND ws.tenant_id = ga.tenant_id
        INNER JOIN gatepasses gp
                ON gp.id = gwi.gatepass_id AND gp.tenant_id = gwi.tenant_id
        LEFT JOIN users u
                ON u.id = ga.approver_user_id AND u.tenant_id = ga.tenant_id
        LEFT JOIN users r
                ON r.id = gp.created_by AND r.tenant_id = gp.tenant_id
        WHERE ga.tenant_id = :tenant_id
          AND ga.status = 'pending'
          AND gwi.status = 'in_progress'
          AND ws.step_order = gwi.current_step_order
          AND TIMESTAMPDIFF(HOUR, $ageColumn, NOW()) >= :reminder_hours
          $quietPeriod
        ORDER BY $orderBy
        LIMIT :limit
    ";
}

/**
 * @return list<array<string, mixed>>
 */
function fetchCandidates(PDO $pdo, int $tenantId, int $reminderHours, int $limit, bool $withEscalation, string $ageColumn): array
{
    $stmt = $pdo->prepare(candidateSql($withEscalation, $ageColumn));

    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->bindValue(':reminder_hours', $reminderHours, PDO::PARAM_INT);

    if ($withEscalation) {
        $stmt->bindValue(':quiet_hours', $reminderHours, PDO::PARAM_INT);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The bookkeeping stamp. Guarded on status so a row signed between the SELECT and
 * this UPDATE is left alone (rowCount 0).
 */
function escalationStampSql(): string
{
    return "
        UPDATE gatepass_approvals
        SET escalated_at = NOW(),
            escalation_count = escalation_count + 1
        WHERE id = :id
          AND tenant_id = :tenant_id
          AND status = 'pending'
    ";
}

function stampEscalation(PDO $pdo, int $approvalId, int $tenantId): int
{
    $stmt = $pdo->prepare(escalationStampSql());

    $stmt->bindValue(':id', $approvalId, PDO::PARAM_INT);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}

function adminHoldersSql(): string
{
    return "
        SELECT DISTINCT
            u.id,
            u.email,
            CONCAT(u.first_name, ' ', u.last_name) AS name
        FROM users u
        INNER JOIN user_roles ur
                ON ur.user_id = u.id AND ur.tenant_id = u.tenant_id
        INNER JOIN role_permissions rp
                ON rp.role_id = ur.role_id AND rp.tenant_id = ur.tenant_id
        INNER JOIN permissions p
                ON p.id = rp.permission_id
        INNER JOIN modules m
                ON m.id = p.module_id AND m.name = 'approval'
        INNER JOIN actions a
                ON a.id = p.action_id AND a.name = 'reassign'
        WHERE u.tenant_id = :tenant_id
          AND u.is_active = 1
        ORDER BY u.id
    ";
}

/**
 * Tenant users who can act on a stuck approval.
 *
 * @return list<array{id:int, email:string, name:string}>
 */
function reassignHolders(PDO $pdo, int $tenantId): array
{
    $stmt = $pdo->prepare(adminHoldersSql());
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------------
// Messages
// ---------------------------------------------------------------------------

function approverMessage(array $row, string $baseUrl): \App\Core\Notification\NotificationMessage
{
    $number = (string) $row['gatepass_number'];
    $link   = $baseUrl . '/approvals/' . (int) $row['id'];
    $step   = (string) $row['step_name'];
    $hours  = (int) $row['age_hours'];
    $name   = trim((string) ($row['approver_name'] ?? '')) !== '' ? trim((string) $row['approver_name']) : 'there';
    $who    = trim((string) ($row['requested_by_name'] ?? '')) !== '' ? trim((string) $row['requested_by_name']) : 'Someone';

    $title = sprintf('Overdue approval — gatepass %s', $number);
    $body  = sprintf(
        '%s requested gatepass %s and it has been waiting on you at step "%s" for %d hours. Purpose: %s',
        $who,
        $number,
        $step,
        $hours,
        trim((string) ($row['purpose'] ?? '')) !== '' ? (string) $row['purpose'] : 'not stated'
    );

    return new \App\Core\Notification\NotificationMessage(
        \App\Core\Notification\Events::APPROVAL_ESCALATED,
        $title,
        $body,
        $link,
        '<h2>This approval is overdue</h2>'
            . '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>' . htmlspecialchars($who, ENT_QUOTES, 'UTF-8')
            . ' requested gatepass <strong>' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ' and it has been waiting on you at step <strong>'
            . htmlspecialchars($step, ENT_QUOTES, 'UTF-8') . '</strong> for ' . $hours . ' hours.</p>'
            . '<p>Purpose: ' . nl2br(htmlspecialchars((string) ($row['purpose'] ?? 'not stated'), ENT_QUOTES, 'UTF-8')) . '</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>',
        $title,
        'warning'
    );
}

function adminMessage(array $row, string $baseUrl): \App\Core\Notification\NotificationMessage
{
    $number  = (string) $row['gatepass_number'];
    $link    = $baseUrl . '/approvals/' . (int) $row['id'];
    $step    = (string) $row['step_name'];
    $hours   = (int) $row['age_hours'];
    $approver = trim((string) ($row['approver_name'] ?? '')) !== '' ? trim((string) $row['approver_name'])
        : ('user #' . (int) $row['approver_user_id']);

    $title = sprintf('Gatepass %s is stuck at step %s', $number, $step);
    $body  = sprintf(
        'Gatepass %s has had no action for %d hours at step "%s" (approver: %s). It can be reassigned.',
        $number,
        $hours,
        $step,
        $approver
    );

    return new \App\Core\Notification\NotificationMessage(
        \App\Core\Notification\Events::APPROVAL_ESCALATED,
        $title,
        $body,
        $link,
        '<h2>An approval needs reassigning</h2>'
            . '<p>Gatepass <strong>' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') . '</strong> is still waiting at step <strong>'
            . htmlspecialchars($step, ENT_QUOTES, 'UTF-8') . '</strong> after ' . $hours . ' hours.</p>'
            . '<p>Current approver: ' . htmlspecialchars($approver, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Purpose: ' . nl2br(htmlspecialchars((string) ($row['purpose'] ?? 'not stated'), ENT_QUOTES, 'UTF-8')) . '</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>',
        $title,
        'warning'
    );
}
