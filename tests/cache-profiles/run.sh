#!/usr/bin/env bash
# New loopback fixture per cell. No production URL/database/path is accepted.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
case "${ECD_CACHE_PROFILE:?}" in baseline|wp-super-cache|w3-total-cache|autoptimize) ;; *) exit 1;; esac
case "${ECD_CACHE_JIT:?}" in 1235|disable) ;; *) exit 1;; esac
root="$(cd "$(dirname "$0")/../.." && pwd)"
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
  for pid in "${web:-}" "${fpm:-}"; do
    if [[ -n "$pid" ]]; then kill "$pid" 2>/dev/null; wait "$pid" 2>/dev/null; fi
  done
  python3 "$root/tests/gutenberg/check-native-logs.py" "$ECD_CACHE_EVIDENCE" --report "$ECD_CACHE_EVIDENCE/native-logs.json"
  native_result=$?
  if [[ "$result" == 0 && "$native_result" != 0 ]]; then result=1; fi
  if [[ "$result" != 0 ]]; then printf '::error title=Cache profile::%s JIT %s failed in %s; retained evidence stays negative.\n' "$ECD_CACHE_PROFILE" "$ECD_CACHE_JIT" "$phase"; fi
  unset ECD_CACHE_PASSWORD
  exit "$result"
}
trap finish EXIT
wp=(wp --path="$ECD_CACHE_WP" --no-color)
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
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" config set AUTOMATIC_UPDATER_DISABLED true --raw
"${wp[@]}" config set WP_DEBUG true --raw
"${wp[@]}" config set WP_DEBUG_DISPLAY false --raw
"${wp[@]}" config set WP_DEBUG_LOG "$ECD_CACHE_EVIDENCE/php-errors.log"
"${wp[@]}" core install --url=http://localhost:8095 --title='Isolated cache lab' --admin_user=ci-admin --admin_password="$ECD_CACHE_PASSWORD" --admin_email=ci@example.invalid --skip-email
"${wp[@]}" theme activate twentytwentyfive
"${wp[@]}" rewrite structure '/%postname%/'
phase=candidate-install
bash "$root/scripts/build-zip.sh" --output "$RUNNER_TEMP/easy-countdown-cache.zip" --report "$ECD_CACHE_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$RUNNER_TEMP/easy-countdown-cache.zip" --activate
python3 "$root/scripts/verify-distribution.py" "$ECD_CACHE_EVIDENCE/distribution.json" "$ECD_CACHE_WP/wp-content/plugins/easy-countdown"
case "$ECD_CACHE_PROFILE" in
  wp-super-cache) version=3.1.3 ;;
  w3-total-cache) version=2.10.6 ;;
  autoptimize) version=3.1.16 ;;
  baseline) version='' ;;
esac
if [[ -n "$version" ]]; then
  phase=vendor-install
  "${wp[@]}" plugin install "$ECD_CACHE_PROFILE" --version="$version"
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
"${wp[@]}" eval-file "$root/tests/cache-profiles/fixture.php" seed > "$ECD_CACHE_EVIDENCE/fixture.json"
"${wp[@]}" eval-file "$root/tests/cache-profiles/fixture.php" inventory > "$ECD_CACHE_EVIDENCE/environment.json"
"${wp[@]}" plugin list --fields=name,status,version --format=json > "$ECD_CACHE_EVIDENCE/plugins.json"
service_dir="$(mktemp -d "$RUNNER_TEMP/ect-cache-server.XXXXXX")"
cat > "$service_dir/fpm.conf" <<CONF
[global]
pid = $service_dir/fpm.pid
error_log = $ECD_CACHE_EVIDENCE/fpm.log
daemonize = no
[lab]
listen = 127.0.0.1:9005
pm = static
pm.max_children = 4
clear_env = yes
catch_workers_output = yes
request_slowlog_timeout = 5s
request_slowlog_trace_depth = 40
slowlog = $ECD_CACHE_EVIDENCE/fpm-slow.log
request_terminate_timeout = 35s
php_admin_value[max_execution_time] = 30
php_admin_value[memory_limit] = 256M
php_admin_value[error_log] = $ECD_CACHE_EVIDENCE/php-errors.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
CONF
cat > "$service_dir/nginx.conf" <<CONF
pid $service_dir/nginx.pid;
error_log $ECD_CACHE_EVIDENCE/nginx-error.log;
events { worker_connections 256; }
http {
  include /etc/nginx/mime.types;
  access_log $ECD_CACHE_EVIDENCE/http.log;
  client_body_temp_path $service_dir/body;
  fastcgi_temp_path $service_dir/fastcgi;
  server {
    listen 127.0.0.1:8095;
    server_name localhost;
    root $ECD_CACHE_WP;
    index index.php;
    location / { try_files \$uri \$uri/ /index.php?\$args; }
    location ~ \.php\$ {
      include /etc/nginx/fastcgi_params;
      fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
      fastcgi_pass 127.0.0.1:9005;
      fastcgi_read_timeout 40s;
    }
  }
}
CONF
phase=server-start
PHP_INI_SCAN_DIR=/etc/php/8.4/cli/conf.d /usr/sbin/php-fpm8.4 -c /etc/php/8.4/cli/php.ini -y "$service_dir/fpm.conf" -F &
fpm=$!
nginx -p "$service_dir/" -c "$service_dir/nginx.conf" -g 'daemon off;' &
web=$!
ready=0
for i in {1..20}; do if curl -fsS http://localhost:8095/wp-login.php -o /dev/null; then ready=1; break; fi; sleep 1; done
test "$ready" == 1
phase=paired-experiment
"$RUNNER_TEMP/cache-venv/bin/python" "$root/tests/cache-profiles/check.py"
