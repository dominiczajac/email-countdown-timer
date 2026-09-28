<?php
/** Uninstall-only cleanup of explicitly owned data, with per-site consent. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

final class Email_Countdown_Timer_Uninstaller {
    private const POLICY_OPTION = 'email_countdown_timer_delete_data_on_uninstall';
    private const TIMERS_OPTION = 'easy_countdown_timers';
    private const BATCH_SIZE = 100;

    public static function run(): void {
        if ( ! is_multisite() ) {
            self::clean_site();
            return;
        }
        // Enumerate across networks; never let one site's consent erase another site's data.
        $offset = 0;
        do {
            $sites = get_sites( array( 'fields' => 'ids', 'number' => self::BATCH_SIZE, 'offset' => $offset, 'orderby' => 'id', 'order' => 'ASC', 'network_id' => 0 ) );
            foreach ( $sites as $site_id ) {
                switch_to_blog( (int) $site_id );
                try {
                    self::clean_site();
                } finally {
                    restore_current_blog();
                }
            }
            $offset += count( $sites );
        } while ( count( $sites ) === self::BATCH_SIZE );
    }

    private static function clean_site(): void {
        if ( ! in_array( get_option( self::POLICY_OPTION, '0' ), array( '1', 1 ), true ) ) {
            return;
        }
        $timers = get_option( self::TIMERS_OPTION, array() );
        if ( is_array( $timers ) ) {
            foreach ( array_keys( $timers ) as $id ) {
                foreach ( array( 'png', 'gif', 'webp' ) as $format ) {
                    self::delete_image_cache( 'ecd_v1211_' . hash( 'sha256', (string) $id . '|' . $format ) );
                }
            }
        }
        // Also catch orphaned and legacy cache entries, including expired timeout-only rows.
        self::clean_database_cache();
        delete_option( self::TIMERS_OPTION );
        delete_option( self::POLICY_OPTION );
        // The plugin schedules no cron hooks and creates no custom tables or metadata.
        // Do not delete the shared `cron` option, guess hooks, or flush a global object cache.
    }

    private static function clean_database_cache(): void {
        global $wpdb;
        $cursor = 0;
        do {
            // Read only known prefixes; exact hash-shaped names are verified again below.
            // The table name is supplied by WordPress. No request values enter this query.
            $query = $wpdb->prepare(
                "SELECT option_id, option_name FROM {$wpdb->options}
                 WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s)
                 ORDER BY option_id ASC LIMIT %d",
                $cursor,
                $wpdb->esc_like( '_transient_ecd_v1211_' ) . '%',
                $wpdb->esc_like( '_transient_timeout_ecd_v1211_' ) . '%',
                $wpdb->esc_like( '_transient_ecd_img_' ) . '%',
                $wpdb->esc_like( '_transient_timeout_ecd_img_' ) . '%',
                self::BATCH_SIZE
            );
            // Uninstall-only enumeration cannot be served from an application cache.
            $rows = $wpdb->get_results( $query, ARRAY_A );
            if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
                throw new RuntimeException( 'Email Countdown Timer could not enumerate its database cache for uninstall.' );
            }
            foreach ( $rows as $row ) {
                $cursor = (int) $row['option_id'];
                if ( preg_match( '/^_transient_(?:timeout_)?(ecd_v1211_[a-f0-9]{64}|ecd_img_[a-f0-9]{32})$/D', $row['option_name'], $matches ) ) {
                    self::delete_image_cache( $matches[1] );
                }
            }
            // A keyset cursor avoids skipped rows when deleting during pagination.
        } while ( count( $rows ) === self::BATCH_SIZE );
    }

    private static function delete_image_cache( string $key ): void {
        delete_transient( $key );
        // With an external object cache, delete_transient() does not remove old DB rows.
        delete_option( '_transient_' . $key );
        delete_option( '_transient_timeout_' . $key );
    }
}
