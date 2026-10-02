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
$start = $checks;
$options['easy_countdown_timers'] = array( 'block-test' => $base );
$before = $options;
$admin = false;
$context = (object) array( 'post' => new WP_Post( 1 ) );
ok( ! isset( Email_Countdown_Timer_Block::editor_settings( array( 'easyCountdown' => 'stale' ), $context )['easyCountdown'] ), 'unauthorized context has no editor list' );
$admin = true;
$data = Email_Countdown_Timer_Block::editor_settings( array(), $context )['easyCountdown'];
ok( $data['timers'] === array( array( 'value' => 'block-test', 'label' => 'block-test' ) ), 'choices contain IDs only' );
ok( str_contains( $data['imageUrl'], 'mode=static' ), 'preview URL is static' );
ok( $data['manageUrl'] !== '', 'administrator settings link' );
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
Email_Countdown_Timer_Block::register( $plugin );
ok( str_ends_with( $GLOBALS['block_type'][0], 'blocks/timer' ), 'native metadata registration' );
ok( in_array( 'wp-block-editor', $GLOBALS['block_script'][2], true ), 'uses core editor dependency' );
$options = $before;
echo 'GUTENBERG DOUBLES PASS: ' . ( $checks - $start ) . " assertions\n";
