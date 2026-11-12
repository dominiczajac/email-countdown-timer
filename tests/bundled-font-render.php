<?php
/** Native stock-face and 24-hour regressions; no WordPress, network or DB writes. */
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'EMAIL_COUNTDOWN_TIMER_DIR', ABSPATH );
function __( $text, $domain = '' ) { return $text; }
function wp_date( $format ) { return '2031-12-31T23:59:59'; }
function wp_timezone_string() { return 'UTC'; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_hex_color( $text ) { return preg_match( '/^#[a-f0-9]{6}$/iD', $text ) ? $text : null; }
require ABSPATH . 'includes/class-email-countdown-timer-fonts.php';
require ABSPATH . 'includes/class-ecd-config.php';
require ABSPATH . 'includes/class-ecd-renderer.php';
require ABSPATH . 'includes/class-email-countdown-timer-admin.php';
if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagettftext' ) || ! function_exists( 'imagettfbbox' ) ) {
    if ( '1' === getenv( 'ECD_REQUIRE_GD' ) ) { throw new RuntimeException( 'Native GD/FreeType is required.' ); }
    echo "SKIP stock-face rendering: GD/FreeType unavailable.\n";
    exit;
}
if ( '1' === getenv( 'ECD_REQUIRE_IMAGICK' ) && ! class_exists( 'Imagick' ) ) { throw new RuntimeException( 'Native Imagick is required.' ); }
$checks = 0;
$expect = static function ( bool $condition, string $message ) use ( &$checks ): void {
    ++$checks;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
};
$renderer = new Email_Countdown_Timer_Renderer();
$deadline = 2000000000;
foreach ( Email_Countdown_Timer_Fonts::bundled() as $choice => $face ) {
    $base = Email_Countdown_Timer_Admin::defaults();
    unset( $base['expiry_image_id'] );
    $base['font'] = $choice;
    foreach ( Email_Countdown_Timer_Admin::label_presets() as $language => $preset ) {
        $c = Email_Countdown_Timer_Config::normalize( array_replace( $base, $preset['labels'], array( 'hide_days' => 1 ) ) );
        $boundary = $renderer->measure( $c, $deadline, $deadline - 86400 );
        foreach ( array( 86401, 86400, 86399, 86341, 60, 0 ) as $remaining ) {
            $layout = $renderer->measure( $c, $deadline, $deadline - $remaining );
            $expect( $layout['width'] === $boundary['width'] && $layout['height'] === $boundary['height'], 'Same canvas across 24 hours: ' . $choice . '/' . $language );
            $expect( basename( $layout['font'] ?? '' ) === $face['file'], 'Actual bundled font used, not bitmap fallback.' );
            $png = $renderer->render_static( $c, $deadline, $deadline - $remaining, 'png' );
            $image = imagecreatefromstring( $png );
            $expect( $image instanceof GdImage && imagesx( $image ) === $layout['width'] && imagesy( $image ) === $layout['height'], 'Native PNG matches reserved geometry.' );
            // The existing low-level frame drawing keeps glyph sizes; only its
            // explicit canvas width is reserved. Frozen oracle tests cover that drawing.
            $expected = $renderer->drawFrame( $remaining, $c['bg'], $c['dc'], $c['lc'], $c['font'], $c['size_digit'], $c['size_label'], true,
                array( 'd' => $c['label_d'], 'h' => $c['label_h'], 'm' => $c['label_m'], 's' => $c['label_s'] ), $boundary['width'] );
            ob_start(); imagepng( $expected ); $expected_png = ob_get_clean();
            $expect( $png === $expected_png, 'Days hide without scaling glyphs or changing the three remaining units.' );
            unset( $image, $expected );
        }
    }
    $c = Email_Countdown_Timer_Config::normalize( array_replace( $base, array( 'hide_days' => 1 ) ) );
    $before = $renderer->render( $c, $deadline, $deadline - 86410, 'gif' );
    $after = $renderer->render( $c, $deadline, $deadline - 86350, 'gif' );
    $first = imagecreatefromstring( $before ); $next = imagecreatefromstring( $after );
    $expect( imagesx( $first ) === imagesx( $next ) && imagesy( $first ) === imagesy( $next ), 'A new post-boundary GIF keeps the preceding GIF canvas.' );
    unset( $first, $next );
    if ( class_exists( 'Imagick' ) ) {
        foreach ( array( $before, $after ) as $gif ) {
            $frames = new Imagick(); $frames->readImageBlob( $gif );
            $expect( $frames->getNumberImages() === 60, 'Sixty real animation frames.' );
            foreach ( $frames as $frame ) { $expect( $frame->getImageDelay() === 100, 'One-second animation timing.' ); }
            $frames->clear();
        }
    }
    foreach ( array( 0, 1 ) as $hide ) {
        $c['hide_days'] = $hide; $c['fixed_width'] = 600;
        $expect( $renderer->measure( $c, $deadline, $deadline - 86399 )['width'] === 600, 'Explicit width and hidden/visible days retain their contract.' );
    }
}
echo 'PASS ' . $checks . ' native bundled-font/boundary assertions; ' . ( class_exists( 'Imagick' ) ? '60-frame GIFs verified' : 'GD-only single-frame GIF fallback verified; animation unavailable' ) . ".\n";
