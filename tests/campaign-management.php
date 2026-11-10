<?php
/** Archive and duplication must preserve already published campaigns. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! function_exists( 'ok' ) ) { exit( 1 ); }
$management_options = $options; $management_transients = $transients; $management_writes = $writes;
$admin = $nonce = true; $_SERVER['REQUEST_METHOD'] = 'POST';
$source_config = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2031-08-20T12:30:00', 'tz' => 'GMT', 'font' => '', 'bg' => '#123456', 'label_h' => '', 'label_s' => 'Custom seconds' ) );
$source_record = $source_config + array( 'campaign_name' => 'Private original' );
$options['easy_countdown_timers'] = array( 'original' => $source_record, 'broken' => 'invalid', 'reserved' => null );
$snapshot = $options;
foreach ( array( 'email_countdown_timer_archive', 'email_countdown_timer_restore' ) as $management_action ) {
    $post = array( 'ecd_action' => $management_action, 'timer_id' => 'original' );
    $admin = false;
    ok( str_starts_with( $submit_admin( $post ), 'die:' ) && $options === $snapshot, 'management action requires capability' );
    $admin = true; $nonce = false;
    ok( 'nonce' === $submit_admin( $post ) && $options === $snapshot, 'management action requires nonce' );
    $nonce = true; $_SERVER['REQUEST_METHOD'] = 'GET';
    ok( str_starts_with( $submit_admin( $post ), 'die:' ) && $options === $snapshot, 'management action requires POST' );
    $_SERVER['REQUEST_METHOD'] = 'POST';
    foreach ( array( 'gone', 'broken' ) as $bad_id ) {
        ok( str_starts_with( $submit_admin( array_replace( $post, array( 'timer_id' => $bad_id ) ) ), 'die:' ) && $options === $snapshot, 'missing or malformed campaign cannot be archived/restored' );
    }
}
$public_before = $plugin->renderShortcode( array( 'id' => 'original' ) );
preg_match( '/<img\b[^>]*\bsrc="([^"]+)"/', $public_before, $public_image_before );
$transients = array(); $writes = 0;
if ( function_exists( 'imagecreatetruecolor' ) ) {
    $_GET = array( 'ecd' => 'original', 'mode' => 'email' );
    $management_image = new ReflectionMethod( $plugin, 'generateImage' );
    ob_start(); $management_image->invoke( $plugin, false, 1790580000 ); $pixels_before = ob_get_clean();
}
$cached_before_archive = $transients;
ok( str_starts_with( $submit_admin( array( 'ecd_action' => 'email_countdown_timer_archive', 'timer_id' => 'original' ) ), 'redirect:' ), 'archive follows protected controller and redirects after saving' );
ok( $options['easy_countdown_timers']['original'] === $source_record + array( 'archived' => true ), 'archiving changes only private metadata' );
ok( $transients === $cached_before_archive, 'archiving leaves image cache entries intact' );
preg_match( '/<img\b[^>]*\bsrc="([^"]+)"/', $plugin->renderShortcode( array( 'id' => 'original' ) ), $public_image_archived );
ok( ! empty( $public_image_before[1] ) && $public_image_before[1] === ( $public_image_archived[1] ?? '' ), 'archived campaign keeps the existing public image URL' );
if ( function_exists( 'imagecreatetruecolor' ) ) {
    ob_start(); $management_image->invoke( $plugin, false, 1790580000 ); $pixels_archived = ob_get_clean();
    ok( $pixels_before === $pixels_archived && $writes === 1, 'archived campaign serves identical pixels using the same image-cache signature' );
}
$_GET = array(); $_POST = array(); $reset_admin();
ok( Email_Countdown_Timer_Admin::state()['total'] === 0 && Email_Countdown_Timer_Admin::state()['archived_count'] === 1, 'archive hides only from default administration list' );
ob_start(); Email_Countdown_Timer_Admin::render_dashboard_widget(); $archive_dashboard = ob_get_clean();
ok( ! str_contains( $archive_dashboard, 'aria-label="Edit Private original"' ), 'archived campaign is absent from dashboard shortcuts' );
$_GET = array( 'filter' => 'archived', 's' => 'Private' );
ok( array_keys( Email_Countdown_Timer_Admin::state()['timers'] ) === array( 'original' ), 'archived view supports private name search' );
$_GET = array( 'duplicate' => 'original' );
$copy_state = Email_Countdown_Timer_Admin::state();
ok( $copy_state['view'] === 'editor' && $copy_state['original'] === '' && $copy_state['saved'] === null && $copy_state['id'] !== 'original', 'duplicate opens an unsaved new ID without editing original' );
ok( $copy_state['data']['tz'] === 'GMT' && $copy_state['data']['bg'] === '#123456' && $copy_state['data']['label_h'] === '' && $copy_state['data']['label_s'] === 'Custom seconds' && $copy_state['data']['font'] === '', 'duplication preserves schedule, exact alias, colors, font and custom/empty labels' );
$archived_source = $options['easy_countdown_timers']['original'];
$copy_post = array_merge( array_map( 'strval', $copy_state['data'] ), array( 'ecd_action' => 'email_countdown_timer_save', 'original_id' => '', 'timer_id' => 'fresh-copy' ) );
ok( str_starts_with( $submit_admin( $copy_post ), 'redirect:' ) && $options['easy_countdown_timers']['original'] === $archived_source, 'copy saves through normal validation without changing source' );
ok( ! Email_Countdown_Timer_Admin::archived( $options['easy_countdown_timers']['fresh-copy'] ), 'copy of an archived campaign starts unarchived' );
$copy_snapshot = $options;
$submit_admin( array_replace( $copy_post, array( 'timer_id' => 'original' ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['timer_id'] ) && $options === $copy_snapshot, 'copy cannot overwrite original ID' );
$submit_admin( array_replace( $copy_post, array( 'timer_id' => 'reserved' ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['timer_id'] ) && $options === $copy_snapshot, 'even a null malformed record reserves its existing ID' );
$old_form = array_merge( array_map( 'strval', $source_config ), array( 'ecd_action' => 'email_countdown_timer_save', 'original_id' => 'original', 'timer_id' => 'original' ) );
ok( str_starts_with( $submit_admin( $old_form ), 'redirect:' ) && $options['easy_countdown_timers']['original'] === $archived_source, 'older edit forms preserve archive status and private name' );
ok( str_starts_with( $submit_admin( array( 'ecd_action' => 'email_countdown_timer_restore', 'timer_id' => 'original' ) ), 'redirect:' ) && $options['easy_countdown_timers']['original'] === $source_record, 'restore removes only archive metadata' );
$reset_admin(); $_GET = array( 'duplicate' => 'gone' ); $_POST = array();
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['form'] ), 'missing duplicate source produces an error without creating a record' );
$options['easy_countdown_timers']['legacy-copy'] = array( 'deadline' => '2031-12-31T12:00:00' );
$GLOBALS['ect_site_tz'] = 'Pacific/Auckland'; $_GET = array( 'duplicate' => 'legacy-copy' );
$legacy_copy = Email_Countdown_Timer_Admin::state();
ok( $legacy_copy['data']['tz'] === 'Europe/Warsaw' && $legacy_copy['data']['font'] === '', 'duplicate keeps legacy missing-zone/font meaning instead of new-campaign defaults' );
unset( $GLOBALS['ect_site_tz'] );
$options = $management_options; $transients = $management_transients; $writes = $management_writes;
$reset_admin(); $_GET = $_POST = array();
