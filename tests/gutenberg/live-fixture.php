<?php
/** Synthetic live-block data in the owned disposable editor installation only. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectblock_' ) { exit( 1 ); }
$image = imagecreatetruecolor( 120, 80 );
imagefill( $image, 0, 0, imagecolorallocate( $image, 20, 90, 120 ) );
ob_start(); imagepng( $image ); $png = ob_get_clean();
$upload = wp_upload_bits( 'synthetic-live-end.png', null, $png );
if ( $upload['error'] ) { WP_CLI::error( 'Disposable end image could not be created.' ); }
$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_title' => 'Synthetic live end' ), $upload['file'], 0, true );
if ( is_wp_error( $attachment ) ) { WP_CLI::error( 'Disposable attachment could not be created.' ); }
$config = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2035-12-31T23:59:59', 'tz' => 'UTC', 'fixed_width' => 400, 'hide_days' => 1, 'alt' => 'Synthetic live campaign', 'label_d' => 'Very long custom day label', 'label_s' => '', 'bg' => '#FFFFFF', 'dc' => '#000000', 'lc' => '#555555' ) );
$timers = get_option( 'easy_countdown_timers', array() );
$timers['gutenberg-live'] = $config + array( 'campaign_name' => 'PRIVATE-LIVE-NAME' );
$timers['gutenberg-live-end'] = array_replace( $config, array( 'expiry_image_id' => $attachment ) );
$timers['gutenberg-ended'] = array_replace( $config, array( 'deadline' => '2000-01-01T00:00:00', 'expiry_image_id' => $attachment ) );
update_option( 'easy_countdown_timers', $timers, false );
$post = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Synthetic live website block', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => '' ), true );
if ( is_wp_error( $post ) ) { WP_CLI::error( 'Disposable live post could not be created.' ); }
echo wp_json_encode( array( 'post' => $post ) );
