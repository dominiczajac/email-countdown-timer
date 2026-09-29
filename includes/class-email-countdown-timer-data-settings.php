<?php
/** Optional uninstall policy. No timer data is changed by saving this setting. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-email-countdown-timer-fonts.php';

final class Email_Countdown_Timer_Data_Settings {
    public const OPTION = 'email_countdown_timer_delete_data_on_uninstall';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'add_menu' ) );
        add_action( 'admin_post_email_countdown_timer_save_data_settings', array( self::class, 'save' ) );
    }

    public static function add_menu(): void {
        require_once __DIR__ . '/class-email-countdown-timer-admin.php';
        $hook = add_submenu_page(
            'ecd-timers',
            __( 'Data Settings', 'easy-countdown' ),
            __( 'Data Settings', 'easy-countdown' ),
            'manage_options',
            'email-countdown-timer-data',
            array( self::class, 'render' )
        );
        Email_Countdown_Timer_Admin::add_screen( $hook );
    }

    public static function enabled(): bool {
        return in_array( get_option( self::OPTION, '0' ), array( '1', 1 ), true );
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change these settings.', 'easy-countdown' ), '', array( 'response' => 403 ) );
            return;
        }
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            wp_die( esc_html__( 'Use the settings form to make this change.', 'easy-countdown' ), '', array( 'response' => 405 ) );
            return;
        }
        check_admin_referer( 'email_countdown_timer_data_settings' );
        // Check only this field. Missing means unchecked; malformed values never enable deletion.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict string allowlist immediately below validates exact '0'/'1'; no escaped value can become consent.
        $raw = $_POST['delete_data_on_uninstall'] ?? '0';
        if ( ! is_string( $raw ) || ! in_array( $raw, array( '0', '1' ), true ) ) {
            wp_die( esc_html__( 'Invalid data retention setting.', 'easy-countdown' ), '', array( 'response' => 400 ) );
            return;
        }
        update_option( self::OPTION, $raw, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'email-countdown-timer-data', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap email-countdown-admin ect-data">
            <h1><?php echo esc_html__( 'Easy Countdown: Data Settings', 'easy-countdown' ); ?></h1>
            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice controlled by an exact constant; contains no query-supplied content.
            if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__( 'Data settings saved. No timer data has been deleted.', 'easy-countdown' ); ?></p></div>
            <?php endif; ?>
            <form class="ect-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="email_countdown_timer_save_data_settings">
                <?php wp_nonce_field( 'email_countdown_timer_data_settings' ); ?>
                <fieldset>
                    <legend class="screen-reader-text"><?php echo esc_html__( 'Uninstall policy', 'easy-countdown' ); ?></legend>
                    <label for="email-countdown-timer-delete-data">
                        <input id="email-countdown-timer-delete-data" type="checkbox" name="delete_data_on_uninstall" value="1" aria-describedby="email-countdown-timer-delete-help" <?php checked( self::enabled() ); ?>>
                        <?php echo esc_html__( 'Delete all plugin data when uninstalling', 'easy-countdown' ); ?>
                    </label>
                    <p class="description" id="email-countdown-timer-delete-help"><?php echo esc_html__( 'Off by default. When enabled, deleting the plugin through WordPress permanently removes this site\'s timers, plugin options, and database image cache. Deactivation and saving this setting never delete timer data. Back up your configuration first.', 'easy-countdown' ); ?></p>
                    <p><?php echo esc_html__( 'Existing emails and pages will no longer receive countdown images after the plugin is removed. Manually deleting files does not run this cleanup.', 'easy-countdown' ); ?></p>
                    <?php if ( is_multisite() ) : ?>
                        <p><?php echo esc_html__( 'Multisite: this choice applies only to this site. Network uninstall removes data only on sites that have enabled this option.', 'easy-countdown' ); ?></p>
                    <?php endif; ?>
                </fieldset>
                <?php submit_button( __( 'Save Data Settings', 'easy-countdown' ) ); ?>
            </form>
            <section class="ect-card" aria-labelledby="email-countdown-render-title">
                <h2 id="email-countdown-render-title"><?php echo esc_html__( 'Rendering Diagnostics', 'easy-countdown' ); ?></h2>
                <?php
                require_once __DIR__ . '/class-email-countdown-timer-render-lock.php';
                $email_countdown_timer_probe = Email_Countdown_Timer_Render_Lock::attempt( 'admin-capability-probe', 0 );
                if ( $email_countdown_timer_probe['lock'] ) {
                    $email_countdown_timer_probe['lock']->release();
                }
                ?>
                <p><?php
                    switch ( $email_countdown_timer_probe['state'] ) {
                        case 'acquired':
                            echo esc_html__( 'Database locking is available.', 'easy-countdown' );
                            break;
                        case 'busy':
                            echo esc_html__( 'The diagnostic lock is currently busy. Refresh this screen to check again.', 'easy-countdown' );
                            break;
                        case 'unsupported':
                            echo esc_html__( 'This database interface does not support the configured session-lock mechanism. Uncached images use static output.', 'easy-countdown' );
                            break;
                        default:
                            echo esc_html__( 'The database lock check failed. Ask your host to check advisory-lock support and connectivity. Uncached images use static output.', 'easy-countdown' );
                    }
                ?></p>
                <p><?php echo esc_html__( 'On a cache miss, image requests wait at most one second for a generation lock. If it is busy or unavailable, a current single-frame image is generated without replacing the animation cache. This avoids stale countdowns, but does not replace hosting rate limits. This probe is not a full database-proxy compatibility test.', 'easy-countdown' ); ?></p>
            </section>
            <section class="ect-card" aria-labelledby="email-countdown-font-access-title">
                <h2 id="email-countdown-font-access-title"><?php echo esc_html__( 'Font File Access', 'easy-countdown' ); ?></h2>
                <p><?php echo esc_html__( 'Local fonts may be publicly downloadable unless your host blocks HTTP access. This tool creates an Apache 2.4 deny rule and a blank index in this site\'s persistent font directory. Existing legacy font directories are included only for single-site or network administrators. Existing rules and symlinks are never replaced.', 'easy-countdown' ); ?></p>
                <p><?php echo esc_html__( 'Apache must allow the Require directive in .htaccess. nginx ignores .htaccess and needs a host-managed location rule. Writing these files does not verify protection: ask your host to check that direct GET and HEAD font requests are denied while timer images still work. No remote probe is performed.', 'easy-countdown' ); ?></p>
                <p><?php echo esc_html__( 'Rules are retained on uninstall because manually managed fonts may remain. Blocking downloads does not grant a font license; verify permission for server-side rendering.', 'easy-countdown' ); ?></p>
                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status label; no data or file mutation. Action is POST/capability/nonce protected.
                $email_countdown_timer_rules_status = sanitize_text_field( wp_unslash( Email_Countdown_Timer_Config::text( $_GET, 'font_rules' ) ) );
                if ( in_array( $email_countdown_timer_rules_status, array( 'written', 'incomplete' ), true ) ) : ?>
                    <p role="status"><?php echo esc_html__( 'Rule installation was attempted without overwriting existing files. Check directory permissions and existing rules with your host; HTTP protection is not verified.', 'easy-countdown' ); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="email_countdown_timer_font_access">
                    <?php wp_nonce_field( 'email_countdown_timer_font_access' ); ?>
                    <?php submit_button( __( 'Install Font Access Rules (Apache)', 'easy-countdown' ) ); ?>
                </form>
            </section>
            <section class="ect-card" aria-labelledby="email-countdown-fonts-title">
                <h2 id="email-countdown-fonts-title"><?php echo esc_html__( 'Local Fonts', 'easy-countdown' ); ?></h2>
                <p><?php echo esc_html__( 'Use a persistent uploads directory for trusted static TTF/OTF files. No Google connection or browser upload is provided.', 'easy-countdown' ); ?></p>
                <p><?php echo esc_html__( 'Persistent location: the uploads base directory, then email-countdown-timer/fonts/. Multisite adds site-ID/ inside fonts/. The directory is created only when copying fonts, installing access rules, or manually through your hosting tools.', 'easy-countdown' ); ?></p>
                <?php $email_countdown_timer_font_directory = Email_Countdown_Timer_Fonts::persistent_directory(); ?>
                <?php if ( $email_countdown_timer_font_directory ) : ?><p><code><?php echo esc_html( $email_countdown_timer_font_directory ); ?></code></p><?php endif; ?>
                <p><?php echo esc_html__( 'Copying preserves original files and timer names, never overwrites a different file, and processes at most 50 new files per request (5 MiB each). A same-name legacy file takes precedence until the old plugin directory is replaced. Copy font license notices manually.', 'easy-countdown' ); ?></p>
                <p><?php echo esc_html__( 'Before the first upgrade from an older version, back up or copy plugin-local fonts manually: this version cannot recover files already deleted by an earlier updater. On multisite, prepare each site before a network update.', 'easy-countdown' ); ?></p>
                <p><?php echo esc_html__( 'With uninstall cleanup enabled, only unchanged files created by this copy tool are removed. Manually uploaded or subsequently replaced files remain; shared directories are never recursively deleted.', 'easy-countdown' ); ?></p>
                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only counts; numeric bounds and output escaping below. The copying action itself requires POST, capability and nonce.
                $email_countdown_timer_copy_query = array_intersect_key( $_GET, array_flip( array( 'fonts_copied', 'fonts_skipped' ) ) );
                if ( isset( $email_countdown_timer_copy_query['fonts_copied'] ) ) : ?>
                    <p role="status"><?php echo esc_html( sprintf( /* translators: 1: copied files, 2: skipped files. */ __( 'Copied: %1$d. Skipped or conflicting: %2$d. Original files are unchanged.', 'easy-countdown' ), max( 0, min( 50, (int) Email_Countdown_Timer_Config::text( $email_countdown_timer_copy_query, 'fonts_copied' ) ) ), max( 0, (int) Email_Countdown_Timer_Config::text( $email_countdown_timer_copy_query, 'fonts_skipped' ) ) ) ); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="email_countdown_timer_copy_fonts">
                    <?php wp_nonce_field( 'email_countdown_timer_copy_fonts' ); ?>
                    <?php submit_button( __( 'Copy Legacy Fonts to Persistent Storage', 'easy-countdown' ) ); ?>
                </form>
            </section>
        </div>
        <?php
    }
}
