<?php
/**
 * Demo / feedback environment seeder.
 *
 * Builds a live-LOOKING but entirely synthetic environment for outside testers,
 * in its own schema, without ever putting demo rows into a migration.
 *
 * Usage (from the project root):
 *   php bin/seed-demo.php --db=glee_feedback            create + fill the demo tenant
 *   php bin/seed-demo.php --db=glee_feedback --rows=80  heavier gatepass volume
 *   php bin/seed-demo.php --db=glee_feedback --reset    re-seed (evidence preserved)
 *   php bin/seed-demo.php --db=glee_feedback --reset --purge-evidence
 *   php bin/seed-demo.php --db=glee_feedback --export-evidence=var/fb.json
 *   php bin/seed-demo.php --db=... --dry-run            print the plan, write nothing
 *
 * Design rules this file enforces:
 *   - Isolation is by SCHEMA (--db), never by code fork or a "demo" flag.
 *   - Everything hangs off ONE tenant, so a reset is tenant-scoped DELETEs and
 *     the schema is never dropped. Testers' own writes survive.
 *   - audit_logs / notifications / both outboxes are EVIDENCE: --reset keeps them
 *     (and --export-evidence dumps them first) because they are what testers did.
 *   - Outbound is forced off at the data layer (tenant_settings mail/sms/push),
 *     so a leaked .env credential cannot make this install send real messages.
 */

