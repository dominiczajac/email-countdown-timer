<?php
/** Only the owned network's ordinary vendor API/configuration; never shipped. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local'
    || $GLOBALS['wpdb']->base_prefix !== 'ecdnetwork_' || ! is_multisite() ) { exit( 1 ); }

final class Email_Countdown_Timer_Network_Super_Cache {
    private static function settings(): array {
        return array( 'wp_cache_mod_rewrite' => 0, 'cache_max_time' => 3600, 'cache_rebuild_files' => 1,
            'cache_compression' => 0, 'ossdlcdn' => 0, 'wp_cache_not_logged_in' => 2, 'wp_cache_slash_check' => 1,
            'wpsc_served_header' => true, 'wp_super_cache_late_init' => 0, 'wp_cache_preload_on' => 0,
            'wp_cache_debug_to_file' => 0, 'wp_super_cache_advanced_debug' => 0, 'wp_cache_mfunc_enabled' => 0, 'wpsc_save_headers' => 0 );
    }

    public static function configure(): void {
        if ( ! wp_cache_verify_cache_dir() || ! wpsc_check_advanced_cache() || ! wp_cache_verify_config_file() ) {
            WP_CLI::error( 'Owned WP Super Cache initialization failed.' );
        }
        wp_cache_enable(); wp_super_cache_enable();
        foreach ( self::settings() as $key => $value ) { wp_cache_setting( $key, $value ); }
        if ( wpsc_identify_advanced_cache() !== 'WPSC' ) { WP_CLI::error( 'Expected vendor drop-in.' ); }
    }

    public static function inventory(): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $data = get_plugin_data( WP_PLUGIN_DIR . '/wp-super-cache/wp-cache.php', false, false );
        // Read only fixed configuration fields in an isolated scope. Never emit
        // the config file, cookie settings, cache-page secrets or vendor debug.
        $fields = array_keys( self::settings() );
        $result = ( static function ( array $fields ): array {
            include WPCACHECONFIGPATH . '/wp-cache-config.php';
            $values = get_defined_vars(); $selected = array();
            foreach ( array_merge( array( 'cache_enabled', 'super_cache_enabled' ), $fields ) as $key ) { $selected[ $key ] = $values[ $key ] ?? null; }
            return $selected;
        } )( $fields );
        return array( 'version' => $data['Version'], 'dropin' => wpsc_identify_advanced_cache() ) + $result;
    }

    public static function purge(): void {
        // One fresh, owned network per cell; this is an explicit network purge.
        wp_cache_clear_cache();
    }
}
