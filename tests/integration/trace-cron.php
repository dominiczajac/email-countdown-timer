<?php
/** Diagnostic probe installed ONLY in a disposable local CI WordPress. */
if (!defined('ABSPATH') || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') {
    return;
}
add_filter('pre_update_option_cron', static function ($new, $old) {
    global $wpdb;
    $database = (string)$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='cron'");
    $has = static fn($value) => strpos(is_string($value) ? $value : serialize($value), 'other_plugin_ci_event') !== false ? 'yes' : 'no';
    error_log('ECD synthetic cron trace site=' . get_current_blog_id() . ' old=' . $has($old) . ' db=' . $has($database) . ' new=' . $has($new) . ' stack=' . wp_debug_backtrace_summary());
    return $new;
}, PHP_INT_MAX, 2);
