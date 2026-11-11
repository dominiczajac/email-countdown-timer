#!/usr/bin/env bash
# CI-only. One fresh network/database and owned FPM/nginx process per cell.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
case "${ECD_NETWORK_THEME:?}" in twentytwentyone|twentytwentyfour) ;; *) exit 1;; esac
case "${ECD_NETWORK_ACTIVATION:?}" in site|network) ;; *) exit 1;; esac
case "${ECD_NETWORK_JIT:?}" in 1235|disable) ;; *) exit 1;; esac
root="$(cd "$(dirname "$0")/../.." && pwd)"
export ECD_NETWORK_WP="$(mktemp -d "$RUNNER_TEMP/ect-network.XXXXXX")"
export ECD_NETWORK_EVIDENCE="$RUNNER_TEMP/network-evidence"
export ECD_NETWORK_PASSWORD="$(openssl rand -hex 20)"
printf '::add-mask::%s\n' "$ECD_NETWORK_PASSWORD"
mkdir -p "$ECD_NETWORK_EVIDENCE"
touch "$ECD_NETWORK_EVIDENCE/php-errors.log"
exec > >(tee "$ECD_NETWORK_EVIDENCE/provision-and-test.log") 2>&1
git -C "$root" rev-parse HEAD > "$ECD_NETWORK_EVIDENCE/source-commit.txt"
phase=provision
finish() {
  result=$?
  trap - EXIT
  set +e
  nginx_status=1; fpm_status=1
  if [[ -n "${web:-}" ]]; then kill "$web" 2>/dev/null; wait "$web"; nginx_status=$?; fi
  if [[ -n "${fpm:-}" ]]; then kill "$fpm" 2>/dev/null; wait "$fpm"; fpm_status=$?; fi
  printf '{"nginx":%s,"fpm":%s}\n' "$nginx_status" "$fpm_status" > "$ECD_NETWORK_EVIDENCE/service-stop.json"
  python3 "$root/tests/multisite/evidence.py" "$ECD_NETWORK_EVIDENCE" "$ECD_NETWORK_EVIDENCE/acceptance.json"
  gate=$?
  if [[ "$result" == 0 && "$gate" != 0 ]]; then result=1; fi
  if [[ "$result" != 0 ]]; then printf '::error title=Network fixture::%s %s JIT %s failed in %s; retained evidence stays negative.\n' "$ECD_NETWORK_THEME" "$ECD_NETWORK_ACTIVATION" "$ECD_NETWORK_JIT" "$phase"; fi
  unset ECD_NETWORK_PASSWORD
  exit "$result"
}
trap finish EXIT
bash "$root/tests/multisite/site.sh"
phase=server-start
service_dir="$(mktemp -d "$RUNNER_TEMP/ect-network-services.XXXXXX")"
cat > "$service_dir/fpm.conf" <<CONF
[global]
pid = $service_dir/fpm.pid
error_log = $ECD_NETWORK_EVIDENCE/fpm.log
daemonize = no
[network]
listen = 127.0.0.1:9007
pm = static
pm.max_children = 4
clear_env = yes
catch_workers_output = yes
request_slowlog_timeout = 5s
request_slowlog_trace_depth = 40
slowlog = $ECD_NETWORK_EVIDENCE/fpm-slow.log
request_terminate_timeout = 35s
php_admin_value[max_execution_time] = 30
php_admin_value[memory_limit] = 256M
php_admin_value[error_log] = $ECD_NETWORK_EVIDENCE/php-errors.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
CONF
cat > "$service_dir/nginx.conf" <<CONF
pid $service_dir/nginx.pid;
error_log $ECD_NETWORK_EVIDENCE/nginx-error.log;
events { worker_connections 256; }
http {
  include /etc/nginx/mime.types;
  access_log $ECD_NETWORK_EVIDENCE/http.log;
  client_body_temp_path $service_dir/body;
  fastcgi_temp_path $service_dir/fastcgi;
  server {
    listen 127.0.0.1:8097;
    server_name localhost;
    root $ECD_NETWORK_WP;
    index index.php;
    rewrite ^/(blue|red)(/wp-(admin|content|includes)/.*)\$ \$2 last;
    rewrite ^/(blue|red)(/.*\.php)\$ \$2 last;
    location / { try_files \$uri \$uri/ /index.php?\$args; }
    location ~ \.php\$ {
      include /etc/nginx/fastcgi_params;
      fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
      fastcgi_pass 127.0.0.1:9007;
      fastcgi_read_timeout 40s;
    }
  }
}
CONF
PHP_INI_SCAN_DIR=/etc/php/8.4/cli/conf.d /usr/sbin/php-fpm8.4 -c /etc/php/8.4/cli/php.ini -y "$service_dir/fpm.conf" -F &
fpm=$!
nginx -p "$service_dir/" -c "$service_dir/nginx.conf" -g 'daemon off;' &
web=$!
ready=0
for i in {1..30}; do
  if curl -fsS http://localhost:8097/blue/wp-login.php -o /dev/null; then ready=1; break; fi
  sleep 1
done
[[ "$ready" == 1 ]]
phase=network-experiment
"$RUNNER_TEMP/network-venv/bin/python" "$root/tests/multisite/check.py"
