<?php
/** Lightweight, server-rendered administration. No public endpoint is added. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Email_Countdown_Timer_Admin {
    private const OPTION = 'easy_countdown_timers';
    private static array $screens = array();
    private static ?array $pending = null;

    public static function add_screen( $hook ): void {
        if ( is_string( $hook ) && '' !== $hook ) {
            self::$screens[] = $hook;
        }
    }

    public static function enqueue( string $hook ): void {
        if ( ! in_array( $hook, self::$screens, true ) ) {
            return;
        }
        // The WordPress media UI belongs only on the timer editor, never the list/front end.
        if ( 'editor' === self::state()['view'] && current_user_can( 'upload_files' ) && function_exists( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
            wp_enqueue_script( 'easy-countdown-end-image', plugins_url( 'assets/end-image.js', EMAIL_COUNTDOWN_TIMER_FILE ), array( 'media-views', 'wp-a11y' ), EMAIL_COUNTDOWN_TIMER_VERSION, true );
        }
        wp_enqueue_style( 'email-countdown-timer-admin', plugins_url( 'assets/admin.css', EMAIL_COUNTDOWN_TIMER_FILE ), array(), EMAIL_COUNTDOWN_TIMER_VERSION );
        wp_enqueue_script( 'email-countdown-timer-admin', plugins_url( 'assets/admin.js', EMAIL_COUNTDOWN_TIMER_FILE ), array( 'wp-a11y' ), EMAIL_COUNTDOWN_TIMER_VERSION, true );
    }

    public static function url( array $args = array() ): string {
        return add_query_arg( array_merge( array( 'page' => 'ecd-timers' ), $args ), admin_url( 'admin.php' ) );
    }

    public static function defaults( bool $new_timer = true ): array {
        // New campaigns use the included font only when FreeType can render it.
        // Saved campaigns keep their original selection and missing values.
        $included = $new_timer && function_exists( 'imagettftext' ) && function_exists( 'imagettfbbox' )
            && null !== Email_Countdown_Timer_Config::fontPath( Email_Countdown_Timer_Fonts::BUNDLED_CHOICE );
        return array( 'deadline' => wp_date( 'Y-12-31\T23:59:59' ), 'tz' => $new_timer ? wp_timezone_string() : 'Europe/Warsaw',
            'bg' => '#FFFFFF', 'dc' => '#000000', 'lc' => '#666666', 'font' => $included ? Email_Countdown_Timer_Fonts::BUNDLED_CHOICE : '',
            'size_digit' => 40, 'size_label' => 12, 'fixed_width' => 0, 'hide_days' => 0,
            'label_d' => 'Days', 'label_h' => 'Hours', 'label_m' => 'Minutes', 'label_s' => 'Seconds', 'alt' => 'Countdown', 'expiry_image_id' => 0 );
    }

    /** Code-owned colors, shared by quick creation and the optional editor buttons. */
    public static function presets(): array {
        return array(
            'classic' => array( 'label' => __( 'Classic', 'easy-countdown' ), 'bg' => '#FFFFFF', 'dc' => '#111827', 'lc' => '#475569' ),
            'midnight' => array( 'label' => __( 'Midnight', 'easy-countdown' ), 'bg' => '#111827', 'dc' => '#F9FAFB', 'lc' => '#CBD5E1' ),
            'ocean' => array( 'label' => __( 'Ocean', 'easy-countdown' ), 'bg' => '#F8FAFC', 'dc' => '#1D4ED8', 'lc' => '#334155' ),
            'warm' => array( 'label' => __( 'Warm', 'easy-countdown' ), 'bg' => '#FFF7ED', 'dc' => '#9A3412', 'lc' => '#713F12' ),
        );
    }

    /** One native POST form for the timer list and dashboard; no JavaScript required. */
    public static function quick_create_form( string $prefix = 'ect-quick' ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $quick = self::defaults();
        ?>
        <form id="<?php echo esc_attr( $prefix ); ?>-create" method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'view' => 'new' ) ) ); ?>">
            <input type="hidden" name="ecd_action" value="email_countdown_timer_save">
            <input type="hidden" name="original_id" value="">
            <input type="hidden" name="creation_mode" value="quick">
            <?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
            <div class="ect-two-fields">
                <div class="ect-field"><label for="<?php echo esc_attr( $prefix ); ?>-id"><?php echo esc_html__( 'Timer ID', 'easy-countdown' ); ?></label>
                    <input id="<?php echo esc_attr( $prefix ); ?>-id" name="timer_id" class="widefat" type="text" required maxlength="200" aria-describedby="<?php echo esc_attr( $prefix ); ?>-id-help">
                    <p class="description" id="<?php echo esc_attr( $prefix ); ?>-id-help"><?php echo esc_html__( 'For example summer-sale. Use a unique ID; it cannot be changed after creation.', 'easy-countdown' ); ?></p></div>
                <div class="ect-field"><label for="<?php echo esc_attr( $prefix ); ?>-deadline"><?php echo esc_html__( 'Deadline', 'easy-countdown' ); ?></label>
                    <input id="<?php echo esc_attr( $prefix ); ?>-deadline" name="deadline" class="widefat" type="datetime-local" required step="1" value="<?php echo esc_attr( $quick['deadline'] ); ?>"></div>
                <div class="ect-field"><label for="<?php echo esc_attr( $prefix ); ?>-tz"><?php echo esc_html__( 'Time Zone', 'easy-countdown' ); ?></label>
                    <input id="<?php echo esc_attr( $prefix ); ?>-tz" name="tz" class="widefat" type="text" required value="<?php echo esc_attr( $quick['tz'] ); ?>"></div>
                <div class="ect-field"><label for="<?php echo esc_attr( $prefix ); ?>-preset"><?php echo esc_html__( 'Appearance preset', 'easy-countdown' ); ?></label>
                    <select id="<?php echo esc_attr( $prefix ); ?>-preset" name="appearance_preset" class="widefat" aria-describedby="<?php echo esc_attr( $prefix ); ?>-preset-help">
                        <?php foreach ( Email_Countdown_Timer_Admin::presets() as $email_countdown_timer_key => $email_countdown_timer_preset ) : ?>
                            <option value="<?php echo esc_attr( $email_countdown_timer_key ); ?>"><?php echo esc_html( $email_countdown_timer_preset['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select><p class="description" id="<?php echo esc_attr( $prefix ); ?>-preset-help"><?php echo esc_html__( 'Four high-contrast color combinations. New timers use included Lato when FreeType is available, otherwise bitmap.', 'easy-countdown' ); ?></p></div>
            </div>
            <div class="ect-inline"><button type="submit" class="button button-primary"><?php echo esc_html__( 'Create campaign', 'easy-countdown' ); ?></button>
                <a href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'view' => 'new' ) ) ); ?>"><?php echo esc_html__( 'Open full editor', 'easy-countdown' ); ?></a></div>
        </form>
        <?php
    }

    private static function timers(): array {
        $timers = get_option( self::OPTION, array() );
        return is_array( $timers ) ? array_filter( $timers, 'is_array' ) : array();
    }

    private static function posted( string $key, string $default = '' ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only called after capability/method/nonce checks; scalar values are bounded here, validated before writes, and escaped by the view.
        return substr( wp_unslash( Email_Countdown_Timer_Config::text( $_POST, $key, $default ) ), 0, 4096 );
    }

    public static function handle_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to manage timers.', 'easy-countdown' ), '', array( 'response' => 403 ) );
            return;
        }
        if ( 'POST' !== sanitize_text_field( wp_unslash( Email_Countdown_Timer_Config::text( $_SERVER, 'REQUEST_METHOD' ) ) ) ) {
            wp_die( esc_html__( 'Use the timer form to make this change.', 'easy-countdown' ), '', array( 'response' => 405 ) );
            return;
        }
        check_admin_referer( 'email_countdown_timer_admin' );
        self::$pending = null;
        $timers = get_option( self::OPTION, array() );
        if ( ! is_array( $timers ) ) {
            wp_die( esc_html__( 'Stored timer data is invalid. Restore a backup before making changes.', 'easy-countdown' ), '', array( 'response' => 500 ) );
            return;
        }
        $original = Email_Countdown_Timer_Config::id( self::posted( 'original_id' ) );
        $id = Email_Countdown_Timer_Config::id( self::posted( 'timer_id' ) );
        $action = self::posted( 'ecd_action' );
        $errors = array();
        $data = array();
        $defaults = self::defaults( '' === $original );
        $quick_create = 'email_countdown_timer_save' === $action && 'quick' === self::posted( 'creation_mode' );
        if ( $quick_create ) {
            $presets = self::presets();
            $preset = self::posted( 'appearance_preset' );
            if ( '' !== $original || ! isset( $presets[ $preset ] ) ) {
                $errors['form'] = __( 'Quick Create needs a new timer and one of the listed appearance presets. Review the fields below before saving.', 'easy-countdown' );
            } else {
                foreach ( array( 'bg', 'dc', 'lc' ) as $color ) {
                    $defaults[ $color ] = $presets[ $preset ][ $color ];
                }
            }
        }
        // Missing fields in old edit forms keep that campaign's interpretation.
        if ( '' !== $original ) {
            $defaults['tz'] = Email_Countdown_Timer_Config::text( $timers[ $original ] ?? array(), 'tz', 'Europe/Warsaw' );
        }
        foreach ( $defaults as $key => $default ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above; reject malformed typed input before normalization.
            if ( isset( $_POST[ $key ] ) && ! is_string( $_POST[ $key ] ) ) {
                $errors[ $key ] = __( 'Enter a single value for this field.', 'easy-countdown' );
            }
            $data[ $key ] = self::posted( $key, 'deadline' === $key ? '' : (string) $default );
        }
        $data['hide_days'] = '1' === self::posted( 'hide_days' ) ? 1 : 0;
        if ( '' === $original && '' === trim( $data['tz'] ) ) {
            $data['tz'] = $defaults['tz'];
        }
        // Preserve old forms and existing metadata when the field is absent.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        if ( ! $quick_create && ! array_key_exists( 'alt', $_POST ) ) {
            unset( $data['alt'] );
            if ( isset( $timers[ $original ]['alt'] ) ) {
                $data['alt'] = $timers[ $original ]['alt'];
            }
        }

        // A pre-update edit form must not erase the new optional selection.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Capability, POST and nonce were checked above.
        if ( ! array_key_exists( 'expiry_image_id', $_POST ) ) {
            unset( $data['expiry_image_id'] );
            if ( isset( $timers[ $original ]['expiry_image_id'] ) ) {
                $data['expiry_image_id'] = $timers[ $original ]['expiry_image_id'];
            }
        }

        if ( 'email_countdown_timer_delete' === $action ) {
            if ( '' === $id || ! isset( $timers[ $id ] ) || '1' !== self::posted( 'confirm_delete' ) ) {
                wp_die( esc_html__( 'Confirm deletion of an existing timer.', 'easy-countdown' ), '', array( 'response' => 400 ) );
                return;
            }
            unset( $timers[ $id ] );
        } elseif ( 'email_countdown_timer_save' === $action ) {
            if ( '' === $id ) {
                $errors['timer_id'] = __( 'Enter a valid timer ID (maximum 200 bytes).', 'easy-countdown' );
            } elseif ( '' === $original && isset( $timers[ $id ] ) ) {
                $errors['timer_id'] = __( 'This ID already exists. Edit the existing timer or choose a different ID.', 'easy-countdown' );
            } elseif ( '' !== $original && ( $original !== $id || ! isset( $timers[ $original ] ) ) ) {
                $errors['timer_id'] = __( 'The original timer no longer exists or its ID was changed. Return to Timers.', 'easy-countdown' );
            }
            foreach ( array( 'size_digit' => array( 1, 200 ), 'size_label' => array( 1, 100 ), 'fixed_width' => array( 0, 4000 ) ) as $key => $range ) {
                $number = filter_var( $data[ $key ], FILTER_VALIDATE_INT );
                if ( false === $number || $number < $range[0] || $number > $range[1] ) {
                    /* translators: 1: minimum value, 2: maximum value. */
                    $errors[ $key ] = sprintf( __( 'Enter a whole number from %1$d to %2$d.', 'easy-countdown' ), $range[0], $range[1] );
                }
            }
            foreach ( array( 'bg', 'dc', 'lc' ) as $key ) {
                if ( ! sanitize_hex_color( $data[ $key ] ) ) {
                    $errors[ $key ] = __( 'Enter a HEX color, for example #135E96.', 'easy-countdown' );
                }
            }
            foreach ( array( 'label_d', 'label_h', 'label_m', 'label_s' ) as $key ) {
                if ( strlen( $data[ $key ] ) > 256 ) {
                    $errors[ $key ] = __( 'Use at most 256 bytes for this label.', 'easy-countdown' );
                }
            }
            if ( isset( $data['alt'] ) && strlen( $data['alt'] ) > 1000 ) {
                $errors['alt'] = __( 'Use at most 1000 bytes for alternative text.', 'easy-countdown' );
            }
            try {
                new DateTimeZone( '' === $data['tz'] ? 'Europe/Warsaw' : $data['tz'] );
            } catch ( Exception $exception ) {
                $errors['tz'] = __( 'Enter a valid time zone, for example Europe/Warsaw.', 'easy-countdown' );
            }
            if ( ! isset( $errors['tz'] ) ) {
                try {
                    Email_Countdown_Timer_Config::deadline( $data );
                } catch ( InvalidArgumentException $exception ) {
                    $errors['deadline'] = $exception->getMessage();
                }
            }
            if ( array_key_exists( 'expiry_image_id', $data ) ) {
                try {
                    $end_id = Email_Countdown_Timer_End_Image::id( $data['expiry_image_id'] );
                    if ( ! Email_Countdown_Timer_End_Image::validate_selection( $end_id ) ) {
                        $errors['expiry_image_id'] = __( 'Choose an accessible local JPEG, PNG, GIF or WebP from the Media Library (up to 4 MiB and 4 million pixels), or use 0 for no end image.', 'easy-countdown' );
                    }
                } catch ( InvalidArgumentException $exception ) {
                    $errors['expiry_image_id'] = __( 'Use a whole attachment ID, or 0 for no end image.', 'easy-countdown' );
                }
            }
            try {
                $normalized = Email_Countdown_Timer_Config::normalize( $data );
            } catch ( InvalidArgumentException $exception ) {
                if ( empty( $errors ) ) {
                    $errors['font'] = $exception->getMessage();
                }
            }
            if ( empty( $errors ) ) {
                $errors = self::preflight( $normalized );
            }
            if ( ! empty( $errors ) ) {
                self::$pending = array( 'id' => self::posted( 'timer_id' ), 'original' => $original, 'data' => $data, 'errors' => $errors );
                return;
            }
            $timers[ $id ] = $normalized;
        } else {
            return;
        }
        if ( ! update_option( self::OPTION, $timers, false ) && get_option( self::OPTION, array() ) !== $timers ) {
            wp_die( esc_html__( 'The database could not save this change. No success has been recorded.', 'easy-countdown' ), '', array( 'response' => 500 ) );
            return;
        }
        foreach ( array( 'png', 'gif', 'webp' ) as $format ) {
            delete_transient( 'ecd_v1211_' . hash( 'sha256', $id . '|' . $format ) );
        }
        $args = 'email_countdown_timer_delete' === $action
            ? array( 'status' => 'deleted' ) : array( 'edit' => $id, 'status' => 'saved' );
        wp_safe_redirect( self::url( $args ) );
        exit;
    }

    /** Only save-time checks: do not retroactively invalidate existing public images. */
    public static function preflight( array $config ): array {
        // Missing GD is surfaced in the panel. Settings remain editable on a temporarily
        // unavailable backend; never claim that geometry was verified in that case.
        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            return array();
        }
        $font = Email_Countdown_Timer_Config::fontPath( $config['font'] );
        $ttf = null !== $font && function_exists( 'imagettfbbox' ) && function_exists( 'imagettftext' );
        $errors = array();
        if ( ! $ttf ) {
            foreach ( array( 'label_d', 'label_h', 'label_m', 'label_s' ) as $field ) {
                if ( preg_match( '/[^\x20-\x7e]/', $config[ $field ] ) ) {
                    $errors[ $field ] = __( 'The active bitmap fallback supports printable ASCII labels only. Select an available TTF/OTF with the required characters.', 'easy-countdown' );
                }
            }
        }
        try {
            ( new Email_Countdown_Timer_Renderer() )->measure( $config, Email_Countdown_Timer_Config::deadline( $config ), time() );
        } catch ( Throwable $error ) {
            $errors['font'] = __( 'This font, text and size combination cannot be rendered. Check the font and reduce text size or width. Maximum: 4000 by 1000 pixels and 400000 pixels total.', 'easy-countdown' );
        }
        return $errors;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $email_countdown_timer_ui = self::state();
        require __DIR__ . '/admin-view.php';
    }

    public static function state(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation; capability checked in render, identifiers are normalized, and output is escaped.
        $query = array_intersect_key( $_GET, array_flip( array( 'edit', 'view', 's', 'paged', 'status' ) ) );
        $timers = self::timers();
        $edit = Email_Countdown_Timer_Config::id( wp_unslash( Email_Countdown_Timer_Config::text( $query, 'edit' ) ) );
        $pending = self::$pending;
        $view = $pending || '' !== $edit || 'new' === Email_Countdown_Timer_Config::text( $query, 'view' ) ? 'editor' : 'list';
        $original = $pending ? $pending['original'] : $edit;
        $saved = '' !== $original && isset( $timers[ $original ] ) ? $timers[ $original ] : null;
        $data = array_replace( self::defaults( null === $saved ), $saved ?? array() );
        $errors = $pending['errors'] ?? array();
        if ( '' !== $original && null === $saved ) {
            $errors['timer_id'] = __( 'This timer no longer exists. Return to Timers to create a new one.', 'easy-countdown' );
        }
        if ( $saved ) {
            try {
                Email_Countdown_Timer_Config::normalize( $saved );
            } catch ( InvalidArgumentException $exception ) {
                $errors['form'] = __( 'The saved configuration contains invalid values. Review the fields before saving.', 'easy-countdown' );
            }
        }
        if ( $pending ) {
            $data = $pending['data'];
        }
        $search = sanitize_text_field( wp_unslash( Email_Countdown_Timer_Config::text( $query, 's' ) ) );
        $filtered = '' === $search ? $timers : array_filter( $timers, static fn( $id ) => false !== stripos( (string) $id, $search ), ARRAY_FILTER_USE_KEY );
        $pages = max( 1, (int) ceil( count( $filtered ) / 25 ) );
        $page = min( $pages, max( 1, (int) Email_Countdown_Timer_Config::text( $query, 'paged', '1' ) ) );
        return array( 'view' => $view, 'id' => $pending ? $pending['id'] : $edit, 'original' => $original,
            'data' => $data, 'errors' => $errors, 'saved' => $saved, 'search' => $search, 'page' => $page, 'pages' => $pages,
            'total' => count( $filtered ), 'timers' => array_slice( $filtered, ( $page - 1 ) * 25, 25, true ),
            'status' => Email_Countdown_Timer_Config::text( $query, 'status' ) );
    }

    public static function value( array $ui, string $key ): string {
        return Email_Countdown_Timer_Config::text( $ui['data'], $key );
    }

    public static function input( array $ui, string $key, string $label, string $type = 'text', string $help = '', array $attributes = array() ): void {
        $value = 'timer_id' === $key ? $ui['id'] : self::value( $ui, $key );
        // Native number inputs discard malformed text; retain it after a server error.
        if ( 'number' === $type && isset( $ui['errors'][ $key ] ) && false === filter_var( $value, FILTER_VALIDATE_INT ) ) {
            $type = 'text';
        }
        ?>
        <div class="ect-field">
            <label for="ect-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
            <input type="<?php echo esc_attr( $type ); ?>" id="ect-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"
                aria-describedby="ect-help-<?php echo esc_attr( $key ); ?> ect-error-<?php echo esc_attr( $key ); ?>"
                <?php if ( isset( $ui['errors'][ $key ] ) ) : ?>aria-invalid="true"<?php endif; ?>
                <?php foreach ( $attributes as $attribute => $setting ) : ?>
                    <?php // Attributes are a fixed code-owned allowlist, never request keys. ?>
                    <?php if ( in_array( $attribute, array( 'min', 'max', 'step', 'maxlength', 'required', 'readonly', 'pattern' ), true ) ) : ?>
                        <?php echo esc_attr( $attribute ); ?>="<?php echo esc_attr( $setting ); ?>"
                    <?php endif; ?>
                <?php endforeach; ?>>
            <p class="description" id="ect-help-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $help ); ?></p>
            <p class="ect-error" id="ect-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $ui['errors'][ $key ] ?? '' ); ?></p>
        </div>
        <?php
    }

    public static function deadline_text( array $data ): string {
        try {
            $timestamp = Email_Countdown_Timer_Config::deadline( $data );
            $data['tz'] = Email_Countdown_Timer_Config::text( $data, 'tz', 'Europe/Warsaw' );
            return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( $data['tz'] ?: 'Europe/Warsaw' ) )->format( 'Y-m-d H:i:s T' ) . ' (' . ( $data['tz'] ?: 'Europe/Warsaw' ) . ')';
        } catch ( Exception $exception ) {
            return __( 'Invalid deadline or time zone', 'easy-countdown' );
        }
    }
}
