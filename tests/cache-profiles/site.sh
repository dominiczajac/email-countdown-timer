#!/usr/bin/env bash
# Shared owned-site provisioning; a failed command always stops this process.
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
wp=(wp --path="$ECD_CACHE_WP" --no-color)
phase=initialization
trap 'printf "::error title=Site provisioning::Failed in %s\n" "$phase"' ERR
[[ "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -d "$ECD_CACHE_WP" && -d "$ECD_CACHE_EVIDENCE" ]] || exit 1
[[ "$(realpath "$RUNNER_TEMP")" == "$RUNNER_TEMP" && "$(realpath "$ECD_CACHE_WP")" == "$ECD_CACHE_WP" ]] || exit 1
[[ "$(realpath "$ECD_CACHE_EVIDENCE")" == "$RUNNER_TEMP/cache-evidence" ]] || exit 1
case "$ECD_CACHE_WP" in "$RUNNER_TEMP"/ect-cache.*) ;; *) exit 1;; esac
case "$ECD_CACHE_SERVER:$ECD_CACHE_PROFILE" in
  nginx-fpm:baseline|nginx-fpm:wp-super-cache|nginx-fpm:w3-total-cache|nginx-fpm:autoptimize|nginx-fpm:elementor|nginx-fpm:woocommerce|openlitespeed:baseline|openlitespeed:litespeed-cache) ;;
  *) exit 1;;
esac
export WP_CLI_CACHE_DIR="$RUNNER_TEMP/wp-cli-cache"
mkdir -p "$WP_CLI_CACHE_DIR"
archive="$(mktemp "$RUNNER_TEMP/ect-cache-core.XXXXXX.zip")"
stage="$(mktemp -d "$RUNNER_TEMP/ect-cache-stage.XXXXXX")"
phase=wordpress-download
curl --fail --location --retry 2 --proto '=https' --proto-redir '=https' https://wordpress.org/wordpress-7.1.2.zip -o "$archive"
printf '%s  %s\n' '8fc96c59a78b7219e4a130222b7fadb51b03e503e8b0123beaa7e28961c21ce2' "$archive" | sha256sum --check
sha256sum "$archive" > "$ECD_CACHE_EVIDENCE/core-archive.sha256"
unzip -q "$archive" -d "$stage"
cp -a "$stage/wordpress/." "$ECD_CACHE_WP/"
rm -f "$archive"
"${wp[@]}" core verify-checksums
phase=wordpress-config
"${wp[@]}" config create --dbname=ect_cache_lab --dbuser=root --dbpass=cache-lab-disposable-only --dbhost=127.0.0.1:3307 --dbprefix=ectcache_ --skip-salts
"${wp[@]}" config shuffle-salts
if [[ "$ECD_CACHE_SERVER" == openlitespeed ]]; then
  "${wp[@]}" config set DB_HOST "getenv( 'ECD_LITESPEED_DB_HOST' ) ?: '127.0.0.1:3307'" --raw
fi
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" config set AUTOMATIC_UPDATER_DISABLED true --raw
"${wp[@]}" config set WP_DEBUG true --raw
"${wp[@]}" config set WP_DEBUG_DISPLAY false --raw
"${wp[@]}" config set WP_DEBUG_LOG "$ECD_CACHE_EVIDENCE/php-errors.log"
"${wp[@]}" core install --url=http://localhost:8095 --title='Isolated cache lab' --admin_user=ci-admin --admin_password="$ECD_CACHE_PASSWORD" --admin_email=ci@example.invalid --skip-email
"${wp[@]}" theme activate twentytwentyfive
"${wp[@]}" rewrite structure '/%postname%/'
if [[ "$ECD_CACHE_SERVER" == openlitespeed ]]; then cp "$root/tests/litespeed/wordpress.htaccess" "$ECD_CACHE_WP/.htaccess"; fi
phase=candidate-install
bash "$root/scripts/build-zip.sh" --output "$RUNNER_TEMP/easy-countdown-cache.zip" --report "$ECD_CACHE_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$RUNNER_TEMP/easy-countdown-cache.zip" --activate
python3 "$root/scripts/verify-distribution.py" "$ECD_CACHE_EVIDENCE/distribution.json" "$ECD_CACHE_WP/wp-content/plugins/easy-countdown"
case "$ECD_CACHE_PROFILE" in
  wp-super-cache) version=3.1.3 ;;
  w3-total-cache) version=2.10.6 ;;
  autoptimize) version=3.1.16 ;;
  elementor) version=4.3.2 ;;
  woocommerce) version=11.1.2 ;;
  litespeed-cache) version=7.9.3 ;;
  baseline) version='' ;;
