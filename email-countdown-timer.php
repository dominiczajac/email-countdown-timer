<?php
/**
 * Plugin Name: Easy Countdown
 * Description: Countdown timers for WordPress pages and email campaigns.
 * Version: 12.5.3
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Dominic Zajac
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: easy-countdown
 */
if (!defined('ABSPATH')) exit;
// A slug transition must not run two versions of the same implementation together.
if (function_exists('plugin_basename') && plugin_basename(__FILE__) !== 'email-countdown-timer/email-countdown-timer.php') {
    $email_countdown_timer_active = (array)get_option('active_plugins', []);
    if (is_multisite()) $email_countdown_timer_active = array_merge($email_countdown_timer_active, array_keys((array)get_site_option('active_sitewide_plugins', [])));
    if (in_array('email-countdown-timer/email-countdown-timer.php', $email_countdown_timer_active, true)) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-warning"><p>'.esc_html__('Easy Countdown is not running because the older email-countdown-timer copy is active. Back up fonts and deactivate the old copy, without uninstalling it, before activating Easy Countdown.', 'easy-countdown').'</p></div>';
        });
        register_activation_hook(__FILE__, static function (): void {
            wp_die(esc_html__('Deactivate the older email-countdown-timer copy before activating Easy Countdown. Do not uninstall it while data removal is enabled.', 'easy-countdown'));
        });
        return;
    }
    unset($email_countdown_timer_active);
}
if (defined('EMAIL_COUNTDOWN_TIMER_VERSION')) return;
// Best effort for optimizers running after normal plugins. Cache drop-ins and
// reverse proxies can run earlier; the render query still needs their bypass rules.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only disables page caching for the public read-only image route; does not authorize a write.
if (isset($_GET['ecd_action']) && is_string($_GET['ecd_action']) && $_GET['ecd_action'] === 'render' && !defined('DONOTCACHEPAGE')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin interoperability flag, not a plugin-owned global.
    define('DONOTCACHEPAGE', true);
}
define('EMAIL_COUNTDOWN_TIMER_VERSION', '12.5.3');
define('EMAIL_COUNTDOWN_TIMER_FILE', __FILE__);
define('EMAIL_COUNTDOWN_TIMER_DIR', __DIR__.'/');
require_once __DIR__.'/includes/class-email-countdown-timer-fonts.php';
Email_Countdown_Timer_Fonts::register();
require_once __DIR__.'/includes/class-ecd-config.php';
require_once __DIR__.'/includes/class-email-countdown-timer-end-image.php';
require_once __DIR__.'/includes/class-ecd-renderer.php';
require_once __DIR__.'/includes/class-email-countdown-timer-embed.php';
require_once __DIR__.'/includes/class-ecd-plugin.php';
new Email_Countdown_Timer_Plugin();
// Load policy wording only in admin, never during public image rendering.
add_action('admin_init', static function (): void {
    require_once __DIR__.'/includes/class-email-countdown-timer-privacy.php';
    Email_Countdown_Timer_Privacy::suggest();
    require_once __DIR__.'/includes/class-email-countdown-timer-font-access.php';
    Email_Countdown_Timer_Font_Access::register();
});
require_once __DIR__.'/includes/class-email-countdown-timer-data-settings.php';
Email_Countdown_Timer_Data_Settings::register();
