#!/usr/bin/env bash
# Dedicated loopback site/database; production URLs and existing paths are not inputs.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true && "${ECD_INTEGRATION_DISPOSABLE:-}" == 1 && -n "${RUNNER_TEMP:-}" ]] || exit 1
case "${ECD_FPM_PROFILE:-}" in none|woo|w3|both) ;; *) exit 1;; esac
root="$(cd "$(dirname "$0")/../.." && pwd)"
export ECD_FPM_ROOT="$(mktemp -d "$RUNNER_TEMP/ect-fpm.XXXXXX")"
export ECD_FPM_EVIDENCE="$RUNNER_TEMP/fpm-evidence"
export ECD_BROWSER_PASSWORD="$(openssl rand -hex 20)"
mkdir -p "$ECD_FPM_ROOT/wp" "$ECD_FPM_EVIDENCE"
wp=(wp --path="$ECD_FPM_ROOT/wp" --no-color)
"${wp[@]}" core download --version=7.1.2 --locale=en_US
"${wp[@]}" config create --dbname=ect_fpm --dbuser=root --dbpass=fpm-disposable-only --dbhost=127.0.0.1:3307 --dbprefix=ectfpm_
"${wp[@]}" config set WP_ENVIRONMENT_TYPE local
"${wp[@]}" config set DISABLE_WP_CRON true --raw
"${wp[@]}" config set WP_DEBUG true --raw
"${wp[@]}" config set WP_DEBUG_DISPLAY false --raw
"${wp[@]}" config set WP_DEBUG_LOG "$ECD_FPM_EVIDENCE/php-debug.log"
"${wp[@]}" core install --url=http://localhost:8091 --title='Disposable FPM lab' --admin_user=ci-admin --admin_password="$ECD_BROWSER_PASSWORD" --admin_email=ci@example.invalid --skip-email
# Hold the theme constant so plugin factors are not confounded with theme changes.
"${wp[@]}" theme activate twentytwentyfive
bash "$root/scripts/build-zip.sh" --output "$ECD_FPM_ROOT/easy-countdown.zip" --report "$ECD_FPM_EVIDENCE/distribution.json"
"${wp[@]}" plugin install "$ECD_FPM_ROOT/easy-countdown.zip" --activate
if [[ "$ECD_FPM_PROFILE" == woo || "$ECD_FPM_PROFILE" == both ]]; then
    "${wp[@]}" plugin install woocommerce --version=11.1.2 --activate
    "${wp[@]}" plugin verify-checksums woocommerce
    "${wp[@]}" eval 'delete_transient("_wc_activation_redirect"); update_option("woocommerce_coming_soon","no"); update_option("woocommerce_allow_tracking","no");'
fi
if [[ "$ECD_FPM_PROFILE" == w3 || "$ECD_FPM_PROFILE" == both ]]; then
    "${wp[@]}" plugin install w3-total-cache --version=2.10.6 --activate
    "${wp[@]}" plugin verify-checksums w3-total-cache
    "${wp[@]}" config set WP_CACHE true --raw
    "${wp[@]}" w3-total-cache option set pgcache.enabled true --type=boolean
    "${wp[@]}" w3-total-cache option set pgcache.engine file
    "${wp[@]}" w3-total-cache option set browsercache.enabled true --type=boolean
    "${wp[@]}" w3-total-cache option set lazyload.enabled true --type=boolean
    "${wp[@]}" w3-total-cache fix_environment
