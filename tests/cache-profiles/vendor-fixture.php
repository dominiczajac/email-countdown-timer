<?php
/** Vendor configuration and synthetic content for the owned CLI fixture only. */
if ( PHP_SAPI !== 'cli' || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcache_' ) { exit( 1 ); }

final class Email_Countdown_Timer_Lab_Vendors {
    public static function configure( string $profile ): void {
        if ( $profile === 'elementor' ) {
            delete_transient( 'elementor_activation_redirect' );
        } elseif ( $profile === 'woocommerce' ) {
            // Preconfigured-site profile; no onboarding, payments or real data.
            delete_transient( '_wc_activation_redirect' );
            update_option( 'woocommerce_coming_soon', 'no' );
            update_option( 'woocommerce_allow_tracking', 'no' );
        }
    }

    public static function seed( string $profile, string $state ): ?array {
        if ( $profile === 'elementor' ) {
            $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish',
                'post_name' => $state . '-elementor', 'post_title' => 'Synthetic Elementor ' . $state, 'post_content' => '' ), true );
            if ( is_wp_error( $id ) ) { WP_CLI::error( 'Could not create Elementor fixture.' ); }
            update_post_meta( $id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
            update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array(
                array( 'id' => 'a102bd3', 'elType' => 'container', 'settings' => new stdClass(), 'elements' => array(
                    array( 'id' => 'b304cf1', 'elType' => 'widget', 'widgetType' => 'shortcode',
                        'settings' => array( 'shortcode' => '[ecd_timer id="cache-lab"]' ), 'elements' => array() ),
                ) ),
            ) ) ) );
        } elseif ( $profile === 'woocommerce' ) {
            $product = new WC_Product_Simple();
            $product->set_name( 'Synthetic product ' . $state );
            $product->set_slug( $state . '-product' );
            $product->set_status( 'publish' );
            $product->set_regular_price( '12.00' );
            $product->set_virtual( true );
            $product->set_description( '[ecd_timer id="cache-lab"]' );
            $id = $product->save();
            if ( ! $id ) { WP_CLI::error( 'Could not create WooCommerce fixture.' ); }
        } else {
            return null;
        }
        return array( 'profile' => $profile, 'post_id' => $id, 'url' => get_permalink( $id ), 'title' => get_the_title( $id ) );
    }

    public static function inventory( string $profile ): array {
        if ( $profile === 'elementor' ) {
            return array( 'version' => ELEMENTOR_VERSION,
                'print_google_fonts' => (bool) apply_filters( 'elementor/frontend/print_google_fonts', true ),
                'activation_redirect_pending' => (bool) get_transient( 'elementor_activation_redirect' ) );
        }
        if ( $profile === 'woocommerce' ) {
            return array( 'version' => WC_VERSION, 'coming_soon' => get_option( 'woocommerce_coming_soon' ),
                'allow_tracking' => get_option( 'woocommerce_allow_tracking' ),
                'activation_redirect_pending' => (bool) get_transient( '_wc_activation_redirect' ) );
        }
        return array();
    }
}
