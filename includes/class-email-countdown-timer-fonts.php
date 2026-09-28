<?php
/** Local font storage. No upload endpoint, remote downloads or visitor data. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Fonts {
    public const OPTION = 'email_countdown_timer_copied_fonts';
    private const MAX_BYTES = 5242880;

    public static function register(): void {
        add_action( 'admin_post_email_countdown_timer_copy_fonts', array( self::class, 'handle_copy' ) );
    }

    public static function valid_name( string $name ): bool {
        return '' !== $name && strlen( $name ) <= 255 && basename( $name ) === $name
            && ! preg_match( '/[\\\\\x00]/', $name ) && (bool) preg_match( '/\.(ttf|otf)$/iD', $name );
    }

    public static function legacy_directory(): ?string {
        $path = ( defined( 'EMAIL_COUNTDOWN_TIMER_DIR' ) ? EMAIL_COUNTDOWN_TIMER_DIR : dirname( __DIR__ ) . '/' ) . 'fonts';
        return ! is_link( $path ) && is_dir( $path ) ? ( realpath( $path ) ?: null ) : null;
    }

    /** No mkdir in read/render paths. Multisite gets a suffix even with shared uploads. */
    public static function persistent_directory( bool $create = false ): ?string {
        if ( ! function_exists( 'wp_get_upload_dir' ) ) {
            return null;
        }
        $uploads = wp_get_upload_dir();
        $base = $uploads['basedir'] ?? '';
        if ( ! empty( $uploads['error'] ) || ! is_string( $base ) || '' === $base || str_contains( $base, '://' ) || str_contains( $base, "\0" ) ) {
            return null;
        }
        if ( ! is_dir( $base ) && ( ! $create || ! wp_mkdir_p( $base ) ) ) {
            return null;
        }
        $path = realpath( $base );
        if ( false === $path ) {
            return null;
        }
        $parts = array( 'email-countdown-timer', 'fonts' );
        if ( function_exists( 'is_multisite' ) && is_multisite() ) {
            $parts[] = 'site-' . (int) get_current_blog_id();
        }
        foreach ( $parts as $part ) {
            $next = $path . DIRECTORY_SEPARATOR . $part;
            if ( is_link( $next ) || ( ! is_dir( $next ) && ( ! $create || ! wp_mkdir_p( $next ) ) ) ) {
                return null;
            }
            $resolved = realpath( $next );
            if ( false === $resolved || dirname( $resolved ) !== $path ) {
                return null;
            }
            $path = $resolved;
        }
        return $path;
    }

    public static function contained_file( ?string $directory, string $name ): ?string {
        if ( null === $directory || ! self::valid_name( $name ) ) {
            return null;
        }
        $candidate = $directory . DIRECTORY_SEPARATOR . $name;
        if ( is_link( $candidate ) ) {
            return null;
        }
        $real = realpath( $candidate );
        return false !== $real && dirname( $real ) === $directory && is_file( $real ) && is_readable( $real ) ? $real : null;
    }

    public static function path( string $name ): ?string {
        if ( ! self::valid_name( $name ) ) {
            return null;
        }
        // Keep legacy precedence until the same file is copied and the old code directory replaced.
        // A name collision never silently changes a running campaign's typeface.
        return self::contained_file( self::legacy_directory(), $name )
            ?? self::contained_file( self::persistent_directory(), $name );
    }

    public static function names(): array {
        $names = array();
        foreach ( array( self::legacy_directory(), self::persistent_directory() ) as $directory ) {
            if ( null === $directory || ! is_readable( $directory ) ) {
                continue;
            }
            foreach ( scandir( $directory ) ?: array() as $name ) {
                if ( null !== self::contained_file( $directory, $name ) ) {
                    $names[ $name ] = $name;
                }
            }
        }
        natcasesort( $names );
        return array_values( $names );
    }

    /** Copy, never move/overwrite. A session lock serializes the ownership manifest. */
    public static function copy_legacy(): array {
        $source = self::legacy_directory();
        $target = self::persistent_directory( true );
        if ( null === $source || null === $target ) {
            throw new RuntimeException( 'Local font directories are unavailable or not writable.' );
        }
        require_once __DIR__ . '/class-email-countdown-timer-render-lock.php';
        $lock = Email_Countdown_Timer_Render_Lock::acquire( 'font-migration' );
        if ( null === $lock ) {
            throw new RuntimeException( 'Font copying is unavailable or busy. Copy files manually without overwriting existing files.' );
        }
        if ( ! function_exists( 'wp_tempnam' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $copied = 0;
        $skipped = 0;
        try {
            $manifest = get_option( self::OPTION, array() );
            if ( ! is_array( $manifest ) ) {
                throw new RuntimeException( 'The font ownership record is invalid.' );
            }
            foreach ( scandir( $source ) ?: array() as $name ) {
                $file = self::contained_file( $source, $name );
                if ( null === $file ) {
                    continue;
                }
                $size = filesize( $file );
                $header = file_get_contents( $file, false, null, 0, 4 );
                if ( false === $size || $size < 12 || $size > self::MAX_BYTES || ! in_array( $header, array( "\x00\x01\x00\x00", 'OTTO', 'true' ), true ) ) {
                    ++$skipped;
                    continue;
                }
                $hash = hash_file( 'sha256', $file );
                $destination = $target . DIRECTORY_SEPARATOR . $name;
                if ( file_exists( $destination ) || is_link( $destination ) ) {
                    // Identical manually copied files are not adopted for deletion.
                    if ( self::contained_file( $target, $name ) === null || hash_file( 'sha256', $destination ) !== $hash ) {
                        ++$skipped;
                    }
                    continue;
                }
                if ( $copied >= 50 ) {
                    ++$skipped;
                    continue;
                }
                $temporary = wp_tempnam( $name, $target . DIRECTORY_SEPARATOR );
                if ( ! $temporary || dirname( realpath( $temporary ) ?: '' ) !== $target ) {
                    if ( $temporary ) {
                        wp_delete_file( $temporary );
                    }
                    throw new RuntimeException( 'Could not create a local font staging file.' );
                }
                try {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Explicit administrator-authorized local copy; contained paths, size limit, hash verification, no remote stream or overwrite.
                    if ( ! copy( $file, $temporary ) || filesize( $temporary ) !== $size || hash_file( 'sha256', $temporary ) !== $hash || ! $lock->owns() ) {
                        throw new RuntimeException( 'The source font changed or could not be copied.' );
                    }
                    // Hard-link publication is atomic and fails if another file already owns this name.
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_link -- Same-directory staged publication without overwriting an existing filename. Fail safely on unsupported filesystems.
                    if ( ! link( $temporary, $destination ) ) {
                        throw new RuntimeException( 'Could not publish the copied font without overwriting a file.' );
                    }
                    $manifest[ $name ] = $hash;
                    if ( ! update_option( self::OPTION, $manifest, false ) && get_option( self::OPTION, array() ) !== $manifest ) {
                        if ( self::contained_file( $target, $name ) === $destination && hash_file( 'sha256', $destination ) === $hash ) {
                            wp_delete_file( $destination );
                        }
                        throw new RuntimeException( 'Could not save font ownership. The new copy was removed.' );
                    }
                    ++$copied;
                } finally {
                    wp_delete_file( $temporary );
                }
            }
        } finally {
            $lock->release();
        }
        return array( 'copied' => $copied, 'skipped' => $skipped );
    }

    public static function handle_copy(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage fonts.', 'email-countdown-timer' ), '', array( 'response' => 403 ) );
            return;
        }
        if ( 'POST' !== sanitize_text_field( wp_unslash( Email_Countdown_Timer_Config::text( $_SERVER, 'REQUEST_METHOD' ) ) ) ) {
            wp_die( esc_html__( 'Use the font copy form.', 'email-countdown-timer' ), '', array( 'response' => 405 ) );
            return;
        }
        check_admin_referer( 'email_countdown_timer_copy_fonts' );
        try {
            $result = self::copy_legacy();
        } catch ( Throwable $error ) {
            wp_die( esc_html__( 'Local font copying failed. Check directory permissions and database locks, or use SFTP. Original files were not removed.', 'email-countdown-timer' ), '', array( 'response' => 500 ) );
            return;
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'email-countdown-timer-data', 'fonts_copied' => $result['copied'], 'fonts_skipped' => $result['skipped'] ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /** Called only after this site's explicit uninstall opt-in. Never recurse. */
    public static function delete_owned(): void {
        $directory = self::persistent_directory();
        $manifest = get_option( self::OPTION, array() );
        if ( null !== $directory && is_array( $manifest ) ) {
            foreach ( $manifest as $name => $hash ) {
                if ( ! is_string( $name ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
                    continue;
                }
                $file = self::contained_file( $directory, $name );
                if ( null !== $file && hash_file( 'sha256', $file ) === $hash ) {
                    wp_delete_file( $file );
                }
            }
        }
        delete_option( self::OPTION );
    }
}
