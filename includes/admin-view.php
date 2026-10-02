<?php
/** Accessible server-rendered admin view; included by Email_Countdown_Timer_Admin. */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
?>
<div class="wrap email-countdown-admin" data-ect-admin>
    <div class="ect-header">
        <div><h1><?php echo esc_html__( 'Easy Countdown', 'easy-countdown' ); ?></h1>
            <p><?php echo esc_html__( 'Create countdown images for your website and email campaigns.', 'easy-countdown' ); ?></p></div>
        <nav aria-label="<?php echo esc_attr__( 'Countdown navigation', 'easy-countdown' ); ?>">
            <a href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>"><?php echo esc_html__( 'Timers', 'easy-countdown' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=email-countdown-timer-data' ) ); ?>"><?php echo esc_html__( 'Data Settings', 'easy-countdown' ); ?></a>
        </nav>
    </div>
    <?php if ( in_array( $email_countdown_timer_ui['status'], array( 'saved', 'deleted' ), true ) && empty( $email_countdown_timer_ui['errors'] ) ) : ?>
        <div class="notice notice-success"><p><?php echo esc_html( 'deleted' === $email_countdown_timer_ui['status'] ? __( 'Timer deleted.', 'easy-countdown' ) : __( 'Timer saved.', 'easy-countdown' ) ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! function_exists( 'imagecreatetruecolor' ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html__( 'GD is unavailable. Settings can be saved, but image generation and geometry validation are unavailable until GD is enabled.', 'easy-countdown' ); ?></p></div>
    <?php elseif ( 'editor' === $email_countdown_timer_ui['view'] && ( ! Email_Countdown_Timer_Config::fontPath( Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'font' ) ) || ! function_exists( 'imagettftext' ) || ! function_exists( 'imagettfbbox' ) ) ) : ?>
        <div class="notice notice-info"><p><?php echo esc_html__( 'The active renderer uses the bitmap fallback: fixed text sizes and printable ASCII labels. Select an available local TTF/OTF for scalable or multilingual text.', 'easy-countdown' ); ?></p></div>
    <?php endif; ?>
    <?php if ( 'list' === $email_countdown_timer_ui['view'] ) : ?>
        <section class="ect-card" aria-labelledby="ect-list-title">
            <div class="ect-heading-row"><h2 id="ect-list-title"><?php echo esc_html__( 'Your Timers', 'easy-countdown' ); ?></h2>
                <a class="button button-primary" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'view' => 'new' ) ) ); ?>"><?php echo esc_html__( 'Create Timer', 'easy-countdown' ); ?></a></div>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ect-search">
                <input type="hidden" name="page" value="ecd-timers">
                <label for="ect-search"><?php echo esc_html__( 'Search timer IDs', 'easy-countdown' ); ?></label>
                <div class="ect-inline"><input id="ect-search" name="s" type="search" value="<?php echo esc_attr( $email_countdown_timer_ui['search'] ); ?>"><button class="button" type="submit"><?php echo esc_html__( 'Search', 'easy-countdown' ); ?></button></div>
            </form>
            <?php if ( empty( $email_countdown_timer_ui['timers'] ) ) : ?>
                <p class="ect-empty"><?php echo esc_html( '' === $email_countdown_timer_ui['search'] ? __( 'No timers yet. Create your first timer to get started.', 'easy-countdown' ) : __( 'No timers match this search.', 'easy-countdown' ) ); ?></p>
            <?php else : ?>
                <div class="ect-table-wrap" role="region" aria-labelledby="ect-list-title" tabindex="0">
                    <table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html__( 'Saved timers, deadlines and actions', 'easy-countdown' ); ?></caption>
                        <thead><tr><th scope="col"><?php echo esc_html__( 'Timer ID', 'easy-countdown' ); ?></th><th scope="col"><?php echo esc_html__( 'Deadline / Time Zone', 'easy-countdown' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'easy-countdown' ); ?></th><th scope="col"><?php echo esc_html__( 'Actions', 'easy-countdown' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $email_countdown_timer_ui['timers'] as $email_countdown_timer_id => $email_countdown_timer_timer ) : ?>
                            <?php
                            try {
                                $email_countdown_timer_status = Email_Countdown_Timer_Config::deadline( $email_countdown_timer_timer ) > time() ? __( 'Active', 'easy-countdown' ) : __( 'Expired', 'easy-countdown' );
                            } catch ( Exception $email_countdown_timer_exception ) {
                                $email_countdown_timer_status = __( 'Needs attention', 'easy-countdown' );
                            }
                            $email_countdown_timer_edit_url = Email_Countdown_Timer_Admin::url( array( 'edit' => $email_countdown_timer_id ) );
                            ?>
                            <tr><th scope="row"><code><?php echo esc_html( $email_countdown_timer_id ); ?></code></th>
                                <td><?php echo esc_html( Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_timer ) ); ?></td>
                                <td><span class="ect-badge"><?php echo esc_html( $email_countdown_timer_status ); ?></span></td>
                                <td><div class="ect-row-actions"><a class="button" href="<?php echo esc_url( $email_countdown_timer_edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Edit %s', 'easy-countdown' ), $email_countdown_timer_id ) ); ?>"><?php echo esc_html__( 'Edit', 'easy-countdown' ); ?></a>
                                    <a href="<?php echo esc_url( $email_countdown_timer_edit_url . '#ect-embed' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Get embed code for %s', 'easy-countdown' ), $email_countdown_timer_id ) ); ?>"><?php echo esc_html__( 'Get Embed Code', 'easy-countdown' ); ?></a></div></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <nav class="ect-pagination" aria-label="<?php echo esc_attr__( 'Timer pages', 'easy-countdown' ); ?>">
                    <span><?php echo esc_html( sprintf( /* translators: 1: current page, 2: total pages. */ __( 'Page %1$d of %2$d', 'easy-countdown' ), $email_countdown_timer_ui['page'], $email_countdown_timer_ui['pages'] ) ); ?></span>
                    <?php if ( $email_countdown_timer_ui['page'] > 1 ) : ?><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 's' => $email_countdown_timer_ui['search'], 'paged' => $email_countdown_timer_ui['page'] - 1 ) ) ); ?>"><?php echo esc_html__( 'Previous', 'easy-countdown' ); ?></a><?php endif; ?>
                    <?php if ( $email_countdown_timer_ui['page'] < $email_countdown_timer_ui['pages'] ) : ?><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 's' => $email_countdown_timer_ui['search'], 'paged' => $email_countdown_timer_ui['page'] + 1 ) ) ); ?>"><?php echo esc_html__( 'Next', 'easy-countdown' ); ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    <?php else : ?>
        <div class="ect-heading-row"><h2><?php echo esc_html( '' === $email_countdown_timer_ui['original'] ? __( 'New Timer', 'easy-countdown' ) : sprintf( /* translators: %s: timer ID. */ __( 'Edit: %s', 'easy-countdown' ), $email_countdown_timer_ui['original'] ) ); ?></h2>
            <span id="ect-save-state" class="ect-badge" data-dirty="<?php echo esc_attr__( 'Unsaved changes', 'easy-countdown' ); ?>" data-clean="<?php echo esc_attr__( 'Saved configuration', 'easy-countdown' ); ?>"><?php echo esc_html( $email_countdown_timer_ui['saved'] && empty( $email_countdown_timer_ui['errors'] ) ? __( 'Saved configuration', 'easy-countdown' ) : __( 'Not saved', 'easy-countdown' ) ); ?></span></div>
        <div id="ect-errors" class="notice notice-error" tabindex="-1" <?php if ( empty( $email_countdown_timer_ui['errors'] ) ) : ?>hidden<?php endif; ?>>
            <h3><?php echo esc_html__( 'Review the following fields. Your changes have not been saved.', 'easy-countdown' ); ?></h3>
            <ul><?php foreach ( $email_countdown_timer_ui['errors'] as $email_countdown_timer_key => $email_countdown_timer_error ) : ?><li><a href="#ect-<?php echo esc_attr( 'form' === $email_countdown_timer_key ? 'timer_id' : $email_countdown_timer_key ); ?>"><?php echo esc_html( $email_countdown_timer_error ); ?></a></li><?php endforeach; ?></ul>
        </div>
        <div class="ect-editor-grid">
            <div>
                <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( '' !== $email_countdown_timer_ui['original'] ? array( 'edit' => $email_countdown_timer_ui['original'] ) : array( 'view' => 'new' ) ) ); ?>" id="ect-editor">
                    <input type="hidden" name="ecd_action" value="email_countdown_timer_save">
                    <input type="hidden" name="original_id" value="<?php echo esc_attr( $email_countdown_timer_ui['original'] ); ?>">
                    <?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                    <section class="ect-card" aria-labelledby="ect-schedule-title"><h3 id="ect-schedule-title"><?php echo esc_html__( 'Schedule', 'easy-countdown' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'timer_id', __( 'Timer ID', 'easy-countdown' ), 'text', __( 'Used in embed URLs. It cannot be changed after creation.', 'easy-countdown' ), array_merge( array( 'required' => 'required', 'maxlength' => '200' ), '' !== $email_countdown_timer_ui['original'] ? array( 'readonly' => 'readonly' ) : array() ) ); ?>
                        <?php $email_countdown_timer_ui['data']['deadline'] = str_replace( ' ', 'T', Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'deadline' ) ); ?>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'deadline', __( 'Deadline', 'easy-countdown' ), isset( $email_countdown_timer_ui['errors']['deadline'] ) ? 'text' : 'datetime-local', __( 'Date and time in the time zone below, including seconds.', 'easy-countdown' ), array( 'required' => 'required', 'step' => '1' ) ); ?>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'tz', __( 'Time Zone', 'easy-countdown' ), 'text', __( 'For example Europe/Warsaw, UTC or America/New_York. Existing values are preserved.', 'easy-countdown' ) ); ?>
                        <label class="ect-check"><input id="ect-hide_days" aria-describedby="ect-error-hide_days" type="checkbox" name="hide_days" value="1" <?php checked( ! empty( $email_countdown_timer_ui['data']['hide_days'] ) ); ?>><?php echo esc_html__( 'Hide days when fewer than 24 hours remain', 'easy-countdown' ); ?></label>
                        <p class="ect-error" id="ect-error-hide_days"><?php echo esc_html( $email_countdown_timer_ui['errors']['hide_days'] ?? '' ); ?></p>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-appearance-title"><h3 id="ect-appearance-title"><?php echo esc_html__( 'Appearance', 'easy-countdown' ); ?></h3>
                        <fieldset><legend><?php echo esc_html__( 'Colors', 'easy-countdown' ); ?></legend><div class="ect-colors">
                        <?php foreach ( array( 'bg' => __( 'Background', 'easy-countdown' ), 'dc' => __( 'Digits', 'easy-countdown' ), 'lc' => __( 'Labels', 'easy-countdown' ) ) as $email_countdown_timer_key => $email_countdown_timer_label ) : ?>
                            <div><?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, $email_countdown_timer_key, $email_countdown_timer_label, 'text', __( 'HEX color', 'easy-countdown' ), array( 'pattern' => '#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})', 'required' => 'required' ) ); ?>
                                <label hidden data-ect-picker><?php echo esc_html( sprintf( /* translators: %s: color role. */ __( 'Choose %s color', 'easy-countdown' ), $email_countdown_timer_label ) ); ?><input type="color" data-color-for="ect-<?php echo esc_attr( $email_countdown_timer_key ); ?>" value="#000000"></label></div>
                        <?php endforeach; ?>
                        </div></fieldset>
                        <p id="ect-contrast" class="description" hidden data-template="<?php echo esc_attr__( 'Contrast on background: digits {digits}:1; labels {labels}:1. Aim for at least 4.5:1. Colors are not changed automatically.', 'easy-countdown' ); ?>"></p>
                        <details class="ect-disclosure" data-ect-disclosure open><summary><?php echo esc_html__( 'Typography and Size', 'easy-countdown' ); ?></summary><div>
                            <div class="ect-field"><label for="ect-font"><?php echo esc_html__( 'Font', 'easy-countdown' ); ?></label>
                                <?php $email_countdown_timer_fonts = Email_Countdown_Timer_Config::fonts(); $email_countdown_timer_font = Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'font' ); ?>
                                <select id="ect-font" name="font" aria-describedby="ect-help-font ect-error-font" <?php if ( isset( $email_countdown_timer_ui['errors']['font'] ) ) : ?>aria-invalid="true"<?php endif; ?>><option value=""><?php echo esc_html__( 'Default bitmap font', 'easy-countdown' ); ?></option>
                                    <?php if ( '' !== $email_countdown_timer_font && ! in_array( $email_countdown_timer_font, $email_countdown_timer_fonts, true ) ) : ?><option value="<?php echo esc_attr( $email_countdown_timer_font ); ?>" selected><?php echo esc_html( sprintf( /* translators: %s: missing font filename. */ __( 'Unavailable: %s (keep saved value)', 'easy-countdown' ), $email_countdown_timer_font ) ); ?></option><?php endif; ?>
                                    <?php foreach ( $email_countdown_timer_fonts as $email_countdown_timer_file ) : ?><option value="<?php echo esc_attr( $email_countdown_timer_file ); ?>" <?php selected( $email_countdown_timer_font, $email_countdown_timer_file ); ?>><?php echo esc_html( Email_Countdown_Timer_Fonts::BUNDLED_FILE === $email_countdown_timer_file ? __( 'Lato Regular (included)', 'easy-countdown' ) : $email_countdown_timer_file ); ?></option><?php endforeach; ?>
                                </select><p id="ect-help-font" class="description"><?php echo esc_html__( 'Lato Regular is included locally; select it and save to use scalable text. FreeType is required. Keep your own licensed TTF/OTF files in the persistent directory shown in Data Settings. Bitmap fallback has fixed sizes and printable ASCII labels only.', 'easy-countdown' ); ?></p><p class="ect-error" id="ect-error-font"><?php echo esc_html( $email_countdown_timer_ui['errors']['font'] ?? '' ); ?></p></div>
                            <div class="ect-two-fields">
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'size_digit', __( 'Digit Size (px)', 'easy-countdown' ), 'number', __( 'Applies to a local TTF/OTF font.', 'easy-countdown' ), array( 'min' => '1', 'max' => '200', 'required' => 'required' ) ); ?>
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'size_label', __( 'Label Size (px)', 'easy-countdown' ), 'number', __( 'Applies to a local TTF/OTF font.', 'easy-countdown' ), array( 'min' => '1', 'max' => '100', 'required' => 'required' ) ); ?>
                            </div>
                            <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'fixed_width', __( 'Minimum Image Width (px)', 'easy-countdown' ), 'number', __( '0 means automatic. This sets a minimum width, not image scaling. Height follows content with padding.', 'easy-countdown' ), array( 'min' => '0', 'max' => '4000', 'required' => 'required' ) ); ?>
                        </div></details>
                        <details class="ect-disclosure" data-ect-disclosure open><summary><?php echo esc_html__( 'Custom Labels', 'easy-countdown' ); ?></summary><div class="ect-two-fields">
                            <?php foreach ( array( 'label_d' => __( 'Days', 'easy-countdown' ), 'label_h' => __( 'Hours', 'easy-countdown' ), 'label_m' => __( 'Minutes', 'easy-countdown' ), 'label_s' => __( 'Seconds', 'easy-countdown' ) ) as $email_countdown_timer_key => $email_countdown_timer_label ) : ?>
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, $email_countdown_timer_key, $email_countdown_timer_label, 'text', __( 'An empty label is allowed. Maximum 256 bytes.', 'easy-countdown' ) ); ?>
                            <?php endforeach; ?>
                        </div></details>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-after-title"><h3 id="ect-after-title"><?php echo esc_html__( 'After Countdown', 'easy-countdown' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'expiry_image_id', __( 'End image attachment ID', 'easy-countdown' ), 'number', __( 'Optional. Choose an image from this site’s Media Library, or enter its attachment ID. Use 0 to keep the zero countdown. Local JPEG, PNG, GIF or WebP only, up to 4 MiB / 4 million pixels. Animated source files use their first frame.', 'easy-countdown' ), array( 'min' => '0', 'step' => '1' ) ); ?>
                        <div class="ect-inline" data-ect-end-tools hidden>
                            <button type="button" class="button" id="ect-end-choose" data-title="<?php echo esc_attr__( 'Choose an End Image', 'easy-countdown' ); ?>" data-select="<?php echo esc_attr__( 'Use This Image', 'easy-countdown' ); ?>"><?php echo esc_html__( 'Choose Image', 'easy-countdown' ); ?></button>
                            <button type="button" class="button" id="ect-end-remove"><?php echo esc_html__( 'Remove End Image', 'easy-countdown' ); ?></button>
                        </div>
                        <p id="ect-end-status" role="status" data-selected="<?php echo esc_attr__( 'Image selected. Save the timer to apply it.', 'easy-countdown' ); ?>" data-removed="<?php echo esc_attr__( 'End image removed. Save the timer to apply this change.', 'easy-countdown' ); ?>"></p>
                        <p class="description"><?php echo esc_html__( 'The selected image becomes public through this timer URL after the deadline. It is fitted to the timer canvas without stretching; use a similar aspect ratio for a readable result. New expired requests and GIF frames crossing the deadline show it. Email caches can retain an earlier image; a GIF already frozen after its 60 seconds cannot refresh itself. Keep the deadline in text and choose alt text suitable for both states.', 'easy-countdown' ); ?></p>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-accessibility-title"><h3 id="ect-accessibility-title"><?php echo esc_html__( 'Accessibility', 'easy-countdown' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'alt', __( 'Image alternative text (alt)', 'easy-countdown' ), 'text', __( 'Describe the timer purpose, not its changing seconds. Used by the website shortcode and new Email HTML. Leave empty only when nearby text provides the same information. Save, then copy updated HTML; already sent emails cannot be changed. Maximum 1000 bytes.', 'easy-countdown' ) ); ?>
                    </section>
                    <div class="ect-save-row"><button type="submit" class="button button-primary"><?php echo esc_html( '' !== $email_countdown_timer_ui['original'] ? __( 'Save Changes', 'easy-countdown' ) : __( 'Create Timer', 'easy-countdown' ) ); ?></button><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>"><?php echo esc_html__( 'Back to Timers', 'easy-countdown' ); ?></a></div>
                </form>
                <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                    <details class="ect-card ect-danger"><summary><?php echo esc_html__( 'Delete Timer', 'easy-countdown' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'edit' => $email_countdown_timer_ui['original'] ) ) ); ?>" id="ect-delete">
                            <input type="hidden" name="ecd_action" value="email_countdown_timer_delete"><input type="hidden" name="timer_id" value="<?php echo esc_attr( $email_countdown_timer_ui['original'] ); ?>"><?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                            <p><?php echo esc_html( sprintf( /* translators: %s: timer ID. */ __( 'Permanently delete %s? Existing emails and pages using this timer will stop receiving countdown images.', 'easy-countdown' ), $email_countdown_timer_ui['original'] ) ); ?></p>
                            <label class="ect-check"><input name="confirm_delete" type="checkbox" value="1" required><?php echo esc_html__( 'I understand this cannot be undone.', 'easy-countdown' ); ?></label>
                            <button type="submit" class="button ect-delete-button"><?php echo esc_html__( 'Delete permanently', 'easy-countdown' ); ?></button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
            <aside aria-label="<?php echo esc_attr__( 'Preview and embedding', 'easy-countdown' ); ?>">
                <section class="ect-card"><h3><?php echo esc_html__( 'Saved Preview', 'easy-countdown' ); ?></h3>
                    <p id="ect-preview-help"><?php echo esc_html__( 'Shows the saved configuration, not unsaved changes. Save to update.', 'easy-countdown' ); ?></p>
                    <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                        <?php $email_countdown_timer_image_url = add_query_arg( array( 'ecd_action' => 'render', 'ecd' => $email_countdown_timer_ui['original'], 'mode' => 'email' ), home_url( '/' ) ); $email_countdown_timer_static_url = add_query_arg( 'mode', 'static', $email_countdown_timer_image_url ); ?>
                        <div class="ect-preview"><img id="ect-preview" src="<?php echo esc_url( $email_countdown_timer_static_url ); ?>" data-static="<?php echo esc_url( $email_countdown_timer_static_url ); ?>" data-animated="<?php echo esc_url( $email_countdown_timer_image_url ); ?>" alt="<?php echo esc_attr( Email_Countdown_Timer_Config::alt( $email_countdown_timer_ui['saved'], __( 'Saved countdown image. The absolute deadline is provided below.', 'easy-countdown' ) ) ); ?>"></div>
                        <p><strong><?php echo esc_html__( 'Ends:', 'easy-countdown' ); ?></strong> <?php echo esc_html( Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_ui['saved'] ) ); ?></p>
                        <?php if ( class_exists( 'Imagick' ) ) : ?><button type="button" class="button" id="ect-animation" hidden data-play="<?php echo esc_attr__( 'Preview Animation', 'easy-countdown' ); ?>" data-stop="<?php echo esc_attr__( 'Stop Preview', 'easy-countdown' ); ?>"><?php echo esc_html__( 'Preview Animation', 'easy-countdown' ); ?></button><?php else : ?><p class="description"><?php echo esc_html__( 'Imagick is unavailable. GIF output is static.', 'easy-countdown' ); ?></p><?php endif; ?>
                        <p id="ect-image-error" class="ect-error" hidden><?php echo esc_html__( 'Preview could not load. Check GD, font support and the saved image URL.', 'easy-countdown' ); ?></p>
                    <?php else : ?><p class="ect-empty"><?php echo esc_html__( 'Create this timer to see its image and embed codes.', 'easy-countdown' ); ?></p><?php endif; ?>
                </section>
                <section class="ect-card" id="ect-embed" tabindex="-1"><h3><?php echo esc_html__( 'Embed Codes', 'easy-countdown' ); ?></h3>
                    <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                        <?php
                        $email_countdown_timer_deadline = Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_ui['saved'] );
                        $email_countdown_timer_email_html = '<img class="email-countdown-timer-image" loading="eager" data-no-lazy="1" referrerpolicy="no-referrer" src="' . esc_url( $email_countdown_timer_image_url ) . '" alt="' . esc_attr( Email_Countdown_Timer_Config::alt( $email_countdown_timer_ui['saved'], sprintf( /* translators: %s: absolute deadline and time zone. */ __( 'Countdown ends %s', 'easy-countdown' ), $email_countdown_timer_deadline ) ) ) . '" style="display:block;max-width:100%;height:auto;border:0;">' . "\n<p>" . esc_html( sprintf( /* translators: %s: absolute deadline and time zone. */ __( 'Ends: %s', 'easy-countdown' ), $email_countdown_timer_deadline ) ) . '</p>';
                        $email_countdown_timer_email_html = Email_Countdown_Timer_Embed::reserve( $email_countdown_timer_email_html, $email_countdown_timer_ui['saved'], true );
                        $email_countdown_timer_shortcode = '[ecd_timer id="' . esc_attr( $email_countdown_timer_ui['original'] ) . '"]';
                        ?>
                        <div data-ect-embed-tools hidden><label for="ect-embed-format"><?php echo esc_html__( 'Embed format', 'easy-countdown' ); ?></label><select id="ect-embed-format"><option value="url"><?php echo esc_html__( 'Image URL', 'easy-countdown' ); ?></option><option value="html"><?php echo esc_html__( 'Email HTML', 'easy-countdown' ); ?></option><option value="shortcode"><?php echo esc_html__( 'Website Shortcode', 'easy-countdown' ); ?></option></select></div>
                        <label for="ect-embed-code" id="ect-embed-label"><?php echo esc_html__( 'Image URL', 'easy-countdown' ); ?></label>
                        <textarea id="ect-embed-code" rows="4" readonly spellcheck="false"><?php echo esc_textarea( $email_countdown_timer_image_url ); ?></textarea>
                        <button type="button" class="button" id="ect-copy" hidden data-success="<?php echo esc_attr__( 'Copied to clipboard.', 'easy-countdown' ); ?>" data-fallback="<?php echo esc_attr__( 'Copy was unavailable. The code is selected; use your keyboard or device copy command.', 'easy-countdown' ); ?>"><?php echo esc_html__( 'Copy Code', 'easy-countdown' ); ?></button><p id="ect-copy-result" class="description"></p>
                        <details data-ect-manual open><summary><?php echo esc_html__( 'All formats (manual copy)', 'easy-countdown' ); ?></summary>
                            <label for="ect-manual-html"><?php echo esc_html__( 'Email HTML', 'easy-countdown' ); ?></label><textarea id="ect-manual-html" rows="6" readonly><?php echo esc_textarea( $email_countdown_timer_email_html ); ?></textarea>
                            <label for="ect-manual-shortcode"><?php echo esc_html__( 'Website Shortcode', 'easy-countdown' ); ?></label><textarea id="ect-manual-shortcode" rows="2" readonly><?php echo esc_textarea( $email_countdown_timer_shortcode ); ?></textarea>
                        </details>
                        <p class="description"><?php echo esc_html__( 'Email clients may cache, prefetch or block images. Include the absolute deadline as text in your message.', 'easy-countdown' ); ?></p>
                    <?php else : ?><p><?php echo esc_html__( 'Embed codes are available after the first successful save.', 'easy-countdown' ); ?></p><?php endif; ?>
                </section>
            </aside>
        </div>
        <dialog id="ect-delete-dialog" aria-labelledby="ect-dialog-title" aria-describedby="ect-dialog-description"><h2 id="ect-dialog-title"><?php echo esc_html__( 'Delete Timer?', 'easy-countdown' ); ?></h2><p id="ect-dialog-description"><?php echo esc_html( sprintf( /* translators: %s: timer ID. */ __( 'Permanently delete %s? This cannot be undone and will break existing embeds.', 'easy-countdown' ), $email_countdown_timer_ui['original'] ) ); ?></p><div class="ect-inline"><button type="button" class="button" id="ect-delete-cancel" autofocus><?php echo esc_html__( 'Cancel', 'easy-countdown' ); ?></button><button type="button" class="button ect-delete-button" id="ect-delete-confirm"><?php echo esc_html__( 'Delete permanently', 'easy-countdown' ); ?></button></div></dialog>
    <?php endif; ?>
</div>
