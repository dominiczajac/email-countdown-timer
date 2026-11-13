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
        // Authenticated editor refresh only: deliberately no wp_ajax_nopriv action.
        add_action( 'wp_ajax_email_countdown_timer_campaigns', array( self::class, 'refresh_campaigns' ) );
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
        // Only an explicit allowlisted mode opts in; existing posts keep image output.
        if ( 'live' === ( $attributes['displayMode'] ?? null ) ) {
            require_once __DIR__ . '/class-email-countdown-timer-live.php';
            $html = Email_Countdown_Timer_Live::render( $id, $timers[ $id ] );
        } else {
            $html = $plugin->renderShortcode( array( 'id' => $id ) );
        }
        $wrapper = get_block_wrapper_attributes( array( 'style' => 'display:flex;justify-content:' . $position . ';' ) );
        return '<div ' . $wrapper . '>' . $html . '</div>';
    }

    /** Restrict editor-only choices to the concrete editable post or site-editor capability. */
    public static function editor_settings( array $settings, $context ): array {
        unset( $settings['easyCountdown'] );
        $post = is_object( $context ) && isset( $context->post ) ? $context->post : null;
        if ( $post instanceof WP_Post ) {
            if ( ! current_user_can( 'edit_post', $post->ID ) ) {
                return self::expose_settings( $settings );
            }
        } elseif ( ! current_user_can( 'edit_theme_options' ) ) {
            return self::expose_settings( $settings );
        }
        $post_id = $post instanceof WP_Post ? $post->ID : 0;
        $settings['easyCountdown'] = self::catalog();
        $settings['easyCountdown']['refresh'] = array(
            'url' => admin_url( 'admin-ajax.php' ),
            'postId' => $post_id,
            'nonce' => wp_create_nonce( 'email_countdown_timer_campaigns_' . $post_id ),
        );
        return self::expose_settings( $settings );
    }

    /** Refresh IDs after creation in another tab, rechecking the original editing context. */
    public static function refresh_campaigns(): void {
        if ( 'POST' !== sanitize_text_field( wp_unslash( Email_Countdown_Timer_Config::text( $_SERVER, 'REQUEST_METHOD' ) ) ) ) {
            wp_send_json_error( null, 405 );
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only to choose the context-bound nonce; capability and nonce are checked before reading campaign data.
        $post_id = filter_var( Email_Countdown_Timer_Config::text( $_POST, 'post_id' ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
        if ( false === $post_id ) {
            wp_send_json_error( null, 400 );
            return;
        }
        check_ajax_referer( 'email_countdown_timer_campaigns_' . $post_id, 'nonce' );
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            $allowed = $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID );
        } else {
            $allowed = current_user_can( 'edit_theme_options' );
        }
        if ( ! $allowed ) {
            wp_send_json_error( null, 403 );
            return;
        }
        wp_send_json_success( self::catalog() );
    }

    /** Bounded references; display names are restricted to timer administrators. */
    private static function catalog(): array {
        $timers = get_option( 'easy_countdown_timers', array() );
        $choices = array();
        $frame_sizes = array();
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
                $name = current_user_can( 'manage_options' ) ? Email_Countdown_Timer_Config::text( $config, 'campaign_name' ) : '';
                $label = '' !== trim( $name ) && strlen( $name ) <= 200 ? $name : $id;
                if ( current_user_can( 'manage_options' ) && '' === trim( $name ) ) {
                    $label = sprintf( /* translators: %s: existing unnamed timer identifier. */ __( 'Unnamed timer (%s)', 'easy-countdown' ), $id );
                }
                $choices[] = array( 'value' => $id, 'label' => $label );
                $frame_sizes[$id] = Email_Countdown_Timer_Frame::dimensions($config);
            }
        }
        // Older records can share names. Keep their selectable references distinguishable.
        $counts = array_count_values( array_column( $choices, 'label' ) );
        foreach ( $choices as &$choice ) {
            if ( $counts[ $choice['label'] ] > 1 ) $choice['label'] .= ' (' . $choice['value'] . ')';
            if ( strlen( $choice['label'] ) > 403 ) $choice['label'] = $choice['value'];
        }
        unset( $choice );
        // A saved name can itself imitate a disambiguated label. IDs are unique.
        if ( count( array_unique( array_column( $choices, 'label' ) ) ) !== count( $choices ) ) {
            foreach ( $choices as &$choice ) $choice['label'] = $choice['value'];
            unset( $choice );
        }
        usort( $choices, static fn( $a, $b ) => strnatcasecmp( $a['label'], $b['label'] ) );
        return array(
            'timers' => $choices,
            'truncated' => $truncated,
            'frameUrl' => add_query_arg(array('ecd_action'=>'frame'),home_url('/')),
            'frameSizes' => $frame_sizes,
            'imageUrl' => add_query_arg( array( 'ecd_action' => 'render', 'mode' => 'static' ), home_url( '/' ) ),
            'manageUrl' => current_user_can( 'manage_options' ) ? add_query_arg( 'page', 'ecd-timers', admin_url( 'admin.php' ) ) : '',
            'createUrl' => current_user_can( 'manage_options' ) ? add_query_arg( 'page', 'ecd-timers', admin_url( 'admin.php' ) ) . '#ect-quick-create' : '',
        );
    }

    /** Core may whitelist store settings; attach the authorized data to our editor-only script. */
    private static function expose_settings( array $settings ): array {
        $data = wp_json_encode( $settings['easyCountdown'] ?? new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
        wp_add_inline_script(
            'easy-countdown-block-editor',
            'window.emailCountdownTimerBlock = ' . ( false === $data ? '{}' : $data ) . ';',
            'before'
        );
        return $settings;
    }

}
