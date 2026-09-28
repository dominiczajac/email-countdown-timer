<?php
/**
 * Plugin Name: Email Countdown Timer
 * Description: Countdown timers for WordPress pages and email campaigns.
 * Version: 12.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Dominic Zajac
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: email-countdown-timer
 */
if (!defined('ABSPATH')) exit;
define('EMAIL_COUNTDOWN_TIMER_FILE', __FILE__);
define('EMAIL_COUNTDOWN_TIMER_DIR', __DIR__.'/');
require_once __DIR__.'/includes/class-ecd-config.php';
require_once __DIR__.'/includes/class-ecd-renderer.php';
require_once __DIR__.'/includes/class-ecd-plugin.php';
new Email_Countdown_Timer_Plugin();
require_once __DIR__.'/includes/class-email-countdown-timer-data-settings.php';
Email_Countdown_Timer_Data_Settings::register();
