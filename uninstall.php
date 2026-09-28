<?php
/** WordPress invokes this file only during uninstall, never during deactivation. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/includes/class-email-countdown-timer-uninstaller.php';
Email_Countdown_Timer_Uninstaller::run();