if (PHP_SAPI !== 'cli') {
    exit("seed-demo.php must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));

// --db is applied BEFORE Dotenv (immutable) so every consumer of
// config('database.default') — Mailer, Notifier, Audit — resolves the same schema.
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

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/bootstrap/app.php';

use App\Core\DB;
use App\Modules\Approval\Services\ApprovalService;

$options = [
    'tenant'   => 'DEMO',
    'name'     => 'Glee Demo (feedback)',
    'template' => 'gpms',
    'rows'     => 40,
    'visits'   => 25,
    'password' => 'Demo!2026',
    'seed'     => 20261025,
    'reset'    => false,
    'purge'    => false,
    'dry'      => false,
    'export'   => null,
    'help'     => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(tenant|name|template|rows|visits|password|seed|export-evidence)=(.*)$/', $arg, $m)) {
        $key = $m[1] === 'export-evidence' ? 'export' : $m[1];
        $options[$key] = in_array($key, ['rows', 'visits', 'seed'], true) ? (int) $m[2] : $m[2];
    } elseif ($arg === '--reset') {
        $options['reset'] = true;
    } elseif ($arg === '--purge-evidence') {
        $options['purge'] = true;
    } elseif ($arg === '--dry-run') {
        $options['dry'] = true;
    } elseif ($arg === '--help') {
        $options['help'] = true;
    }
}

if ($options['help']) {
    echo substr(file_get_contents(__FILE__), 0, 2200), "\n";
    exit(0);
}

if ($dbOverride === null) {
    exit("Refusing to seed the .env database without --db=NAME. This tool writes demo rows.\n");
}

$db = DB::connect();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

mt_srand($options['seed']);

$stats = [];

// ---------------------------------------------------------------------------
// Reference data cloned per tenant (the boot seed only fills the template).
// ---------------------------------------------------------------------------

$referenceTables = [
    'departments', 'gatepass_statuses', 'visit_statuses', 'visit_types',
    'identification_types', 'gatepass_types', 'visitor_companies', 'roles',
];

function tenantId(PDO $db, string $code): ?int
{
    $stmt = $db->prepare('SELECT id FROM tenants WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

echo "Database: ", env('DB_DATABASE'), "\n";

$templateId = tenantId($db, $options['template']);

if ($templateId === null) {
    exit("Template tenant '{$options['template']}' not found — run php bin/migrate.php up --db=" . env('DB_DATABASE') . " first.\n");
}

$demoId = tenantId($db, $options['tenant']);

echo "Template tenant: {$templateId}   Demo tenant: " . ($demoId ?? 'will be created') . "\n";

if ($options['dry']) {
    echo "\n--dry-run: would create the demo tenant, clone reference rows, and fabricate\n",
         "  {$options['rows']} gatepasses (engine-driven approvals), {$options['visits']} visits,\n",
         "  gates, delegates, notifications and an audit trail. Nothing was written.\n";
    exit(0);
}

$db->beginTransaction();

try {
    if ($demoId === null) {
        $db->prepare('INSERT INTO tenants (name, code, logo, email, phone, country, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)')
            ->execute([
                $options['name'],
                $options['tenant'],
                'acme-logo.png',
                'feedback@' . strtolower($options['tenant']) . '.example',
                '+254700000000',
                'Kenya',
            ]);

        $demoId = (int) $db->lastInsertId();
        $stats['tenant'] = "created (id {$demoId})";
    } else {
        $stats['tenant'] = "reused (id {$demoId})";
    }

    // -----------------------------------------------------------------
    // Reset: tenant-scoped deletes, fabricated rows first (FK order).
    // -----------------------------------------------------------------
    if ($options['reset']) {
        $fabricated = [
            'gatepass_items', 'gatepass_approvals', 'gatepass_workflow_instances', 'gatepasses',
            'visit_badges', 'scan_logs', 'visits', 'visitors', 'approval_delegates',
            'notification_preferences', 'notification_devices', 'workflow_gatepass_type', 'workflow_steps', 'workflows',
        ];

        foreach ($fabricated as $table) {
            $db->exec("DELETE FROM `{$table}` WHERE tenant_id = {$demoId}");
        }

        $evidence = ['audit_logs', 'notifications', 'email_outbox', 'notification_outbox'];

        foreach ($evidence as $table) {
            if ($options['purge']) {
                $db->exec("DELETE FROM `{$table}` WHERE tenant_id = {$demoId}");
            } else {
                $kept = (int) $db->query("SELECT COUNT(*) FROM `{$table}` WHERE tenant_id = {$demoId}")->fetchColumn();
                $stats[$table] = "kept {$kept} row(s) of tester evidence";
            }
        }

        $stats['reset'] = 'fabricated rows deleted';
    }

    // -----------------------------------------------------------------
    // Reference clone (only fills tables the demo tenant has no rows in).
    // -----------------------------------------------------------------
    $roleMap = [];

    foreach ($referenceTables as $table) {
        $has = (int) $db->query("SELECT COUNT(*) FROM `{$table}` WHERE tenant_id = {$demoId}")->fetchColumn();

        if ($has > 0) {
            $stats[$table] = "already present ({$has})";
            continue;
        }

        $columns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME NOT IN ('tenant_id','id')
            ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);

        $select = '`' . implode('`, `', $columns) . '`';
        $insert = '`' . implode('`, `', $columns) . '`';

        $db->exec("INSERT INTO `{$table}` (tenant_id, {$insert})
            SELECT {$demoId}, {$select} FROM `{$table}` WHERE tenant_id = {$templateId}");

        $stats[$table] = 'cloned';
    }

    // Always resolved — the clone above is skipped when the tenant already has roles.
    foreach ($db->query("SELECT id, name FROM roles WHERE tenant_id = {$demoId}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $roleMap[$row['name']] = (int) $row['id'];
    }

    foreach (['Receptionist', 'Security Officer', 'Department Head'] as $needed) {
        if (!isset($roleMap[$needed])) {
            throw new RuntimeException("Role '{$needed}' is missing from the template tenant.");
        }
    }

    // Permissions: map the template's grants onto the cloned roles by name.
    if ($roleMap) {
        $grants = $db->prepare('
            INSERT IGNORE INTO role_permissions (role_id, tenant_id, permission_id)
            SELECT :new_role, :tenant, rp.permission_id
            FROM role_permissions rp
            WHERE rp.tenant_id = :src AND rp.role_id = :old_role
        ');

        $oldRoles = $db->query("SELECT id, name FROM roles WHERE tenant_id = {$templateId}")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($oldRoles as $old) {
            if (isset($roleMap[$old['name']])) {
                $grants->execute([
                    ':new_role' => $roleMap[$old['name']],
                    ':tenant'   => $demoId,
                    ':src'      => $templateId,
                    ':old_role' => (int) $old['id'],
                ]);
            }
        }

        $stats['role_permissions'] = 'granted for ' . count($roleMap) . ' roles';
    }

    // Gatepass numbering has to continue from whatever the UI creates next.
    $numbering = $db->prepare('SELECT config_json FROM tenant_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1');
    $numbering->execute([$templateId, 'gatepass_numbering']);
    $numberConfig = json_decode((string) ($numbering->fetchColumn() ?: '{}'), true) ?: [];

    $numberConfig += ['prefix' => 'GPN', 'padding' => 4, 'include_year' => true, 'reset_yearly' => true];

    $upsertSetting = $db->prepare('
        INSERT INTO tenant_settings (tenant_id, setting_key, config_json, setting_value, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE config_json = VALUES(config_json), setting_value = VALUES(setting_value), updated_at = NOW()
    ');

    // Outbound is disabled at the DATA layer, independent of .env.
    $upsertSetting->execute([$demoId, 'mail', json_encode(\App\Core\Mailer::defaults()), '{"last_seq":0}']);
    $upsertSetting->execute([$demoId, 'sms', json_encode(\App\Core\Notification\NotificationGateway::defaults()), null]);
    $upsertSetting->execute([$demoId, 'push', json_encode(\App\Core\Notification\NotificationGateway::defaults()), null]);
    $upsertSetting->execute([$demoId, 'gatepass_numbering', json_encode($numberConfig), json_encode(['last_seq' => 0, 'sequence' => 4])]);
    $upsertSetting->execute([$demoId, 'badge_expiry_hours', '{"max":72,"min":1,"type":"number"}', '{"value":8}']);
    $upsertSetting->execute([$demoId, 'auto_checkout_hours', '{"max":24,"min":1,"type":"number"}', '{"value":12}']);

    $stats['mail/sms/push'] = 'driver=null, enabled=false (nothing leaves this install)';

    // -----------------------------------------------------------------
    // Users
    // -----------------------------------------------------------------
    $departments = $db->query("SELECT id, code FROM departments WHERE tenant_id = {$demoId}")->fetchAll(PDO::FETCH_ASSOC);
    $deptIds     = array_map(fn($d) => (int) $d['id'], $departments) ?: [];

    $unmapped = [];

    foreach (['User', 'Receptionist', 'Security Officer'] as $candidate) {
        if (isset($roleMap[$candidate])) {
            $fallbackRoleName = $candidate;
            $fallbackRoleId   = $roleMap[$candidate];
            break;
        }
    }

    $fallbackRoleName ??= (string) array_key_first($roleMap);
    $fallbackRoleId   ??= (int) reset($roleMap);

    $hash = password_hash($options['password'], PASSWORD_BCRYPT);

    $roster = [
        ['Owner',      'Achieng',   'owner',      'Owner',            1, false],
        ['Grace',      'Wanjiru',   'gm',         'General Manager',  1, false],
        ['Samuel',     'Otieno',    'head_it',    'Department Head',  2, false],
        ['Peter',      'Kamau',     'head_fin',   'Department Head',  3, false],
        ['Mary',       'Njeri',     'sec1',       'Security Officer', 1, false],
        ['John',       'Mwangi',    'sec2',       'Security Officer', 1, false],
        ['Faith',      'Chebet',    'rec1',       'Receptionist',     1, false],
        ['Brian',      'Omondi',    'rec2',       'Receptionist',     1, false],
        ['Alice',      'Nyambura',  'staff1',     'User',             2, false],
        ['David',      'Kimani',    'staff2',     'User',             3, false],
        ['Joseph',     'Mutua',     'staff3',     'User',             4, false],
    ];

    $insertUser = $db->prepare('
        INSERT INTO users (tenant_id, email, phone, password_hash, first_name, last_name, username,
                           is_active, is_admin, department_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())
    ');

    $usersByRole = [];
    $userIds     = [];

    foreach ($roster as $i => [$first, $last, $slug, $role, $deptSlot, $isAdmin]) {
        $email = strtolower($first . '.' . $last) . '@' . strtolower($options['tenant']) . '.example';

        $exists = $db->prepare('SELECT id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1');
        $exists->execute([$demoId, $email]);

        if ($id = $exists->fetchColumn()) {
            $id = (int) $id;
        } else {
            $insertUser->execute([
                $demoId,
                $email,
                '+2547' . str_pad((string) (1000000 + mt_rand(0, 8999999)), 7, '0', STR_PAD_LEFT),
                $hash,
                $first,
                $last,
                $slug . '.' . strtolower($options['tenant']),
                $isAdmin ? 1 : 0,
                $deptIds[($deptSlot - 1) % max(1, count($deptIds))] ?? null,
            ]);

            $id = (int) $db->lastInsertId();
        }

        $userIds[$slug] = $id;
        $usersByRole[$role][] = $id;

        // A fresh schema seeds fewer roles than a drifted one, so a named role
        // can be absent — never leave a demo user with no permissions at all.
        $roleId = $roleMap[$role] ?? $fallbackRoleId;

        $db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)')
           ->execute([$id, $roleId, $demoId]);

        if (!isset($roleMap[$role])) {
            $unmapped[] = $role;
        }
    }

    $stats['users'] = count($userIds) . ' across ' . count($usersByRole) . ' roles';

    if ($unmapped) {
        $stats['role fallback'] = count($unmapped) . ' users placed on "' . $fallbackRoleName
            . '" (template has no ' . implode('/', array_unique($unmapped)) . ')';
    }

    // -----------------------------------------------------------------
    // Gates (plain key printed once; only its sha256 is stored)
    // -----------------------------------------------------------------
    $gateKeys = [];

    foreach ([['Main Gate', 'MAIN'], ['Staff Entrance', 'STAFF'], ['Cargo Gate', 'CARGO']] as [$name, $code]) {
        $existing = $db->prepare('SELECT id FROM gates WHERE tenant_id = ? AND code = ? LIMIT 1');
        $existing->execute([$demoId, $code]);

        if ($gid = $existing->fetchColumn()) {
            $gateKeys[$code] = ['id' => (int) $gid, 'key' => 'existing (rotate from Settings > Gates)'];
            continue;
        }

        // DeviceScanController only accepts ^gk_[0-9a-f]{40}$ — anything else is
        // stored fine but can never authenticate a scan.
        $plain = 'gk_' . bin2hex(random_bytes(20));

        $db->prepare('INSERT INTO gates (tenant_id, name, code, key_hash, is_active) VALUES (?, ?, ?, ?, 1)')
           ->execute([$demoId, $name, $code, hash('sha256', $plain)]);

        $gateKeys[$code] = ['id' => (int) $db->lastInsertId(), 'key' => $plain];
    }

    $stats['gates'] = count($gateKeys) . ' devices';

    // -----------------------------------------------------------------
    // Workflows: one short, one two-step
    // -----------------------------------------------------------------
    $workflowOf = [];

    foreach ([
        ['name' => 'Single step — visitor', 'steps' => [['Reception check', 'Receptionist', 'any']], 'types' => ['VISITOR', 'DELIVERY']],
        ['name' => 'Two step — assets',     'steps' => [
            ['Security review', 'Security Officer', 'any'],
            ['Department sign-off', 'Department Head', 'all'],
        ], 'types' => ['VEHICLE', 'EQUIP_OUT', 'CONTRACTOR', 'TEMP_STAFF']],
    ] as $blueprint) {
        $db->prepare('INSERT INTO workflows (tenant_id, name, description, is_active, created_at)
            VALUES (?, ?, ?, 1, NOW())')
           ->execute([$demoId, $blueprint['name'], 'Seeded for the feedback environment.']);

        $workflowId = (int) $db->lastInsertId();
        $workflowOf[] = $workflowId;

        foreach ($blueprint['steps'] as $order => $step) {
            [$stepName, $roleName, $mode] = $step;

            $roleId = $roleMap[$roleName];

            $db->prepare('INSERT INTO workflow_steps
                (tenant_id, workflow_id, name, role_id, step_order, is_mandatory, approval_mode, department_source)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?)')
               ->execute([
                   $demoId, $workflowId, $stepName, $roleId, $order + 1, $mode, 'none',
               ]);
        }

        foreach ($blueprint['types'] as $typeCode) {
            $typeStmt = $db->prepare('SELECT id FROM gatepass_types WHERE tenant_id = ? AND type_code = ? LIMIT 1');
            $typeStmt->execute([$demoId, $typeCode]);

            if ($tid = $typeStmt->fetchColumn()) {
                $db->prepare('INSERT IGNORE INTO workflow_gatepass_type (tenant_id, workflow_id, gatepass_type_id)
                    VALUES (?, ?, ?)')->execute([$demoId, $workflowId, (int) $tid]);
            }
        }
    }

    $stats['workflows'] = count($workflowOf) . ' bound to gatepass types';

    // -----------------------------------------------------------------
    // Gatepasses — real engine for the in-flight ones
    // -----------------------------------------------------------------
    $statusOf = [];

    foreach ($db->query("SELECT id, code FROM gatepass_statuses WHERE tenant_id = {$demoId}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statusOf[strtolower($row['code'])] = (int) $row['id'];
    }

    $visitStatusOf = [];

    foreach ($db->query("SELECT id, code FROM visit_statuses WHERE tenant_id = {$demoId}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $visitStatusOf[strtolower($row['code'])] = (int) $row['id'];
    }

    foreach (['pending', 'approved', 'rejected', 'checked_in', 'checked_out'] as $needed) {
        if (!isset($statusOf[$needed])) {
            throw new RuntimeException("Gatepass status '{$needed}' is missing — clone reference data first.");
        }
    }

    $types = $db->query("SELECT id, type_code, name FROM gatepass_types WHERE tenant_id = {$demoId} AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    $typeByCode = [];
    foreach ($types as $t) {
        $typeByCode[$t['type_code']] = $t;
    }

    $purposes = [
        'Site survey with the client team', 'Contractor tooling for the server room',
        'Board meeting with investors', 'Delivery of two workstations',
        'Annual audit walk-through', 'Vendor demo of the access readers',
        'Camera install at the cargo gate', 'Facilities repair crew',
        'Bank courier cash collection', 'External trainer for the workshop',
    ];

    $numberSeq = (int) ($numberConfig['last_seq'] ?? 0);
    $makeNumber = function () use (&$numberSeq, $numberConfig, $db, $demoId): string {
        $numberSeq++;
        $db->prepare('UPDATE tenant_settings SET setting_value = ? WHERE tenant_id = ? AND setting_key = ?')
           ->execute([json_encode(['last_seq' => $numberSeq, 'sequence' => 4]), $demoId, 'gatepass_numbering']);

        $parts = [$numberConfig['prefix']];

        if (!empty($numberConfig['include_year'])) {
            $parts[] = date('Y');
        }

        $parts[] = str_pad((string) $numberSeq, max(2, (int) $numberConfig['padding']), '0', STR_PAD_LEFT);

        return implode('-', $parts);
    };

    $service   = new ApprovalService();
    $requesters = array_values(array_intersect_key($userIds, array_flip(['staff1', 'staff2', 'staff3'])));
    $approvers  = $usersByRole['Receptionist'] ?? [];
    $security   = $usersByRole['Security Officer'] ?? [];

    $insertGatepass = $db->prepare('
        INSERT INTO gatepasses
            (tenant_id, gatepass_number, scan_token, gatepass_type_id, status_id, department_id,
             purpose, needs_approval, is_returnable, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $backdate = function (int $hours): string {
        return date('Y-m-d H:i:s', time() - $hours * 3600);
    };

    $counts = ['pending' => 0, 'in_progress' => 0, 'approved' => 0, 'rejected' => 0, 'terminal' => 0];
    $warnings = [];
    $made   = 0;

    while ($made < max(1, $options['rows'])) {
        $type = $types[$made % count($types)];
        $age  = [2, 6, 26, 50, 74, 120, 170][mt_rand(0, 6)] + mt_rand(0, 5);

        // Every tenth gatepass is deliberately overdue so the escalation sweep has work.
        $isOverdue = $made % 10 === 9;
        $hoursAgo  = $isOverdue ? mt_rand(120, 400) : $age;

        $requester = $requesters[$made % count($requesters)];
        $returnable = ($type['type_code'] === 'EQUIP_OUT') ? 1 : 0;
        $number     = $makeNumber();
        $createdAt  = $backdate($hoursAgo);

        $workflowId = (int) $db->query("
            SELECT wgt.workflow_id FROM workflow_gatepass_type wgt
            WHERE wgt.tenant_id = {$demoId} AND wgt.gatepass_type_id = " . (int) $type['id'] . " LIMIT 1
        ")->fetchColumn();

        $needsApproval = $workflowId > 0 ? 1 : 0;

        $insertGatepass->execute([
            $demoId, $number, bin2hex(random_bytes(32)), (int) $type['id'],
            $statusOf['pending'] ?? null, $deptIds[$made % max(1, count($deptIds))] ?? null,
            $purposes[$made % count($purposes)], $needsApproval, $returnable, $requester, $createdAt,
        ]);

        $gatepassId = (int) $db->lastInsertId();
        $made++;

        if (!$needsApproval) {
            $db->prepare('UPDATE gatepasses SET status_id = ? WHERE id = ?')
               ->execute([$statusOf['approved'] ?? null, $gatepassId]);
            $counts['terminal']++;
            continue;
        }

        // The engine resolves approvers, fans out rows, freezes the step snapshot.
        $_SESSION['user'] = ['id' => $requester, 'tenant_id' => $demoId,
            'first_name' => 'Seeder', 'last_name' => 'Demo', 'username' => 'seeder'];

        try {
            $service->startWorkflow($demoId, $gatepassId, $workflowId);
        } catch (Throwable $e) {
            $warnings[] = 'start ' . $number . ': ' . $e->getMessage();
            $counts['terminal']++;
            continue;
        }

        $instanceId = (int) $db->query("
            SELECT id FROM gatepass_workflow_instances
            WHERE tenant_id = {$demoId} AND gatepass_id = {$gatepassId}
            ORDER BY id DESC LIMIT 1
        ")->fetchColumn();

        $service->discardNotifications();

        $stepCount = count($db->query("SELECT id FROM workflow_steps WHERE workflow_id = {$workflowId}")->fetchAll());

        // The engine fans out one row per role holder and cancels the siblings
        // once an 'any' step clears, so rows must be re-read one at a time and
        // always acted on by THEIR OWN approver.
        $firstPending = function (?int $stepId = null) use ($db, $demoId, $instanceId): ?array {
            $stmt = $db->prepare("
                SELECT id, approver_user_id, workflow_step_id FROM gatepass_approvals
                WHERE tenant_id = ? AND workflow_instance_id = ? AND status = 'pending'
                  " . ($stepId === null ? '' : ' AND workflow_step_id = ?') . "
                ORDER BY id LIMIT 1
            ");

            $stmt->execute($stepId === null ? [$demoId, $instanceId] : [$demoId, $instanceId, $stepId]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        /** Clear the current step; returns how many approvals were acted on. */
        $advanceStep = function () use ($db, $demoId, $instanceId, $service, $firstPending, &$warnings, $number): int {
            $row = $firstPending();

            if (!$row) {
                return 0;
            }

            $stepId = (int) $row['workflow_step_id'];
            $acted  = 0;

            for ($guard = 0; $guard < 25; $guard++) {
                $_SESSION['user']['id'] = (int) $row['approver_user_id'];

                try {
                    $service->approve((int) $row['id'], (int) $row['approver_user_id']);
                } catch (Throwable $e) {
                    $warnings[] = 'approve ' . $number . ': ' . $e->getMessage();
                    break;
                }

                $service->discardNotifications();
                $acted++;

                $next = $firstPending($stepId);

                if (!$next) {
                    break;
                }

                $row = $next;
            }

            return $acted;
        };

        $roll = $made % 10;

        if ($roll < 4) {
            $counts['pending']++;
        } elseif ($roll < 6 && $stepCount > 1) {
            $advanceStep();

            $db->prepare("UPDATE gatepass_approvals SET acted_at = ? WHERE workflow_instance_id = ? AND acted_at IS NOT NULL")
               ->execute([$backdate(max(1, $hoursAgo - 6)), $instanceId]);

            $counts['in_progress']++;
        } elseif ($roll < 8) {
            for ($pass = 0; $pass < max(1, $stepCount); $pass++) {
                if ($advanceStep() === 0) {
                    break;
                }
            }

            $db->prepare("UPDATE gatepass_approvals SET acted_at = ? WHERE workflow_instance_id = ? AND acted_at IS NOT NULL")
               ->execute([$backdate(max(1, $hoursAgo - 3)), $instanceId]);

            $counts['approved']++;
        } elseif ($roll === 8) {
            $row = $firstPending();

            if ($row) {
                $_SESSION['user']['id'] = (int) $row['approver_user_id'];

                try {
                    $service->reject((int) $row['id'], (int) $row['approver_user_id'], 'Purpose not justified in the request.');
                    $service->discardNotifications();
                    $counts['rejected']++;
                } catch (Throwable $e) {
                    $warnings[] = 'reject ' . $number . ': ' . $e->getMessage();
                    $counts['terminal']++;
                }
            } else {
                $counts['terminal']++;
            }
        } else {
            $db->prepare("UPDATE gatepasses SET status_id = ?, checked_in_by = ?, actual_in = ? WHERE id = ?")
               ->execute([$statusOf['approved'] ?? null, $security[0] ?? $requester, $backdate(max(1, $hoursAgo - 2)), $gatepassId]);

            $counts['terminal']++;
        }

        // Pending approvals age so the "waiting since" sort has a spread.
        $db->prepare("UPDATE gatepass_approvals SET created_at = ? WHERE tenant_id = ? AND workflow_instance_id = ? AND status = 'pending'")
           ->execute([$backdate($hoursAgo), $demoId, $instanceId]);
    }

    $stats['gatepasses'] = implode(', ', $counts);

    if ($warnings) {
        $distinct = array_unique(array_map(fn($w) => substr($w, strpos($w, ': ') + 2), $warnings));

        $stats['engine skips'] = count($warnings) . ' — ' . implode(' | ', array_slice($distinct, 0, 3));
    }

    // -----------------------------------------------------------------
    // Items on asset passes, check-in/out + scan trail
    // -----------------------------------------------------------------
    $assetPasses = $db->query("SELECT id, tenant_id FROM gatepasses WHERE tenant_id = {$demoId} AND is_returnable = 1")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assetPasses as $i => $gp) {
        $db->prepare('INSERT INTO gatepass_items (tenant_id, gatepass_id, item_name, quantity, serial_number, is_returnable, returned_quantity)
            VALUES (?, ?, ?, ?, ?, 1, ?)')
           ->execute([$demoId, (int) $gp['id'], $purposes[$i % count($purposes)], mt_rand(1, 4),
               'SN-' . strtoupper(substr(md5((string) $gp['id']), 0, 8)), mt_rand(0, 1)]);
    }

    $checkedIn = $db->query("SELECT id, created_at FROM gatepasses WHERE tenant_id = {$demoId} AND status_id = " . ($statusOf['approved'] ?? 0) . " LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

    $gateIds = array_map(fn($g) => (int) $g['id'], array_values($gateKeys));

    foreach ($checkedIn as $i => $gp) {
        $state = ['checked_in', 'checked_out'][$i % 2];
        $gate  = $gateIds[$i % max(1, count($gateIds))];
        $inAt  = $backdate(mt_rand(4, 40));
        $outAt = $state === 'checked_out' ? $backdate(mt_rand(0, 3)) : null;

        $db->prepare('UPDATE gatepasses
            SET status_id = ?, checked_in_by = ?, actual_in = ?, last_in_gate_id = ?,
                checked_out_by = ?, actual_out = ?, last_out_gate_id = ?
            WHERE id = ?')
           ->execute([
               $statusOf[$state], $security[0] ?? null, $inAt, $gate,
               $outAt !== null ? ($security[1] ?? $security[0] ?? null) : null,
               $outAt,
               $outAt !== null ? $gate : null,
               (int) $gp['id'],
           ]);

        $db->prepare('INSERT INTO scan_logs (tenant_id, gate_id, subject_type, subject_id, action, source, user_id, detail, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$demoId, $gate, 'gatepass', (int) $gp['id'], 'in',
               $i % 3 === 0 ? 'device' : 'manual', $security[0] ?? null, 'Demo scan', $inAt]);

        if ($outAt !== null) {
            $db->prepare('INSERT INTO scan_logs (tenant_id, gate_id, subject_type, subject_id, action, source, user_id, detail, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$demoId, $gate, 'gatepass', (int) $gp['id'], 'out', 'manual',
                   $security[1] ?? $security[0] ?? null, 'Demo scan', $outAt]);
        }
    }

    // -----------------------------------------------------------------
    // Visits (reception traffic)
    // -----------------------------------------------------------------
    $visitTypes   = $db->query("SELECT id FROM visit_types WHERE tenant_id = {$demoId}")->fetchAll(PDO::FETCH_COLUMN);
    $idTypes      = $db->query("SELECT id FROM identification_types WHERE tenant_id = {$demoId}")->fetchAll(PDO::FETCH_COLUMN);
    $hostIds      = array_values($userIds);

    $visitorNames = [['Amina','Hassan'],['Carlos','Mendes'],['Wanjiku','Maina'],['Kwame','Boateng'],
        ['Lena','Fischer'],['Omar','Sayed'],['Nadia','Ali'],['Tariq','Juma'],['Elena','Petrova']];

    for ($i = 0; $i < max(0, $options['visits']); $i++) {
        $name = $visitorNames[$i % count($visitorNames)];

        $db->prepare('INSERT INTO visitors (tenant_id, first_name, last_name, id_type_id, id_number, phone, email, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$demoId, $name[0], $name[1], $idTypes[$i % max(1, count($idTypes))] ?? null,
               'ID' . mt_rand(100000, 999999), '+2547' . mt_rand(10000000, 99999999),
               strtolower($name[0] . $name[1] . $i) . '@example.com', $hostIds[$i % count($hostIds)], $backdate(mt_rand(1, 500))]);

        $visitorId = (int) $db->lastInsertId();

        $code = ['scheduled', 'checked_in', 'checked_out', 'no_show'][$i % 4];
        $age  = mt_rand(1, 300);

        $db->prepare('INSERT INTO visits (tenant_id, department_id, visitor_id, host_user_id, visit_type_id,
                visit_status_id, scan_token, purpose, expected_in, expected_out, checkin_time, checkout_time, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([
               $demoId, $deptIds[$i % max(1, count($deptIds))] ?? null, $visitorId,
               $hostIds[$i % count($hostIds)], $visitTypes[$i % max(1, count($visitTypes))] ?? null,
               $visitStatusOf[$code] ?? null, bin2hex(random_bytes(32)), $purposes[$i % count($purposes)],
               $backdate($age), $backdate(max(0, $age - 3)),
               in_array($code, ['checked_in', 'checked_out'], true) ? $backdate($age) : null,
               $code === 'checked_out' ? $backdate(max(0, $age - 2)) : null,
               $hostIds[$i % count($hostIds)], $backdate($age + 1),
           ]);

        $visitId = (int) $db->lastInsertId();

        if ($code !== 'no_show') {
            $db->prepare('INSERT INTO visit_badges (tenant_id, visit_id, badge_code, is_active, printed_at, returned_at)
                VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$demoId, $visitId, 'VB-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                   $code === 'checked_out' ? 0 : 1, $backdate($age), $code === 'checked_out' ? $backdate(max(0, $age - 2)) : null]);
        }

        $db->prepare('INSERT INTO scan_logs (tenant_id, gate_id, subject_type, subject_id, action, source, user_id, detail, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$demoId, $gateIds[$i % max(1, count($gateIds))] ?? null, 'visit', $visitId,
               $code === 'checked_out' ? 'out' : 'in', 'manual', $security[0] ?? null, 'Desk check-in', $backdate($age)]);
    }

    $stats['visits'] = $options['visits'] . ' with visitors and badges';

    // -----------------------------------------------------------------
    // One live delegation + a handful of in-app notifications
    // -----------------------------------------------------------------
    if (($approvers[0] ?? null) && ($security[0] ?? null)) {
        $db->prepare('INSERT INTO approval_delegates (tenant_id, user_id, delegate_user_id, role_id, starts_at, ends_at, note)
            VALUES (?, ?, ?, ?, NOW() - INTERVAL 2 DAY, NOW() + INTERVAL 5 DAY, ?)')
           ->execute([$demoId, $security[0], $approvers[0], $roleMap['Security Officer'] ?? null,
               'Covering while on leave (seeded).']);

        $stats['delegation'] = '1 active';
    }

    $titles = [
        ['info', 'Approval needed', 'A gatepass is waiting on your step.'],
        ['success', 'Gatepass approved', 'Your gatepass completed all approval steps.'],
        ['warning', 'Gatepass rejected', 'A request was rejected — see the reason in the trail.'],
        ['info', 'Escalation notice', 'An approval has been pending past the reminder window.'],
    ];

    $note = 0;

    foreach (array_slice($userIds, 0, 6) as $uid) {
        [$type, $title, $body] = $titles[$note % count($titles)];

        $db->prepare('INSERT INTO notifications (tenant_id, user_id, type, title, body, link, read_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$demoId, $uid, $type, $title, $body, '/approvals',
               $note % 3 === 0 ? null : $backdate($note + 1), $backdate($note * 2 + 1)]);

        $note++;
    }

    $stats['notifications'] = $note . ' rows (some unread)';

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    exit("\nSeed failed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}

// ---------------------------------------------------------------------------
// Evidence export — feedback that must survive a future rebuild.
// ---------------------------------------------------------------------------
if ($options['export']) {
    $payload = ['exported_at' => date('c'), 'tenant_id' => $demoId, 'tables' => []];

    foreach (['audit_logs', 'notifications', 'email_outbox', 'notification_outbox'] as $table) {
        $rows = $db->query("SELECT * FROM `{$table}` WHERE tenant_id = {$demoId} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $payload['tables'][$table] = $rows;
    }

    file_put_contents(BASE_PATH . '/' . ltrim($options['export'], '/'), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $stats['evidence'] = 'exported to ' . $options['export'];
}

echo "\n--- seeded ---\n";

foreach ($stats as $key => $value) {
    printf("  %-22s %s\n", $key, $value);
}

echo "\nLogin as any of these (password: {$options['password']})\n";

foreach ($roster as [$first, $last, $slug, $role]) {
    printf("  %-28s %s\n", strtolower($first . '.' . $last) . '@' . strtolower($options['tenant']) . '.example', $role);
}

echo "\nGate device keys (shown once — store the sha256 hash is what the DB keeps)\n";

foreach ($gateKeys as $code => $gate) {
    printf("  %-8s %s\n", $code, $gate['key']);
}

echo "\nServe it locally:  php -S 127.0.0.1:8123 bin/serve-demo.php"
   . "\nThen open          http://demo.gpms.local:8123  (hosts: 127.0.0.1 demo.gpms.local)"
   . "\nThe first host label is the tenant code ('" . $options['tenant'] . "'); a deployed install instead sets"
   . "\nDB_DATABASE=" . env('DB_DATABASE') . " in its own .env. Outbound is already disabled per-tenant.\n";
