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
        self::$screen = (string) add_submenu_page( Email_Countdown_Timer_Plugin::MENU_PAGE, __( 'Status & Troubleshooting', 'tickloom-countdown-timers' ), __( 'Status & Troubleshooting', 'tickloom-countdown-timers' ), 'manage_options', 'email-countdown-timer-status', array( self::class, 'render' ) );
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
            self::row( 'GD', $gd ? 'pass' : 'error', $gd ? 'available' : 'missing', $gd ? __( 'GD image functions are available.', 'tickloom-countdown-timers' ) : __( 'Countdown image generation is unavailable. Live text blocks can still work.', 'tickloom-countdown-timers' ), $gd ? __( 'Test a public image below; function availability alone does not verify codecs or hosting capacity.', 'tickloom-countdown-timers' ) : __( 'Ask your host to enable GD with PNG/GIF support, then test a public image below.', 'tickloom-countdown-timers' ) ),
            self::row( 'FreeType', $freetype ? 'pass' : 'warning', $freetype ? 'available' : 'bitmap_fallback', $freetype ? __( 'FreeType text functions are available.', 'tickloom-countdown-timers' ) : __( 'Images use bitmap text without FreeType; accented labels may not render.', 'tickloom-countdown-timers' ), __( 'Use available local fonts with FreeType, or printable ASCII bitmap labels. Check the actual image for glyph coverage.', 'tickloom-countdown-timers' ) ),
            self::row( 'Imagick', $imagick ? 'pass' : 'warning', $imagick ? 'available' : 'static_fallback', $imagick ? __( 'Imagick is available for GIF animation.', 'tickloom-countdown-timers' ) : __( 'Imagick is unavailable; image requests use a static frame.', 'tickloom-countdown-timers' ), $imagick ? __( 'If GIFs stay static, check advisory locks, GIF codec policy and hosting memory. Test the actual image.', 'tickloom-countdown-timers' ) : __( 'For animated email GIFs, ask your host to enable Imagick with GIF support.', 'tickloom-countdown-timers' ) ),
        );
        require_once __DIR__ . '/class-email-countdown-timer-render-lock.php';
        $probe = Email_Countdown_Timer_Render_Lock::attempt( 'admin-status-probe', 0 );
        if ( $probe['lock'] ) { $probe['lock']->release(); }
        $lock_messages = array(
            'acquired' => __( 'A zero-wait database advisory lock was acquired; its cleanup was requested immediately.', 'tickloom-countdown-timers' ),
            'busy' => __( 'The diagnostic lock is busy.', 'tickloom-countdown-timers' ),
            'unsupported' => __( 'This database interface does not support the session-lock mechanism. Uncached images use static output.', 'tickloom-countdown-timers' ),
            'error' => __( 'The advisory-lock check failed. Uncached images use static output.', 'tickloom-countdown-timers' ),
        );
        $state = isset( $lock_messages[ $probe['state'] ] ) ? $probe['state'] : 'error';
        $rows[] = self::row( __( 'Database locking', 'tickloom-countdown-timers' ), 'acquired' === $state ? 'pass' : 'warning', $state, $lock_messages[ $state ], 'acquired' === $state ? __( 'No lock configuration change is indicated. This probe does not certify database proxies or multiple primaries.', 'tickloom-countdown-timers' ) : __( 'Retry a busy check. Otherwise ask your host about advisory locks and connection affinity.', 'tickloom-countdown-timers' ) );
        $font = self::row( __( 'Campaign font', 'tickloom-countdown-timers' ), 'info', 'not_selected', __( 'No campaign was selected.', 'tickloom-countdown-timers' ), __( 'Enter a saved campaign ID and run local checks to verify its font resolution.', 'tickloom-countdown-timers' ) );
        $valid_campaign = false;
        if ( $invalid ) {
            $font = self::row( __( 'Campaign font', 'tickloom-countdown-timers' ), 'warning', 'invalid_campaign', __( 'The campaign ID is invalid or no longer saved.', 'tickloom-countdown-timers' ), __( 'Choose an existing campaign and try again.', 'tickloom-countdown-timers' ) );
        } elseif ( null !== $raw ) {
            try {
                $config = Email_Countdown_Timer_Config::normalize( $raw );
                // Use the renderer's actual choice/metrics without allocating or encoding an image.
                $path = $gd
                    ? ( new Email_Countdown_Timer_Renderer() )->measure( $config, Email_Countdown_Timer_Config::deadline( $config ), time() )['font']
                    : Email_Countdown_Timer_Config::fontPath( $config['font'] );
                $valid_campaign = true;
                $builtin = '' === $config['font'];
                $usable = $builtin || ( $freetype && null !== $path );
                $font = self::row( __( 'Campaign font', 'tickloom-countdown-timers' ), $usable ? 'pass' : 'warning', $builtin ? 'bitmap' : ( $usable ? 'local_font_available' : 'bitmap_fallback' ), $builtin ? __( 'The campaign selects built-in bitmap text.', 'tickloom-countdown-timers' ) : ( $usable ? __( 'The selected local font resolves to a readable file with FreeType support.', 'tickloom-countdown-timers' ) : __( 'The selected font is missing or FreeType is unavailable. Images fall back to bitmap text.', 'tickloom-countdown-timers' ) ), $usable ? __( 'Inspect the actual image: file availability does not certify glyph coverage or layout.', 'tickloom-countdown-timers' ) : __( 'Restore the selected local font or choose an available font in the campaign editor, then inspect the image.', 'tickloom-countdown-timers' ) );
            } catch ( Throwable $error ) {
                $font = self::row( __( 'Campaign font', 'tickloom-countdown-timers' ), 'warning', 'invalid_configuration', __( 'The saved campaign could not be validated.', 'tickloom-countdown-timers' ), __( 'Open the campaign editor, check its settings and restore or reselect its font before testing the image.', 'tickloom-countdown-timers' ) );
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
        if ( 'POST' === Email_Countdown_Timer_Config::request_method() ) {
            check_admin_referer( 'email_countdown_timer_status' );
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- request_id unslashes, sanitizes and validates the canonical ID before lookup or output.
            $id = Email_Countdown_Timer_Config::request_id( $_POST['campaign_id'] ?? null, true );
            $valid = '' !== $id && isset( $timers[ $id ] ) && is_array( $timers[ $id ] );
            $provided = isset( $_POST['campaign_id'] ) && '' !== $_POST['campaign_id'];
            $result = self::collect( $valid ? $timers[ $id ] : null, $provided && ! $valid );
        }
        $levels = array( 'pass' => __( 'Passed', 'tickloom-countdown-timers' ), 'warning' => __( 'Warning', 'tickloom-countdown-timers' ), 'error' => __( 'Needs attention', 'tickloom-countdown-timers' ), 'info' => __( 'Information', 'tickloom-countdown-timers' ) );
        ?>
        <div class="wrap email-countdown-admin" data-ect-admin>
            <h1><?php echo esc_html__( 'Tickloom Countdown Timers: Status & Troubleshooting', 'tickloom-countdown-timers' ); ?></h1>
            <p><?php echo esc_html__( 'Checks run only when requested. They do not change campaigns or settings, send a report, or run background image requests.', 'tickloom-countdown-timers' ); ?></p>
            <form class="ect-card" method="post">
                <?php wp_nonce_field( 'email_countdown_timer_status' ); ?>
                <p><label for="ect-status-campaign"><?php echo esc_html__( 'Campaign ID (optional)', 'tickloom-countdown-timers' ); ?></label></p>
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
                <p id="ect-status-campaign-help"><?php echo esc_html__( 'Enter a saved ID to check its font and enable the separate image test. The first 250 IDs are suggested; other saved IDs may be typed.', 'tickloom-countdown-timers' ); ?></p>
                <?php submit_button( __( 'Run local checks', 'tickloom-countdown-timers' ), 'primary', 'submit', false ); ?>
            </form>
            <?php if ( null !== $result ) : ?>
                <section class="ect-card" aria-labelledby="ect-status-checks-title">
                    <h2 id="ect-status-checks-title"><?php echo esc_html__( 'Local checks', 'tickloom-countdown-timers' ); ?></h2>
                    <?php foreach ( $result['report']['checks'] as $row ) : ?>
                        <h3><?php echo esc_html( $row['name'] . ': ' . $levels[ $row['status'] ] ); ?></h3>
                        <p><?php echo esc_html( $row['message'] ); ?></p><p><?php echo esc_html( $row['action'] ); ?></p>
                    <?php endforeach; ?>
                </section>
                <?php if ( $result['valid_campaign'] ) : ?>
                    <?php $url = add_query_arg( array( 'ecd_action' => 'render', 'ecd' => $id, 'mode' => 'static' ), home_url( '/' ) ); ?>
                    <section class="ect-card" aria-labelledby="ect-status-image-title" data-ect-image-test>
                        <h2 id="ect-status-image-title"><?php echo esc_html__( 'Public image test', 'tickloom-countdown-timers' ); ?></h2>
                        <p><?php echo esc_html__( 'The button makes one anonymous request from this browser, without WordPress cookies. It does not test a remote network, email provider or recipient client. A different image hostname must allow browser CORS access for this test.', 'tickloom-countdown-timers' ); ?></p>
                        <button type="button" class="button" hidden
                            data-ect-image-url="<?php echo esc_url( $url ); ?>"
                            data-loading="<?php echo esc_attr__( 'Testing public image…', 'tickloom-countdown-timers' ); ?>"
                            data-success="<?php echo esc_attr__( 'The anonymous image request loaded successfully. Test your real sender and recipients separately.', 'tickloom-countdown-timers' ); ?>"
                            data-failure="<?php echo esc_attr__( 'The anonymous image test failed.', 'tickloom-countdown-timers' ); ?>"
                            data-timeout="<?php echo esc_attr__( 'The 15-second limit was reached. Check host responsiveness and retry.', 'tickloom-countdown-timers' ); ?>"
                            data-network="<?php echo esc_attr__( 'The browser could not reach or read the response. Check the network, firewall and cross-host CORS access; this alone does not indicate a GD failure.', 'tickloom-countdown-timers' ); ?>"
                            data-http="<?php echo esc_attr__( 'The public server rejected the request. Check the HTTP status in the report, public URL and firewall rules.', 'tickloom-countdown-timers' ); ?>"
                            data-mime="<?php echo esc_attr__( 'The response is not a supported image. Check redirects, caching and firewall challenge pages.', 'tickloom-countdown-timers' ); ?>"
                            data-decode="<?php echo esc_attr__( 'The browser could not decode the image. Check GD, image conversion and proxy caching.', 'tickloom-countdown-timers' ); ?>"
                            data-pixel="<?php echo esc_attr__( 'The endpoint returned an error pixel. Check the campaign configuration, GD and host capacity.', 'tickloom-countdown-timers' ); ?>"
                            data-target="<?php echo esc_attr__( 'The image target is invalid. Reload this screen and check the site home URL.', 'tickloom-countdown-timers' ); ?>"><?php echo esc_html__( 'Test public image', 'tickloom-countdown-timers' ); ?></button>
                        <p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open image manually (new tab)', 'tickloom-countdown-timers' ); ?></a></p>
                        <p data-ect-image-result role="status"></p>
                        <img hidden alt="<?php echo esc_attr__( 'Result of the requested public image test.', 'tickloom-countdown-timers' ); ?>" style="max-width:100%;height:auto;" referrerpolicy="no-referrer">
                    </section>
                <?php endif; ?>
                <section class="ect-card" aria-labelledby="ect-status-report-title">
                    <h2 id="ect-status-report-title"><?php echo esc_html__( 'Redacted troubleshooting report', 'tickloom-countdown-timers' ); ?></h2>
                    <p><?php echo esc_html__( 'Only versions and diagnostic results are included. Site URLs, campaign IDs/names/settings, font filenames/paths, media IDs, usernames, credentials, nonces and raw errors are excluded. Review the text before sharing; copying never sends it.', 'tickloom-countdown-timers' ); ?></p>
                    <label class="screen-reader-text" for="ect-status-report"><?php echo esc_html__( 'Troubleshooting report', 'tickloom-countdown-timers' ); ?></label>
                    <textarea class="widefat code" id="ect-status-report" rows="14" readonly><?php echo esc_textarea( (string) wp_json_encode( $result['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
                    <button class="button" type="button" hidden data-ect-copy="ect-status-report" data-success="<?php echo esc_attr__( 'Report copied.', 'tickloom-countdown-timers' ); ?>" data-fallback="<?php echo esc_attr__( 'Report selected. Copy it manually.', 'tickloom-countdown-timers' ); ?>"><?php echo esc_html__( 'Copy report', 'tickloom-countdown-timers' ); ?></button>
                    <span id="ect-status-report-result" role="status"></span>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }
}
