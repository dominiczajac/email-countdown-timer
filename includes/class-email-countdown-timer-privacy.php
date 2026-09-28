<?php
/** Suggested text only; no published page, visitor data or external request. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Privacy {
    public static function suggest(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! is_admin() ) {
            return;
        }
        $content = '<p class="privacy-policy-tutorial">' . esc_html__( 'Review this suggestion against your hosting, email and optimization services. Add their actual logging, retention and processor details. Do not put personal data or recipient identifiers in public timer fields or image URLs.', 'email-countdown-timer' ) . '</p>'
            . '<p>' . esc_html__( 'We serve countdown images from our WordPress server. The countdown plugin does not set tracking cookies, count individual views or email opens, profile visitors, or send telemetry to its developer. It stores administrator-entered timer settings and temporary shared image caches locally.', 'email-countdown-timer' ) . '</p>'
            . '<p>' . esc_html__( 'Delivering an image requires an HTTP request. Our hosting, email-image proxies and other services may process connection data under their own logging and retention policies. Local fonts are read on the server; this plugin does not contact Google Fonts.', 'email-countdown-timer' ) . '</p>';
        wp_add_privacy_policy_content( 'Email Countdown Timer', $content );
    }
}
