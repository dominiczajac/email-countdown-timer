<?php
/** On-demand admin/report contracts with DB doubles; native HTTP/browser stays separate. */
require __DIR__ . '/run.php';
require_once __DIR__ . '/../includes/class-email-countdown-timer-status.php';
function add_submenu_page( ...$args ) { $GLOBALS['status_menu'] = $args; return 'ecd-timers_page_email-countdown-timer-status'; }
$start = $checks;
$admin = $nonce = true;
$wp_version = '7.1.4';
$wpdb = new Email_Countdown_Timer_Lock_DB_Double();
$options['easy_countdown_timers'] = array( 'private-id-sentinel' => array_replace( $base, array( 'font' => 'private-font-sentinel.ttf', 'campaign_name' => 'PRIVATE NAME', 'alt' => 'admin@example.invalid', 'expiry_image_id' => 123456, 'tz' => 'UTC' ) ) );
$before = array( $options, $transients, $writes );
$render_status = static function ( string $method, $id = '' ): string {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = array( 'campaign_id' => $id );
    ob_start();
    try { Email_Countdown_Timer_Status::render(); return ob_get_contents(); }
    finally { ob_end_clean(); }
};
$decode_status = static function ( string $html ): array {
    if ( ! preg_match( '/<textarea[^>]*id="ect-status-report"[^>]*>(.*?)<\/textarea>/s', $html, $match ) ) { throw new RuntimeException( 'Missing report' ); }
    return json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true, 512, JSON_THROW_ON_ERROR );
};
$html = $render_status( 'GET' );
ok( ! str_contains( $html, 'id="ect-status-report"' ) && $wpdb->names === array(), 'opening status performs no checks or lock probe' );
ok( ! str_contains( $html, '<img' ) && str_contains( $html, 'Run local checks' ), 'GET has only an explicit action and no image request' );
$admin = false;
ok( $render_status( 'POST', 'private-id-sentinel' ) === '' && $wpdb->names === array(), 'capability checked before report, nonce or probes' );
$admin = true; $nonce = false;
try { $render_status( 'POST', 'private-id-sentinel' ); ok( false, 'missing nonce denied' ); }
catch ( ECD_Test_Stop $error ) { ok( $error->getMessage() === 'nonce' && $wpdb->names === array(), 'nonce checked before any diagnostic probe' ); }
$nonce = true;
$html = $render_status( 'POST', 'private-id-sentinel' );
$report = $decode_status( $html );
ok( array_keys( $report ) === array( 'format', 'plugin', 'wordpress', 'php', 'checks', 'image_test' ), 'report has a fixed top-level allowlist' );
ok( $report['wordpress'] === '7.1.4' && count( $report['checks'] ) === 5, 'versions and five explicit local checks' );
ok( $wpdb->owner === null && $report['checks'][3]['code'] === 'acquired', 'diagnostic advisory lock is released' );
ok( $report['checks'][4]['code'] === 'bitmap_fallback', 'missing selected local font reports its real fallback' );
ok( $report['checks'][0]['status'] === ( function_exists( 'imagecreatetruecolor' ) ? 'pass' : 'error' ), 'GD availability matches the native runtime' );
$json = json_encode( $report );
foreach ( array( 'private-id-sentinel', 'private-font-sentinel.ttf', 'PRIVATE NAME', 'admin@example.invalid', '123456', 'example.test', '2030-', 'UTC', 'test_options', DB_NAME, '/workspace/', '_wpnonce', 'Simulated database error' ) as $secret ) {
    ok( ! str_contains( $json, $secret ), 'report excludes private/configuration sentinel: ' . $secret );
}
ok( str_contains( $html, 'data-ect-image-url=' ) && str_contains( $html, 'mode=static' ) && str_contains( $html, ' hidden ' ), 'valid selected campaign enables only an explicit static browser test' );
ok( $report['image_test'] === array( 'status' => 'not_run', 'http_status' => null ), 'local POST never automatically tests an image' );
foreach ( array( '', 'missing', '../private-id-sentinel', array(), str_repeat( 'a', 201 ) ) as $id ) {
    $html = $render_status( 'POST', $id );
    ok( ! str_contains( $html, 'data-ect-image-url=' ), 'empty/unknown/malformed campaign cannot produce an image target' );
}
$options['easy_countdown_timers']['broken'] = array_replace( $base, array( 'tz' => 'Invalid/Zone' ) );
$html = $render_status( 'POST', 'broken' );
ok( $decode_status( $html )['checks'][4]['code'] === 'invalid_configuration' && ! str_contains( $html, 'data-ect-image-url=' ), 'malformed saved configuration is explained without an image request' );
$options = $before[0];
foreach ( array( 'query', 'exception' ) as $failure ) {
    $wpdb->failure = $failure;
    $r = $decode_status( $render_status( 'POST' ) );
    ok( $r['checks'][3]['code'] === 'error' && ! str_contains( json_encode( $r ), 'Simulated database error' ), 'database failures use fixed redacted messages' );
}
$wpdb->failure = ''; $wpdb->allow = false;
ok( $decode_status( $render_status( 'POST' ) )['checks'][3]['code'] === 'busy', 'busy lock gets actionable warning' );
$wpdb = null;
ok( $decode_status( $render_status( 'POST' ) )['checks'][3]['code'] === 'unsupported', 'unsupported backend keeps a truthful static-fallback warning' );
$wpdb = new Email_Countdown_Timer_Lock_DB_Double();
$wp_version = '/private/path/secret';
ok( $decode_status( $render_status( 'POST' ) )['wordpress'] === 'unknown', 'unexpected version data cannot leak arbitrary strings into report' );
$options['easy_countdown_timers'] = array_fill_keys( array_map( static fn( $n ) => 'status-' . $n, range( 1, 251 ) ), $base );
$html = $render_status( 'GET' );
ok( substr_count( $html, '<option value=' ) === 250, 'admin campaign suggestions are bounded' );
ok( str_contains( $render_status( 'POST', 'status-251' ), 'data-ect-image-url=' ), 'known IDs beyond suggestions may be entered explicitly' );
$options = $before[0];
ok( array( $options, $transients, $writes ) === $before, 'all diagnostics leave options and image caches unchanged' );
Email_Countdown_Timer_Status::add_menu();
ok( $status_menu[3] === 'manage_options', 'native submenu requires administrator capability' );
$scripts = $styles = array();
Email_Countdown_Timer_Status::enqueue( 'dashboard' );
ok( $scripts === array() && $styles === array(), 'diagnostic assets absent from unrelated screens' );
Email_Countdown_Timer_Status::enqueue( 'ecd-timers_page_email-countdown-timer-status' );
ok( isset( $scripts['email-countdown-timer-status'], $scripts['email-countdown-timer-admin'], $styles['email-countdown-timer-admin'] ), 'status and existing clipboard/style assets stay scoped' );
echo 'STATUS DOUBLES PASS: ' . ( $checks - $start ) . " assertions\n";
