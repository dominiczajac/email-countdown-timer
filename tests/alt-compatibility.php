<?php
/** Metadata and output contracts; use the existing WordPress doubles. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! function_exists( 'ok' ) ) { exit( 1 ); }
$admin = $nonce = true;
$_SERVER['REQUEST_METHOD'] = 'POST';
$reset_admin();
$alt_config = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2030-12-31T23:59:59', 'alt' => 'Sale "summer" & deadline' ) );
ok( $alt_config['alt'] === 'Sale "summer" & deadline', 'alt preserves plain quotes and ampersands' );
ok( Email_Countdown_Timer_Config::normalize( array_replace( $alt_config, array( 'alt' => '<b>Sale</b>' ) ) )['alt'] === 'Sale', 'alt strips HTML on save' );
foreach ( array( array( 'attack' ), str_repeat( 'x', 1001 ) ) as $bad_alt ) {
    ok( rejects( fn() => Email_Countdown_Timer_Config::normalize( array_replace( $alt_config, array( 'alt' => $bad_alt ) ) ) ), 'malformed or oversized alt rejected' );
}
ok( Email_Countdown_Timer_Config::alt( array(), 'Default' ) === 'Default', 'missing alt uses fallback' );
ok( Email_Countdown_Timer_Config::alt( array( 'alt' => '' ), 'Default' ) === '', 'explicit empty alt remains empty (PR #5 semantics)' );
ok( Email_Countdown_Timer_Config::alt( array( 'alt' => array( 'x' ) ), 'Default' ) === 'Default', 'corrupt stored alt cannot break shortcode' );
$options['easy_countdown_timers'] = array( 'alt-test' => $alt_config );
$post_alt = array_merge( array_map( 'strval', $alt_config ), array( 'ecd_action' => 'email_countdown_timer_save', 'original_id' => 'alt-test', 'timer_id' => 'alt-test' ) );
ok( str_starts_with( $submit_admin( $post_alt ), 'redirect:' ), 'alt form saves through protected controller' );
ok( $options['easy_countdown_timers']['alt-test']['alt'] === $alt_config['alt'], 'alt persisted as timer configuration' );
$short_alt = $plugin->renderShortcode( array( 'id' => 'alt-test' ) );
ok( str_contains( $short_alt, 'alt="Sale &quot;summer&quot; &amp; deadline"' ), 'shortcode escapes saved alt exactly once' );
foreach ( array( 'class="email-countdown-timer-image"', 'loading="eager"', 'data-no-lazy="1"', 'referrerpolicy="no-referrer"' ) as $marker ) {
    ok( str_contains( $short_alt, $marker ), 'shortcode supplies scoped image marker: ' . $marker );
}
ok( ! str_contains( $short_alt, '&amp;alt=' ) && ! str_contains( $short_alt, 'data-lazy-src=' ), 'alt stays in HTML; plugin does not introduce competing lazy source' );
$_GET = array( 'edit' => 'alt-test' );
ob_start(); $plugin->renderAdminPage(); $alt_view = ob_get_clean();
ok( str_contains( $alt_view, 'for="ect-alt"' ) && str_contains( $alt_view, 'name="alt"' ), 'alt input has visible associated label' );
ok( str_contains( $alt_view, 'id="ect-help-alt"' ) && str_contains( $alt_view, 'id="ect-error-alt"' ), 'alt help and error targets exist' );
preg_match( '/<textarea[^>]*id="ect-manual-html"[^>]*>(.*?)<\/textarea>/s', $alt_view, $html_match );
$copied_html = html_entity_decode( $html_match[1] ?? '', ENT_QUOTES, 'UTF-8' );
ok( str_contains( $copied_html, 'alt="Sale &quot;summer&quot; &amp; deadline"' ), 'copied email HTML escapes alt at image sink' );
ok( str_contains( $copied_html, 'Ends:' ) && str_contains( $copied_html, 'Europe/Warsaw' ), 'email retains visible deadline independent of alt' );
// Two markup contexts must remain safe, including deliberately corrupted stored metadata.
$options['easy_countdown_timers']['alt-test']['alt'] = '" onerror="alert(1)';
$attack = $plugin->renderShortcode( array( 'id' => 'alt-test' ) );
ok( ! str_contains( $attack, ' onerror="' ) && str_contains( $attack, '&quot; onerror=&quot;' ), 'stored attribute-breakout is escaped' );
$options['easy_countdown_timers']['alt-test'] = $alt_config;
$before_alt = $options;
$submit_admin( array_replace( $post_alt, array( 'alt' => array( 'x' ) ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['alt'] ) && $options === $before_alt, 'array alt cannot cause a write' );
$submit_admin( array_replace( $post_alt, array( 'alt' => str_repeat( 'x', 1001 ) ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['alt'] ) && $options === $before_alt, 'oversized alt has field error and no write' );
$submit_admin( array_replace( $post_alt, array( 'tz' => 'invalid/time-zone', 'alt' => 'Retain alternative text' ) ) );
ok( Email_Countdown_Timer_Admin::state()['data']['alt'] === 'Retain alternative text', 'alt survives unrelated validation failure' );
$old_form = $post_alt; unset( $old_form['alt'] );
$submit_admin( $old_form );
ok( $options['easy_countdown_timers']['alt-test']['alt'] === $alt_config['alt'], 'old modern form does not erase saved alt' );
$old_form['ecd_action'] = 'save_timer';
$_POST = $old_form;
try { $plugin->handleFormSave(); } catch ( ECD_Test_Stop $e ) {}
ok( $options['easy_countdown_timers']['alt-test']['alt'] === $alt_config['alt'], 'legacy save also preserves omitted alt' );
$submit_admin( array_replace( $post_alt, array( 'alt' => '' ) ) );
ok( str_contains( $plugin->renderShortcode( array( 'id' => 'alt-test' ) ), 'alt=""' ), 'clearing field preserves intentional empty alternative text' );
// Alt never changes image pixels or partitions the public transient cache.
if ( function_exists( 'imagecreatetruecolor' ) ) {
    $options['easy_countdown_timers']['alt-test'] = $alt_config;
    $_GET = array( 'ecd' => 'alt-test', 'mode' => 'email' );
    $image_method = new ReflectionMethod( $plugin, 'generateImage' );
    $fixed_now = 1790598000;
    $writes = 0; $transients = array();
    ob_start(); $image_method->invoke( $plugin, false, $fixed_now ); $bytes_before = ob_get_clean();
    $options['easy_countdown_timers']['alt-test']['alt'] = 'A different description';
    ob_start(); $image_method->invoke( $plugin, false, $fixed_now ); $bytes_after = ob_get_clean();
    ok( $bytes_before === $bytes_after && $writes === 1, 'alt change leaves cached image signature and pixels identical' );
    $options['easy_countdown_timers']['alt-test']['alt'] = array( 'corrupt HTML metadata' );
    ob_start(); $image_method->invoke( $plugin, false, $fixed_now ); $metadata_only = ob_get_clean();
    ok( $metadata_only === $bytes_before && $writes === 1, 'corrupt HTML-only metadata does not affect binary validation/cache' );
    $_SERVER['REMOTE_ADDR'] = '192.0.2.99'; $_SERVER['HTTP_USER_AGENT'] = 'SYNTHETIC-PRIVATE-MARKER';
    $_COOKIE = array( 'visitor' => 'SYNTHETIC-PRIVATE-MARKER' ); $_GET['recipient'] = 'SYNTHETIC-PRIVATE-MARKER';
    ob_start(); $image_method->invoke( $plugin, false, $fixed_now ); $anonymous = ob_get_clean();
    ok( $anonymous === $bytes_before && $writes === 1, 'visitor headers and cookies do not personalize image/cache' );
    ok( ! str_contains( serialize( $transients ), 'SYNTHETIC-PRIVATE-MARKER' ), 'visitor metadata absent from plugin cache' );
    unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ); $_COOKIE = array();
}
$reset_admin(); $_GET = $_POST = array();