fi
# A controlled offline-services profile, not a test of external APIs or delivery.
"${wp[@]}" config set WP_HTTP_BLOCK_EXTERNAL true --raw
"${wp[@]}" config set WP_ACCESSIBLE_HOSTS 'localhost,127.0.0.1'
mkdir -p "$ECD_FPM_ROOT/wp/wp-content/mu-plugins"
cp "$root/tests/fpm/probe.php" "$ECD_FPM_ROOT/wp/wp-content/mu-plugins/ect-fpm-probe.php"
"${wp[@]}" eval 'require_once EMAIL_COUNTDOWN_TIMER_DIR."includes/class-email-countdown-timer-admin.php"; $c=Email_Countdown_Timer_Config::normalize(Email_Countdown_Timer_Admin::defaults()); $c["deadline"]="2035-12-31T23:59:59"; update_option("easy_countdown_timers",array("fpm-timer"=>$c),false); $p=wp_insert_post(array("post_type"=>"page","post_status"=>"publish","post_title"=>"FPM fixture","post_content"=>"[ect_fpm_origin][ecd_timer id=\"fpm-timer\"]")); update_option("show_on_front","page"); update_option("page_on_front",$p);'
"${wp[@]}" plugin list --fields=name,status,version --format=json > "$ECD_FPM_EVIDENCE/plugins.json"
"${wp[@]}" core version --extra > "$ECD_FPM_EVIDENCE/versions.txt"
php -v >> "$ECD_FPM_EVIDENCE/versions.txt"
php-fpm8.4 -v >> "$ECD_FPM_EVIDENCE/versions.txt" 2>&1
nginx -v >> "$ECD_FPM_EVIDENCE/versions.txt" 2>&1
"${wp[@]}" eval 'global $wpdb; echo $wpdb->get_var("SELECT VERSION()")."\n"; echo "GD ".phpversion("gd")." Imagick ".phpversion("imagick")."\n"; if(class_exists("W3TC\\Dispatcher")){ $c=\W3TC\Dispatcher::config(); foreach(array("pgcache.enabled","pgcache.engine","browsercache.enabled","lazyload.enabled") as $k){ echo $k."=".wp_json_encode($c->get($k))."\n"; }}' >> "$ECD_FPM_EVIDENCE/versions.txt"
cat > "$ECD_FPM_ROOT/fpm.conf" <<EOF
[global]
pid = $ECD_FPM_ROOT/fpm.pid
error_log = $ECD_FPM_EVIDENCE/fpm.log
daemonize = no
[lab]
user = $(id -un)
group = $(id -gn)
listen = 127.0.0.1:9091
pm = static
pm.max_children = 4
catch_workers_output = yes
clear_env = yes
request_slowlog_timeout = 2s
request_slowlog_trace_depth = 40
slowlog = $ECD_FPM_EVIDENCE/fpm-slow.log
request_terminate_timeout = 35s
php_admin_value[max_execution_time] = 30
php_admin_value[memory_limit] = 256M
php_admin_value[error_log] = $ECD_FPM_EVIDENCE/php-fpm.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
php_admin_flag[opcache.enable] = on
EOF
cat > "$ECD_FPM_ROOT/nginx.conf" <<EOF
pid $ECD_FPM_ROOT/nginx.pid;
error_log $ECD_FPM_EVIDENCE/nginx-error.log info;
events { worker_connections 128; }
http {
    include /etc/nginx/mime.types;
    access_log $ECD_FPM_EVIDENCE/nginx-access.log;
    client_body_temp_path $ECD_FPM_ROOT/body;
    fastcgi_temp_path $ECD_FPM_ROOT/fastcgi;
    server {
        listen 127.0.0.1:8091;
        server_name localhost;
        root $ECD_FPM_ROOT/wp;
        index index.php;
        location / { try_files \$uri \$uri/ /index.php?\$args; }
        location ~ \.php$ {
            try_files \$uri =404;
            include /etc/nginx/fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_pass 127.0.0.1:9091;
            fastcgi_read_timeout 40s;
        }
    }
}
EOF
# Unprivileged masters; bind loopback ports only. No system configuration is changed.
php-fpm8.4 --nodaemonize --fpm-config "$ECD_FPM_ROOT/fpm.conf" > "$ECD_FPM_EVIDENCE/fpm-console.log" 2>&1 &
fpm=$!
nginx -p "$ECD_FPM_ROOT/" -c "$ECD_FPM_ROOT/nginx.conf" -g 'daemon off;' > "$ECD_FPM_EVIDENCE/nginx-console.log" 2>&1 &
web=$!
trap 'kill "$web" "$fpm" 2>/dev/null || true; unset ECD_BROWSER_PASSWORD' EXIT
for i in {1..25}; do if curl -fsS http://localhost:8091/wp-login.php -o /dev/null; then break; fi; sleep 1; done
python3 "$root/tests/fpm/repeat.py"
