<?php
/** Disposable network observer; excluded from the installation manifest. */
if ( ! defined( 'ABSPATH' ) || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ecdnetwork_' ) { exit; }
add_filter( 'pre_wp_mail', '__return_false' );
add_shortcode( 'ecd_cache_origin', static function () {
    return '<span hidden id="ecd-cache-origin" data-generation="' . esc_attr( bin2hex( random_bytes( 12 ) ) ) . '"></span>';
} );
add_action( 'plugins_loaded', static function () {
    if ( PHP_SAPI === 'cli' ) { return; }
    $status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : false;
    $fields = array( 'blog' => get_current_blog_id(), 'home' => home_url( '/' ), 'theme' => get_stylesheet(),
        'sapi' => PHP_SAPI, 'php' => PHP_VERSION, 'opcache' => is_array( $status ) ? 'on' : 'off',
        'jit' => ini_get( 'opcache.jit' ), 'jit-active' => ! empty( $status['jit']['on'] ) ? 'on' : 'off',
        'gd' => function_exists( 'imagecreatetruecolor' ) ? 'yes' : 'no',
        'active' => class_exists( 'Email_Countdown_Timer_Plugin', false ) ? 'on' : 'off',
        'memory' => ini_get( 'memory_limit' ), 'execution' => ini_get( 'max_execution_time' ) );
    foreach ( $fields as $name => $value ) { header( 'X-Ecd-Network-' . $name . ': ' . $value ); }
}, 999 );
