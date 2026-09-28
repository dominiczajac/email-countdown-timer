<?php
/** Session-owned MySQL/MariaDB lock; no options, files, cron or visitor identifiers. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class Email_Countdown_Timer_Render_Lock {
    private string $name;
    private int $connection;
    private bool $released = false;

    private function __construct( string $name, int $connection ) {
        $this->name = $name;
        $this->connection = $connection;
    }

    public static function acquire( string $key ): ?self {
        global $wpdb;
        // 64-byte maximum on MySQL. Scope includes database and per-site table.
        $name = 'ect:' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->options . '|' . $key ), 0, 60 );
        // No WordPress locking API exists. This SELECT is intentionally uncached.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic session lock, prepared code-owned name; bounded two-second wait, no table mutation.
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT GET_LOCK(%s, 2) AS acquired, CONNECTION_ID() AS connection_id', $name ), ARRAY_A );
        if ( ! is_array( $row ) || '1' !== (string) ( $row['acquired'] ?? '' ) || empty( $row['connection_id'] ) ) {
            return null;
        }
        $lock = new self( $name, (int) $row['connection_id'] );
        // Also release on an early exit/fatal error; connection termination is the
        // final safety net. Never expire a live lock while its renderer still runs.
        register_shutdown_function( array( $lock, 'release' ) );
        return $lock;
    }

    public function owns(): bool {
        global $wpdb;
        if ( $this->released ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify the original session still owns this lock after any DB reconnect.
        return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT (CONNECTION_ID() = %d AND IS_USED_LOCK(%s) = CONNECTION_ID())', $this->connection, $this->name ) );
    }

    public function release(): void {
        global $wpdb;
        if ( $this->released ) {
            return;
        }
        $this->released = true;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release only this name on its original session, never another request's lock.
        $wpdb->get_var( $wpdb->prepare( 'SELECT IF(CONNECTION_ID() = %d, RELEASE_LOCK(%s), 0)', $this->connection, $this->name ) );
    }
}
