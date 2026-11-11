#!/usr/bin/env bash
# Fresh loopback-only WordPress/MySQL/OLS resources per cell, never production.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
case "${ECD_CACHE_PROFILE:?}" in baseline|litespeed-cache) ;; *) exit 1;; esac
case "${ECD_CACHE_JIT:?}" in 1235|disable) ;; *) exit 1;; esac
root="$(cd "$(dirname "$0")/../.." && pwd)"
export ECD_CACHE_SERVER=openlitespeed
export ECD_CACHE_WP="$(mktemp -d "$RUNNER_TEMP/ect-cache.XXXXXX")"
export ECD_CACHE_EVIDENCE="$RUNNER_TEMP/cache-evidence"
export ECD_CACHE_PASSWORD="$(openssl rand -hex 20)"
printf '::add-mask::%s\n' "$ECD_CACHE_PASSWORD"
mkdir -p "$ECD_CACHE_EVIDENCE"
touch "$ECD_CACHE_EVIDENCE/php-errors.log"
exec > >(tee "$ECD_CACHE_EVIDENCE/provision-and-test.log") 2>&1
git -C "$root" rev-parse HEAD > "$ECD_CACHE_EVIDENCE/source-commit.txt"
phase=provision
finish() {
  result=$?
  trap - EXIT
  set +e
  python3 "$root/tests/litespeed/server.py" stop
  stop_result=$?
  python3 "$root/tests/litespeed/native_logs.py" "$ECD_CACHE_EVIDENCE" --report "$ECD_CACHE_EVIDENCE/native-logs.json"
  native_result=$?
  python3 "$root/tests/cache-profiles/contracts.py" "$ECD_CACHE_EVIDENCE/checks.json" --report "$ECD_CACHE_EVIDENCE/acceptance.json"
  acceptance_result=$?
  python3 "$root/tests/litespeed/server.py" clean
  clean_result=$?
  if [[ "$result" == 0 && ( "$stop_result" != 0 || "$native_result" != 0 || "$acceptance_result" != 0 || "$clean_result" != 0 ) ]]; then result=1; fi
  if [[ "$result" != 0 ]]; then printf '::error title=LSAPI profile::%s JIT %s failed in %s; evidence stays negative.\n' "$ECD_CACHE_PROFILE" "$ECD_CACHE_JIT" "$phase"; fi
  unset ECD_CACHE_PASSWORD
  exit "$result"
}
trap finish EXIT
phase=database-start
python3 "$root/tests/litespeed/server.py" database
phase=site-provision
bash "$root/tests/cache-profiles/site.sh"
phase=server-start
python3 "$root/tests/litespeed/server.py" start
phase=paired-experiment
"$RUNNER_TEMP/cache-venv/bin/python" "$root/tests/cache-profiles/check.py"
