<?php
/**
 * Plugin Name: ActNow – Countdown Timers
 * Description: Create one countdown in WordPress and use it in emails and on websites. No external account required.
 * Version: 12.9.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Dominic Zajac
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: actnow-countdown-timers
 */
if (!defined('ABSPATH')) exit;
// A slug transition must not run two versions of the same implementation together.
if (function_exists('plugin_basename')) {
    $email_countdown_timer_active = (array)get_option('active_plugins', []);
    if (is_multisite()) $email_countdown_timer_active = array_merge($email_countdown_timer_active, array_keys((array)get_site_option('active_sitewide_plugins', [])));
    $email_countdown_timer_previous = array_diff(['easy-countdown/email-countdown-timer.php', 'email-countdown-timer/email-countdown-timer.php'], [plugin_basename(__FILE__)]);
    if (array_intersect($email_countdown_timer_previous, $email_countdown_timer_active)) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-warning"><p>'.esc_html__('ActNow cannot start while an older copy of this countdown plugin is active. Back up your fonts and deactivate the old copy before activating ActNow.', 'actnow-countdown-timers').'</p></div>';
        });
        register_activation_hook(__FILE__, static function (): void {
            wp_die(esc_html__('Deactivate the older countdown plugin before activating ActNow. Keep the old copy if you still need its data or fonts.', 'actnow-countdown-timers'));
        });
        return;
    }
    unset($email_countdown_timer_active, $email_countdown_timer_previous);
}
if (defined('EMAIL_COUNTDOWN_TIMER_VERSION')) return;
// Best effort for optimizers running after normal plugins. Cache drop-ins and
// reverse proxies can run earlier; the render query still needs their bypass rules.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only disables page caching for the public read-only image route; does not authorize a write.
if (isset($_GET['ecd_action']) && is_string($_GET['ecd_action']) && in_array($_GET['ecd_action'], ['render','frame'], true) && !defined('DONOTCACHEPAGE')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin interoperability flag, not a plugin-owned global.
    define('DONOTCACHEPAGE', true);
}
define('EMAIL_COUNTDOWN_TIMER_VERSION', '12.9.0');
define('EMAIL_COUNTDOWN_TIMER_FILE', __FILE__);
define('EMAIL_COUNTDOWN_TIMER_DIR', __DIR__.'/');
require_once __DIR__.'/includes/class-email-countdown-timer-fonts.php';
Email_Countdown_Timer_Fonts::register();
require_once __DIR__.'/includes/class-ecd-config.php';
require_once __DIR__.'/includes/class-email-countdown-timer-end-image.php';
require_once __DIR__.'/includes/class-ecd-renderer.php';
require_once __DIR__.'/includes/class-email-countdown-timer-embed.php';
require_once __DIR__.'/includes/class-ecd-plugin.php';
// Reuse the same controller; image requests exit before the later block hook.
( static function (): void {
    $plugin = new Email_Countdown_Timer_Plugin();
    add_action( 'init', static function () use ( $plugin ): void {
        require_once __DIR__ . '/includes/class-email-countdown-timer-block.php';
        Email_Countdown_Timer_Block::register( $plugin );
    } );
} )();
// Load policy wording only in admin, never during public image rendering.
add_action('admin_init', static function (): void {
    require_once __DIR__.'/includes/class-email-countdown-timer-privacy.php';
    Email_Countdown_Timer_Privacy::suggest();
    require_once __DIR__.'/includes/class-email-countdown-timer-font-access.php';
    Email_Countdown_Timer_Font_Access::register();
});
require_once __DIR__.'/includes/class-email-countdown-timer-data-settings.php';
Email_Countdown_Timer_Data_Settings::register();

require_once __DIR__.'/includes/class-email-countdown-timer-status.php';
Email_Countdown_Timer_Status::register();

require_once __DIR__.'/includes/class-email-countdown-timer-frame.php';
add_action('init', array(Email_Countdown_Timer_Frame::class, 'listen'));
