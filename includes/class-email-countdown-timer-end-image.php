<?php
/** Local Media Library end images. No URL input, download, redirect or media deletion. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_End_Image {
    private const MAX_BYTES = 4194304;
    private const MAX_PIXELS = 4000000;

    /** Validate scalar IDs without turning malformed input into a valid attachment. */
    public static function id( $value ): int {
        if ( '' === $value ) {
            return 0;
        }
        if ( ! is_int( $value ) && ! is_string( $value ) ) {
            throw new InvalidArgumentException( 'Invalid end image ID.' );
        }
        $id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0, 'max_range' => PHP_INT_MAX ) ) );
        if ( false === $id ) {
            throw new InvalidArgumentException( 'Invalid end image ID.' );
        }
        return $id;
    }

    /** Resolve only regular, contained local raster files belonging to this site's attachments. */
    public static function resolve( int $id ): ?array {
        if ( $id < 1 ) {
            return null;
        }
        $post = get_post( $id );
        if ( ! $post || 'attachment' !== $post->post_type || ! in_array( $post->post_status, array( 'inherit', 'publish' ), true )
            || ! in_array( get_post_status( $post ), array( 'inherit', 'publish' ), true ) ) {
            return null;
        }
        // Bypass offload/URL filters: an absent local copy is not downloaded.
        $file = get_attached_file( $id, true );
        $uploads = wp_get_upload_dir();
        $base = $uploads['basedir'] ?? '';
        if ( ! empty( $uploads['error'] ) || ! is_string( $file ) || ! is_string( $base )
            || '' === $base || str_contains( $file, '://' ) || str_contains( $file, "\0" ) || str_contains( $base, '://' ) ) {
            return null;
        }
        $base = realpath( $base );
        $real = realpath( $file );
        if ( false === $base || false === $real || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR )
            || is_link( $file ) || ! is_file( $real ) || ! is_readable( $real ) ) {
            return null;
        }
        // Refuse symlinked components of the supplied path, including contained links.
        $absolute = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $file );
        $walk = str_starts_with( $absolute, DIRECTORY_SEPARATOR ) ? DIRECTORY_SEPARATOR : '';
        foreach ( explode( DIRECTORY_SEPARATOR, $absolute ) as $part ) {
            if ( '' === $part ) {
                continue;
            }
            if ( '..' === $part || '.' === $part ) {
                return null;
            }
            $walk = rtrim( $walk, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $part;
            if ( is_link( $walk ) ) {
                return null;
            }
        }
        clearstatcache( true, $real );
        $size = self::quiet( static fn() => filesize( $real ) );
        if ( ! is_int( $size ) || $size < 12 || $size > self::MAX_BYTES ) {
            return null;
        }
        // Only a short header read here; decode after limits are checked, never on a warm hit.
        $info = self::quiet( static fn() => getimagesize( $real ) );
        $allowed = array( IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp' );
        if ( ! is_array( $info ) || ! isset( $allowed[ $info[2] ?? 0 ] ) || $post->post_mime_type !== $allowed[ $info[2] ]
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > self::MAX_PIXELS ) {
            return null;
        }
        return array( 'id' => $id, 'file' => $real, 'type' => $info[2], 'width' => $info[0], 'height' => $info[1], 'bytes' => $size, 'mtime' => self::quiet( static fn() => filemtime( $real ) ) );
    }

    /** Public validation error is fixed; no attachment path or native decoder warning is exposed. */
    public static function validate_selection( int $id ): bool {
        if ( 0 === $id ) {
            return true;
        }
        if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) ) {
            return false;
        }
        $asset = self::resolve( $id );
        if ( null === $asset ) {
            return false;
        }
        $image = self::decode( $asset );
        $valid = $image instanceof GdImage;
        unset( $image );
        return $valid;
    }

    /** One decoded source frame, preserving aspect ratio on the timer's background. */
    public static function canvas( array $asset, int $width, int $height, string $background ): ?GdImage {
        Email_Countdown_Timer_Config::checkCanvas( $width, $height );
        $source = self::decode( $asset );
        if ( ! $source instanceof GdImage ) {
            return null;
        }
        $target = imagecreatetruecolor( $width, $height );
        if ( ! $target instanceof GdImage ) {
            return null;
        }
        $hex = ltrim( $background, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        imagefill( $target, 0, 0, imagecolorallocate( $target, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ) );
        $sw = imagesx( $source );
        $sh = imagesy( $source );
        $scale = min( $width / $sw, $height / $sh );
        $dw = max( 1, (int) floor( $sw * $scale ) );
        $dh = max( 1, (int) floor( $sh * $scale ) );
        $ok = imagecopyresampled( $target, $source, (int) floor( ( $width - $dw ) / 2 ), (int) floor( ( $height - $dh ) / 2 ), 0, 0, $dw, $dh, $sw, $sh );
        unset( $source );
        return $ok ? $target : null;
    }

    private static function decode( array $asset ): ?GdImage {
        // Revalidate after a wait: metadata/paths may have changed since the first lookup.
        $current = self::resolve( $asset['id'] );
        if ( $current !== $asset ) {
            return null;
        }
        $decoders = array( IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif', IMAGETYPE_WEBP => 'imagecreatefromwebp' );
        $decoder = $decoders[ $asset['type'] ];
        if ( ! function_exists( $decoder ) ) {
            return null;
        }
        $image = self::quiet( static fn() => $decoder( $asset['file'] ) );
        if ( ! $image instanceof GdImage || imagesx( $image ) !== $asset['width'] || imagesy( $image ) !== $asset['height'] ) {
            return null;
        }
        return $image;
    }

    /** Restore the caller's error handler, even for malformed local binary input. */
    private static function quiet( callable $operation ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped native decoder warning suppression prevents file paths/binary diagnostics leaking into images; the prior handler is restored in finally. No logging or debug output.
        set_error_handler( static function () { return true; } );
        try {
            return $operation();
        } catch ( Throwable $error ) {
            return null;
        } finally {
            restore_error_handler();
        }
    }
}
