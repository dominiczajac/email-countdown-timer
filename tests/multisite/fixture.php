<?php
/** Fixed synthetic network only; never accepts an arbitrary site/database. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local'
    || $GLOBALS['wpdb']->base_prefix !== 'ecdnetwork_' || ! is_multisite() || is_subdomain_install() ) { exit( 1 ); }

final class Email_Countdown_Timer_Network_Lab {
    public static function write( string $name, array $value ): void {
        $temp = realpath( getenv( 'RUNNER_TEMP' ) ?: '' );
        $directory = realpath( getenv( 'ECD_NETWORK_EVIDENCE' ) ?: '' );
        if ( ! $temp || ! $directory || $directory !== $temp . '/network-evidence'
            || is_link( getenv( 'ECD_NETWORK_EVIDENCE' ) ) || ! in_array( $name, array( 'fixture', 'inventory' ), true ) ) { WP_CLI::error( 'Invalid owned evidence path.' ); }
        $json = wp_json_encode( $value );
        if ( ! is_string( $json ) ) { WP_CLI::error( 'Invalid evidence data.' ); }
        $file = fopen( $directory . '/' . $name . '.json', 'xb' );
        if ( ! $file ) { WP_CLI::error( 'Evidence must be new.' ); }
        $written = fwrite( $file, $json . "\n" ); fclose( $file );
        if ( $written !== strlen( $json ) + 1 ) { WP_CLI::error( 'Incomplete evidence.' ); }
    }

    private static function canonical( array $data ): array {
        ksort( $data );
        foreach ( $data as &$value ) { if ( is_array( $value ) ) { $value = self::canonical( $value ); } }
        return $data;
    }

    public static function sites( bool $seed ): array {
        global $wpdb;
        $sites = get_sites( array( 'number' => 10, 'orderby' => 'id', 'order' => 'ASC' ) );
        if ( count( $sites ) !== 3 ) { WP_CLI::error( 'Expected three disposable sites.' ); }
        $result = array();
        foreach ( $sites as $index => $site ) {
            $role = array( 'root', 'blue', 'red' )[ $index ];
            $path = $index ? '/' . $role . '/' : '/';
            if ( (int) $site->blog_id !== $index + 1 || $site->path !== $path ) { WP_CLI::error( 'Unexpected owned network layout.' ); }
            switch_to_blog( (int) $site->blog_id );
            try {
                if ( $seed ) {
                    $config = Email_Countdown_Timer_Config::normalize( array_replace( Email_Countdown_Timer_Admin::defaults(), array(
                        'deadline' => '2035-12-' . ( 29 + $index ) . 'T23:59:59', 'tz' => 'UTC', 'fixed_width' => 480 + 60 * $index,
                        'alt' => 'Synthetic ' . $role . ' countdown', 'campaign_name' => 'PRIVATE-NETWORK-' . $role,
                    ) ) );
                    $config['campaign_name'] = 'PRIVATE-NETWORK-' . $role;
                    update_option( 'easy_countdown_timers', array( 'shared-timer' => $config, 'only-' . $role => $config ), false );
                    $pages = array();
                    foreach ( array( 'pair-1-off', 'pair-1-on', 'pair-2-on', 'pair-2-off', 'pair-3-off', 'pair-3-on' ) as $key ) {
                        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $key,
                            'post_title' => 'Synthetic ' . $role . ' ' . $key,
                            'post_content' => '[ecd_cache_origin][ecd_timer id="shared-timer"]<!-- wp:easy-countdown/timer {"timerId":"shared-timer","displayMode":"live"} /-->' ), true );
                        if ( is_wp_error( $id ) ) { WP_CLI::error( 'Could not seed page.' ); }
                        $pages[ $key ] = get_permalink( $id );
                    }
                }
                $timers = get_option( 'easy_countdown_timers', array() );
                $entry = array( 'role' => $role, 'id' => get_current_blog_id(), 'home' => home_url( '/' ), 'path' => $path,
                    'wordpress' => get_bloginfo( 'version' ), 'php_cli' => PHP_VERSION, 'mysql' => $wpdb->get_var( 'SELECT VERSION()' ),
                    'theme' => array( 'name' => get_stylesheet(), 'version' => wp_get_theme()->get( 'Version' ), 'block' => wp_is_block_theme() ),
                    'site_plugins' => array_values( get_option( 'active_plugins', array() ) ),
                    'network_plugins' => array_keys( get_site_option( 'active_sitewide_plugins', array() ) ),
                    'campaign_keys' => array_keys( $timers ), 'campaign_sha256' => hash( 'sha256', wp_json_encode( self::canonical( $timers ) ) ),
                    'width' => $timers['shared-timer']['fixed_width'], 'deadline' => Email_Countdown_Timer_Config::deadline( $timers['shared-timer'] ),
                    'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE, 'object_cache_dropin' => is_file( WP_CONTENT_DIR . '/object-cache.php' ),
                    'advanced_cache_dropin' => is_file( WP_CONTENT_DIR . '/advanced-cache.php' ) );
                if ( $seed ) { $entry['pages'] = $pages; }
                $result[] = $entry;
            } finally { restore_current_blog(); }
        }
        return $result;
    }
}

$mode = $args[0] ?? '';
if ( $mode === 'seed' ) {
    require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
    Email_Countdown_Timer_Network_Lab::write( 'fixture', array( 'sites' => Email_Countdown_Timer_Network_Lab::sites( true ) ) );
} elseif ( $mode === 'inventory' ) {
    Email_Countdown_Timer_Network_Lab::write( 'inventory', array( 'sites' => Email_Countdown_Timer_Network_Lab::sites( false ) ) );
} else { exit( 1 ); }
