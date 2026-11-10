<?php
/** Lab-only observer, derived from PR #13. Never shipped or used on production. */
if ( ! defined( 'ABSPATH' ) || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcache_' ) { exit; }
add_filter( 'pre_wp_mail', '__return_false' );
add_action( 'admin_menu', static function () {
    add_menu_page( 'Cache lab media control', 'Cache lab media control', 'manage_options', 'ecd-cache-media-control', static function () {
        echo '<div id="ecd-cache-media-control">Synthetic core media-enqueue control</div>';
    } );
} );
add_action( 'admin_enqueue_scripts', static function ( $hook ) {
    if ( $hook === 'toplevel_page_ecd-cache-media-control' ) { wp_enqueue_media(); }
} );
add_shortcode( 'ecd_cache_origin', static function () {
    return '<span hidden id="ecd-cache-origin" data-generation="' . esc_attr( bin2hex( random_bytes( 12 ) ) ) . '"></span>';
} );
add_action( 'wp', static function () {
    if ( PHP_SAPI === 'cli' ) { return; }
    header( 'X-Ecd-Lab-Cache-Phase1: ' . ( ! empty( $GLOBALS['wp_cache_phase1_loaded'] ) ? 'yes' : 'no' ) );
    header( 'X-Ecd-Lab-Dontcache: ' . ( defined( 'DONOTCACHEPAGE' ) ? 'yes' : 'no' ) );
    header( 'X-Ecd-Lab-Cache-Request: ' . ( empty( $GLOBALS['wp_cache_request_uri'] ) ? 'empty' : 'set' ) );
    header( 'X-Ecd-Lab-Cache-Buffer: ' . ( in_array( 'wp_cache_ob_callback', ob_list_handlers(), true ) ? 'yes' : 'no' ) );
    header( 'X-Ecd-Lab-Cache-Page: ' . ( is_page() ? 'yes' : 'no' ) );
} );
add_action( 'plugins_loaded', static function () {
    if ( PHP_SAPI === 'cli' ) { return; }
    $status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : false;
    header( 'X-Ecd-Lab-Sapi: ' . PHP_SAPI );
    header( 'X-Ecd-Lab-PHP: ' . PHP_VERSION );
    header( 'X-Ecd-Lab-OPcache: ' . ( is_array( $status ) ? 'on' : 'off' ) );
    header( 'X-Ecd-Lab-JIT: ' . ini_get( 'opcache.jit' ) );
    header( 'X-Ecd-Lab-JIT-Active: ' . ( ! empty( $status['jit']['on'] ) ? 'on' : 'off' ) );
    header( 'X-Ecd-Lab-GD: ' . ( function_exists( 'imagecreatetruecolor' ) ? 'yes' : 'no' ) );
    header( 'X-Ecd-Lab-Active: ' . ( class_exists( 'Email_Countdown_Timer_Plugin', false ) ? 'on' : 'off' ) );
}, 999 );
