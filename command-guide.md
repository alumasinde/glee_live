# Command guide

Every command runs from the project root (`C:\xampp\htdocs\glee_live`) with the
XAMPP `php` on PATH. All four `bin/` scripts share one convention: `--db=NAME`
overrides `DB_DATABASE` from `.env`, `--dry-run` changes nothing, and exit code
`0` = ran cleanly / `1` = fatal.

---

## Migrations

```
php bin/migrate.php status              list applied / pending / DRIFT per file
php bin/migrate.php up [N]              apply all pending (or the next N)
php bin/migrate.php down [N|all]        roll back the last N (default 1) or everything
php bin/migrate.php --db=NAME           run against NAME instead of DB_DATABASE from .env
```

`status` prints `applied (DRIFT!)` when an applied file's text changed after it
ran. That is bookkeeping only — fix the stored checksum, do not roll back.

---

## Test database (`glee_test`)

```
php bin/migrate.php status --db=glee_test   what the scratch schema is missing
php bin/migrate.php up --db=glee_test       build/advance it (creates the schema if absent)
```

Rebuild from scratch: the runner does `CREATE DATABASE IF NOT EXISTS`, so drop
the schema first, then `up`. Snippets use single quotes around the PHP so
PowerShell does not interpolate `$c`, and double quotes inside the PHP:

```
php -r 'require "vendor/autoload.php"; Dotenv\Dotenv::createImmutable(".")->load();
function env($k,$d=null){return $_ENV[$k]??$_SERVER[$k]??$d;}
$c=(require "config/database.php"); $x=$c["connections"][$c["default"]];
$pdo=new PDO(preg_replace("/dbname=[^;]+;?/","",$x["dsn"]),$x["username"],$x["password"]);
$pdo->exec("DROP DATABASE IF EXISTS glee_test");
$pdo->exec("CREATE DATABASE glee_test CHARACTER SET utf8mb4");'

php bin/migrate.php up --db=glee_test
```

Integration tests repoint the schema in `tests/bootstrap.php`; override with the
`TEST_DB_DATABASE` env var. Never run `down` / a full re-apply against the real
database — `0010_seed.sql` uses `REPLACE INTO`.

---

## PHPUnit

```
vendor/bin/phpunit                              everything (Unit + Integration)
vendor/bin/phpunit --testsuite Unit             no database access
vendor/bin/phpunit --testsuite Integration
vendor/bin/phpunit tests/Integration/ApprovalFlowP1Test.php      one file
vendor/bin/phpunit --filter testReassign        one method or a name fragment
vendor/bin/phpunit --testdox                    readable per-test output
```

Coverage requires `pcov.enabled=1` in `C:\xampp\php\php.ini` (already set):

```
vendor/bin/phpunit --coverage-html coverage/html
vendor/bin/phpunit --coverage-clover coverage/clover.xml
vendor/bin/phpunit --coverage-text --only-summary-for-coverage-text
```

---

## One command for all of it

```
bash test.sh                    unit + integration + phpstan + worker dry-runs
bash test.sh --quick            test suites only
bash test.sh --unit             unit suite only (no database)
bash test.sh --filter NAME      integration suite filtered by test name
bash test.sh --coverage         integration run also writes coverage/html
bash test.sh --lint             php -l over src bin routes config bootstrap tests
```

`test.sh` exits `0` only when every check passed; it also fails if migrations are
pending, and treats the 6-error PHPStan count as the baseline to beat.

---

## Static analysis

```
vendor/bin/phpstan analyse                  level 5 over src, routes, config, bootstrap
vendor/bin/phpstan analyse --no-progress    quieter CI output
vendor/bin/phpstan analyse --memory-limit=1G
```

Baseline as of 2026-10-25 is **6 pre-existing errors** (Redis x3,
RedisSessionHandler, an intentional `&&` guard in `ApprovalService`, unused
`AuthService::$mailer`, `GatepassTypeService.php:46`). Anything above 6 is new.
Psalm is unusable here (needs PHP >= 8.2.27; this box is 8.2.12).

---

## Queue workers and sweeps (`bin/`)