esac
if [[ -n "$version" ]]; then
  phase=vendor-install
  if [[ "$ECD_CACHE_PROFILE" == litespeed-cache ]]; then
    vendor_archive="$(mktemp "$RUNNER_TEMP/ect-litespeed.XXXXXX.zip")"
    curl --fail --location --retry 2 --proto '=https' --proto-redir '=https' https://downloads.wordpress.org/plugin/litespeed-cache.7.9.3.zip -o "$vendor_archive"
    printf '%s  %s\n' '8ab0373e41a3e9d4329d8fa6f99f3afd096cc21ac2befb0a84dc005d8afb36d7' "$vendor_archive" | sha256sum --check
    sha256sum "$vendor_archive" > "$ECD_CACHE_EVIDENCE/litespeed-archive.sha256"
    "${wp[@]}" plugin install "$vendor_archive"
    rm -f "$vendor_archive"
  else
    "${wp[@]}" plugin install "$ECD_CACHE_PROFILE" --version="$version"
  fi
  "${wp[@]}" plugin verify-checksums "$ECD_CACHE_PROFILE"
fi
mkdir -p "$ECD_CACHE_WP/wp-content/mu-plugins"
cp "$root/tests/cache-profiles/observer.php" "$ECD_CACHE_WP/wp-content/mu-plugins/ecd-cache-lab.php"
"${wp[@]}" config set WP_HTTP_BLOCK_EXTERNAL true --raw
"${wp[@]}" config set WP_ACCESSIBLE_HOSTS 'localhost,127.0.0.1'
phase=vendor-config
if [[ -n "$version" ]]; then "${wp[@]}" plugin activate "$ECD_CACHE_PROFILE"; fi
if [[ "$ECD_CACHE_PROFILE" == wp-super-cache || "$ECD_CACHE_PROFILE" == w3-total-cache ]]; then
  "${wp[@]}" config set WP_CACHE true --raw
fi
if [[ "$ECD_CACHE_PROFILE" == wp-super-cache ]]; then
  "${wp[@]}" config set WPCACHEHOME "$ECD_CACHE_WP/wp-content/plugins/wp-super-cache/"
fi
if [[ "$ECD_CACHE_PROFILE" == w3-total-cache ]]; then
  "${wp[@]}" w3-total-cache option set pgcache.enabled true --type=boolean
  "${wp[@]}" w3-total-cache option set pgcache.engine file
  "${wp[@]}" w3-total-cache option set browsercache.enabled true --type=boolean
  "${wp[@]}" w3-total-cache option set lazyload.enabled true --type=boolean
  "${wp[@]}" w3-total-cache fix_environment
fi
"${wp[@]}" eval-file "$root/tests/cache-profiles/fixture.php" configure
phase=fixture-seed
"${wp[@]}" eval-file "$root/tests/cache-profiles/fixture.php" seed
if [[ "$ECD_CACHE_PROFILE" == elementor || "$ECD_CACHE_PROFILE" == woocommerce ]]; then
  "${wp[@]}" rewrite flush
fi
"${wp[@]}" eval-file "$root/tests/cache-profiles/fixture.php" inventory
"${wp[@]}" plugin list --fields=name,status,version --format=json > "$ECD_CACHE_EVIDENCE/plugins-cli.log"
