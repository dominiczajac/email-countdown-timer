<?php
/** Keep fixture data separate from third-party WP-CLI/shutdown diagnostics. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcache_' ) { exit( 1 ); }

final class Email_Countdown_Timer_Lab_CLI {
    public static function write_json( string $name, array $value ): void {
        $directory = realpath( getenv( 'ECD_CACHE_EVIDENCE' ) ?: '' );
        $temp = realpath( getenv( 'RUNNER_TEMP' ) ?: '' );
        if ( ! in_array( $name, array( 'fixture', 'environment' ), true ) || ! $temp || ! $directory
            || $directory !== $temp . '/cache-evidence' || is_link( getenv( 'ECD_CACHE_EVIDENCE' ) ) ) {
            WP_CLI::error( 'Invalid owned CLI evidence path.' );
        }
        $json = wp_json_encode( $value );
        if ( ! is_string( $json ) ) { WP_CLI::error( 'Could not encode CLI evidence.' ); }
        // Exclusive create rejects a stale result or symlink. Callers remove the
        // previous fixed inventory file before invoking a new CLI process.
        $file = fopen( $directory . '/' . $name . '.json', 'xb' );
        if ( ! $file ) { WP_CLI::error( 'CLI evidence must be a new file.' ); }
        $written = fwrite( $file, $json . "\n" );
        fclose( $file );
        if ( $written !== strlen( $json ) + 1 ) { WP_CLI::error( 'Could not write complete CLI evidence.' ); }
    }
}
