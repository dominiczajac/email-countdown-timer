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
function add_filter( ...$args ) { $GLOBALS['filters'][] = $args; }
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../email-countdown-timer.php';
if ( 'image' === $case ) {
    if ( ! defined( 'DONOTCACHEPAGE' ) || true !== DONOTCACHEPAGE || array( array( 'flying_press_is_cacheable', '__return_false' ) ) !== $filters ) { exit( 1 ); }
} elseif ( defined( 'DONOTCACHEPAGE' ) || array() !== $filters ) { exit( 1 ); }
echo 'PASS scoped cache bootstrap: ' . $case . "\n";
