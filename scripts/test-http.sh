#!/usr/bin/env bash
# Loopback-only test against a freshly provisioned DB prefix. Never accepts a site URL.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
root="$(cd "$(dirname "$0")/.." && pwd)"
wpdir="$(mktemp -d "$RUNNER_TEMP/ect-http.XXXXXX")"
export ECD_HTTP_DISPOSABLE=1 ECD_HTTP_WP="$wpdir"
export ECD_HTTP_EVIDENCE="$RUNNER_TEMP/http-evidence"
mkdir -p "$ECD_HTTP_EVIDENCE"
wp=(wp --path="$wpdir" --no-color)
"${wp[@]}" core download --locale=en_US
"${wp[@]}" config create --dbname=ecd_ci --dbuser=root --dbpass=ecd-ci-only --dbhost=127.0.0.1:3307 --dbprefix=ecthttp_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" core install --url=http://127.0.0.1:8082 --title='Disposable HTTP benchmark' --admin_user=http-ci --admin_password="$(openssl rand -hex 20)" --admin_email=ci@example.invalid --skip-email
if [[ "${ECD_CACHE:-database}" == redis ]]; then
  "${wp[@]}" config set WP_REDIS_PREFIX ect-http-isolated
  "${wp[@]}" plugin install redis-cache --version=3.0.0 --activate
  "${wp[@]}" redis enable
fi
target="$wpdir/wp-content/plugins/email-countdown-timer"
archive="$(mktemp "$RUNNER_TEMP/ect-package.XXXXXX.zip")"
bash "$root/scripts/build-zip.sh" --output "$archive"
"${wp[@]}" plugin install "$archive"
rm -f "$archive"
mkdir -p "$target/fonts" "$wpdir/wp-content/mu-plugins"
# Test-only local runner font, never included in the ZIP or repository.
cp /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf "$target/fonts/fixture.ttf"
"${wp[@]}" plugin activate email-countdown-timer
cp "$root/tests/http/observer.php" "$wpdir/wp-content/mu-plugins/ect-http-observer.php"
"${wp[@]}" eval-file "$root/tests/http/fixture.php" seed
"${wp[@]}" eval-file "$root/tests/http/fixture.php" versions > "$ECD_HTTP_EVIDENCE/versions.json"
printf '' > "$ECD_HTTP_EVIDENCE/publications.jsonl"
# Multiworker CLI server allows true simultaneous PHP requests. This is not FPM.
setsid env PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8082 -t "$wpdir" > "$RUNNER_TEMP/ect-http-server.log" 2>&1 &
server=$!
export ECD_HTTP_SERVER_PID="$server"
trap 'kill -- -"$server" 2>/dev/null || true; rm -f "$target/fonts/fixture.ttf"' EXIT
for i in {1..30}; do curl -fsS http://127.0.0.1:8082/?ecd_action=render\&ecd=missing -o /dev/null 2>/dev/null || true; if kill -0 "$server" && curl -sI http://127.0.0.1:8082/ | grep -q HTTP; then break; fi; sleep 1; done
python3 "$root/tests/http/benchmark.py"
