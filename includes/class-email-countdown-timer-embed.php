<?php
/** Presentation only: reserve space without encoding or fetching an image. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Embed {
    private static array $dimensions = array();

    /** Request-local memoization only; no additional persistent or visitor data. */
    public static function dimensions( array $data ): ?array {
        if ( ! function_exists( 'imagefontwidth' ) || ! function_exists( 'imagefontheight' ) ) {
            return null;
        }
        unset( $data['alt'], $data['expiry_image_id'] );
        $now = time();
        $json = wp_json_encode( array( $data, $now ) );
        if ( false === $json ) {
            return null;
        }
        $key = hash( 'sha256', $json );
        if ( array_key_exists( $key, self::$dimensions ) ) {
            return self::$dimensions[ $key ];
        }
        try {
            $config = Email_Countdown_Timer_Config::normalize( $data );
            $box = ( new Email_Countdown_Timer_Renderer() )->measure( $config, Email_Countdown_Timer_Config::deadline( $config ), $now );
            $size = array( 'width' => $box['width'], 'height' => $box['height'] );
        } catch ( Throwable $error ) {
            // A malformed legacy record or unavailable font must not break the page.
            $size = null;
        }
        if ( count( self::$dimensions ) < 64 ) {
            self::$dimensions[ $key ] = $size;
        }
        return $size;
    }

    /** Core HTML API preserves existing escaped URLs, alt and optimizer attributes. */
    public static function reserve( string $html, array $data, bool $email = false ): string {
        if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
            return $html;
        }
        $size = self::dimensions( $data );
        if ( null === $size ) {
            return $html;
        }
        $tags = new WP_HTML_Tag_Processor( $html );
        if ( ! $tags->next_tag( 'IMG' ) ) {
            return $html;
        }
        $width = $email ? min( 600, $size['width'] ) : $size['width'];
        $height = max( 1, (int) round( $size['height'] * $width / $size['width'] ) );
        $tags->set_attribute( 'width', $width );
        $tags->set_attribute( 'height', $height );
        // Fixed CSS ratio (not auto) keeps cached HTML stable if a later image has
        // different natural dimensions, e.g. hidden days, changed font or an error.
        $style = 'display:block;width:' . $width . 'px;max-width:100%;height:auto;border:0;';
        if ( $email ) {
            $style .= 'outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;';
        } else {
            $style .= 'aspect-ratio:' . $width . '/' . $height . ';object-fit:contain;';
            $tags->set_attribute( 'decoding', 'async' );
        }
        $tags->set_attribute( 'style', $style );
        return $tags->get_updated_html();
    }
}
