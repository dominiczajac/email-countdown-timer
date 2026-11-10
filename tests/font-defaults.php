<?php
/** New-campaign font choice with and without an available bundled face/FreeType. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$fixture = sys_get_temp_dir() . '/ect-default-font-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture . '/fonts', 0755, true );
define( 'ABSPATH', $fixture . '/' );
define( 'EMAIL_COUNTDOWN_TIMER_DIR', $fixture . '/' );
require __DIR__ . '/../includes/class-email-countdown-timer-fonts.php';
require __DIR__ . '/../includes/class-ecd-config.php';
require __DIR__ . '/../includes/class-email-countdown-timer-admin.php';
function wp_date( $format ) { return '2031-12-31T23:59:59'; }
function wp_timezone_string() { return 'UTC'; }
$checks = 0;
$expect = static function ( bool $value, string $message ) use ( &$checks ): void {
    ++$checks;
    if ( ! $value ) { throw new RuntimeException( $message ); }
};
$font = $fixture . '/fonts/' . Email_Countdown_Timer_Fonts::BUNDLED_FILE;
try {
    $expect( Email_Countdown_Timer_Admin::defaults()['font'] === '', 'Missing bundled font must propose bitmap.' );
    copy( __DIR__ . '/../fonts/' . Email_Countdown_Timer_Fonts::BUNDLED_FILE, $font );
    $expected = function_exists( 'imagettftext' ) && function_exists( 'imagettfbbox' ) ? Email_Countdown_Timer_Fonts::BUNDLED_CHOICE : '';
    $expect( Email_Countdown_Timer_Admin::defaults()['font'] === $expected, 'New campaigns need both a local file and FreeType before proposing Lato.' );
    $expect( Email_Countdown_Timer_Admin::defaults( false )['font'] === '', 'Existing-record defaults must retain bitmap even with an available bundled font.' );
    unlink( $font );
    $expect( Email_Countdown_Timer_Admin::defaults()['font'] === '', 'Disappearing bundled font must return to bitmap without a persisted default.' );
} finally {
    if ( is_file( $font ) ) { unlink( $font ); }
    rmdir( $fixture . '/fonts' );
    rmdir( $fixture );
}
echo 'PASS ' . $checks . " new-campaign font assertions\n";
