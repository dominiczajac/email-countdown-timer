#!/usr/bin/env bash
# Provision only an owned disposable network. Standalone shell preserves errexit.
set -euo pipefail
[[ "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -d "$ECD_NETWORK_WP" && -d "$ECD_NETWORK_EVIDENCE" ]] || exit 1
[[ "$(realpath "$RUNNER_TEMP")" == "$RUNNER_TEMP" && "$(realpath "$ECD_NETWORK_WP")" == "$ECD_NETWORK_WP" ]] || exit 1
[[ "$(realpath "$ECD_NETWORK_EVIDENCE")" == "$RUNNER_TEMP/network-evidence" ]] || exit 1
case "$ECD_NETWORK_WP" in "$RUNNER_TEMP"/ect-network.*) ;; *) exit 1;; esac
case "${ECD_NETWORK_THEME:?}" in
  twentytwentyone) theme_version=2.9; theme_hash=51d4ac1dcaef18b0e35a1a313bce8115d3aa4ff315bb1143f95c223c38817d8b ;;
  twentytwentyfour) theme_version=1.6; theme_hash=9128150eff2682bf07ca7fdada2d2ed86a8f0d9210814d5369241e51e7a50baf ;;
  *) exit 1;;
esac
root="$(cd "$(dirname "$0")/../.." && pwd)"
export WP_CLI_CACHE_DIR="$RUNNER_TEMP/wp-cli-cache"
wp=(wp --path="$ECD_NETWORK_WP" --no-color)
phase=core-download
trap 'printf "::error title=Network provisioning::Failed in %s\n" "$phase"' ERR
archive="$(mktemp "$RUNNER_TEMP/ect-network-core.XXXXXX.zip")"
stage="$(mktemp -d "$RUNNER_TEMP/ect-network-stage.XXXXXX")"
curl --fail --location --retry 2 --proto '=https' --proto-redir '=https' https://wordpress.org/wordpress-7.1.2.zip -o "$archive"
printf '%s  %s\n' 8fc96c59a78b7219e4a130222b7fadb51b03e503e8b0123beaa7e28961c21ce2 "$archive" | sha256sum --check
sha256sum "$archive" > "$ECD_NETWORK_EVIDENCE/core-archive.sha256"
unzip -q "$archive" -d "$stage"
cp -a "$stage/wordpress/." "$ECD_NETWORK_WP/"
rm -f "$archive"
"${wp[@]}" core verify-checksums
phase=network-config
"${wp[@]}" config create --dbname=ecd_network --dbuser=root --dbpass=network-disposable-only --dbhost=127.0.0.1:3307 --dbprefix=ecdnetwork_ --skip-salts
"${wp[@]}" config shuffle-salts
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" config set AUTOMATIC_UPDATER_DISABLED true --raw
"${wp[@]}" config set WP_DEBUG true --raw
"${wp[@]}" config set WP_DEBUG_DISPLAY false --raw
"${wp[@]}" config set WP_DEBUG_LOG "$ECD_NETWORK_EVIDENCE/php-errors.log"
mkdir -p "$ECD_NETWORK_WP/wp-content/mu-plugins"
cp "$root/tests/multisite/observer.php" "$ECD_NETWORK_WP/wp-content/mu-plugins/ecd-network-lab.php"
"${wp[@]}" core multisite-install --url=http://localhost:8097 --title='Synthetic network' --admin_user=ci-admin --admin_password="$ECD_NETWORK_PASSWORD" --admin_email=ci@example.invalid --skip-email
for role in blue red; do "${wp[@]}" site create --slug="$role" --title="Synthetic $role" --email=ci@example.invalid; done
phase=theme-install
theme_archive="$(mktemp "$RUNNER_TEMP/ect-network-theme.XXXXXX.zip")"
curl --fail --location --retry 2 --proto '=https' --proto-redir '=https' "https://downloads.wordpress.org/theme/$ECD_NETWORK_THEME.$theme_version.zip" -o "$theme_archive"
printf '%s  %s\n' "$theme_hash" "$theme_archive" | sha256sum --check
sha256sum "$theme_archive" > "$ECD_NETWORK_EVIDENCE/theme-archive.sha256"
"${wp[@]}" theme install "$theme_archive"
python3 "$root/tests/multisite/verify-theme.py" "$theme_archive" "$ECD_NETWORK_WP/wp-content/themes/$ECD_NETWORK_THEME" \
  --slug "$ECD_NETWORK_THEME" --sha256 "$theme_hash" --report "$ECD_NETWORK_EVIDENCE/theme-files.json"
rm -f "$theme_archive"
"${wp[@]}" theme enable "$ECD_NETWORK_THEME" --network
for site in http://localhost:8097/ http://localhost:8097/blue/ http://localhost:8097/red/; do
  "${wp[@]}" --url="$site" theme activate "$ECD_NETWORK_THEME"
  "${wp[@]}" --url="$site" rewrite structure '/%postname%/'
done
phase=candidate-install
bash "$root/scripts/build-zip.sh" --output "$RUNNER_TEMP/easy-countdown-network.zip" --report "$ECD_NETWORK_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$RUNNER_TEMP/easy-countdown-network.zip"
python3 "$root/scripts/verify-distribution.py" "$ECD_NETWORK_EVIDENCE/distribution.json" "$ECD_NETWORK_WP/wp-content/plugins/easy-countdown"
"${wp[@]}" plugin activate easy-countdown --network
"${wp[@]}" config set WP_HTTP_BLOCK_EXTERNAL true --raw
"${wp[@]}" config set WP_ACCESSIBLE_HOSTS 'localhost,127.0.0.1'
phase=fixture-seed
"${wp[@]}" eval-file "$root/tests/multisite/fixture.php" seed
