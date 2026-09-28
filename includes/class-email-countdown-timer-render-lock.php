<?php
/** Session-owned database locks. No options, files, cron or visitor identifiers. */
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

    /** Compatibility wrapper: only a real acquired lock can be returned. */
    public static function acquire( string $key ): ?self {
        return self::attempt( $key, 2 )['lock'];
    }

    /** @return array{state:string,lock:?self} No backend error text is exposed. */
    public static function attempt( string $key, int $wait = 1 ): array {
        global $wpdb;
        if ( ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) || ! defined( 'DB_NAME' )
            || ! is_object( $wpdb ) || ! isset( $wpdb->options ) || ! is_string( $wpdb->options )
            || ! is_callable( array( $wpdb, 'get_row' ) ) || ! is_callable( array( $wpdb, 'get_var' ) ) || ! is_callable( array( $wpdb, 'prepare' ) ) ) {
            return array( 'state' => 'unsupported', 'lock' => null );
        }
        $wait = max( 0, min( 2, $wait ) );
        $name = 'ect:' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->options . '|' . $key ), 0, 60 );
        $can_suppress = is_callable( array( $wpdb, 'suppress_errors' ) );
        $previous = $can_suppress ? $wpdb->suppress_errors( true ) : null;
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic session lock; prepared code-owned name and bounded wait, session-owned and released only by its original owner.
            $row = $wpdb->get_row( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d) AS acquired, CONNECTION_ID() AS connection_id', $name, $wait ), ARRAY_A );
            if ( ! is_array( $row ) || ! empty( $wpdb->last_error ) || ! isset( $row['acquired'] ) ) {
                return array( 'state' => 'error', 'lock' => null );
            }
            if ( '0' === (string) $row['acquired'] ) {
                return array( 'state' => 'busy', 'lock' => null );
            }
            if ( '1' !== (string) $row['acquired'] || empty( $row['connection_id'] ) ) {
                return array( 'state' => 'error', 'lock' => null );
            }
            $lock = new self( $name, (int) $row['connection_id'] );
            register_shutdown_function( array( $lock, 'release' ) );
            return array( 'state' => 'acquired', 'lock' => $lock );
        } catch ( Throwable $error ) {
            return array( 'state' => 'error', 'lock' => null );
        } finally {
            if ( $can_suppress ) {
                $wpdb->suppress_errors( $previous );
            }
        }
    }

    public function owns(): bool {
        global $wpdb;
        if ( $this->released ) {
            return false;
        }
        $can_suppress = is_callable( array( $wpdb, 'suppress_errors' ) );
        $previous = $can_suppress ? $wpdb->suppress_errors( true ) : null;
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Original session must still own the lock after any reconnect.
            return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT (CONNECTION_ID() = %d AND IS_USED_LOCK(%s) = CONNECTION_ID())', $this->connection, $this->name ) );
        } catch ( Throwable $error ) {
            return false;
        } finally {
            if ( $can_suppress ) {
                $wpdb->suppress_errors( $previous );
            }
        }
    }

    public function release(): void {
        global $wpdb;
        if ( $this->released ) {
            return;
        }
        $this->released = true;
        $can_suppress = is_callable( array( $wpdb, 'suppress_errors' ) );
        $previous = $can_suppress ? $wpdb->suppress_errors( true ) : null;
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release only the original session's named lock, including shutdown cleanup.
            $wpdb->get_var( $wpdb->prepare( 'SELECT IF(CONNECTION_ID() = %d, RELEASE_LOCK(%s), 0)', $this->connection, $this->name ) );
        } catch ( Throwable $error ) {
            // A closed connection already releases its session-owned locks.
        } finally {
            if ( $can_suppress ) {
                $wpdb->suppress_errors( $previous );
            }
        }
    }
}
