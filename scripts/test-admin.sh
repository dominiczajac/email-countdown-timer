#!/usr/bin/env bash
# New disposable database prefix and loopback-only server; never a production target.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
root="$(cd "$(dirname "$0")/.." && pwd)"
wpdir="$(mktemp -d "$RUNNER_TEMP/ecd-admin.XXXXXX")"
mkdir -p "$RUNNER_TEMP/admin-evidence"
wp=(wp --path="$wpdir" --no-color)
export ECD_BROWSER_PASSWORD="$(openssl rand -hex 20)"
"${wp[@]}" core download --locale=en_US
"${wp[@]}" config create --dbname=ecd_ci --dbuser=root --dbpass=ecd-ci-only --dbhost=127.0.0.1:3307 --dbprefix=ecdui_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" core install --url=http://localhost:8081 --title='Disposable Admin UI' --admin_user=ci-admin --admin_password="$ECD_BROWSER_PASSWORD" --admin_email=ci@example.invalid --skip-email
install_dir="$wpdir/wp-content/plugins/email-countdown-timer"
mkdir -p "$install_dir"
cp "$root/email-countdown-timer.php" "$root/uninstall.php" "$root/readme.txt" "$root/LICENSE" "$install_dir/"
cp -R "$root/includes" "$root/assets" "$install_dir/"
"${wp[@]}" plugin activate email-countdown-timer
export ECD_BROWSER_PAGE_ID="$("${wp[@]}" post create --post_type=page --post_status=publish --post_title='Disposable Timer Page' --post_content='[ecd_timer id="alt-browser-test"]' --porcelain)"
php -S 127.0.0.1:8081 -t "$wpdir" > "$RUNNER_TEMP/admin-evidence/http.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true; unset ECD_BROWSER_PASSWORD' EXIT
for i in {1..20}; do curl -fsS http://localhost:8081/wp-login.php -o /dev/null && break; sleep 1; done
# Collect both reports even if one fails; the job still fails on any failed test.
browser_result=0
"$RUNNER_TEMP/ect-browser-venv/bin/python" "$root/tests/browser/admin.py" || browser_result=$?
alt_result=0
"$RUNNER_TEMP/ect-browser-venv/bin/python" "$root/tests/browser/alt.py" || alt_result=$?
performance_result=0
"${wp[@]}" eval-file "$root/tests/integration/performance.php" > "$RUNNER_TEMP/admin-evidence/performance.json" || performance_result=$?
python3 - <<'PY'
import gzip, json, os, pathlib, subprocess
paths=[pathlib.Path('assets/admin.css'),pathlib.Path('assets/admin.js')]
report={'source':subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip(),'custom_gzip_bytes':{str(p):len(gzip.compress(p.read_bytes(),mtime=0)) for p in paths}}
report['total_gzip_bytes']=sum(report['custom_gzip_bytes'].values())
assert report['total_gzip_bytes']<=15*1024
pathlib.Path(os.environ['RUNNER_TEMP']+'/admin-evidence/asset-budget.json').write_text(json.dumps(report,indent=2)+'\n')
PY

test "$browser_result" -eq 0
test "$performance_result" -eq 0
test "$alt_result" -eq 0
