<?php
/** Regression assertions for the approved UI; loaded by tests/run.php. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! function_exists( 'ok' ) ) { exit( 1 ); }
require_once __DIR__ . '/../includes/class-email-countdown-timer-admin.php';
function wp_add_dashboard_widget( ...$args ) { $GLOBALS['dashboard_widget'] = $args; }
$admin = true; $nonce = true; $_SERVER['REQUEST_METHOD'] = 'POST';
$reset_admin = static function () { $property = new ReflectionProperty( Email_Countdown_Timer_Admin::class, 'pending' ); $property->setValue( null, null ); };
$submit_admin = static function ( array $post ) use ( $plugin, $reset_admin ) {
    $reset_admin(); $_POST = $post;
    try { $plugin->handleFormSave(); return ''; } catch ( ECD_Test_Stop $e ) { return $e->getMessage(); }
};
$ui_config = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2030-12-31T23:59:59', 'label_d' => 'Dni', 'label_h' => '', 'fixed_width' => 600 ) );
$options['easy_countdown_timers'] = array( 'existing' => $ui_config );
$valid_post = array_merge( array_map( 'strval', $ui_config ), array( 'ecd_action' => 'email_countdown_timer_save', 'timer_id' => 'existing', 'original_id' => 'existing' ) );
$before = $options;
$admin = false;
ok( str_starts_with( $submit_admin( $valid_post ), 'die:' ) && $options === $before, 'UI unauthorized save rejected' );
$admin = true; $nonce = false;
ok( 'nonce' === $submit_admin( $valid_post ) && $options === $before, 'UI save requires nonce' );
$nonce = true; $_SERVER['REQUEST_METHOD'] = 'GET';
ok( str_starts_with( $submit_admin( $valid_post ), 'die:' ) && $options === $before, 'UI save requires POST' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$invalid = array_replace( $valid_post, array( 'size_digit' => '201', 'label_s' => 'KEEP THIS VALUE', 'bg' => 'invalid' ) );
ok( '' === $submit_admin( $invalid ) && $options === $before, 'invalid UI request returns to form without writes' );
$state = Email_Countdown_Timer_Admin::state();
ok( isset( $state['errors']['size_digit'], $state['errors']['bg'] ), 'field-specific errors collected' );
ok( 'KEEP THIS VALUE' === $state['data']['label_s'] && '' === $state['data']['label_h'], 'invalid POST retains custom and empty labels' );
ob_start(); $plugin->renderAdminPage(); $markup = ob_get_clean();
ok( str_contains( $markup, 'value="KEEP THIS VALUE"' ) && str_contains( $markup, 'aria-invalid="true"' ), 'invalid values and errors rendered' );
$submit_admin( array_replace( $valid_post, array( 'bg' => array( 'attack' ) ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['bg'] ) && $options === $before, 'array-typed input rejected, not converted to defaults' );
$submit_admin( array_replace( $valid_post, array( 'original_id' => '' ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['timer_id'] ) && $options === $before, 'duplicate create cannot overwrite existing timer' );
$submit_admin( array_replace( $valid_post, array( 'timer_id' => 'renamed' ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['timer_id'] ) && $options === $before, 'read-only ID enforced server-side' );
ok( str_starts_with( $submit_admin( $valid_post ), 'redirect:' ), 'valid modern form redirects only after saving' );
ok( $options['easy_countdown_timers']['existing'] === $ui_config, 'modern form preserves campaign configuration' );
$before_names = $options;
$test_uuid_sequence = array( 'deadbeef-0000-4000-8000-000000000001', 'cafebabe-0000-4000-8000-000000000002' );
$options['easy_countdown_timers']['timer-deadbeef0000'] = null;
ok( Email_Countdown_Timer_Admin::suggested_id() === 'timer-cafebabe0000', 'ID suggestion skips occupied keys, including malformed stored records' );
$options = $before_names;
$new_id = Email_Countdown_Timer_Admin::suggested_id();
ok( (bool) preg_match( '/^timer-[0-9a-f]{12}$/D', $new_id ) && $options === $before_names, 'opaque ID suggestion needs no saved reservation or campaign name' );
$private_name = 'Private "Łódź" & summer';
$named_post = array_replace( $valid_post, array( 'campaign_name' => '<b>' . $private_name . '</b>' ) );
$admin = false;
ok( str_starts_with( $submit_admin( $named_post ), 'die:' ) && $options === $before_names, 'private-name writes retain the capability gate' );
$admin = true;
foreach ( array( array( 'bad' ), str_repeat( 'n', 201 ) ) as $bad_name ) {
    $submit_admin( array_replace( $valid_post, array( 'campaign_name' => $bad_name ) ) );
    ok( isset( Email_Countdown_Timer_Admin::state()['errors']['campaign_name'] ) && $options === $before_names, 'invalid name cannot mutate a campaign' );
}
ok( str_starts_with( $submit_admin( $named_post ), 'redirect:' ), 'private name saves through the existing form' );
$named_config = $options['easy_countdown_timers']['existing'];
ok( $named_config['campaign_name'] === $private_name && Email_Countdown_Timer_Config::normalize( $named_config ) === $ui_config, 'name is plain admin metadata outside renderer normalization' );
$_GET = array( 's' => 'Łódź' );
ok( array_keys( Email_Countdown_Timer_Admin::state()['timers'] ) === array( 'existing' ), 'administration search finds a private name with its existing immutable ID' );
ob_start(); $plugin->renderAdminPage(); $named_list = ob_get_clean();
ok( str_contains( $named_list, 'Private &quot;Łódź&quot; &amp; summer' ) && str_contains( $named_list, 'edit=existing' ), 'list escapes names and links by the original ID' );
ok( ! str_contains( $plugin->renderShortcode( array( 'id' => 'existing' ) ), 'Private' ), 'private name is not used as public alt text or markup' );
$reset_admin(); $_GET = array( 'edit' => 'existing' );
ob_start(); $plugin->renderAdminPage(); $named_editor = ob_get_clean();
preg_match( '/<textarea[^>]*id="ect-manual-html"[^>]*>(.*?)<\/textarea>/s', $named_editor, $named_html );
ok( ! str_contains( $named_html[1] ?? '', 'Private' ), 'private name never enters email HTML' );
ok( str_starts_with( $submit_admin( $valid_post ), 'redirect:' ) && $options['easy_countdown_timers']['existing'] === $named_config, 'older forms without the new field preserve a saved private name' );
if ( function_exists( 'imagecreatetruecolor' ) ) {
    $old_transients = $transients; $old_writes = $writes; $old_get = $_GET;
    $transients = array(); $writes = 0;
    $_GET = array( 'ecd' => 'existing', 'mode' => 'email' );
    $image_method = new ReflectionMethod( $plugin, 'generateImage' );
    ob_start(); $image_method->invoke( $plugin, false, 1790580000 ); $named_pixels = ob_get_clean();
    $options['easy_countdown_timers']['existing']['campaign_name'] = 'Different private name';
    ob_start(); $image_method->invoke( $plugin, false, 1790580000 ); $renamed_pixels = ob_get_clean();
    ok( $named_pixels === $renamed_pixels && $writes === 1 && ! str_contains( serialize( $transients ), $private_name ), 'renaming private metadata does not change image pixels, cache signature or cached content' );
    $transients = $old_transients; $writes = $old_writes; $_GET = $old_get;
}
$before_empty_name = $options;
$submit_admin( array_replace( $valid_post, array( 'campaign_name' => '' ) ) );
ok( $options === $before_empty_name && isset( Email_Countdown_Timer_Admin::state()['errors']['campaign_name'] ), 'an older form cannot explicitly erase the now-required name' );
$options = $before_names; $reset_admin(); $_GET = array();
$quick = array( 'ecd_action' => 'email_countdown_timer_save', 'creation_mode' => 'quick', 'original_id' => '', 'timer_id' => 'quick-new', 'deadline' => '2031-08-20T12:30:00', 'tz' => 'America/New_York', 'appearance_preset' => 'midnight' );
$before_quick = $options;
$admin = false;
ok( str_starts_with( $submit_admin( $quick ), 'die:' ) && $options === $before_quick, 'quick creation requires administrator capability' );
$admin = true; $nonce = false;
ok( 'nonce' === $submit_admin( $quick ) && $options === $before_quick, 'quick creation requires the existing form nonce' );
$nonce = true; $_SERVER['REQUEST_METHOD'] = 'GET';
ok( str_starts_with( $submit_admin( $quick ), 'die:' ) && $options === $before_quick, 'quick creation requires POST' );
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( array( 'unknown', array( 'midnight' ) ) as $bad_preset ) {
    $submit_admin( array_replace( $quick, array( 'appearance_preset' => $bad_preset ) ) );
    ok( isset( Email_Countdown_Timer_Admin::state()['errors']['form'] ) && $options === $before_quick, 'unknown or array preset never creates a timer' );
}
$submit_admin( array_replace( $quick, array( 'timer_id' => 'existing' ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['timer_id'] ) && $options === $before_quick, 'quick creation cannot overwrite a duplicate ID' );
$submit_admin( array_replace( $quick, array( 'timer_id' => 'existing', 'original_id' => 'existing' ) ) );
ok( isset( Email_Countdown_Timer_Admin::state()['errors']['form'] ) && $options === $before_quick, 'quick creation cannot modify an existing campaign' );
$submit_admin( array_replace( $quick, array( 'tz' => 'not/a-zone' ) ) );
$quick_error = Email_Countdown_Timer_Admin::state();
ok( isset( $quick_error['errors']['tz'] ) && $options === $before_quick, 'quick creation validates the deadline time zone without a write' );
ok( $quick_error['id'] === 'quick-new' && $quick_error['data']['dc'] === '#F9FAFB' && $quick_error['data']['deadline'] === $quick['deadline'], 'failed quick create retains identity, preset colors and deadline in the full editor' );
$appearance_presets = Email_Countdown_Timer_Admin::presets();
foreach ( array( 'classic' => array( '#FFFFFF', '#111827', '#475569' ), 'midnight' => array( '#111827', '#F9FAFB', '#CBD5E1' ), 'ocean' => array( '#F8FAFC', '#1D4ED8', '#334155' ), 'warm' => array( '#FFF7ED', '#9A3412', '#713F12' ) ) as $preset => $colors ) {
    ok( array_values( array_intersect_key( $appearance_presets[ $preset ], array_flip( array( 'bg', 'dc', 'lc' ) ) ) ) === $colors, 'original preset colors remain stable: ' . $preset );
}
$relative_luminance = static function ( string $hex ): float {
    $rgb = array_map( static fn( $v ) => $v <= 0.04045 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4, array_map( static fn( $v ) => hexdec( $v ) / 255, str_split( substr( $hex, 1 ), 2 ) ) );
    return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
};
foreach ( $appearance_presets as $preset => $appearance ) {
    ok( isset( $appearance['font'] ) && Email_Countdown_Timer_Fonts::is_bundled( $appearance['font'] ), 'every appearance preset references a registered included face: ' . $preset );
    $colors = array( $appearance['bg'], $appearance['dc'], $appearance['lc'] );
    $background = $relative_luminance( $appearance['bg'] );
    foreach ( array( 'dc', 'lc' ) as $foreground ) {
        $text = $relative_luminance( $appearance[ $foreground ] );
        ok( ( max( $background, $text ) + 0.05 ) / ( min( $background, $text ) + 0.05 ) >= 4.5, 'preset text has at least 4.5:1 contrast: ' . $preset . '/' . $foreground );
    }
    $post = array_replace( $quick, array( 'timer_id' => 'quick-' . $preset, 'appearance_preset' => $preset ) );
    ok( str_starts_with( $submit_admin( $post ), 'redirect:' ), 'quick creation saves ' . $preset );
    $created = $options['easy_countdown_timers'][ 'quick-' . $preset ];
    ok( array( $created['bg'], $created['dc'], $created['lc'] ) === $colors, 'server applies the selected colors without JavaScript: ' . $preset );
    ok( $created['font'] === Email_Countdown_Timer_Admin::preset_font( $appearance ), 'server applies the registered preset font without JavaScript: ' . $preset );
    ok( $created['deadline'] === $quick['deadline'] && $created['tz'] === $quick['tz'] && $created['alt'] === 'Countdown', 'quick creation retains schedule and accessible default text' );
    ok( ! isset( $created['creation_mode'], $created['appearance_preset'] ) && $options['easy_countdown_timers']['existing'] === $ui_config, 'presets add no saved fields or existing-campaign changes' );
}
$options = $before_quick;
$delete_post = array( 'ecd_action' => 'email_countdown_timer_delete', 'timer_id' => 'existing' );
ok( str_starts_with( $submit_admin( $delete_post ), 'die:' ) && isset( $options['easy_countdown_timers']['existing'] ), 'delete requires explicit confirmation' );
$nonce = false;
ok( 'nonce' === $submit_admin( $delete_post + array( 'confirm_delete' => '1' ) ), 'confirmed delete still requires nonce' );
$nonce = true;
ok( str_starts_with( $submit_admin( $delete_post + array( 'confirm_delete' => '1' ) ), 'redirect:' ) && ! isset( $options['easy_countdown_timers']['existing'] ), 'confirmed deletion follows separate action' );
$reset_admin(); $_POST = array(); $_GET = array();
$options['easy_countdown_timers'] = array();
for ( $i = 0; $i < 61; ++$i ) $options['easy_countdown_timers'][ 'campaign-' . $i ] = $ui_config;
$state = Email_Countdown_Timer_Admin::state();
ok( 'list' === $state['view'] && 25 === count( $state['timers'] ) && 3 === $state['pages'], 'list is paginated at 25 without schema migration' );
ob_start(); $plugin->renderAdminPage(); $markup = ob_get_clean();
ok( ! str_contains( $markup, '<img' ), 'list generates no image previews' );
$_GET = array( 's' => 'campaign-60' );
ok( 1 === count( Email_Countdown_Timer_Admin::state()['timers'] ), 'server-side ID search works without JS' );
$_GET = array( 'paged' => '3' );
ok( 11 === count( Email_Countdown_Timer_Admin::state()['timers'] ), 'last page does not omit matching timers' );
$_GET = array( 'edit' => 'campaign-0' );
ob_start(); $plugin->renderAdminPage(); $markup = ob_get_clean();
ok( str_contains( $markup, 'ecd_action=frame' ) && str_contains($markup, 'sandbox="allow-scripts"') && !str_contains($markup, 'ect-save-state'), 'editor has an isolated active frame and no save-status badge' );
ok( str_contains( $markup, 'id="ect-website-title"' ) && str_contains( $markup, 'id="ect-email-title"' ) && str_contains( $markup, '[ecd_timer id=' ), 'website and email embedding have separate sections without JavaScript' );
ok( str_contains( $markup, 'data-ect-copy="ect-manual-shortcode"' ) && str_contains( $markup, 'data-ect-copy="ect-manual-html"' ) && str_contains( $markup, 'data-ect-copy="ect-embed-code"' ), 'each embed has its own copy action' );
ok( str_contains( $markup, '2030-12-31' ) && str_contains( $markup, 'Europe/Warsaw' ), 'absolute deadline and zone accompany image' );
$styles = $scripts = array();
Email_Countdown_Timer_Admin::add_screen( 'toplevel_page_ecd-timers' );
$styles = $scripts = array();
$admin=false; Email_Countdown_Timer_Admin::enqueue('index.php');
ok(empty($styles) && empty($scripts), 'dashboard script excluded for non-administrators');
$admin=true; Email_Countdown_Timer_Admin::enqueue('index.php');
ok(empty($styles) && isset($scripts['email-countdown-timer-admin']) && count($scripts)===1, 'dashboard enqueues only the timezone initialization script for timer administrators');
$styles = $scripts = array();
Email_Countdown_Timer_Admin::enqueue( 'dashboard' );
ok( empty( $styles ) && empty( $scripts ), 'admin assets not enqueued on unrelated screens' );
Email_Countdown_Timer_Admin::enqueue( 'toplevel_page_ecd-timers' );
ok( count( $styles ) === 1 && count( $scripts ) === 1, 'only one custom stylesheet and script' );
$styles = $scripts = array();
unset( $dashboard_widget );
$snapshot_dashboard = $options;
$admin = false;
$plugin->registerDashboardWidget();
Email_Countdown_Timer_Admin::register_dashboard_widget();
ob_start(); Email_Countdown_Timer_Admin::render_dashboard_widget(); $dashboard_denied = ob_get_clean();
ok( ! isset( $dashboard_widget ) && $dashboard_denied === '', 'dashboard widget registration and rendering both require timer management capability' );
$admin = true;
$plugin->registerDashboardWidget();
ok( $dashboard_widget[0] === 'email_countdown_timer_quick_access' && is_callable( $dashboard_widget[2] ), 'ordinary WordPress dashboard gets the native Easy Countdown widget' );
ob_start(); call_user_func( $dashboard_widget[2] ); $dashboard = ob_get_clean();
ok( str_contains( $dashboard, 'id="ect-dashboard-create"' ) && str_contains( $dashboard, 'name="creation_mode" value="quick"' ) && str_contains( $dashboard, 'value="midnight"' ), 'dashboard uses the shared no-JS quick form with presets' );
ok( str_contains( $dashboard, 'name="_wpnonce"' ) && str_contains( $dashboard, 'email_countdown_timer_save' ), 'dashboard posts to the same nonce-protected save controller' );
ok( substr_count( $dashboard, 'aria-label="Edit ' ) === 5 && str_contains( $dashboard, 'All timers' ), 'dashboard offers five edit shortcuts and access to every campaign' );
ok( ! str_contains( $dashboard, '<img' ) && empty( $styles ) && empty( $scripts ) && $options === $snapshot_dashboard, 'dashboard renders no image, adds no assets and never rewrites existing timers' );
$options['easy_countdown_timers'] = array( 'bad\"><script>' => $ui_config, 'not-a-timer' => 'invalid' );
ob_start(); Email_Countdown_Timer_Admin::render_dashboard_widget(); $dashboard_empty = ob_get_clean();
ok( str_contains( $dashboard_empty, 'No timers yet.' ) && ! str_contains( $dashboard_empty, '<script>' ) && ! str_contains( $dashboard_empty, 'aria-label="Edit ' ), 'dashboard empty state excludes malformed IDs and records' );
$options = $snapshot_dashboard;
$reset_admin(); $_GET = $_POST = array();
