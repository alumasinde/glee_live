#!/usr/bin/env bash
#
# Project test/check runner. Run from the repo root:
#
#   bash test.sh                 unit + integration + phpstan + worker dry-runs
#   bash test.sh --quick         unit + integration only
#   bash test.sh --coverage      integration run also writes coverage/html
#   bash test.sh --unit          unit suite only
#   bash test.sh --filter NAME   integration suite filtered to matching tests
#   bash test.sh --lint          php -l over src/ bin/ routes/ config/ instead of phpunit
#   bash test.sh --help          this message
#
# Exit 0 = everything passed, 1 = at least one check failed.

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

PHP="${PHP_BIN:-php}"
PHPUNIT="vendor/bin/phpunit"
PHPSTAN="vendor/bin/phpstan"

# Known pre-existing phpstan findings (level 5). Anything above this is new.
PHPSTAN_BASELINE=6

RUN_COVERAGE=0
RUN_LINT=0
RUN_UNIT=1
RUN_INTEGRATION=1
RUN_STATIC=1
RUN_WORKERS=1
FILTER=""

fail=0
declare -a results=()

record() { results+=("$1"); }

step_name() { printf '\n==> %s\n' "$1"; }

run_step() {
  local name="$1"; shift
  step_name "$name"
  if "$@"; then
    record "PASS  $name"
  else
    record "FAIL  $name"
    fail=1
  fi
}

usage() {
  sed -n '3,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
  exit 0
}

while [ $# -gt 0 ]; do
  case "$1" in
    --quick)     RUN_STATIC=0; RUN_WORKERS=0 ;;
    --unit)      RUN_INTEGRATION=0; RUN_STATIC=0; RUN_WORKERS=0 ;;
    --coverage)  RUN_COVERAGE=1 ;;
    --filter)    shift; FILTER="${1:-}" ;;
    --lint)      RUN_LINT=1; RUN_UNIT=0; RUN_INTEGRATION=0; RUN_STATIC=0; RUN_WORKERS=0 ;;
    -h|--help)   usage ;;
    *) printf 'Unknown option: %s (--help)\n' "$1" >&2; exit 2 ;;
  esac
  shift
done

if ! command -v "$PHP" >/dev/null 2>&1; then
  printf 'php not found (%s). Set PHP_BIN or put it on PATH.\n' "$PHP" >&2
  exit 1
fi

printf 'PHP: %s\n' "$("$PHP" -r 'echo PHP_VERSION;')"

# ---------------------------------------------------------------- lint
if [ "$RUN_LINT" -eq 1 ]; then
  step_name 'Syntax check (php -l)'
  lint_fail=0
  while IFS= read -r file; do
    "$PHP" -l "$file" >/dev/null 2>&1 || { printf '  %s\n' "$("$PHP" -l "$file" 2>&1)"; lint_fail=1; }
  done < <(find src bin routes config bootstrap tests -name '*.php' -type f 2>/dev/null | sort)
  if [ $lint_fail -eq 0 ]; then
    record 'PASS  php -l'
  else
    record 'FAIL  php -l'
    fail=1
  fi
fi

# ---------------------------------------------------------------- migrations
step_name 'Migration status'
pending=$("$PHP" bin/migrate.php status 2>/dev/null | grep -c '[[:space:]]pending[[:space:]]' || true)
drift=$("$PHP" bin/migrate.php status 2>/dev/null | grep -c 'DRIFT' || true)
printf '  pending: %s   drift-flagged: %s\n' "$pending" "$drift"
if [ "${pending:-0}" -gt 0 ]; then
  record "FAIL  schema is pending $pending migration(s) — php bin/migrate.php up"
  fail=1
fi
if [ "${drift:-0}" -gt 0 ]; then
  record "WARN  $drift migration file(s) changed after being applied (DRIFT)"
fi

# ---------------------------------------------------------------- tests
if [ "$RUN_UNIT" -eq 1 ]; then
  run_step 'Unit suite' "$PHP" "$PHPUNIT" --testsuite Unit
fi

if [ "$RUN_INTEGRATION" -eq 1 ]; then
  if [ -n "$FILTER" ]; then
    run_step "Integration suite ($FILTER)" "$PHP" "$PHPUNIT" --testsuite Integration --filter "$FILTER"
  elif [ "$RUN_COVERAGE" -eq 1 ]; then
    mkdir -p coverage
    run_step 'Integration suite + coverage' "$PHP" "$PHPUNIT" --testsuite Integration \
      --coverage-html coverage/html --coverage-text --only-summary-for-coverage-text
  else
    run_step 'Integration suite' "$PHP" "$PHPUNIT" --testsuite Integration
  fi
fi

# ---------------------------------------------------------------- static analysis
if [ "$RUN_STATIC" -eq 1 ]; then
  step_name 'PHPStan (level 5)'
  stan_out=$("$PHP" "$PHPSTAN" analyse --no-progress 2>&1)
  stan_count=$(printf '%s' "$stan_out" | grep -oE 'Found [0-9]+ error' | grep -oE '[0-9]+' | tail -1)
  stan_count=${stan_count:-0}
  printf '  findings: %s (baseline %s)\n' "$stan_count" "$PHPSTAN_BASELINE"
  if [ "$stan_count" -le "$PHPSTAN_BASELINE" ] && printf '%s' "$stan_out" | grep -qE 'Found [0-9]+ error|\[OK\]'; then
    record "PASS  phpstan ($stan_count)"
  elif [ "$stan_count" -gt "$PHPSTAN_BASELINE" ]; then
    printf '%s\n' "$stan_out" | tail -30
    record "FAIL  phpstan ($stan_count > baseline $PHPSTAN_BASELINE)"
    fail=1
  else
    printf '%s\n' "$stan_out" | tail -30
    record 'FAIL  phpstan crashed'
    fail=1
  fi
fi

# ---------------------------------------------------------------- workers
if [ "$RUN_WORKERS" -eq 1 ]; then
  for worker in notify-outbox approval-escalation audit-retention; do
    run_step "$worker --dry-run" "$PHP" "bin/$worker.php" --dry-run
  done
fi

# ---------------------------------------------------------------- summary
step_name 'Summary'
for line in "${results[@]}"; do
  printf '  %s\n' "$line"
done

if [ $fail -eq 0 ]; then
  printf '\nAll checks passed.\n'
else
  printf '\nOne or more checks failed.\n'
fi

exit $fail
