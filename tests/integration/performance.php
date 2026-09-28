<?php
/** Synthetic per-request PHP measurements, not an HTTP/hosting load benchmark. */
if ( PHP_SAPI !== 'cli' || ! defined( 'WP_CLI' ) || ! WP_CLI || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' ) {
    exit( 1 );
}
ob_start();
$baseline = get_option( 'easy_countdown_timers', array() );
$get = $_GET;
$server = $_SERVER;
$now = time();
$config = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2030-12-31T23:59:59', 'fixed_width' => 600 ) );
$plugin = new Email_Countdown_Timer_Plugin();
$method = new ReflectionMethod( $plugin, 'generateImage' );
$font = '';
$results = array();
$measure = static function ( callable $callback, int $samples = 9 ): array {
    $times = array();
    $bytes = 0;
    for ( $i = 0; $i < $samples; ++$i ) {
        $start = hrtime( true );
        $bytes = strlen( $callback() );
        $times[] = ( hrtime( true ) - $start ) / 1000000;
    }
    sort( $times );
    return array( 'samples' => $samples, 'median_ms' => round( $times[ intdiv( $samples, 2 ) ], 3 ), 'p95_ms' => round( $times[ (int) ceil( $samples * .95 ) - 1 ], 3 ), 'output_bytes' => $bytes );
};
try {
    $fonts_dir = EMAIL_COUNTDOWN_TIMER_DIR . 'fonts';
    foreach ( array( '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf' ) as $system_font ) {
        if ( is_readable( $system_font ) ) {
            if ( ! is_dir( $fonts_dir ) ) mkdir( $fonts_dir );
            $font = 'benchmark-' . bin2hex( random_bytes( 6 ) ) . '.ttf';
            copy( $system_font, $fonts_dir . '/' . $font );
            break;
        }
    }
    foreach ( array( 'bitmap' => '', 'ttf' => $font ) as $label => $font_name ) {
        if ( 'ttf' === $label && '' === $font_name ) {
            throw new RuntimeException( 'A local test font is required for the TTF benchmark.' );
        }
        $config['font'] = $font_name;
        $deadline = Email_Countdown_Timer_Config::deadline( $config );
        foreach ( array( 'png', 'gif' ) as $format ) {
            $results[ $label . '_' . $format . '_renderer' ] = $measure( static fn() => ( new Email_Countdown_Timer_Renderer() )->render( $config, $deadline, $now, $format ) );
        }
        foreach ( array( 1, 250 ) as $count ) {
            $timers = array();
            for ( $i = 0; $i < $count; ++$i ) $timers[ 'perf-' . $i ] = $config;
            update_option( 'easy_countdown_timers', $timers, false );
            $_GET = array( 'ecd' => 'perf-0', 'mode' => 'email' );
            $_SERVER['HTTP_ACCEPT'] = 'image/gif';
            $key = 'ecd_v1211_' . hash( 'sha256', 'perf-0|gif' );
            $call = static function () use ( $method, $plugin, $now ): string { ob_start(); $method->invoke( $plugin, false, $now ); return (string) ob_get_clean(); };
            $results[ $label . '_gif_cold_' . $count . '_timers' ] = $measure( static function () use ( $key, $call ) { delete_transient( $key ); return $call(); } );
            $call();
            $results[ $label . '_gif_warm_' . $count . '_timers' ] = $measure( $call, 31 );
            $cache = get_transient( $key );
            $results[ $label . '_stored_base64_bytes' ] = strlen( $cache['data'] );
            delete_transient( $key );
        }
    }
} finally {
    update_option( 'easy_countdown_timers', $baseline, false );
    $_GET = $get;
    $_SERVER = $server;
    if ( '' !== $font ) unlink( EMAIL_COUNTDOWN_TIMER_DIR . 'fonts/' . $font );
}
ob_end_clean();
echo json_encode( array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'imagick' => phpversion( 'imagick' ), 'results' => $results,
    'php_process_peak_bytes' => memory_get_peak_usage( true ), 'process_peak_rss_kib_linux' => getrusage()['ru_maxrss'] ?? null,
    'scope' => 'Sequential synthetic local PHP calls in one bootstrapped WordPress process; fixed time and warm Options API cache. Cold means image transient invalidated. Excludes full HTTP/WordPress bootstrap, concurrency and client delivery. Native image memory is not fully captured by PHP allocation counters.' ), JSON_PRETTY_PRINT ) . "\n";
