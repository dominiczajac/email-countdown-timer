#!/usr/bin/env bash
# This provisions a NEW local CI WordPress. Never accepts a production URL/path.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || {
  echo 'Disposable GitHub Actions environment required.' >&2; exit 1;
}
root="$(cd "$(dirname "$0")/.." && pwd)"
wpdir="$(mktemp -d "$RUNNER_TEMP/ecd-wordpress.XXXXXX")"
export ECD_WP_PATH="$wpdir"
echo "ECD_WP_PATH=$wpdir" >> "$GITHUB_ENV"
wp=(wp --path="$wpdir" --no-color)
"${wp[@]}" core download --version="${ECD_WORDPRESS_VERSION:?}" --locale=en_US
"${wp[@]}" config create --dbname=ecd_ci --dbuser=root --dbpass=ecd-ci-only --dbhost=127.0.0.1:3307 --dbprefix=ecdci_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" core install --url=http://localhost:8080 --title='Disposable integration test' --admin_user=ci-admin --admin_password="$(openssl rand -hex 20)" --admin_email=ci@example.invalid --skip-email
if [[ "${ECD_TOPOLOGY:?}" == multisite ]]; then
  "${wp[@]}" core multisite-convert --title='Disposable network'
fi
install_dir="$wpdir/wp-content/plugins/email-countdown-timer"
mkdir -p "$install_dir"
cp "$root/email-countdown-timer.php" "$root/uninstall.php" "$root/readme.txt" "$root/LICENSE" "$install_dir/"
cp -R "$root/includes" "$root/assets" "$install_dir/"
if [[ "${ECD_CACHE:?}" == redis ]]; then
  "${wp[@]}" plugin install redis-cache --version=3.0.0 --activate
  "${wp[@]}" config set WP_REDIS_HOST 127.0.0.1
  "${wp[@]}" config set WP_REDIS_PORT 6379 --raw
  "${wp[@]}" config set WP_REDIS_PREFIX ecd-ci
  "${wp[@]}" redis enable
  "${wp[@]}" redis status
fi
network=()
[[ "$ECD_TOPOLOGY" != multisite ]] || network=(--network)
"${wp[@]}" plugin activate email-countdown-timer "${network[@]}"
"${wp[@]}" core version --extra
"${wp[@]}" cli version
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" seed
php -S 127.0.0.1:8080 -t "$wpdir" > "$RUNNER_TEMP/ecd-http.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true' EXIT
for i in {1..20}; do
  if curl -fsS 'http://localhost:8080/?ecd_action=render&ecd=integration-timer&mode=email' -o "$RUNNER_TEMP/ecd-fixture.gif"; then break; fi
  sleep 1
done
php -r '$a=new Imagick(); $a->readImageBlob(file_get_contents($argv[1])); if($a->getNumberImages()!==60){throw new RuntimeException("Expected 60 GIF frames");} echo "HTTP GIF: 60 frames PASS\n";' "$RUNNER_TEMP/ecd-fixture.gif"
status="$(curl -sS -o /dev/null -w '%{http_code}' 'http://localhost:8080/?ecd_action=render&ecd=unknown-ci-timer&mode=email')"
[[ "$status" == 404 ]]
"${wp[@]}" plugin deactivate email-countdown-timer "${network[@]}"
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" retained
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" invoke-uninstall
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" retained
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" optin
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" invoke-uninstall
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" deleted
"${wp[@]}" plugin uninstall email-countdown-timer --skip-delete
"${wp[@]}" eval-file "$root/tests/integration/lifecycle.php" deleted
# A fresh PHP process verifies persistent cache coherence after actual uninstall.
echo "WORDPRESS LIFECYCLE PASS: version=$ECD_WORDPRESS_VERSION topology=$ECD_TOPOLOGY cache=$ECD_CACHE"
