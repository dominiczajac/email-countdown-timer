#!/usr/bin/env bash
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
root="$(cd "$(dirname "$0")/../.." && pwd)"
wpdir="$(mktemp -d "$RUNNER_TEMP/ect-block.XXXXXX")"
export ECD_BLOCK_WP_PATH="$wpdir" ECD_BLOCK_EVIDENCE="$RUNNER_TEMP/block-evidence"
mkdir -p "$ECD_BLOCK_EVIDENCE"
wp=(wp --path="$wpdir" --no-color)
export ECD_BLOCK_PASSWORD="$(openssl rand -hex 20)"
"${wp[@]}" core download --version="${ECD_WORDPRESS_VERSION:?}" --locale=en_US
"${wp[@]}" config create --dbname=ect_block --dbuser=root --dbpass=block-disposable-only --dbhost=127.0.0.1:3307 --dbprefix=ectblock_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" core install --url=http://localhost:8093 --title='Block acceptance' --admin_user=ci-admin --admin_password="$ECD_BLOCK_PASSWORD" --admin_email=ci@example.invalid --skip-email
"${wp[@]}" theme install "${ECD_BLOCK_THEME:?}" --activate
"${wp[@]}" core verify-checksums
bash "$root/scripts/build-zip.sh" --output "$RUNNER_TEMP/easy-countdown-block.zip" --report "$ECD_BLOCK_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$RUNNER_TEMP/easy-countdown-block.zip" --activate
python3 "$root/scripts/verify-distribution.py" "$ECD_BLOCK_EVIDENCE/distribution.json" "$wpdir/wp-content/plugins/easy-countdown"
"${wp[@]}" eval-file "$root/tests/integration/gutenberg.php" > "$ECD_BLOCK_EVIDENCE/integration.txt"
export ECD_BLOCK_POST_ID="$("${wp[@]}" eval-file "$root/tests/gutenberg/fixture.php")"
{ git rev-parse HEAD; php -v; "${wp[@]}" core version --extra; "${wp[@]}" theme list --status=active --format=json; } > "$ECD_BLOCK_EVIDENCE/versions.txt"
php -S 127.0.0.1:8093 -t "$wpdir" > "$ECD_BLOCK_EVIDENCE/http.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true; unset ECD_BLOCK_PASSWORD' EXIT
for i in {1..20}; do if curl -fsS http://localhost:8093/wp-login.php -o /dev/null; then break; fi; sleep 1; done
"$RUNNER_TEMP/block-venv/bin/python" "$root/tests/gutenberg/browser.py"
