<?php
/** Focused adapter checks with existing controller doubles; not a real editor. */
require __DIR__ . '/run.php';
require_once __DIR__ . '/../includes/class-email-countdown-timer-block.php';
class WP_Post { public function __construct( public int $ID ) {} }
function get_block_wrapper_attributes( $attributes ) { return 'style="' . esc_attr( $attributes['style'] ) . '"'; }
function wp_register_script( ...$args ) { $GLOBALS['block_script'] = $args; }
function wp_set_script_translations( ...$args ) {}
function add_filter( ...$args ) {}
function register_block_type( ...$args ) { $GLOBALS['block_type'] = $args; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_add_inline_script( $handle, $data, $position ) { $GLOBALS['block_bootstrap'] = array( $handle, $data, $position ); return true; }
function wp_create_nonce( $action ) { return 'test-nonce-' . $action; }
function check_ajax_referer( $action, $key ) {
    if ( ! $GLOBALS['nonce'] || ( $_POST[ $key ] ?? null ) !== wp_create_nonce( $action ) ) throw new ECD_Test_Stop( 'ajax-nonce' );
}
function get_post( $id ) { return $GLOBALS['block_posts'][ $id ] ?? null; }
class ECD_Block_Response extends RuntimeException {
    public function __construct( public bool $success, public $data, public int $status ) { parent::__construct( 'block-response' ); }
}
function wp_send_json_error( $data = null, $status = 200 ) { throw new ECD_Block_Response( false, $data, $status ); }
function wp_send_json_success( $data, $status = 200 ) { throw new ECD_Block_Response( true, $data, $status ); }
$start = $checks;
$options['easy_countdown_timers'] = array( 'block-test' => $base + array( 'campaign_name' => 'Private campaign sentinel', 'archived' => true ) );
$before = $options;
$admin = false;
$context = (object) array( 'post' => new WP_Post( 1 ) );
ok( ! isset( Email_Countdown_Timer_Block::editor_settings( array( 'easyCountdown' => 'stale' ), $context )['easyCountdown'] ), 'unauthorized context has no editor list' );
ok( $GLOBALS['block_bootstrap'][1] === 'window.emailCountdownTimerBlock = {};', 'denied context clears previous editor payload' );
$admin = true;
$data = Email_Countdown_Timer_Block::editor_settings( array(), $context )['easyCountdown'];
ok( $data['timers'] === array( array( 'value' => 'block-test', 'label' => 'block-test' ) ), 'choices contain IDs only' );
ok( str_contains( $data['imageUrl'], 'mode=static' ), 'preview URL is static' );
ok( $data['manageUrl'] !== '', 'administrator settings link' );
ok( str_ends_with( $data['createUrl'], '#ect-quick-create' ), 'administrator empty state links directly to quick creation' );
ok( $data['refresh']['postId'] === 1 && $data['refresh']['nonce'] === wp_create_nonce( 'email_countdown_timer_campaigns_1' ), 'refresh nonce is bound to the concrete post' );
ok( $GLOBALS['block_bootstrap'][0] === 'easy-countdown-block-editor' && $GLOBALS['block_bootstrap'][2] === 'before', 'data only attached before the editor-only script' );
$bootstrap = substr( $GLOBALS['block_bootstrap'][1], strlen( 'window.emailCountdownTimerBlock = ' ), -1 );
ok( json_decode( $bootstrap, true ) === $data, 'native script bridge carries the authorized list without relying on store keys' );
ok( !str_contains( $bootstrap, '<' ) && !str_contains( $bootstrap, 'deadline' ), 'bootstrap excludes raw HTML and campaign configuration' );
ok( ! str_contains( $bootstrap, 'Private campaign sentinel' ) && ! str_contains( $bootstrap, 'campaign_name' ), 'editor catalog excludes private campaign names even for administrators' );
ok( ! str_contains( $bootstrap, 'archived' ) && str_contains( $bootstrap, 'block-test' ), 'archived timer IDs remain selectable without exposing private archive metadata' );
foreach ( array( '', null, array(), 'unknown', 'BLOCK-TEST', 'block-test ', str_repeat( 'a', 201 ) ) as $invalid ) {
    ok( Email_Countdown_Timer_Block::render( array( 'timerId' => $invalid ), $plugin ) === '', 'invalid or missing reference has no public image' );
}
foreach ( array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ) as $alignment => $expected ) {
    $html = Email_Countdown_Timer_Block::render( array( 'timerId' => 'block-test', 'alignment' => $alignment ), $plugin );
    ok( str_contains( $html, 'justify-content:' . $expected ) && str_contains( $html, 'data-ecd-src=' ), 'alignment and shared shortcode output: ' . $alignment );
}
foreach ( array( array(), 'unknown', 'center;display:none' ) as $invalid ) {
    ok( str_contains( Email_Countdown_Timer_Block::render( array( 'timerId' => 'block-test', 'alignment' => $invalid ), $plugin ), 'justify-content:flex-start' ), 'alignment is allowlisted' );
}
ok( $options === $before, 'render and discovery do not write settings' );
$options['easy_countdown_timers'] = array_fill_keys( array_map( static fn( $i ) => 'timer-' . $i, range( 1, 251 ) ), $base );
$data = Email_Countdown_Timer_Block::editor_settings( array(), $context )['easyCountdown'];
ok( count( $data['timers'] ) === 250 && $data['truncated'], 'bounded list has explicit truncation' );
$refresh = static function ( array $post, string $method = 'POST' ) {
    $_POST = $post; $_SERVER['REQUEST_METHOD'] = $method;
    try { Email_Countdown_Timer_Block::refresh_campaigns(); } catch ( ECD_Block_Response $response ) { return $response; }
    catch ( ECD_Test_Stop $response ) { return $response->getMessage(); }
    throw new RuntimeException( 'Missing JSON response' );
};
$request = array( 'post_id' => '1', 'nonce' => wp_create_nonce( 'email_countdown_timer_campaigns_1' ) );
$block_posts = array( 1 => new WP_Post( 1 ), 2 => new WP_Post( 2 ) );
$test_capabilities = array( 'edit_post' => array( 1 => true, 2 => false ), 'manage_options' => false, 'edit_theme_options' => false );
$author_data = Email_Countdown_Timer_Block::editor_settings( array(), $context )['easyCountdown'];
ok( $author_data['manageUrl'] === '' && $author_data['createUrl'] === '', 'content editor gets catalog access without administration links' );
$response = $refresh( $request );
ok( $response->success && count( $response->data['timers'] ) === 250 && $response->data['truncated'], 'authorized refresh keeps the 250-ID bound' );
ok( $response->data['manageUrl'] === '' && $response->data['createUrl'] === '', 'author refresh cannot gain campaign management links' );
$options['easy_countdown_timers'] = array( 'created-in-another-tab' => $base );
$snapshot = $options;
$response = $refresh( $request );
ok( $response->success && $response->data['timers'] === array( array( 'value' => 'created-in-another-tab', 'label' => 'created-in-another-tab' ) ), 'explicit refresh reads newly created campaigns without editor reload' );
ok( $options === $snapshot && ! isset( $response->data['refresh'] ), 'refresh neither mutates campaigns nor returns more credentials' );
ok( $refresh( $request, 'GET' )->status === 405, 'catalog refresh requires POST' );
foreach ( array( '', '-1', '1.2', '999999999999999999999999', array( '1' ) ) as $invalid_post ) {
    ok( $refresh( array_replace( $request, array( 'post_id' => $invalid_post ) ) )->status === 400, 'malformed editing context rejected before catalog discovery' );
}
ok( $refresh( array_replace( $request, array( 'nonce' => '' ) ) ) === 'ajax-nonce', 'refresh requires a valid nonce' );
ok( $refresh( array_replace( $request, array( 'post_id' => '2' ) ) ) === 'ajax-nonce', 'a post nonce cannot be reused for another post' );
ok( $refresh( array( 'post_id' => '2', 'nonce' => wp_create_nonce( 'email_countdown_timer_campaigns_2' ) ) )->status === 403, 'valid nonce alone does not grant another post capability' );
ok( $refresh( array( 'post_id' => '3', 'nonce' => wp_create_nonce( 'email_countdown_timer_campaigns_3' ) ) )->status === 403, 'nonexistent post cannot provide a catalog context' );
$test_capabilities['edit_post'][1] = false;
ok( $refresh( $request )->status === 403, 'permission loss is rechecked after editor bootstrap' );
$site_request = array( 'post_id' => '0', 'nonce' => wp_create_nonce( 'email_countdown_timer_campaigns_0' ) );
ok( $refresh( $site_request )->status === 403, 'author cannot impersonate the site editor' );
unset( $test_capabilities );
$admin = false;
ok( $refresh( $request )->status === 403 && $options === $snapshot, 'denied refresh exposes no campaign data and makes no write' );
$admin = true;
ok( $refresh( $site_request )->success, 'site editor uses edit_theme_options and a site-context nonce' );
$_POST = array();
Email_Countdown_Timer_Block::register( $plugin );
ok( str_ends_with( $GLOBALS['block_type'][0], 'blocks/timer' ), 'native metadata registration' );
ok( in_array( 'wp-block-editor', $GLOBALS['block_script'][2], true ), 'uses core editor dependency' );
$options = $before;
require __DIR__ . '/live-block.php';
echo 'GUTENBERG DOUBLES PASS: ' . ( $checks - $start ) . " assertions\n";
