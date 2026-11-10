<?php
/** Opt-in website text output. Campaign storage and image rendering stay authoritative. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Live {
    public static function render( string $id, array $raw ): string {
        try {
            $config = Email_Countdown_Timer_Config::normalize( $raw );
            $deadline = Email_Countdown_Timer_Config::deadline( $config );
            $date = ( new DateTimeImmutable( '@' . $deadline ) )->setTimezone( new DateTimeZone( $config['tz'] ) );
        } catch ( InvalidArgumentException $error ) {
            return '';
        }
        wp_enqueue_script( 'email-countdown-timer-live', plugins_url( 'assets/live-countdown.js', EMAIL_COUNTDOWN_TIMER_FILE ), array(), EMAIL_COUNTDOWN_TIMER_VERSION, true );
        wp_enqueue_style( 'email-countdown-timer-live', plugins_url( 'assets/live-countdown.css', EMAIL_COUNTDOWN_TIMER_FILE ), array(), EMAIL_COUNTDOWN_TIMER_VERSION );
        $remaining = max( 0, $deadline - time() );
        // Match the image: hiding days omits that column, without adding days to hours.
        $values = array( 'd' => intdiv( $remaining, 86400 ), 'h' => intdiv( $remaining % 86400, 3600 ), 'm' => intdiv( $remaining % 3600, 60 ), 's' => $remaining % 60 );
        $end_url = empty( $config['expiry_image_id'] ) ? '' : add_query_arg( array( 'ecd_action' => 'render', 'ecd' => $id, 'mode' => 'static' ), home_url( '/' ) );
        $alt = Email_Countdown_Timer_Config::alt( $config, __( 'Countdown', 'easy-countdown' ) );
        $style = '--ecd-bg:' . $config['bg'] . ';--ecd-digit:' . $config['dc'] . ';--ecd-label:' . $config['lc'] . ';';
        ob_start();
        ?>
        <div class="ecd-live" role="group" aria-label="<?php echo esc_attr( $alt ); ?>" style="<?php echo esc_attr( $style ); ?>" data-ecd-deadline="<?php echo esc_attr( (string) $deadline ); ?>" data-ecd-end-image="<?php echo esc_url( $end_url ); ?>">
            <div class="ecd-live__stage">
                <div class="ecd-live__digits<?php echo $config['hide_days'] ? ' ecd-live__digits--three' : ''; ?>" aria-hidden="true">
                    <?php foreach ( $values as $unit => $value ) : ?>
                        <?php if ( 'd' === $unit && $config['hide_days'] ) { continue; } ?>
                        <div class="ecd-live__unit">
                            <span class="ecd-live__number" data-ecd-unit="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ); ?></span>
                            <span class="ecd-live__label" title="<?php echo esc_attr( $config[ 'label_' . $unit ] ); ?>"><?php echo esc_html( $config[ 'label_' . $unit ] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="ecd-live__deadline"><?php echo esc_html__( 'Deadline:', 'easy-countdown' ); ?> <time datetime="<?php echo esc_attr( $date->format( DATE_ATOM ) ); ?>"><?php echo esc_html( $date->format( 'Y-m-d H:i:s P' ) . ' (' . $config['tz'] . ')' ); ?></time></p>
            <div class="ecd-live__controls"><button type="button" style="visibility:hidden" data-ecd-pause="<?php echo esc_attr__( 'Pause countdown', 'easy-countdown' ); ?>" data-ecd-resume="<?php echo esc_attr__( 'Resume countdown', 'easy-countdown' ); ?>" data-ecd-ended="<?php echo esc_attr__( 'Countdown ended', 'easy-countdown' ); ?>"><?php echo esc_html__( 'Pause countdown', 'easy-countdown' ); ?></button></div>
            <noscript><p><?php echo esc_html__( 'Snapshot only. Enable JavaScript to update the digits; use the deadline above for the exact end time.', 'easy-countdown' ); ?></p></noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
