<?php
/**
 * Plugin Name: Email Countdown Timer
 * Description: Countdown timers for WordPress pages and email campaigns.
 * Version: 12.1.2
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Dominic Zajac
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: email-countdown-timer
 */
if (!defined('ABSPATH')) exit;
define('ECD_PLUGIN_FILE', __FILE__);
define('ECD_PLUGIN_DIR', __DIR__.'/');
require_once __DIR__.'/includes/class-ecd-config.php';
require_once __DIR__.'/includes/class-ecd-renderer.php';
require_once __DIR__.'/includes/class-ecd-plugin.php';
new ECD_Plugin_Colons_Fix();
require_once __DIR__.'/includes/class-email-countdown-timer-data-settings.php';
Email_Countdown_Timer_Data_Settings::register();
