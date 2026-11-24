<?php
/** Isolated processes prove image-only cache exclusions; no WordPress/optimizer emulation claim. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$case = $argv[1] ?? 'normal';
$_GET = match ( $case ) {
    'image' => array( 'ecd_action' => 'render', 'ecd' => 'sale' ),
    'array' => array( 'ecd_action' => array( 'render' ) ),
    default => array(),
};
$filters = array();
function add_action( ...$args ) {}
function add_shortcode( ...$args ) {}
function shortcode_exists( $tag ) { return false; }
function wp_unslash( $value ) { return stripslashes( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function add_filter( ...$args ) { $GLOBALS['filters'][] = $args; }
if ( in_array( $case, array( 'new-slug', 'same-basename', 'old-site', 'old-network', 'previous-candidate' ), true ) ) {
    function plugin_basename( $file ) { return ( 'same-basename' === $GLOBALS['case'] ? 'easy-countdown' : 'tickloom-countdown-timers' ) . '/email-countdown-timer.php'; }
    function is_multisite() { return 'old-network' === $GLOBALS['case']; }
    function get_option( $name, $default ) {
        return match ( $GLOBALS['case'] ) {
            'old-site', 'same-basename' => array( 'easy-countdown/email-countdown-timer.php' ),
            'previous-candidate' => array( 'actnow-countdown-timers/email-countdown-timer.php' ),
            default => $default,
        };
    }
    function get_site_option( $name, $default ) { return array( 'email-countdown-timer/email-countdown-timer.php' => 1 ); }
    function register_activation_hook( $file, $callback ) { $GLOBALS['activation'] = $callback; }
    function esc_html__( $message, $domain ) { return $message; }
    function wp_die( $message ) { throw new RuntimeException( $message ); }
}
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../email-countdown-timer.php';
if ( in_array( $case, array( 'old-site', 'old-network', 'previous-candidate' ), true ) ) {
    if ( defined( 'EMAIL_COUNTDOWN_TIMER_VERSION' ) || ! isset( $activation ) ) exit( 1 );
    try { $activation(); exit( 1 ); }
    catch ( RuntimeException $e ) { if ( ! str_contains( $e->getMessage(), 'Deactivate the older countdown plugin' ) ) exit( 1 ); }
} elseif ( 'image' === $case ) {
    if ( ! defined( 'DONOTCACHEPAGE' ) || true !== DONOTCACHEPAGE || array( array( 'flying_press_is_cacheable', '__return_false' ) ) !== $filters ) { exit( 1 ); }
} elseif ( ! defined( 'EMAIL_COUNTDOWN_TIMER_VERSION' ) || isset( $activation ) || defined( 'DONOTCACHEPAGE' ) || array() !== $filters ) { exit( 1 ); }
echo 'PASS scoped cache bootstrap: ' . $case . "\n";
