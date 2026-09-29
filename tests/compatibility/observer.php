<?php
// Test-only marker. Does not intercept image rendering, hooks or third-party caches.
if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') { exit; }
add_shortcode('ect_compat_origin', static function () {
    return '<span hidden id="ect-compat-origin" data-generation="' . esc_attr(wp_generate_uuid4()) . '"></span>';
});
// Synthetic sites must never send mail, even if another plugin schedules it.
add_filter('pre_wp_mail', '__return_false');
