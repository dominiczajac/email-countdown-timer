<?php
/** Local font storage. No upload endpoint, remote downloads or visitor data. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Fonts {
    public const BARLOW_CONDENSED_BLACK = 'builtin:barlow-condensed-black';
    public const OPTION = 'email_countdown_timer_tickloom_copied_fonts';
    private const LEGACY_UPLOADS = array(
        'email_countdown_timer_actnow_copied_fonts' => 'actnow-countdown-timers',
        'email_countdown_timer_copied_fonts' => 'email-countdown-timer',
    );
    private const LEGACY_SLUGS = array( 'actnow-countdown-timers', 'easy-countdown', 'email-countdown-timer' );
    public const BUNDLED_FILE = 'easy-countdown-lato-regular.ttf';
    public const BUNDLED_CHOICE = 'builtin:lato-regular';
    public const IBM_PLEX_MONO = 'builtin:ibm-plex-mono-regular';
    public const BARLOW_CONDENSED = 'builtin:barlow-condensed-regular';
    public const CRIMSON_TEXT = 'builtin:crimson-text-regular';
    private const BUNDLED_SHA256 = 'd636e4683231f931eda222d588e944d082bfd3bdba02f928bee461c0f185b251';
    private const MAX_BYTES = 5242880;

    /** Reviewed immutable font/notice pairs; filenames never become saved choices. */
    public static function bundled(): array {
        return array(
            self::BARLOW_CONDENSED_BLACK => array( 'file' => 'easy-countdown-barlow-condensed-black.ttf', 'sha256' => 'e74b750df582c608f35db467b711b2b60d2217618e85e60b72b42dfd00446cab', 'license' => 'OFL-barlow-condensed.txt', 'license_sha256' => '186d750eb496a4c17a76385f82be6aea2ac1cf2de074a811d63786cf374ea73f' ),
            self::BUNDLED_CHOICE => array( 'file' => self::BUNDLED_FILE, 'sha256' => self::BUNDLED_SHA256,
                'license' => 'OFL.txt', 'license_sha256' => '74ba064d03f1f1c4a952da936c3eb71866c34404916734de3cae73b34357e59e' ),
            self::IBM_PLEX_MONO => array( 'file' => 'easy-countdown-ibm-plex-mono-regular.ttf', 'sha256' => '6a3412f058c7d8dfd9170c41e85ade48e5156ecb89356110ca57a0a27734af46',
                'license' => 'OFL-ibm-plex-mono.txt', 'license_sha256' => '7e6b2818edbd8f6a01ae80641cc8f16a51080d08fb4e532be3a0b6f74adb07da' ),
            self::BARLOW_CONDENSED => array( 'file' => 'easy-countdown-barlow-condensed-regular.ttf', 'sha256' => '583cec5da3b84bc4dc7c9c72e2a565c94d34e431518b19d7e250b7830ad5f996',
                'license' => 'OFL-barlow-condensed.txt', 'license_sha256' => '186d750eb496a4c17a76385f82be6aea2ac1cf2de074a811d63786cf374ea73f' ),
            self::CRIMSON_TEXT => array( 'file' => 'easy-countdown-crimson-text-regular.ttf', 'sha256' => '48e6c5d5ad1d01599d374ecb817e15890d1feb3b8a3a88e527d44c90389e1f06',
                'license' => 'OFL-crimson-text.txt', 'license_sha256' => '50fd67cddc097377a5c871e8452b778bc5aedfa3480a705cb27c5e3a078218df' ),
        );
    }

    public static function is_bundled( string $name ): bool {
        return isset( self::bundled()[ $name ] );
    }

    public static function label( string $name ): string {
        return match ( $name ) {
            self::BARLOW_CONDENSED_BLACK => __( 'Barlow Condensed Black (included)', 'tickloom-countdown-timers' ),
            self::BUNDLED_CHOICE => __( 'Lato Regular (included)', 'tickloom-countdown-timers' ),
            self::IBM_PLEX_MONO => __( 'IBM Plex Mono Regular (included)', 'tickloom-countdown-timers' ),
            self::BARLOW_CONDENSED => __( 'Barlow Condensed Regular (included)', 'tickloom-countdown-timers' ),
            self::CRIMSON_TEXT => __( 'Crimson Text Regular (included)', 'tickloom-countdown-timers' ),
            default => $name,
        };
    }

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

    /** Previous installed copies are read sources, never write targets. */
    private static function plugin_font_directories(): array {
        $dirs = array_filter( array( self::legacy_directory() ) );
        $root = defined( 'WP_PLUGIN_DIR' ) ? realpath( WP_PLUGIN_DIR ) : false;
        if ( false !== $root ) {
            foreach ( self::LEGACY_SLUGS as $slug ) {
                $plugin = $root . DIRECTORY_SEPARATOR . $slug;
                $fonts = $plugin . DIRECTORY_SEPARATOR . 'fonts';
                $main = $plugin . DIRECTORY_SEPARATOR . 'email-countdown-timer.php';
                if ( ! is_link( $plugin ) && ! is_link( $main ) && is_file( $main ) && ! is_link( $fonts ) && is_dir( $fonts ) && realpath( $fonts ) === $fonts ) {
                    $dirs[] = $fonts;
                }
            }
        }
        return array_unique( $dirs );
    }

    private static function previous_plugin_installed(): bool {
        if ( ! defined( 'WP_PLUGIN_DIR' ) ) return false;
        $current = defined( 'EMAIL_COUNTDOWN_TIMER_DIR' ) ? EMAIL_COUNTDOWN_TIMER_DIR : dirname( __DIR__ ) . '/';
        $current_main = realpath( $current . 'email-countdown-timer.php' );
        foreach ( self::LEGACY_SLUGS as $slug ) {
            $old_main = WP_PLUGIN_DIR . '/' . $slug . '/email-countdown-timer.php';
            // Even a linked install can still need its previous uploads files.
            if ( is_file( $old_main ) && realpath( $old_main ) !== $current_main ) return true;
        }
        return false;
    }

    /** Reject a misconfigured uploads path that resolves into plugin code. */
    private static function plugin_code_directory( string $path ): bool {
        // Be conservative where a case-insensitive filesystem preserves path casing.
        $path = strtolower( $path );
        $roots = array( defined( 'EMAIL_COUNTDOWN_TIMER_DIR' ) ? EMAIL_COUNTDOWN_TIMER_DIR : dirname( __DIR__ ) );
        foreach ( array( 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR' ) as $constant ) {
            if ( defined( $constant ) ) {
                $roots[] = constant( $constant );
            }
        }
        foreach ( $roots as $root ) {
            $resolved = realpath( $root );
            if ( false !== $resolved ) {
                $resolved = strtolower( $resolved );
                if ( $path === $resolved || str_starts_with( $path, $resolved . DIRECTORY_SEPARATOR ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /** No mkdir in read/render paths. Multisite gets a suffix even with shared uploads. */
    public static function persistent_directory( bool $create = false ): ?string {
        return self::uploads_font_directory( 'tickloom-countdown-timers', $create );
    }

    private static function legacy_uploads_directories(): array {
        return array_map( static fn( string $folder ): ?string => self::uploads_font_directory( $folder, false ), self::LEGACY_UPLOADS );
    }

    private static function uploads_font_directory( string $folder, bool $create ): ?string {
        if ( ! function_exists( 'wp_get_upload_dir' ) ) {
            return null;
        }
        $uploads = wp_get_upload_dir();
        $base = $uploads['basedir'] ?? '';
        if ( ! empty( $uploads['error'] ) || ! is_string( $base ) || '' === $base || str_contains( $base, '://' ) || str_contains( $base, "\0" ) ) {
            return null;
        }
        if ( ! is_dir( $base ) ) {
            // A nonexistent base cannot be canonicalized. Check its nearest
            // existing parent without allowing a parent hop during creation.
            if ( ! $create || in_array( '..', explode( '/', str_replace( '\\', '/', $base ) ), true ) ) {
                return null;
            }
            $ancestor = $base;
            while ( ! is_dir( $ancestor ) && dirname( $ancestor ) !== $ancestor ) {
                $ancestor = dirname( $ancestor );
            }
            $resolved = realpath( $ancestor );
            if ( false === $resolved || self::plugin_code_directory( $resolved ) || ! wp_mkdir_p( $base ) ) {
                return null;
            }
        }
        $path = realpath( $base );
        if ( false === $path || ( $create && self::plugin_code_directory( $path ) ) ) {
            return null;
        }
        $parts = array( $folder, 'fonts' );
        if ( function_exists( 'is_multisite' ) && is_multisite() ) {
            $parts[] = 'site-' . (int) get_current_blog_id();
        }
        foreach ( $parts as $part ) {
            $next = $path . DIRECTORY_SEPARATOR . $part;
            if ( is_link( $next ) || ( ! is_dir( $next ) && ( ! $create || ! wp_mkdir_p( $next ) ) ) ) {
                return null;
            }
            $resolved = realpath( $next );
            if ( false === $resolved || dirname( $resolved ) !== $path || ( $create && self::plugin_code_directory( $resolved ) ) ) {
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
        $bundled = self::bundled();
        if ( isset( $bundled[ $name ] ) ) {
            return self::contained_file( self::legacy_directory(), $bundled[ $name ]['file'] );
        }
        if ( ! self::valid_name( $name ) ) {
            return null;
        }
        // An old filename selection must never become a shipped face.
        if ( ! in_array( $name, array_column( $bundled, 'file' ), true ) ) {
            foreach ( self::plugin_font_directories() as $directory ) {
                $file = self::contained_file( $directory, $name );
                if ( null !== $file ) return $file;
            }
        }
        foreach ( array_merge( array( self::persistent_directory() ), self::legacy_uploads_directories() ) as $directory ) {
            $file = self::contained_file( $directory, $name );
            if ( null !== $file ) return $file;
        }
        return null;
    }

    public static function names(): array {
        $names = array();
        $bundled = self::bundled();
        $shipped_files = array_column( $bundled, 'file' );
        $legacy = self::legacy_directory();
        $plugin_dirs = self::plugin_font_directories();
        foreach ( array_merge( $plugin_dirs, array( self::persistent_directory() ), self::legacy_uploads_directories() ) as $directory ) {
            if ( null === $directory || ! is_readable( $directory ) ) {
                continue;
            }
            foreach ( scandir( $directory ) ?: array() as $name ) {
                if ( in_array( $directory, $plugin_dirs, true ) && in_array( $name, $shipped_files, true ) ) {
                    // Shipped faces are exposed only through reserved choices.
                    continue;
                }
                if ( null !== self::contained_file( $directory, $name ) ) {
                    $names[ $name ] = $name;
                }
            }
        }
        foreach ( $bundled as $choice => $font ) {
            if ( null !== self::contained_file( $legacy, $font['file'] ) ) {
                $names[ $choice ] = $choice;
            }
        }
        natcasesort( $names );
        return array_values( $names );
    }

    /** Copy, never move/overwrite. A session lock serializes the ownership manifest. */
    public static function copy_legacy(): array {
        $previous_uploads = self::legacy_uploads_directories();
        $sources = array_filter( array_merge( self::plugin_font_directories(), $previous_uploads ) );
        $target = self::persistent_directory( true );
        if ( empty( $sources ) || null === $target ) {
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
            $shipped_hashes = array_column( self::bundled(), 'sha256', 'file' );
            foreach ( self::font_sources( $sources ) as $name => $file ) {
                $size = filesize( $file );
                $header = file_get_contents( $file, false, null, 0, 4 );
                if ( false === $size || $size < 12 || $size > self::MAX_BYTES || ! in_array( $header, array( "\x00\x01\x00\x00", 'OTTO', 'true' ), true ) ) {
                    ++$skipped;
                    continue;
                }
                $hash = hash_file( 'sha256', $file );
                // An unchanged shipped font is restored by updates, not user migration data.
                if ( ! in_array( dirname( $file ), $previous_uploads, true ) && isset( $shipped_hashes[ $name ] ) && $shipped_hashes[ $name ] === $hash ) {
                    continue;
                }
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

    private static function font_sources( array $directories ): Generator {
        foreach ( $directories as $directory ) {
            if ( ! is_readable( $directory ) ) continue;
            foreach ( scandir( $directory ) ?: array() as $name ) {
                $file = self::contained_file( $directory, $name );
                if ( null !== $file ) yield $name => $file;
            }
        }
    }

    public static function handle_copy(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage fonts.', 'tickloom-countdown-timers' ), '', array( 'response' => 403 ) );
            return;
        }
        if ( 'POST' !== Email_Countdown_Timer_Config::request_method() ) {
            wp_die( esc_html__( 'Use the font copy form.', 'tickloom-countdown-timers' ), '', array( 'response' => 405 ) );
            return;
        }
        check_admin_referer( 'email_countdown_timer_copy_fonts' );
        try {
            $result = self::copy_legacy();
        } catch ( Throwable $error ) {
            wp_die( esc_html__( 'Local font copying failed. Check directory permissions and database locks, or use SFTP. Original files were not removed.', 'tickloom-countdown-timers' ), '', array( 'response' => 500 ) );
            return;
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'email-countdown-timer-data', 'fonts_copied' => $result['copied'], 'fonts_skipped' => $result['skipped'] ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /** Called only after this site's explicit uninstall opt-in. Never recurse. */
    public static function delete_owned(): void {
        $owned_roots = array( self::OPTION => self::persistent_directory() );
        // Keep earlier copies' files and ownership maps while rollback is possible.
        if ( ! self::previous_plugin_installed() ) {
            $owned_roots += self::legacy_uploads_directories();
        }
        foreach ( $owned_roots as $option => $directory ) {
            $manifest = get_option( $option, array() );
            if ( null !== $directory && ! self::plugin_code_directory( $directory ) && is_array( $manifest ) ) {
                foreach ( $manifest as $name => $hash ) {
                    if ( ! is_string( $name ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) continue;
                    $file = self::contained_file( $directory, $name );
                    if ( null !== $file && hash_file( 'sha256', $file ) === $hash ) {
                        wp_delete_file( $file );
                    }
                }
            }
            delete_option( $option );
        }
    }
}
