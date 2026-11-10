<?php
/** Loaded by gutenberg.php: public HTML contracts, independent of native GD. */
$options['easy_countdown_timers'] = array( 'live-test' => array_replace( $base, array( 'deadline' => '2035-03-25T02:30:00', 'tz' => 'Europe/Warsaw', 'campaign_name' => 'PRIVATE-LIVE-NAME', 'archived' => true, 'label_d' => '<script>Days</script>', 'label_h' => '" onmouseover="sentinel', 'label_s' => '', 'alt' => '" autofocus sentinel', 'expiry_image_id' => 77 ) ) );
$before = $options;
$scripts = $styles = array();
$html = Email_Countdown_Timer_Block::render( array( 'timerId' => 'live-test', 'displayMode' => 'live' ), $plugin );
$expected = Email_Countdown_Timer_Config::deadline( $options['easy_countdown_timers']['live-test'] );
ok( str_contains( $html, 'data-ecd-deadline="' . $expected . '"' ), 'live uses the existing absolute deadline parser across DST' );
ok( str_contains( $html, 'datetime="2035-03-25T03:30:00+02:00"' ), 'accessible deadline confirms the same normalized DST instant' );
ok( ! str_contains( $html, 'PRIVATE-LIVE-NAME' ) && ! str_contains( $html, 'archived' ), 'live output excludes private metadata' );
ok( ! str_contains( $html, '<script>' ) && str_contains( $html, '&quot; onmouseover=&quot;sentinel' ), 'live labels and accessible name are sanitized and escaped' );
ok( str_contains( $html, 'title=""></span>' ) && str_contains( $html, 'aria-hidden="true"' ), 'empty custom labels are preserved and digits do not announce every second' );
ok( str_contains( $html, 'Without JavaScript, these digits are a snapshot.' ) && str_contains( $html, 'data-ecd-resume=' ), 'no-JS warning and accessible pause/resume labels' );
ok( isset( $scripts['email-countdown-timer-live'], $styles['email-countdown-timer-live'] ) && ! isset( $scripts['ecd-refresh'] ), 'live loads only its own local assets' );
ok( ! str_contains( $html, '<img' ) && str_contains( $html, 'mode=end' ), 'end image is deferred to one static request at expiry' );
ok( $options === $before, 'live render never writes or migrates campaign settings' );
$options['easy_countdown_timers']['live-test']['hide_days'] = 1;
$html = Email_Countdown_Timer_Block::render( array( 'timerId' => 'live-test', 'displayMode' => 'live' ), $plugin );
ok( ! str_contains( $html, 'data-ecd-unit="d"' ) && str_contains( $html, 'data-ecd-unit="h"' ), 'hide-days live layout omits days exactly as the image' );
foreach ( array( null, '', array(), 'LIVE', 'unknown', 'image' ) as $mode ) {
    $scripts = $styles = array();
    $html = Email_Countdown_Timer_Block::render( array( 'timerId' => 'live-test', 'displayMode' => $mode ), $plugin );
    ok( str_contains( $html, 'data-ecd-src=' ) && ! isset( $scripts['email-countdown-timer-live'], $styles['email-countdown-timer-live'] ), 'only exact live mode opts in' );
}
foreach ( array( array( 'deadline' => 'invalid' ), array( 'bg' => 'red;background:url(sentinel)' ), array( 'label_d' => array() ), array( 'tz' => 'Invalid/Zone' ) ) as $invalid ) {
    $options['easy_countdown_timers']['broken-live'] = array_replace( $base, $invalid );
    $html = Email_Countdown_Timer_Block::render( array( 'timerId' => 'broken-live', 'displayMode' => 'live' ), $plugin );
    ok( ! str_contains( $html, 'ecd-live' ), 'malformed legacy configuration cannot break or inject public live output' );
}

$options['easy_countdown_timers']['live-test']['expiry_image_id'] = 0;
$html = Email_Countdown_Timer_Block::render( array( 'timerId' => 'live-test', 'displayMode' => 'live' ), $plugin );
ok( str_contains( $html, 'data-ecd-end-image=""' ), 'no configured end image means no image URL or request' );
