<?php
/** Administrator-requested local diagnostics; no persistence or background probes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Email_Countdown_Timer_Status {
    private static string $screen = '';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
    }

    public static function add_menu(): void {
        self::$screen = (string) add_submenu_page( 'ecd-timers', __( 'Status & Troubleshooting', 'easy-countdown' ), __( 'Status & Troubleshooting', 'easy-countdown' ), 'manage_options', 'email-countdown-timer-status', array( self::class, 'render' ) );
    }

    public static function enqueue( string $hook ): void {
        if ( '' !== self::$screen && $hook === self::$screen ) {
            wp_enqueue_style( 'email-countdown-timer-admin', plugins_url( 'assets/admin.css', EMAIL_COUNTDOWN_TIMER_FILE ), array(), EMAIL_COUNTDOWN_TIMER_VERSION );
            wp_enqueue_script( 'email-countdown-timer-admin', plugins_url( 'assets/admin.js', EMAIL_COUNTDOWN_TIMER_FILE ), array( 'wp-a11y' ), EMAIL_COUNTDOWN_TIMER_VERSION, true );
            wp_enqueue_script( 'email-countdown-timer-status', plugins_url( 'assets/status.js', EMAIL_COUNTDOWN_TIMER_FILE ), array(), EMAIL_COUNTDOWN_TIMER_VERSION, true );
        }
    }

    private static function row( string $name, string $status, string $code, string $message, string $action ): array {
        return compact( 'name', 'status', 'code', 'message', 'action' );
    }

    private static function version( $value ): string {
        return is_string( $value ) && preg_match( '/^\d+\.\d+(?:\.\d+)?(?:[-+a-zA-Z0-9.]+)?$/D', $value ) ? $value : 'unknown';
    }

    /** Fixed fields only: never add raw configuration, paths, IDs, URLs or errors. */
    private static function collect( ?array $raw, bool $invalid ): array {
        $gd = function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' );
        $freetype = function_exists( 'imagettfbbox' ) && function_exists( 'imagettftext' );
        $imagick = class_exists( 'Imagick' );
        $rows = array(
            self::row( 'GD', $gd ? 'pass' : 'error', $gd ? 'available' : 'missing', $gd ? __( 'GD image functions are available.', 'easy-countdown' ) : __( 'Countdown image generation is unavailable. Live text blocks can still work.', 'easy-countdown' ), $gd ? __( 'Test a public image below; function availability alone does not verify codecs or hosting capacity.', 'easy-countdown' ) : __( 'Ask your host to enable GD with PNG/GIF support, then test a public image below.', 'easy-countdown' ) ),
            self::row( 'FreeType', $freetype ? 'pass' : 'warning', $freetype ? 'available' : 'bitmap_fallback', $freetype ? __( 'FreeType text functions are available.', 'easy-countdown' ) : __( 'Images use bitmap text without FreeType; accented labels may not render.', 'easy-countdown' ), __( 'Use available local fonts with FreeType, or printable ASCII bitmap labels. Check the actual image for glyph coverage.', 'easy-countdown' ) ),
            self::row( 'Imagick', $imagick ? 'pass' : 'warning', $imagick ? 'available' : 'static_fallback', $imagick ? __( 'Imagick is available for GIF animation.', 'easy-countdown' ) : __( 'Imagick is unavailable; image requests use a static frame.', 'easy-countdown' ), $imagick ? __( 'If GIFs stay static, check advisory locks, GIF codec policy and hosting memory. Test the actual image.', 'easy-countdown' ) : __( 'For animated email GIFs, ask your host to enable Imagick with GIF support.', 'easy-countdown' ) ),
        );
        require_once __DIR__ . '/class-email-countdown-timer-render-lock.php';
        $probe = Email_Countdown_Timer_Render_Lock::attempt( 'admin-status-probe', 0 );
        if ( $probe['lock'] ) { $probe['lock']->release(); }
        $lock_messages = array(
            'acquired' => __( 'A zero-wait database advisory lock was acquired; its cleanup was requested immediately.', 'easy-countdown' ),
            'busy' => __( 'The diagnostic lock is busy.', 'easy-countdown' ),
            'unsupported' => __( 'This database interface does not support the session-lock mechanism. Uncached images use static output.', 'easy-countdown' ),
            'error' => __( 'The advisory-lock check failed. Uncached images use static output.', 'easy-countdown' ),
        );
        $state = isset( $lock_messages[ $probe['state'] ] ) ? $probe['state'] : 'error';
        $rows[] = self::row( __( 'Database locking', 'easy-countdown' ), 'acquired' === $state ? 'pass' : 'warning', $state, $lock_messages[ $state ], 'acquired' === $state ? __( 'No lock configuration change is indicated. This probe does not certify database proxies or multiple primaries.', 'easy-countdown' ) : __( 'Retry a busy check. Otherwise ask your host about advisory locks and connection affinity.', 'easy-countdown' ) );
        $font = self::row( __( 'Campaign font', 'easy-countdown' ), 'info', 'not_selected', __( 'No campaign was selected.', 'easy-countdown' ), __( 'Enter a saved campaign ID and run local checks to verify its font resolution.', 'easy-countdown' ) );
        $valid_campaign = false;
        if ( $invalid ) {
            $font = self::row( __( 'Campaign font', 'easy-countdown' ), 'warning', 'invalid_campaign', __( 'The campaign ID is invalid or no longer saved.', 'easy-countdown' ), __( 'Choose an existing campaign and try again.', 'easy-countdown' ) );
        } elseif ( null !== $raw ) {
            try {
                $config = Email_Countdown_Timer_Config::normalize( $raw );
                $path = Email_Countdown_Timer_Config::fontPath( $config['font'] );
                $valid_campaign = true;
                $builtin = '' === $config['font'];
                $usable = $builtin || ( $freetype && null !== $path );
                $font = self::row( __( 'Campaign font', 'easy-countdown' ), $usable ? 'pass' : 'warning', $builtin ? 'bitmap' : ( $usable ? 'local_font_available' : 'bitmap_fallback' ), $builtin ? __( 'The campaign selects built-in bitmap text.', 'easy-countdown' ) : ( $usable ? __( 'The selected local font resolves to a readable file with FreeType support.', 'easy-countdown' ) : __( 'The selected font is missing or FreeType is unavailable. Images fall back to bitmap text.', 'easy-countdown' ) ), $usable ? __( 'Inspect the actual image: file availability does not certify glyph coverage or layout.', 'easy-countdown' ) : __( 'Restore the selected local font or choose an available font in the campaign editor, then inspect the image.', 'easy-countdown' ) );
            } catch ( Throwable $error ) {
                $font = self::row( __( 'Campaign font', 'easy-countdown' ), 'warning', 'invalid_configuration', __( 'The saved campaign could not be validated.', 'easy-countdown' ), __( 'Open the campaign editor and correct its settings before testing the image.', 'easy-countdown' ) );
            }
        }
        $rows[] = $font;
        return array(
            'report' => array( 'format' => 1, 'plugin' => self::version( EMAIL_COUNTDOWN_TIMER_VERSION ), 'wordpress' => self::version( $GLOBALS['wp_version'] ?? null ), 'php' => self::version( PHP_VERSION ), 'checks' => $rows, 'image_test' => array( 'status' => 'not_run', 'code' => 'not_run', 'http_status' => null ) ),
            'valid_campaign' => $valid_campaign,
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $timers = get_option( 'easy_countdown_timers', array() );
        $timers = is_array( $timers ) ? $timers : array();
        $id = '';
        $result = null;
        if ( 'POST' === Email_Countdown_Timer_Config::text( $_SERVER, 'REQUEST_METHOD' ) ) {
            check_admin_referer( 'email_countdown_timer_status' );
            $id = wp_unslash( Email_Countdown_Timer_Config::text( $_POST, 'campaign_id' ) );
            $valid = '' !== $id && strlen( $id ) <= 200 && Email_Countdown_Timer_Config::id( $id ) === $id && isset( $timers[ $id ] ) && is_array( $timers[ $id ] );
            $result = self::collect( $valid ? $timers[ $id ] : null, '' !== $id && ! $valid );
        }
        $levels = array( 'pass' => __( 'Passed', 'easy-countdown' ), 'warning' => __( 'Warning', 'easy-countdown' ), 'error' => __( 'Needs attention', 'easy-countdown' ), 'info' => __( 'Information', 'easy-countdown' ) );
        ?>
        <div class="wrap email-countdown-admin" data-ect-admin>
            <h1><?php echo esc_html__( 'Easy Countdown: Status & Troubleshooting', 'easy-countdown' ); ?></h1>
            <p><?php echo esc_html__( 'Checks run only when requested. They do not change campaigns or settings, send a report, or run background image requests.', 'easy-countdown' ); ?></p>
            <form class="ect-card" method="post">
                <?php wp_nonce_field( 'email_countdown_timer_status' ); ?>
                <p><label for="ect-status-campaign"><?php echo esc_html__( 'Campaign ID (optional)', 'easy-countdown' ); ?></label></p>
                <input class="regular-text" id="ect-status-campaign" name="campaign_id" value="<?php echo esc_attr( $id ); ?>" maxlength="200" list="ect-status-campaigns" aria-describedby="ect-status-campaign-help">
                <datalist id="ect-status-campaigns"><?php
                    $count = 0;
                    foreach ( $timers as $key => $raw ) {
                        if ( $count >= 250 ) { break; }
                        $key = (string) $key;
                        if ( ! is_array( $raw ) || '' === $key || Email_Countdown_Timer_Config::id( $key ) !== $key ) { continue; }
                        ++$count;
                        echo '<option value="' . esc_attr( $key ) . '"></option>';
                    }
                ?></datalist>
                <p id="ect-status-campaign-help"><?php echo esc_html__( 'Enter a saved ID to check its font and enable the separate image test. The first 250 IDs are suggested; other saved IDs may be typed.', 'easy-countdown' ); ?></p>
                <?php submit_button( __( 'Run local checks', 'easy-countdown' ), 'primary', 'submit', false ); ?>
            </form>
            <?php if ( null !== $result ) : ?>
                <section class="ect-card" aria-labelledby="ect-status-checks-title">
                    <h2 id="ect-status-checks-title"><?php echo esc_html__( 'Local checks', 'easy-countdown' ); ?></h2>
                    <?php foreach ( $result['report']['checks'] as $row ) : ?>
                        <h3><?php echo esc_html( $row['name'] . ': ' . $levels[ $row['status'] ] ); ?></h3>
                        <p><?php echo esc_html( $row['message'] ); ?></p><p><?php echo esc_html( $row['action'] ); ?></p>
                    <?php endforeach; ?>
                </section>
                <?php if ( $result['valid_campaign'] ) : ?>
                    <?php $url = add_query_arg( array( 'ecd_action' => 'render', 'ecd' => $id, 'mode' => 'static' ), home_url( '/' ) ); ?>
                    <section class="ect-card" aria-labelledby="ect-status-image-title" data-ect-image-test>
                        <h2 id="ect-status-image-title"><?php echo esc_html__( 'Public image test', 'easy-countdown' ); ?></h2>
                        <p><?php echo esc_html__( 'The button makes one anonymous request from this browser, without WordPress cookies. It does not test a remote network, email provider or recipient client. A different image hostname must allow browser CORS access for this test.', 'easy-countdown' ); ?></p>
                        <button type="button" class="button" hidden data-ect-image-url="<?php echo esc_url( $url ); ?>" data-loading="<?php echo esc_attr__( 'Testing public image…', 'easy-countdown' ); ?>" data-success="<?php echo esc_attr__( 'The anonymous image request loaded successfully. Test your real sender and recipients separately.', 'easy-countdown' ); ?>" data-failure="<?php echo esc_attr__( 'The anonymous image test failed.', 'easy-countdown' ); ?>" data-timeout="<?php echo esc_attr__( 'The 15-second limit was reached. Check host responsiveness and retry.', 'easy-countdown' ); ?>" data-network="<?php echo esc_attr__( 'The browser could not reach or read the response. Check the network, firewall and cross-host CORS access; this alone does not indicate a GD failure.', 'easy-countdown' ); ?>" data-http="<?php echo esc_attr__( 'The public server rejected the request. Check the HTTP status in the report, public URL and firewall rules.', 'easy-countdown' ); ?>" data-mime="<?php echo esc_attr__( 'The response is not a supported image. Check redirects, caching and firewall challenge pages.', 'easy-countdown' ); ?>" data-decode="<?php echo esc_attr__( 'The browser could not decode the image. Check GD, image conversion and proxy caching.', 'easy-countdown' ); ?>" data-pixel="<?php echo esc_attr__( 'The endpoint returned an error pixel. Check the campaign configuration, GD and host capacity.', 'easy-countdown' ); ?>" data-target="<?php echo esc_attr__( 'The image target is invalid. Reload this screen and check the site home URL.', 'easy-countdown' ); ?>><?php echo esc_html__( 'Test public image', 'easy-countdown' ); ?></button>
                        <p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open image manually (new tab)', 'easy-countdown' ); ?></a></p>
                        <p data-ect-image-result role="status"></p>
                        <img hidden alt="<?php echo esc_attr__( 'Result of the requested public image test.', 'easy-countdown' ); ?>" style="max-width:100%;height:auto;" referrerpolicy="no-referrer">
                    </section>
                <?php endif; ?>
                <section class="ect-card" aria-labelledby="ect-status-report-title">
                    <h2 id="ect-status-report-title"><?php echo esc_html__( 'Redacted troubleshooting report', 'easy-countdown' ); ?></h2>
                    <p><?php echo esc_html__( 'Only versions and diagnostic results are included. Site URLs, campaign IDs/names/settings, font filenames/paths, media IDs, usernames, credentials, nonces and raw errors are excluded. Review the text before sharing; copying never sends it.', 'easy-countdown' ); ?></p>
                    <label class="screen-reader-text" for="ect-status-report"><?php echo esc_html__( 'Troubleshooting report', 'easy-countdown' ); ?></label>
                    <textarea class="widefat code" id="ect-status-report" rows="14" readonly><?php echo esc_textarea( (string) wp_json_encode( $result['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
                    <button class="button" type="button" hidden data-ect-copy="ect-status-report" data-success="<?php echo esc_attr__( 'Report copied.', 'easy-countdown' ); ?>" data-fallback="<?php echo esc_attr__( 'Report selected. Copy it manually.', 'easy-countdown' ); ?>"><?php echo esc_html__( 'Copy report', 'easy-countdown' ); ?></button>
                    <span id="ect-status-report-result" role="status"></span>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }
}
