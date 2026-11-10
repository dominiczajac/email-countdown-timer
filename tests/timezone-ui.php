<?php
/** Named-zone selection must preserve exact saved interpretation and show UTC. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! function_exists( 'ok' ) ) { exit( 1 ); }
$zone_options_before = $options;
$admin = $nonce = true;
$reset_admin(); $_GET = array( 'view' => 'new' ); $_POST = array();
$options['easy_countdown_timers'] = array();
foreach ( array( 'America/New_York', '+05:30', 'UTC' ) as $site_zone ) {
    $GLOBALS['ect_site_tz'] = $site_zone;
    ok( Email_Countdown_Timer_Admin::state()['data']['tz'] === $site_zone, 'new campaign inherits exact site zone ' . $site_zone );
    ob_start(); Email_Countdown_Timer_Admin::timezone_input( 'ect-tz', $site_zone ); $zone_markup = ob_get_clean();
    ok( str_contains( $zone_markup, '<select id="ect-tz" name="tz"' ) && str_contains( $zone_markup, 'value="America/New_York"' ), 'native selector offers IANA zones without scripts' );
    ok( str_contains( $zone_markup, 'value="' . $site_zone . '" selected' ), 'exact site zone or fixed offset stays selected' );
}
$GLOBALS['ect_site_tz'] = 'Pacific/Auckland';
$options['easy_countdown_timers'] = array( 'legacy-zone' => array( 'deadline' => '2030-12-31T23:59:59' ) );
$_GET = array( 'edit' => 'legacy-zone' );
ok( Email_Countdown_Timer_Admin::state()['data']['tz'] === 'Europe/Warsaw', 'legacy missing zone never inherits a new site zone' );
foreach ( array( '+05:30', 'GMT', 'Etc/GMT+3' ) as $saved_zone ) {
    $options['easy_countdown_timers']['legacy-zone']['tz'] = $saved_zone;
    $state = Email_Countdown_Timer_Admin::state();
    ok( $state['data']['tz'] === $saved_zone, 'editing retains saved zone or alias ' . $saved_zone );
    ob_start(); Email_Countdown_Timer_Admin::timezone_input( 'ect-tz', $saved_zone ); $zone_markup = ob_get_clean();
    ok( str_contains( $zone_markup, 'value="' . $saved_zone . '" selected' ), 'saved alias is selected even outside canonical IANA choices' );
}
$winter = array( 'deadline' => '2030-01-15T12:00:00', 'tz' => 'America/New_York' );
$summer = array( 'deadline' => '2030-07-15T12:00:00', 'tz' => 'America/New_York' );
ok( Email_Countdown_Timer_Admin::deadline_text( $winter ) === '2030-01-15 12:00:00 -05:00 (America/New_York) / 2030-01-15 17:00:00 UTC', 'winter confirmation uses the deadline offset and exact UTC instant' );
ok( Email_Countdown_Timer_Admin::deadline_text( $summer ) === '2030-07-15 12:00:00 -04:00 (America/New_York) / 2030-07-15 16:00:00 UTC', 'summer confirmation follows named-zone DST rather than current offset' );
ok( str_contains( Email_Countdown_Timer_Admin::deadline_text( array_replace( $summer, array( 'tz' => '+05:30' ) ) ), '2030-07-15 06:30:00 UTC' ), 'fixed-offset confirmation does not apply DST' );
$bad_zone = '"><script>bad</script>';
ob_start(); Email_Countdown_Timer_Admin::timezone_input( 'ect-tz', $bad_zone, 'Invalid zone' ); $zone_markup = ob_get_clean();
ok( ! str_contains( $zone_markup, '<script>' ) && str_contains( $zone_markup, '&lt;script&gt;' ) && str_contains( $zone_markup, 'aria-invalid="true"' ), 'invalid selected input and error are escaped and recoverable' );
ok( str_contains( $zone_markup, 'id="ect-error-tz"' ) && str_contains( $zone_markup, 'ect-help-tz ect-error-tz' ), 'selector retains existing client-validation error and description IDs' );
$options = $zone_options_before;
unset( $GLOBALS['ect_site_tz'] );
$reset_admin(); $_GET = $_POST = array();
