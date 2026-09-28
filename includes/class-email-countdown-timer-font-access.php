<?php
/** Explicit, local font access rules. HTTP enforcement remains the host's responsibility. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Font_Access {
    /** No font names, request values or executable code are written into these files. */
    public static function templates(): array {
        return array(
            '.htaccess' => "# Email Countdown Timer: server-side font directory only.\nRequire all denied\n",
            'index.html' => "<!-- Email Countdown Timer: no directory listing. Direct font access needs server rules. -->\n",
        );
    }

    public static function register(): void {
        add_action( 'admin_post_email_countdown_timer_font_access', array( self::class, 'handle' ) );
    }

    /**
     * Install rules only in this site's owned font roots, never the uploads root.
     * Existing files and symlinks are not replaced, even if they appear equivalent.
     * Guard files intentionally survive uninstall: manual fonts may remain there.
     */
    public static function install(): array {
        $target = Email_Countdown_Timer_Fonts::persistent_directory( true );
        if ( null === $target ) {
            throw new RuntimeException( 'The persistent font directory is unavailable.' );
        }
        // Legacy files live in the shared plugin directory on multisite. A site
        // administrator may protect their own uploads, not change shared server rules.
        $legacy = ! is_multisite() || current_user_can( 'manage_network_options' )
            ? Email_Countdown_Timer_Fonts::legacy_directory() : null;
        $directories = array_unique( array_filter( array( $target, $legacy ) ) );
        $result = array( 'created' => 0, 'existing' => 0, 'failed' => 0 );
        foreach ( $directories as $directory ) {
            foreach ( self::templates() as $name => $content ) {
                $path = $directory . DIRECTORY_SEPARATOR . $name;
                if ( file_exists( $path ) || is_link( $path ) ) {
                    ++$result['existing'];
                    continue;
                }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive local create: fixed names/content, contained font root, no overwrite, no network stream.
                $stream = fopen( $path, 'xb' );
                if ( false === $stream ) {
                    ++$result['failed'];
                    continue;
                }
                try {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Fixed short server-rule text to the exclusively created local file, never user input.
                    $written = fwrite( $stream, $content );
                    $complete = strlen( $content ) === $written && fflush( $stream );
                    if ( $complete ) {
                        ++$result['created'];
                    } else {
                        // Keep a failed/partial rule for inspection, never delete a replacement.
                        ++$result['failed'];
                    }
                } finally {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close only our local rule-file handle.
                    fclose( $stream );
                }
            }
        }
        return $result;
    }

    public static function handle(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage font access.', 'email-countdown-timer' ), '', array( 'response' => 403 ) );
            return;
        }
        $method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'POST' !== $method ) {
            wp_die( esc_html__( 'Use the font access form.', 'email-countdown-timer' ), '', array( 'response' => 405 ) );
            return;
        }
        check_admin_referer( 'email_countdown_timer_font_access' );
        try {
            $result = self::install();
        } catch ( Throwable $error ) {
            wp_die( esc_html__( 'Font access rules could not be created. Configure the font directories through your host. No existing rules were overwritten.', 'email-countdown-timer' ), '', array( 'response' => 500 ) );
            return;
        }
        // A local write is not proof that the web server enforces it. Never claim otherwise.
        wp_safe_redirect( add_query_arg( array( 'page' => 'email-countdown-timer-data', 'font_rules' => $result['failed'] ? 'incomplete' : 'written' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
