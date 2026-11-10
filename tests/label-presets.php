<?php
/** Presets are explicit output data; locale changes must not rewrite campaigns. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! function_exists( 'ok' ) ) { exit( 1 ); }
$label_options_before = $options;
$label_translations_before = $GLOBALS['test_translations'] ?? array();
$admin = $nonce = true; $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET = $_POST = array();
$GLOBALS['test_translations'] = array( 'English' => 'Translated admin name', 'Days' => 'DO NOT STORE THIS TRANSLATION', 'Seconds' => 'OTHER TRANSLATED DATA' );
$output_label_presets = Email_Countdown_Timer_Admin::label_presets();
ok( array_keys( $output_label_presets ) === array( 'en', 'pl', 'de', 'es', 'fr' ), 'five explicit output languages are offered' );
ok( $output_label_presets['en']['label'] === 'Translated admin name' && $output_label_presets['en']['labels']['label_d'] === 'Days', 'admin language names translate without translating stored output labels' );
$manual_labels = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2031-12-31T12:00:00', 'label_d' => 'Campaign days', 'label_h' => '', 'label_m' => 'My minutes', 'label_s' => 'Keep seconds' ) );
$options['easy_countdown_timers'] = array( 'label-existing' => $manual_labels );
$_GET = array( 'edit' => 'label-existing' ); $reset_admin();
ok( Email_Countdown_Timer_Admin::state()['data']['label_d'] === 'Campaign days' && Email_Countdown_Timer_Admin::state()['data']['label_h'] === '' && $options['easy_countdown_timers']['label-existing'] === $manual_labels, 'opening a translated admin preserves handcrafted and empty labels without writes' );
$manual_post = array_merge( array_map( 'strval', $manual_labels ), array( 'ecd_action' => 'email_countdown_timer_save', 'timer_id' => 'label-existing', 'original_id' => 'label-existing', 'label_preset' => 'es' ) );
ok( str_starts_with( $submit_admin( $manual_post ), 'redirect:' ) && $options['easy_countdown_timers']['label-existing'] === $manual_labels, 'normal edits ignore preset metadata and save only explicitly edited label fields' );
$quick_labels = array( 'ecd_action' => 'email_countdown_timer_save', 'creation_mode' => 'quick', 'appearance_preset' => 'classic', 'deadline' => '2031-12-31T12:00:00' );
foreach ( array( 'unknown', '', array( 'en' ), null ) as $bad_language ) {
    $before_bad_language = $options;
    $submit_admin( $quick_labels + array( 'timer_id' => 'labels-invalid', 'label_preset' => $bad_language ) );
    ok( isset( Email_Countdown_Timer_Admin::state()['errors']['form'] ) && $options === $before_bad_language, 'unknown, empty and malformed language presets cannot write a campaign' );
}
ok( str_starts_with( $submit_admin( $quick_labels + array( 'timer_id' => 'labels-old-form' ) ), 'redirect:' ) && $options['easy_countdown_timers']['labels-old-form']['label_d'] === 'Days', 'older Quick Create forms retain English output independently of admin locale' );
foreach ( $output_label_presets as $language => $preset ) {
    $id = 'labels-new-' . $language;
    $before_language = $options;
    $result = $submit_admin( $quick_labels + array( 'timer_id' => $id, 'label_preset' => $language ) );
    if ( '' === $result ) {
        ok( $language === 'es' && isset( Email_Countdown_Timer_Admin::state()['errors']['label_d'] ) && $options === $before_language, 'active bitmap fallback rejects accented preset without a partial write' );
        ok( Email_Countdown_Timer_Admin::state()['data']['label_d'] === 'Días', 'failed accented preset preserves the requested label for recovery' );
    } else {
        ok( str_starts_with( $result, 'redirect:' ) && array_intersect_key( $options['easy_countdown_timers'][ $id ], $preset['labels'] ) === $preset['labels'], 'explicit Quick Create saves exactly the requested output labels: ' . $language );
        ok( ! isset( $options['easy_countdown_timers'][ $id ]['label_preset'] ) && $options['easy_countdown_timers']['label-existing'] === $manual_labels, 'preset selection adds no stored language field and changes no existing campaign' );
    }
    if ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) && null !== Email_Countdown_Timer_Config::fontPath( Email_Countdown_Timer_Fonts::BUNDLED_CHOICE ) ) {
        $image_config = Email_Countdown_Timer_Config::normalize( array_replace( $manual_labels, $preset['labels'], array( 'font' => Email_Countdown_Timer_Fonts::BUNDLED_CHOICE ) ) );
        ok( Email_Countdown_Timer_Admin::preflight( $image_config ) === array(), 'included Lato measures output preset ' . $language );
        $deadline = Email_Countdown_Timer_Config::deadline( $image_config );
        $label_image = imagecreatefromstring( ( new Email_Countdown_Timer_Renderer() )->render_static( $image_config, $deadline, $deadline - 90061, 'png' ) );
        ok( $label_image instanceof GdImage && imagesx( $label_image ) > 1, 'real PNG decodes for preset ' . $language );
        unset( $label_image );
    }
}
if ( function_exists( 'imagecreatetruecolor' ) ) {
    $accent_bitmap = array_replace( $manual_labels, $output_label_presets['es']['labels'], array( 'font' => '' ) );
    ok( isset( Email_Countdown_Timer_Admin::preflight( $accent_bitmap )['label_d'] ), 'bitmap validation never silently transliterates accented labels' );
}
$GLOBALS['test_translations'] = $label_translations_before; $options = $label_options_before;
$reset_admin(); $_GET = $_POST = array();
