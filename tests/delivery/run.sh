#!/usr/bin/env bash
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
root="$(cd "$(dirname "$0")/../.." && pwd)"
wpdir="$(mktemp -d "$RUNNER_TEMP/ect-delivery.XXXXXX")"
export ECD_DELIVERY_WP_PATH="$wpdir"
export ECD_DELIVERY_EVIDENCE="$RUNNER_TEMP/delivery-evidence"
mkdir -p "$ECD_DELIVERY_EVIDENCE"
wp=(wp --path="$wpdir" --no-color)
"${wp[@]}" core download --version=7.1.2 --locale=en_US
"${wp[@]}" config create --dbname=ect_delivery --dbuser=root --dbpass=delivery-disposable-only --dbhost=127.0.0.1:3307 --dbprefix=ectdelivery_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" core install --url=http://localhost:8092 --title='Delivery test' --admin_user=ci-admin --admin_password="$(openssl rand -hex 20)" --admin_email=ci@example.invalid --skip-email
bash "$root/scripts/build-zip.sh" --output "$RUNNER_TEMP/easy-countdown-delivery.zip" --report "$ECD_DELIVERY_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$RUNNER_TEMP/easy-countdown-delivery.zip" --activate
"${wp[@]}" eval-file "$root/tests/delivery/fixture.php"
"${wp[@]}" core version --extra > "$ECD_DELIVERY_EVIDENCE/versions.txt"
php -v >> "$ECD_DELIVERY_EVIDENCE/versions.txt"
git rev-parse HEAD >> "$ECD_DELIVERY_EVIDENCE/versions.txt"
php -S 127.0.0.1:8092 -t "$wpdir" > "$ECD_DELIVERY_EVIDENCE/http.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true' EXIT
for i in {1..20}; do if curl -fsS http://localhost:8092/wp-login.php -o /dev/null; then break; fi; sleep 1; done
result=0
"$RUNNER_TEMP/delivery-venv/bin/python" "$root/tests/delivery/layout.py" || result=$?
xvfb-run -a -s '-screen 0 1280x900x24' "$RUNNER_TEMP/delivery-venv/bin/python" "$root/tests/delivery/thunderbird.py" || result=$?
test "$result" -eq 0
