#!/usr/bin/env bash
# New loopback fixture per cell. No production URL/database/path is accepted.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
case "${ECD_CACHE_PROFILE:?}" in baseline|wp-super-cache|w3-total-cache|autoptimize|elementor|woocommerce) ;; *) exit 1;; esac
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
  python3 "$root/tests/cache-profiles/contracts.py" "$ECD_CACHE_EVIDENCE/checks.json" --report "$ECD_CACHE_EVIDENCE/acceptance.json"
  acceptance_result=$?
  if [[ "$result" == 0 && "$acceptance_result" != 0 ]]; then result=1; fi
  if [[ "$result" != 0 ]]; then printf '::error title=Cache profile::%s JIT %s failed in %s; retained evidence stays negative.\n' "$ECD_CACHE_PROFILE" "$ECD_CACHE_JIT" "$phase"; fi
  unset ECD_CACHE_PASSWORD
  exit "$result"
}
trap finish EXIT
wp=(wp --path="$ECD_CACHE_WP" --no-color)
export ECD_CACHE_SERVER=nginx-fpm
phase=site-provision
bash "$root/tests/cache-profiles/site.sh"
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
