<?php
/** Fixed vendor configuration for the owned disposable CLI fixture only. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcache_' ) { exit( 1 ); }

final class Email_Countdown_Timer_Lab_LiteSpeed {
    private static function settings(): array {
        return array( 'cache' => 1, 'cache-ttl_pub' => 1800, 'guest' => false, 'object' => false, 'crawler' => false,
            'cdn' => false, 'cdn-quic' => false, 'optm-css_min' => false, 'optm-js_min' => false,
            'optm-html_min' => false, 'media-lazy' => false );
    }

    public static function configure(): void {
        // Normal vendor save path updates WP_CACHE and its .htaccess rules.
        \LiteSpeed\Conf::get_instance()->update_confs( self::settings() );
    }

    public static function inventory(): array {
        $conf = \LiteSpeed\Conf::get_instance();
        $result = array( 'version' => LSCWP_V );
        foreach ( self::settings() as $key => $value ) { $result[ $key ] = $conf->conf( $key ); }
        return $result;
    }
}
