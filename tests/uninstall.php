<?php
/** Isolated lifecycle safety tests; WordPress/database doubles, not integration tests. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
error_reporting( E_ALL );
set_error_handler( static function ( $code, $message, $file, $line ) {
    throw new ErrorException( $message, 0, $code, $file, $line );
} );
$checks = 0;
function expect( $value, $message ) {
    ++$GLOBALS['checks'];
    if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); }
}
// Direct access must terminate before WordPress functions or cleanup can run.
foreach ( array( '', "define('ABSPATH', '/');" ) as $prefix ) {
    $command = escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $prefix . 'include ' . var_export( __DIR__ . '/../uninstall.php', true ) . ';echo "UNEXPECTED";' );
    exec( $command . ' 2>&1', $output, $exit_code );
    expect( 0 === $exit_code && array() === $output, 'uninstall guard' );
    $output = array();
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_UNINSTALL_PLUGIN', 'email-countdown-timer/email-countdown-timer.php' );
define( 'ARRAY_A', 'ARRAY_A' );
$site = 1; $sites = array( 1 ); $stack = array(); $db = array(); $objects = array();
$sequence = 0; $external = false; $multisite = false; $admin = true; $nonce = true; $hooks = array();
class Test_Stop extends RuntimeException {}
function put_option( $name, $value ) {
    $GLOBALS['db'][$GLOBALS['site']][$name] = array( 'option_id' => ++$GLOBALS['sequence'], 'option_name' => $name, 'value' => $value );
}
function get_option( $name, $default = false ) { return $GLOBALS['db'][$GLOBALS['site']][$name]['value'] ?? $default; }
function update_option( $name, $value, $autoload = false ) { put_option( $name, $value ); return true; }
function delete_option( $name ) { unset( $GLOBALS['db'][$GLOBALS['site']][$name] ); return true; }
function delete_transient( $key ) {
    if ( $GLOBALS['external'] ) { unset( $GLOBALS['objects'][$GLOBALS['site']][$key] ); return true; }
    delete_option( '_transient_' . $key ); delete_option( '_transient_timeout_' . $key ); return true;
}
function is_multisite() { return $GLOBALS['multisite']; }
function get_sites( $args ) {
    expect( 0 === $args['network_id'] && 100 === $args['number'], 'bounded all-network site enumeration' );
    return array_slice( $GLOBALS['sites'], $args['offset'], $args['number'] );
}
function switch_to_blog( $id ) { $GLOBALS['stack'][] = $GLOBALS['site']; $GLOBALS['site'] = $id; }
function restore_current_blog() { $GLOBALS['site'] = array_pop( $GLOBALS['stack'] ); }
function wp_cache_flush() { throw new RuntimeException( 'Global cache flush is forbidden' ); }
function add_action( $hook, $callback ) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_submenu_page( ...$args ) {}
function current_user_can( $cap ) { return $GLOBALS['admin'] && 'manage_options' === $cap; }
function check_admin_referer( $action ) { if ( ! $GLOBALS['nonce'] ) { throw new Test_Stop( 'nonce' ); } }
function __( $text, $domain ) { return $text; }
// Minimal WordPress API doubles for the read-only admin status notice.
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function wp_unslash( $text ) { return stripslashes( $text ); }
function esc_html__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="test">'; }
function checked( $value ) { if ( $value ) { echo 'checked="checked"'; } }
function submit_button( $text ) { echo '<button>' . htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) . '</button>'; }
function wp_die( $text, $title, $args ) { throw new Test_Stop( 'http:' . $args['response'] ); }
function wp_safe_redirect( $url ) { throw new Test_Stop( 'redirect:' . $url ); }
class Database_Double {
    public $options = 'test_options';
    public $queries = 0;
    public $fail_site = 0;
    public $last_error = '';
    public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
    public function prepare( $query, ...$args ) {
        expect( strpos( $query, 'option_id > %d' ) !== false && strpos( $query, 'ORDER BY option_id ASC LIMIT %d' ) !== false, 'keyset pagination in SQL' );
        return $args;
    }
    public function get_results( $args, $mode ) {
        ++$this->queries;
        $this->last_error = '';
        if ( $this->fail_site === $GLOBALS['site'] ) { $this->last_error = 'Simulated SQL error'; return array(); }
        $prefixes = array_map( static fn( $v ) => stripcslashes( substr( $v, 0, -1 ) ), array_slice( $args, 1, 4 ) );
        $rows = array();
        foreach ( $GLOBALS['db'][$GLOBALS['site']] ?? array() as $row ) {
            if ( $row['option_id'] <= $args[0] ) { continue; }
            foreach ( $prefixes as $prefix ) {
                if ( str_starts_with( $row['option_name'], $prefix ) ) { $rows[] = $row; break; }
            }
        }
        usort( $rows, static fn( $a, $b ) => $a['option_id'] <=> $b['option_id'] );
        return array_slice( $rows, 0, $args[5] );
    }
}
$wpdb = new Database_Double();
require __DIR__ . '/../includes/class-ecd-config.php';
require __DIR__ . '/../includes/class-email-countdown-timer-data-settings.php';
require __DIR__ . '/../includes/class-email-countdown-timer-uninstaller.php';
$policy = Email_Countdown_Timer_Data_Settings::OPTION;
$timers = array( 'sale' => array( 'deadline' => '2027-12-31T23:59:59', 'label_d' => 'Dni' ) );
put_option( 'easy_countdown_timers', $timers );
$before = $db;
Email_Countdown_Timer_Data_Settings::register();
expect( ! isset( $hooks['deactivate_email-countdown-timer/email-countdown-timer.php'] ), 'no destructive deactivation hook' );
expect( ! Email_Countdown_Timer_Data_Settings::enabled(), 'retention is the default' );
include __DIR__ . '/../uninstall.php';
expect( $before === $db && 0 === $wpdb->queries, 'default uninstall leaves data intact' );
foreach ( array( '0', 0, false, true, 'yes', '01', array( '1' ) ) as $flag ) {
    put_option( $policy, $flag ); $before = $db;
    Email_Countdown_Timer_Uninstaller::run();
    expect( $before === $db, 'only exact consent values permit cleanup' );
}
function save_result() {
    try { Email_Countdown_Timer_Data_Settings::save(); }
    catch ( Test_Stop $e ) { return $e->getMessage(); }
    return 'unexpected';
}
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array( 'delete_data_on_uninstall' => '1' );
$admin = false; $before = $db;
expect( 'http:403' === save_result() && $before === $db, 'unauthorized settings write denied' );
$admin = true; $nonce = false;
expect( 'nonce' === save_result() && $before === $db, 'nonce failure does not save consent' );
$nonce = true; $_SERVER['REQUEST_METHOD'] = 'GET';
expect( 'http:405' === save_result() && $before === $db, 'GET cannot enable cleanup' );
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( array( array( '1' ), 'yes', '01', 1 ) as $invalid ) {
    $_POST['delete_data_on_uninstall'] = $invalid;
    expect( 'http:400' === save_result() && $before === $db, 'malformed checkbox rejected' );
}
$_POST = array( 'delete_data_on_uninstall' => '1' );
expect( str_starts_with( save_result(), 'redirect:' ) && Email_Countdown_Timer_Data_Settings::enabled(), 'valid opt-in saved' );
expect( get_option( 'easy_countdown_timers' ) === $timers, 'saving consent never deletes timers' );
$_POST = array();
expect( str_starts_with( save_result(), 'redirect:' ) && ! Email_Countdown_Timer_Data_Settings::enabled(), 'unchecked form revokes consent' );
$_GET = array(); ob_start(); Email_Countdown_Timer_Data_Settings::render(); $html = ob_get_clean();
expect( str_contains( $html, 'Delete all plugin data when uninstalling' ) && ! str_contains( $html, 'checked="checked"' ), 'English opt-in starts unchecked' );
expect( str_contains( $html, 'aria-describedby=' ) && str_contains( $html, '_wpnonce' ), 'accessible field and nonce' );
// Status is read-only and exact-allowlisted, not evidence of HTTP enforcement.
$status_before = $db;
foreach ( array( 'written', 'incomplete', 'unknown', '<script>alert(1)</script>', array( 'written' ) ) as $status ) {
    $_GET = array( 'font_rules' => $status );
    ob_start(); Email_Countdown_Timer_Data_Settings::render(); $notice_html = ob_get_clean();
    expect( str_contains( $notice_html, 'HTTP protection is not verified.' ) === in_array( $status, array( 'written', 'incomplete' ), true ), 'only allowed rule-status labels display the fixed notice' );
    expect( $db === $status_before && ! str_contains( $notice_html, '<script>alert(1)</script>' ), 'read-only notice neither mutates options nor outputs raw query content' );
}
$_GET = array();
// Both WordPress DB-backed and external-cache paths must remove owned DB rows.
foreach ( array( false, true ) as $cache_backend ) {
    $site = 1; $db = array(); $objects = array(); $external = $cache_backend;
    put_option( $policy, '1' ); put_option( 'easy_countdown_timers', $timers );
    $sentinels = array( 'cron' => array( 'wp_version_check' => 'keep' ), 'siteurl' => 'https://example.test', 'other_plugin_setting' => 'keep', '_transient_ecd_v1211_not-our-key' => 'keep', '_transient_ecd_img_not-our-key' => 'keep', '_transientXecdXimg_' . str_repeat( 'a', 32 ) => 'keep' );
    foreach ( $sentinels as $key => $value ) { put_option( $key, $value ); }
    foreach ( array( 'png', 'gif', 'webp' ) as $fmt ) {
        $key = 'ecd_v1211_' . hash( 'sha256', 'sale|' . $fmt );
        $objects[1][$key] = 'image'; put_option( '_transient_' . $key, 'image' ); put_option( '_transient_timeout_' . $key, 1 );
    }
    // More than two batches, not associated with any surviving timer IDs.
    for ( $i = 0; $i < 205; ++$i ) {
        $key = 'ecd_img_' . md5( 'legacy-' . $i );
        put_option( '_transient_' . $key, 'old' ); put_option( '_transient_timeout_' . $key, 1 );
    }
    put_option( '_transient_timeout_ecd_img_' . md5( 'timeout-only' ), 1 );
    put_option( '_transient_ecd_v1211_' . hash( 'sha256', 'orphan' ), 'old' );
    $objects[1]['other_plugin_cache'] = 'keep';
    include __DIR__ . '/../uninstall.php';
    expect( count( $db[1] ) === count( $sentinels ), 'all owned rows removed across batches' );
    foreach ( $sentinels as $key => $value ) { expect( get_option( $key ) === $value, 'unrelated data survives: ' . $key ); }
    if ( $external ) { expect( array( 'other_plugin_cache' => 'keep' ) === $objects[1], 'known external keys removed without global flushing' ); }
    $before = $db; Email_Countdown_Timer_Uninstaller::run();
    expect( $before === $db, 'uninstall is idempotent' );
}
// Network uninstall: consent is per site, and enumeration must go past 100 sites.
$multisite = true; $sites = range( 1, 205 ); $db = array();
foreach ( $sites as $site ) {
    put_option( $policy, 0 === $site % 2 ? '1' : '0' ); put_option( 'easy_countdown_timers', $timers ); put_option( 'cron', 'keep' );
}
$site = 7; Email_Countdown_Timer_Uninstaller::run();
expect( 7 === $site && array() === $stack, 'original blog restored' );
foreach ( $sites as $id ) {
    expect( isset( $db[$id]['easy_countdown_timers'] ) === ( 1 === $id % 2 ), 'site-specific consent ' . $id );
    expect( isset( $db[$id]['cron'] ), 'network cron untouched ' . $id );
}
$site = 204; put_option( $policy, '1' ); put_option( 'easy_countdown_timers', $timers );
$site = 7; $wpdb->fail_site = 204;
try { Email_Countdown_Timer_Uninstaller::run(); expect( false, 'DB failure must stop cleanup' ); }
catch ( RuntimeException $e ) { expect( str_contains( $e->getMessage(), 'could not enumerate' ), 'database failure surfaced' ); }
expect( 7 === $site && array() === $stack && isset( $db[204][$policy] ), 'failure restores blog and keeps consent for retry' );
echo 'PASS ' . $checks . ' lifecycle assertions on PHP ' . PHP_VERSION . " (WordPress/database doubles)\n";
