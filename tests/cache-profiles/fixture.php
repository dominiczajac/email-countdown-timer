<?php
/** Owned disposable CLI fixture/configuration only; no runtime changes. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcache_' ) { exit( 1 ); }
$mode = $args[0] ?? '';
$profile = getenv( 'ECD_CACHE_PROFILE' );
if ( $mode === 'seed' ) {
    require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
    $config = Email_Countdown_Timer_Config::normalize( array_replace( Email_Countdown_Timer_Admin::defaults(), array( 'deadline' => '2035-12-31T23:59:59', 'tz' => 'UTC', 'fixed_width' => 600, 'alt' => 'Cache lab countdown' ) ) );
    update_option( 'easy_countdown_timers', array( 'cache-lab' => $config + array( 'campaign_name' => 'PRIVATE-CACHE-LAB-NAME' ) ), false );
    $upload = wp_upload_dir();
    $file = $upload['path'] . '/cache-control.png';
    $image = imagecreatetruecolor( 80, 40 ); imagefill( $image, 0, 0, imagecolorallocate( $image, 20, 80, 150 ) ); imagepng( $image, $file );
    $content = '[ecd_cache_origin]<h2>Image countdown</h2>[ecd_timer id="cache-lab"]';
    $content .= '<!-- wp:easy-countdown/timer {"timerId":"cache-lab","displayMode":"live"} /-->';
    $content .= '<div style="height:2500px"></div><img id="cache-control-image" src="' . esc_url( $upload['url'] . '/cache-control.png' ) . '" width="80" height="40" alt="Synthetic control image">';
    $pages = array();
    foreach ( array( 1, 2, 3 ) as $pair ) {
        foreach ( array( 'off', 'on' ) as $state ) {
            $key = 'pair-' . $pair . '-' . $state;
            $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $key, 'post_title' => 'Cache lab ' . $key, 'post_content' => $content ), true );
            if ( is_wp_error( $id ) ) { WP_CLI::error( 'Could not create owned fixture.' ); }
            $pages[ $key ] = array( 'id' => $id, 'url' => get_permalink( $id ) );
        }
    }
    echo wp_json_encode( array( 'pages' => $pages, 'deadline' => Email_Countdown_Timer_Config::deadline( $config ) ) );
} elseif ( $mode === 'configure' ) {
    if ( $profile === 'wp-super-cache' ) {
        // Use the installed vendor's activation checks, not a fabricated drop-in.
        // Upstream 3.1.3: Automattic/wp-super-cache a4f8616 inc/lifecycle.php.
        if ( ! wp_cache_verify_cache_dir() || ! wpsc_check_advanced_cache() || ! wp_cache_verify_config_file() ) { WP_CLI::error( 'WP Super Cache fixture initialization failed.' ); }
        wp_cache_enable(); wp_super_cache_enable();
        if ( wpsc_identify_advanced_cache() !== 'WPSC' ) { WP_CLI::error( 'Expected the owned WP Super Cache drop-in.' ); }
    } elseif ( $profile === 'autoptimize' ) {
        foreach ( array( 'autoptimize_html', 'autoptimize_js', 'autoptimize_js_aggregate', 'autoptimize_css', 'autoptimize_css_aggregate' ) as $key ) { update_option( $key, 'on' ); }
    }
} elseif ( $mode === 'purge' ) {
    // Every profile owns a fresh site/database/cache directory; never a shared host.
    if ( function_exists( 'wp_cache_clear_cache' ) ) { wp_cache_clear_cache(); }
    if ( function_exists( 'w3tc_flush_all' ) ) { w3tc_flush_all(); }
    if ( class_exists( 'autoptimizeCache' ) ) { autoptimizeCache::clearall(); }
} elseif ( $mode === 'inventory' ) {
    global $wpdb, $cache_enabled, $super_cache_enabled, $wp_cache_mod_rewrite, $cache_rebuild_files;
    $configuration = array();
    if ( $profile === 'wp-super-cache' ) { $configuration = array( 'cache_enabled' => $cache_enabled ?? null, 'super_cache_enabled' => $super_cache_enabled ?? null, 'wp_cache_mod_rewrite' => $wp_cache_mod_rewrite ?? null, 'cache_rebuild_files' => $cache_rebuild_files ?? null ); }
    if ( $profile === 'w3-total-cache' ) {
        $c = \W3TC\Dispatcher::config();
        foreach ( array( 'pgcache.enabled', 'pgcache.engine', 'browsercache.enabled', 'lazyload.enabled' ) as $key ) { $configuration[ $key ] = $c->get( $key ); }
    }
    if ( $profile === 'autoptimize' ) {
        foreach ( array( 'autoptimize_html', 'autoptimize_js', 'autoptimize_js_aggregate', 'autoptimize_css', 'autoptimize_css_aggregate' ) as $key ) { $configuration[ $key ] = get_option( $key ); }
    }
    echo wp_json_encode( array( 'wordpress' => get_bloginfo( 'version' ), 'php_cli' => PHP_VERSION, 'mysql' => $wpdb->get_var( 'SELECT VERSION()' ), 'theme' => array( 'name' => get_stylesheet(), 'version' => wp_get_theme()->get( 'Version' ) ), 'active_plugins' => array_values( get_option( 'active_plugins', array() ) ), 'configuration' => $configuration, 'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE, 'advanced_cache_dropin' => is_file( WP_CONTENT_DIR . '/advanced-cache.php' ) ) );
} else { exit( 1 ); }
