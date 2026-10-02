<?php
/** Native block adapter; saved campaigns and the public renderer remain authoritative. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Block {
    private const LIST_LIMIT = 250;

    public static function register( Email_Countdown_Timer_Plugin $plugin ): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }
        wp_register_script(
            'easy-countdown-block-editor',
            plugins_url( 'assets/block-editor.js', EMAIL_COUNTDOWN_TIMER_FILE ),
            array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
            EMAIL_COUNTDOWN_TIMER_VERSION,
            true
        );
        wp_set_script_translations( 'easy-countdown-block-editor', 'easy-countdown' );
        register_block_type(
            EMAIL_COUNTDOWN_TIMER_DIR . 'blocks/timer',
            array( 'render_callback' => static function ( $attributes ) use ( $plugin ): string {
                return self::render( is_array( $attributes ) ? $attributes : array(), $plugin );
            } )
        );
        add_filter( 'block_editor_settings_all', array( self::class, 'editor_settings' ), 10, 2 );
    }

    /** A campaign reference is not a free-form URL, filename or configuration. */
    private static function timer_id( $value ): string {
        if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 200 ) {
            return '';
        }
        $id = Email_Countdown_Timer_Config::id( $value );
        return $id === $value ? $id : '';
    }

    public static function render( array $attributes, Email_Countdown_Timer_Plugin $plugin ): string {
        $id = self::timer_id( $attributes['timerId'] ?? null );
        $timers = get_option( 'easy_countdown_timers', array() );
        if ( '' === $id || ! is_array( $timers ) || ! isset( $timers[ $id ] ) || ! is_array( $timers[ $id ] ) ) {
            return '';
        }
        $alignment = $attributes['alignment'] ?? 'left';
        $positions = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
        $position = is_string( $alignment ) && isset( $positions[ $alignment ] ) ? $positions[ $alignment ] : 'flex-start';
        // The shortcode is the one authoritative implementation of alt, dimensions,
        // cache interoperability and frontend refresh. Never generate an image here.
        $html = $plugin->renderShortcode( array( 'id' => $id ) );
        $wrapper = get_block_wrapper_attributes( array( 'style' => 'display:flex;justify-content:' . $position . ';' ) );
        return '<div ' . $wrapper . '>' . $html . '</div>';
    }

    /** Restrict editor-only choices to the concrete editable post or site-editor capability. */
    public static function editor_settings( array $settings, $context ): array {
        unset( $settings['easyCountdown'] );
        $post = is_object( $context ) && isset( $context->post ) ? $context->post : null;
        if ( $post instanceof WP_Post ) {
            if ( ! current_user_can( 'edit_post', $post->ID ) ) {
                return $settings;
            }
        } elseif ( ! current_user_can( 'edit_theme_options' ) ) {
            return $settings;
        }
        $timers = get_option( 'easy_countdown_timers', array() );
        $choices = array();
        $truncated = false;
        if ( is_array( $timers ) ) {
            foreach ( $timers as $key => $config ) {
                $id = self::timer_id( (string) $key );
                if ( '' === $id || ! is_array( $config ) ) {
                    continue;
                }
                if ( count( $choices ) >= self::LIST_LIMIT ) {
                    $truncated = true;
                    break;
                }
                // Do not expose raw settings, attachment IDs, deadlines, alt or disk paths.
                $choices[] = array( 'value' => $id, 'label' => $id );
            }
        }
        usort( $choices, static fn( $a, $b ) => strnatcasecmp( $a['label'], $b['label'] ) );
        $settings['easyCountdown'] = array(
            'timers' => $choices,
            'truncated' => $truncated,
            'imageUrl' => add_query_arg( array( 'ecd_action' => 'render', 'mode' => 'static' ), home_url( '/' ) ),
            'manageUrl' => current_user_can( 'manage_options' ) ? add_query_arg( 'page', 'ecd-timers', admin_url( 'admin.php' ) ) : '',
        );
        // Older editors filter unknown settings before populating core/block-editor.
        // Attach the capability-checked data to the editor script via the public API.
        wp_add_inline_script(
            'easy-countdown-block-editor',
            'window.emailCountdownTimerBlock = ' . wp_json_encode( $settings['easyCountdown'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
            'before'
        );
        return $settings;
    }
}
