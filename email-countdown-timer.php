<?php
/**
 * Plugin Name: Email Countdown Timer
 * Description: Countdown timers for WordPress pages and email campaigns.
 * Version: 12.3.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Dominic Zajac
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: email-countdown-timer
 */
if (!defined('ABSPATH')) exit;
// Best effort for optimizers running after normal plugins. Cache drop-ins and
// reverse proxies can run earlier; the render query still needs their bypass rules.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only disables page caching for the public read-only image route; does not authorize a write.
if (isset($_GET['ecd_action']) && is_string($_GET['ecd_action']) && $_GET['ecd_action'] === 'render' && !defined('DONOTCACHEPAGE')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin interoperability flag, not a plugin-owned global.
    define('DONOTCACHEPAGE', true);
}
define('EMAIL_COUNTDOWN_TIMER_FILE', __FILE__);
define('EMAIL_COUNTDOWN_TIMER_DIR', __DIR__.'/');
require_once __DIR__.'/includes/class-ecd-config.php';
require_once __DIR__.'/includes/class-ecd-renderer.php';
require_once __DIR__.'/includes/class-ecd-plugin.php';
new Email_Countdown_Timer_Plugin();
require_once __DIR__.'/includes/class-email-countdown-timer-data-settings.php';
Email_Countdown_Timer_Data_Settings::register();
