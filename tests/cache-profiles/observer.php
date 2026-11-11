<?php
/** Lab-only observer, derived from PR #13. Never shipped or used on production. */
if ( ! defined( 'ABSPATH' ) || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcache_' ) { exit; }
add_filter( 'pre_wp_mail', '__return_false' );
// Explicit offline/local-font configuration for the Elementor profile.
// Public Elementor hook; unexpected external browser attempts still fail.
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
// Pinned Elementor 4.3.2 admin/home/promotion font-only handles bypass the
// frontend switch. Keep their UI/CSS/scripts, using system font fallbacks.
// Unknown handles/hosts are untouched and still fail the browser network gate.
add_filter( 'style_loader_src', static function ( $src, $handle ) {
    $fonts = array( 'elementor-admin-top-bar-fonts', 'elementor-home-screen-fonts', 'elementor-onboarding-fonts', 'elementor-atomic-widgets-promotion-fonts' );
    if ( ! defined( 'ELEMENTOR_VERSION' ) || ELEMENTOR_VERSION !== '4.3.2' || ! in_array( $handle, $fonts, true )
        || wp_parse_url( $src, PHP_URL_HOST ) !== 'fonts.googleapis.com' ) { return $src; }
    if ( PHP_SAPI !== 'cli' && defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && basename( dirname( WP_DEBUG_LOG ) ) === 'cache-evidence' ) {
        file_put_contents( dirname( WP_DEBUG_LOG ) . '/local-font-overrides.jsonl', wp_json_encode( array( 'handle' => $handle, 'admin' => is_admin() ) ) . "\n", FILE_APPEND | LOCK_EX );
    }
    return '';
}, 10, 2 );
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
add_filter( 'wp_cache_ob_callback_filter', static function ( $body ) {
    // Fixed synthetic eligibility fields only. Never enable vendor raw debug
    // logging, which can include cookies or request arguments.
    if ( PHP_SAPI === 'cli' || ! defined( 'WP_DEBUG_LOG' ) || ! is_string( WP_DEBUG_LOG ) || basename( dirname( WP_DEBUG_LOG ) ) !== 'cache-evidence' ) { return $body; }
    $path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
    $key = is_string( $path ) && preg_match( '~^/(pair-[1-3]-(?:on|off))/$~', $path, $matches ) ? $matches[1] : 'supplementary';
    $query = array();
    foreach ( array( 'is_page', 'is_single', 'is_front_page', 'is_home', 'is_rest', 'is_redirect', 'is_304' ) as $flag ) { $query[ $flag ] = ! empty( $GLOBALS['wp_super_cache_query'][ $flag ] ); }
    $record = array( 'state' => $key, 'query' => $query,
        'enabled' => ! empty( $GLOBALS['cache_enabled'] ), 'super_enabled' => ! empty( $GLOBALS['super_cache_enabled'] ),
        'dontcache' => defined( 'DONOTCACHEPAGE' ), 'max_time' => (int) ( $GLOBALS['cache_max_time'] ?? 0 ),
        'get_query' => wpsc_is_get_query(), 'uri_rejected' => wp_cache_is_rejected( $GLOBALS['wp_cache_request_uri'] ?? '' ),
        'agent_rejected' => wp_cache_user_agent_is_rejected(), 'known_user_disabled' => wpsc_is_caching_user_disabled(),
        'page_rejected' => ! empty( $GLOBALS['wp_cache_pages']['pages'] ), 'headers_sent' => headers_sent(),
    );
    file_put_contents( dirname( WP_DEBUG_LOG ) . '/cache-eligibility.jsonl', wp_json_encode( $record ) . "\n", FILE_APPEND | LOCK_EX );
    return $body;
}, 999 );
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
