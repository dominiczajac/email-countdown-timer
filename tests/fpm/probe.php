<?php
/** Test-only observation and a media-enqueue control, never shipped. */
if (!defined('ABSPATH') || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectfpm_') { exit; }
add_filter('pre_wp_mail', '__return_false');
add_action('plugins_loaded', static function () {
    if (PHP_SAPI !== 'cli') {
        header('X-ECT-Lab-SAPI: ' . PHP_SAPI);
        header('X-ECT-Lab-Active: ' . (class_exists('Email_Countdown_Timer_Plugin', false) ? 'on' : 'off'));
    }
}, 999);
add_action('admin_menu', static function () {
    add_menu_page('FPM media control', 'FPM media control', 'manage_options', 'ect-fpm-probe', static function () {
        echo '<div id="ect-fpm-probe">Synthetic media-enqueue control</div>';
    });
});
add_action('admin_enqueue_scripts', static function ($hook) {
    if ($hook === 'toplevel_page_ect-fpm-probe') { wp_enqueue_media(); }
});
add_shortcode('ect_fpm_origin', static function () {
    return '<span id="ect-fpm-origin" data-generation="' . esc_attr(wp_generate_uuid4()) . '"></span>';
});
