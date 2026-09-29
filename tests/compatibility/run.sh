#!/usr/bin/env bash
# Test-only: never accepts a production URL, database name or existing WP path.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
case "${ECD_COMPAT_PROFILE:-}" in baseline|publishing|commerce|builder) ;; *) exit 1;; esac
root="$(cd "$(dirname "$0")/../.." && pwd)"
export ECD_COMPAT_WP="$(mktemp -d "$RUNNER_TEMP/ect-compat.XXXXXX")"
export ECD_COMPAT_EVIDENCE="$RUNNER_TEMP/compat-evidence"
export ECD_BROWSER_PASSWORD="$(openssl rand -hex 20)"
mkdir -p "$ECD_COMPAT_EVIDENCE"
wp=(wp --path="$ECD_COMPAT_WP" --no-color)
"${wp[@]}" core download --version=7.1.2 --locale=en_US
"${wp[@]}" config create --dbname=ect_compat --dbuser=root --dbpass=compatibility-disposable-only --dbhost=127.0.0.1:3307 --dbprefix=ectcompat_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" config set WP_DEBUG true --raw
"${wp[@]}" config set WP_DEBUG_DISPLAY false --raw
"${wp[@]}" config set WP_DEBUG_LOG "$ECD_COMPAT_EVIDENCE/php-debug.log"
"${wp[@]}" core install --url=http://localhost:8081 --title='Disposable compatibility lab' --admin_user=ci-admin --admin_password="$ECD_BROWSER_PASSWORD" --admin_email=ci@example.invalid --skip-email
bash "$root/scripts/build-zip.sh" --output "$RUNNER_TEMP/easy-countdown.zip" --report "$ECD_COMPAT_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$RUNNER_TEMP/easy-countdown.zip" --activate
"${wp[@]}" theme activate twentytwentyfive
"${wp[@]}" option update timezone_string Europe/Warsaw
"${wp[@]}" rewrite structure '/%postname%/'
# Pin the observed official distribution versions to make reruns comparable.
case "$ECD_COMPAT_PROFILE" in
  publishing) plugins=(wp-super-cache:3.1.3 autoptimize:3.1.16 wordpress-seo:28.6 contact-form-7:6.1.7);;
  commerce) plugins=(woocommerce:11.1.2 w3-total-cache:2.10.6);;
  builder) plugins=(elementor:4.3.2 seo-by-rank-math:1.0.279 query-monitor:4.0.7 limit-login-attempts-reloaded:3.3.10);;
  baseline) plugins=();;
esac
for spec in "${plugins[@]}"; do
  plugin="${spec%:*}"; version="${spec#*:}"
  "${wp[@]}" plugin install "$plugin" --version="$version" --activate
  "${wp[@]}" plugin verify-checksums "$plugin"
done
if [[ "$ECD_COMPAT_PROFILE" == commerce ]]; then
  "${wp[@]}" theme install storefront --version=4.6.2 --activate
  "${wp[@]}" config set WP_CACHE true --raw
  "${wp[@]}" w3-total-cache option set pgcache.enabled true --type=boolean
  "${wp[@]}" w3-total-cache option set pgcache.engine file
  "${wp[@]}" w3-total-cache option set browsercache.enabled true --type=boolean
  "${wp[@]}" w3-total-cache option set lazyload.enabled true --type=boolean
  "${wp[@]}" w3-total-cache fix_environment
fi
if [[ "$ECD_COMPAT_PROFILE" == builder ]]; then
  "${wp[@]}" theme install hello-elementor --version=3.5.1 --activate
fi
if [[ "$ECD_COMPAT_PROFILE" == publishing ]]; then
  "${wp[@]}" config set WP_CACHE true --raw
  "${wp[@]}" config set WPCACHEHOME "$ECD_COMPAT_WP/wp-content/plugins/wp-super-cache/"
  "${wp[@]}" eval 'wp_cache_enable(); wp_super_cache_enable();'
fi
# Runtime external services are intentionally unavailable; they are not certified here.
"${wp[@]}" config set WP_HTTP_BLOCK_EXTERNAL true --raw
"${wp[@]}" config set WP_ACCESSIBLE_HOSTS 'localhost,127.0.0.1'
mkdir -p "$ECD_COMPAT_WP/wp-content/mu-plugins"
cp "$root/tests/compatibility/observer.php" "$ECD_COMPAT_WP/wp-content/mu-plugins/ect-compat-observer.php"
"${wp[@]}" eval-file "$root/tests/compatibility/fixture.php" seed > "$ECD_COMPAT_EVIDENCE/fixture.json"
"${wp[@]}" plugin list --fields=name,status,version --format=json > "$ECD_COMPAT_EVIDENCE/plugins.json"
"${wp[@]}" theme list --status=active --fields=name,status,version --format=json > "$ECD_COMPAT_EVIDENCE/themes.json"
"${wp[@]}" eval-file "$root/tests/compatibility/fixture.php" inventory > "$ECD_COMPAT_EVIDENCE/environment.json"
# Native PHP workers and real database, not WordPress function doubles. No public bind.
setsid env PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8081 -t "$ECD_COMPAT_WP" "$root/tests/compatibility/router.php" > "$ECD_COMPAT_EVIDENCE/http.log" 2>&1 &
server=$!
trap 'kill -- -"$server" 2>/dev/null || true; unset ECD_BROWSER_PASSWORD' EXIT
for i in {1..25}; do
  if curl -fsS http://localhost:8081/wp-login.php -o /dev/null; then break; fi
  sleep 1
done
# Preserve failures independently instead of stopping before other evidence is collected.
http_result=0; browser_result=0
"$RUNNER_TEMP/compat-venv/bin/python" "$root/tests/compatibility/check_http.py" || http_result=$?
"$RUNNER_TEMP/compat-venv/bin/python" "$root/tests/compatibility/browser.py" || browser_result=$?
"${wp[@]}" eval-file "$root/tests/compatibility/fixture.php" inventory > "$ECD_COMPAT_EVIDENCE/environment-final.json"
python3 - <<'PY'
import hashlib,json,os,pathlib
root=pathlib.Path(os.environ['ECD_COMPAT_WP'])/'wp-content/plugins'
report={}
for package in root.iterdir():
 if not package.is_dir():continue
 files={str(p.relative_to(package)):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(package.rglob('*')) if p.is_file() and not p.is_symlink() and p.suffix in ('.php','.js','.css')}
 report[package.name]={'file_count':len(files),'source_digest':hashlib.sha256(json.dumps(files,sort_keys=True).encode()).hexdigest()}
(pathlib.Path(os.environ['ECD_COMPAT_EVIDENCE'])/'plugin-source-digests.json').write_text(json.dumps(report,indent=2)+'\n')
PY
# Negative attribution control: bootstrap other plugins with Easy Countdown skipped.
# Retain all resulting third-party diagnostics; never filter real image responses.
"${wp[@]}" --skip-plugins=easy-countdown eval 'if (defined("EMAIL_COUNTDOWN_TIMER_VERSION")) { WP_CLI::error("Control did not skip Easy Countdown."); } WP_CLI::line("Control: WordPress and other active plugins loaded without Easy Countdown.");' > "$ECD_COMPAT_EVIDENCE/without-easy-countdown.log" 2>&1
test "$http_result" -eq 0
test "$browser_result" -eq 0