Outbox drain — email retries plus SMS/push:

```
php bin/notify-outbox.php --dry-run          what is due, send nothing
php bin/notify-outbox.php --limit=5          small manual run
php bin/notify-outbox.php --tenant=1
php bin/notify-outbox.php --help
```

Approval aging — re-notify overdue approvers, escalate hopeless ones to
`approval.reassign` holders:

```
php bin/approval-escalation.php --dry-run             candidates only, validates write SQL
php bin/approval-escalation.php --tenant=1 --limit=50
php bin/approval-escalation.php --reminder-hours=24   age before the approver is nudged again
php bin/approval-escalation.php --admin-hours=72      age before admins are told
php bin/approval-escalation.php --max-escalations=3   nudge cap per approval row
```

PII retention — anonymise old audit rows, purge read notifications:

```
php bin/audit-retention.php --dry-run                  counts only, writes nothing
php bin/audit-retention.php --audit-days=365           anonymise audit_logs older than N
php bin/audit-retention.php --notify-days=90           delete read notifications older than N
php bin/audit-retention.php --chunk=1000 --max-chunks=50
php bin/audit-retention.php --tenant=1
```

Email/SMS/push backoff is `15 * 2^(attempt-1)` minutes, capped at 5 attempts;
`email_outbox` is retried in place. Retention deliberately never purges the
outbox backlog.

Crontab:

```
0,5,10,15,20,25,30,35,40,45,50,55 * * * * cd /var/www/glee_live && /usr/bin/php bin/notify-outbox.php --limit=100 >> var/log/notify-outbox.log 2>&1
15 * * * *       cd /var/www/glee_live && /usr/bin/php bin/approval-escalation.php --limit=200 >> var/log/approval-escalation.log 2>&1
30 2 * * 0       cd /var/www/glee_live && /usr/bin/php bin/audit-retention.php >> var/log/audit-retention.log 2>&1
```

---

## Demo / feedback environment

A live-looking but synthetic install for outside testers. Isolation is by
**schema**, never by a code fork or a "demo" flag, so `--db=` is mandatory:

```
php bin/migrate.php up --db=glee_feedback          build the schema (creates it if absent)
php bin/seed-demo.php --db=glee_feedback           create + fill the demo tenant
php bin/seed-demo.php --db=glee_feedback --rows=80 --visits=50
php bin/seed-demo.php --db=glee_feedback --dry-run print the plan, write nothing
```

Re-seeding keeps tester feedback intact — `--reset` deletes only fabricated rows,
never `audit_logs` / `notifications` / the two outboxes:

```
php bin/seed-demo.php --db=glee_feedback --reset
php bin/seed-demo.php --db=glee_feedback --export-evidence=var/fb.json --reset
php bin/seed-demo.php --db=glee_feedback --reset --purge-evidence   throwaway rounds only
```

Repeated `--reset` deliberately accumulates `audit_logs`, so evidence is only
lost with `--purge-evidence`. Export first if you are about to purge.

Other flags: `--tenant=DEMO` (slug; drives the `@demo.example` emails),
`--template=gpms` (reference tenant whose gatepass types, departments and
departments/roles are cloned when the target has none), `--password=Demo!2026`,
`--seed=20261025` (same seed = same data), `--name`, `--help`.

Approvals are produced by the real workflow engine rather than inserted, so
`step_snapshot`, pending-row counts and final statuses always satisfy the
invariants — expect a mix of `pending` / `in_progress` / `approved` / `rejected`
plus deliberately overdue rows for `approval-escalation.php` to find.

### Reaching it in a browser

Two independent selectors decide what a visitor sees:

- **Schema** — `.env` `DB_DATABASE`. One install = one schema; the web app has no
  per-request override (`--db=` exists only on the CLI scripts).
- **Tenant** — the first label of the hostname (`LoginController::resolveTenantId()`),
  matched case-insensitively against `tenants.code`. `gpms.local` → tenant `gpms`;
  `demo.gpms.local` → tenant `DEMO` (the seeded one). A bare IP or `localhost`
  looks for a tenant coded `default`, which does not exist, so login 400s.

