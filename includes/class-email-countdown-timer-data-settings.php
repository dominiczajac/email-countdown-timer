<?php
/** Optional uninstall policy. No timer data is changed by saving this setting. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Data_Settings {
    public const OPTION = 'email_countdown_timer_delete_data_on_uninstall';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'add_menu' ) );
        add_action( 'admin_post_email_countdown_timer_save_data_settings', array( self::class, 'save' ) );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'ecd-timers',
            __( 'Data Settings', 'email-countdown-timer' ),
            __( 'Data Settings', 'email-countdown-timer' ),
            'manage_options',
            'email-countdown-timer-data',
            array( self::class, 'render' )
        );
    }

    public static function enabled(): bool {
        return in_array( get_option( self::OPTION, '0' ), array( '1', 1 ), true );
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to change these settings.', 'email-countdown-timer' ), '', array( 'response' => 403 ) );
            return;
        }
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            wp_die( esc_html__( 'Use the settings form to make this change.', 'email-countdown-timer' ), '', array( 'response' => 405 ) );
            return;
        }
        check_admin_referer( 'email_countdown_timer_data_settings' );
        // Check only this field. Missing means unchecked; malformed values never enable deletion.
        $raw = $_POST['delete_data_on_uninstall'] ?? '0';
        if ( ! is_string( $raw ) || ! in_array( $raw, array( '0', '1' ), true ) ) {
            wp_die( esc_html__( 'Invalid data retention setting.', 'email-countdown-timer' ), '', array( 'response' => 400 ) );
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
        <div class="wrap">
            <h1><?php echo esc_html__( 'Email Countdown Timer: Data Settings', 'email-countdown-timer' ); ?></h1>
            <?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__( 'Data settings saved. No timer data has been deleted.', 'email-countdown-timer' ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="email_countdown_timer_save_data_settings">
                <?php wp_nonce_field( 'email_countdown_timer_data_settings' ); ?>
                <fieldset>
                    <legend class="screen-reader-text"><?php echo esc_html__( 'Uninstall policy', 'email-countdown-timer' ); ?></legend>
                    <label for="email-countdown-timer-delete-data">
                        <input id="email-countdown-timer-delete-data" type="checkbox" name="delete_data_on_uninstall" value="1" aria-describedby="email-countdown-timer-delete-help" <?php checked( self::enabled() ); ?>>
                        <?php echo esc_html__( 'Delete all plugin data when uninstalling', 'email-countdown-timer' ); ?>
                    </label>
                    <p class="description" id="email-countdown-timer-delete-help"><?php echo esc_html__( 'Off by default. When enabled, deleting the plugin through WordPress permanently removes this site\'s timers, plugin options, and database image cache. Deactivation and saving this setting never delete timer data. Back up your configuration first.', 'email-countdown-timer' ); ?></p>
                    <p><?php echo esc_html__( 'Existing emails and pages will no longer receive countdown images after the plugin is removed. Manually deleting files does not run this cleanup.', 'email-countdown-timer' ); ?></p>
                    <?php if ( is_multisite() ) : ?>
                        <p><?php echo esc_html__( 'Multisite: this choice applies only to this site. Network uninstall removes data only on sites that have enabled this option.', 'email-countdown-timer' ); ?></p>
                    <?php endif; ?>
                </fieldset>
                <?php submit_button( __( 'Save Data Settings', 'email-countdown-timer' ) ); ?>
            </form>
        </div>
        <?php
    }
}
