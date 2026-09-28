<?php
/** Destructive lifecycle checks for disposable local WordPress installations ONLY. */
if (PHP_SAPI !== 'cli' || !defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}
global $wpdb;
if (getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local' ||
    $wpdb->base_prefix !== 'ecdci_' || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    WP_CLI::error('Refusing destructive tests outside the disposable local CI installation.');
}
$phase = $args[0] ?? '';
$policy = 'email_countdown_timer_delete_data_on_uninstall';
$plugin = 'email-countdown-timer/email-countdown-timer.php';
$checks = 0;
$expect = static function ($condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        WP_CLI::error($message);
    }
};
$expect(wp_using_ext_object_cache() === (getenv('ECD_CACHE') === 'redis'), 'Expected cache backend.');
if ($phase === 'seed' && is_multisite()) {
    foreach (['retained', 'consented'] as $path) {
        $site = wpmu_create_blog('localhost', '/' . $path . '/', 'Synthetic fixture', 1);
        $expect(!is_wp_error($site), 'Create synthetic subsite.');
    }
}
$sites = is_multisite() ? array_map('intval', get_sites(['fields'=>'ids', 'number'=>100, 'orderby'=>'id', 'order'=>'ASC', 'network_id'=>0])) : [get_current_blog_id()];
$expect(count($sites) === (is_multisite() ? 3 : 1), 'Expected isolated site count.');
$original = get_current_blog_id();
if ($phase === 'invoke-uninstall') {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $expect(uninstall_plugin($plugin) === true, 'WordPress invoked guarded uninstall.php.');
    $expect(get_current_blog_id() === $original, 'Uninstaller restores blog context.');
    WP_CLI::success('LIFECYCLE ' . $phase . ': ' . $checks . ' assertions.');
    return;
}
$known_keys = [];
foreach (['png', 'gif', 'webp'] as $format) {
    $known_keys[] = 'ecd_v1211_' . hash('sha256', 'integration-timer|' . $format);
}
$orphans = [];
for ($i=0; $i<120; ++$i) {
    $orphans[] = 'ecd_v1211_' . hash('sha256', 'orphan-' . $i);
    $orphans[] = 'ecd_img_' . md5('legacy-' . $i);
}
$timeout_only = 'ecd_img_' . md5('timeout-only');
$near_match = '_transient_ecd_img_' . str_repeat('a', 32) . '_other_plugin';
foreach ($sites as $site_id) {
    if (is_multisite()) {
        switch_to_blog($site_id);
    }
    try {
        $delete_this_site = $site_id === $sites[0] || $site_id === $sites[count($sites)-1];
        if ($phase === 'seed') {
            update_option('easy_countdown_timers', ['integration-timer'=>['deadline'=>'2030-12-31T23:59:59','label_d'=>'Days']]);
            delete_option($policy);
            foreach ($known_keys as $key) {
                // Deliberately longer than production TTL: expiry must not conceal a deletion bug.
                set_transient($key, 'synthetic cache', 600);
            }
            foreach ($orphans as $key) {
                update_option('_transient_' . $key, 'synthetic DB cache', false);
                update_option('_transient_timeout_' . $key, time()+3600, false);
                if (wp_using_ext_object_cache()) {
                    wp_cache_set($key, 'synthetic external cache', 'transient', 600);
                }
                get_option('_transient_' . $key); // Prime the options cache, including Redis.
            }
            update_option('_transient_timeout_' . $timeout_only, time()+3600, false);
            update_option($near_match, 'keep', false);
            update_option('other_plugin_option', ['keep'=>true]);
            set_transient('other_plugin_transient', 'keep', 600);
            wp_cache_set('ecd-ci-sentinel', 'keep', 'other_plugin', 600);
            wp_schedule_single_event(time()+86400, 'other_plugin_ci_event');
            update_option('ecd_ci_cron_snapshot', get_option('cron'));
        } elseif ($phase === 'optin') {
            if ($delete_this_site) {
                update_option($policy, '1', false);
            }
        } elseif (in_array($phase, ['retained','deleted'], true)) {
            $removed = $phase === 'deleted' && $delete_this_site;
            $expect((get_option('easy_countdown_timers', false) === false) === $removed, 'Timer retention/deletion on site ' . $site_id);
            $expect(get_option($policy, false) === false, 'Policy absent after default-retain or opted-in uninstall.');
            foreach ($known_keys as $key) {
                $expect((get_transient($key) === false) === $removed, 'Known cache key on site ' . $site_id);
            }
            foreach ($orphans as $key) {
                // Direct SQL proves deletion from MySQL, not merely an API cache miss.
                $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name IN (%s,%s)", '_transient_'.$key, '_transient_timeout_'.$key));
                $expect($count === ($removed ? 0 : 2), 'Orphan/legacy database rows on site ' . $site_id);
                $expect((get_option('_transient_'.$key, false) === false) === $removed, 'Options cache coherence.');
                if (wp_using_ext_object_cache()) {
                    $expect((get_transient($key) === false) === $removed, 'Enumerated Redis key on site ' . $site_id);
                }
            }
            $expect((get_option('_transient_timeout_'.$timeout_only, false) === false) === $removed, 'Timeout-only row.');
            $expect(get_option($near_match) === 'keep', 'Similar prefix belonging to another component retained.');
            $expect(get_option('other_plugin_option') === ['keep'=>true], 'Unrelated option retained.');
            $expect(get_transient('other_plugin_transient') === 'keep', 'Unrelated transient retained.');
            if (wp_using_ext_object_cache()) {
                $expect(wp_cache_get('ecd-ci-sentinel', 'other_plugin') === 'keep', 'Shared cache not flushed.');
            }
            $expect(get_option('cron') === get_option('ecd_ci_cron_snapshot'), 'Shared cron unchanged.');
        } else {
            WP_CLI::error('Unknown lifecycle phase.');
        }
    } finally {
        if (is_multisite()) {
            restore_current_blog();
        }
    }
}
$expect(get_current_blog_id() === $original, 'Test restores blog context.');
WP_CLI::success('LIFECYCLE ' . $phase . ': ' . $checks . ' assertions, ' . count($sites) . ' sites, cache=' . getenv('ECD_CACHE'));