So the demo URL is `http://demo.<your-domain>`, and the demo *install* is whatever
`.env` points at `glee_feedback`.

Locally, without editing `.env` or Apache:

```
127.0.0.1 demo.gpms.local        <- add to C:\Windows\System32\drivers\etc\hosts (admin)
php -S 127.0.0.1:8123 bin/serve-demo.php
```

open `http://demo.gpms.local:8123` — the router pins `DB_DATABASE=glee_feedback`
before `.env` loads (`DEMO_DB=` overrides). Login as any seeded user
(`mary.njeri@demo.example` / `Demo!2026`); `/approvals` correctly 403s for the
Security Officer.

To exercise the gate hardware endpoint, use a printed device key (`gk_` + 40 hex,
shown once at seed time and only as its sha256 in the DB):

```
curl -X POST http://demo.gpms.local:8123/gate-scan -H "Content-Type: application/json" \
  -d '{"key":"gk_....","code":"GPN-2026-0014"}'
```

`code` accepts a gatepass number or a 64-hex scan token.

For outside testers over the internet, that means a second vhost (or second
server) whose `.env` has `DB_DATABASE=glee_feedback` — outbound stays off because
the demo tenant's `mail`/`sms`/`push` settings are the disabled defaults, whatever
`.env` says.

---

## Run the app locally

```
composer serve                     php -S 0.0.0.0:8000 -t public
php -S 127.0.0.1:8080 -t public    alternate port, http://127.0.0.1:8080
```

Prefer the built-in server for checks. The Apache vhost `gpms.local` maps to
`public/` and rewrites odd statuses (a 419 CSRF page surfaces as 500), so
compare against `php -S` before believing an HTTP code.

---

## Database one-offs

`mysql.exe` is broken on this machine (auth_gssapi error) — always go through
PHP + PDO using the `.env` credentials. `config/database.php` calls the global
`env()` helper, so any snippet must define it:

```
php -r 'require "vendor/autoload.php"; Dotenv\Dotenv::createImmutable(".")->load();
function env($k,$d=null){return $_ENV[$k]??$_SERVER[$k]??$d;}
$c=(require "config/database.php"); $x=$c["connections"][$c["default"]];
$pdo=new PDO($x["dsn"],$x["username"],$x["password"]);
foreach($pdo->query("SELECT COUNT(*) c FROM gatepasses") as $r){echo $r["c"],PHP_EOL;}'
```

Swap the schema for any database in the same DSN:

```
$dsn = preg_replace("/dbname=[^;]+/", "dbname=glee_test", $x["dsn"]);
```

---

## Dependencies

```
composer install              restore from composer.lock
composer update               refresh deps (touches composer.json/lock — review the diff)
composer dump-autoload -o     after adding classes when the optimised map is stale
```

---

## Before you share this project (zip, push, hand-off)

`.env` is git-ignored and untracked; `.env.example` is the committed template and
carries every key the code actually reads (`config/*.php` plus the MFA cipher).
Copy it and fill in the blanks:

```
cp .env.example .env                  # then edit DB_*, MAIL_*, APP_KEY
php -r "echo bin2hex(random_bytes(32));"   # -> APP_KEY (2FA secret encryption)
```

Check nothing secret crept back in before zipping:

```
git ls-files | grep -iE "\.env$|\.pem$|secret|credential"   # only .env.example is allowed
git grep -nI "DB_PASSWORD=\|MAIL_PASSWORD=\|key_hash" -- .  # must show no real values
```

Two things a clean working tree does NOT fix:

* **Git history keeps old `.env` blobs.** `git log --oneline -- .env` lists the
  commits that still contain the previous DB/mail/Redis passwords. Untracking
  stops new leaks; it does not revoke the old ones. If the repo or zip went out,
  change those credentials — they are the only ones still in the history.
* **Gate device keys are seeded as plaintext in demo data only.** Real installs
  store `hash('sha256', $plain)` in `gates.key_hash`; a key that appeared in a
  shared log or zip is burned — reissue it from Settings → Gates.
