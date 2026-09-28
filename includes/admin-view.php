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
        <div><h1><?php echo esc_html__( 'Email Countdown Timer', 'email-countdown-timer' ); ?></h1>
            <p><?php echo esc_html__( 'Create countdown images for your website and email campaigns.', 'email-countdown-timer' ); ?></p></div>
        <nav aria-label="<?php echo esc_attr__( 'Countdown navigation', 'email-countdown-timer' ); ?>">
            <a href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>"><?php echo esc_html__( 'Timers', 'email-countdown-timer' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=email-countdown-timer-data' ) ); ?>"><?php echo esc_html__( 'Data Settings', 'email-countdown-timer' ); ?></a>
        </nav>
    </div>
    <?php if ( in_array( $email_countdown_timer_ui['status'], array( 'saved', 'deleted' ), true ) && empty( $email_countdown_timer_ui['errors'] ) ) : ?>
        <div class="notice notice-success"><p><?php echo esc_html( 'deleted' === $email_countdown_timer_ui['status'] ? __( 'Timer deleted.', 'email-countdown-timer' ) : __( 'Timer saved.', 'email-countdown-timer' ) ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! function_exists( 'imagecreatetruecolor' ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html__( 'GD is unavailable. Settings can be saved, but image generation and geometry validation are unavailable until GD is enabled.', 'email-countdown-timer' ); ?></p></div>
    <?php elseif ( 'editor' === $email_countdown_timer_ui['view'] && ( ! Email_Countdown_Timer_Config::fontPath( Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'font' ) ) || ! function_exists( 'imagettftext' ) || ! function_exists( 'imagettfbbox' ) ) ) : ?>
        <div class="notice notice-info"><p><?php echo esc_html__( 'The active renderer uses the bitmap fallback: fixed text sizes and printable ASCII labels. Select an available local TTF/OTF for scalable or multilingual text.', 'email-countdown-timer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( 'list' === $email_countdown_timer_ui['view'] ) : ?>
        <section class="ect-card" aria-labelledby="ect-list-title">
            <div class="ect-heading-row"><h2 id="ect-list-title"><?php echo esc_html__( 'Your Timers', 'email-countdown-timer' ); ?></h2>
                <a class="button button-primary" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'view' => 'new' ) ) ); ?>"><?php echo esc_html__( 'Create Timer', 'email-countdown-timer' ); ?></a></div>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ect-search">
                <input type="hidden" name="page" value="ecd-timers">
                <label for="ect-search"><?php echo esc_html__( 'Search timer IDs', 'email-countdown-timer' ); ?></label>
                <div class="ect-inline"><input id="ect-search" name="s" type="search" value="<?php echo esc_attr( $email_countdown_timer_ui['search'] ); ?>"><button class="button" type="submit"><?php echo esc_html__( 'Search', 'email-countdown-timer' ); ?></button></div>
            </form>
            <?php if ( empty( $email_countdown_timer_ui['timers'] ) ) : ?>
                <p class="ect-empty"><?php echo esc_html( '' === $email_countdown_timer_ui['search'] ? __( 'No timers yet. Create your first timer to get started.', 'email-countdown-timer' ) : __( 'No timers match this search.', 'email-countdown-timer' ) ); ?></p>
            <?php else : ?>
                <div class="ect-table-wrap" role="region" aria-labelledby="ect-list-title" tabindex="0">
                    <table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html__( 'Saved timers, deadlines and actions', 'email-countdown-timer' ); ?></caption>
                        <thead><tr><th scope="col"><?php echo esc_html__( 'Timer ID', 'email-countdown-timer' ); ?></th><th scope="col"><?php echo esc_html__( 'Deadline / Time Zone', 'email-countdown-timer' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'email-countdown-timer' ); ?></th><th scope="col"><?php echo esc_html__( 'Actions', 'email-countdown-timer' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $email_countdown_timer_ui['timers'] as $email_countdown_timer_id => $email_countdown_timer_timer ) : ?>
                            <?php
                            try {
                                $email_countdown_timer_status = Email_Countdown_Timer_Config::deadline( $email_countdown_timer_timer ) > time() ? __( 'Active', 'email-countdown-timer' ) : __( 'Expired', 'email-countdown-timer' );
                            } catch ( Exception $email_countdown_timer_exception ) {
                                $email_countdown_timer_status = __( 'Needs attention', 'email-countdown-timer' );
                            }
                            $email_countdown_timer_edit_url = Email_Countdown_Timer_Admin::url( array( 'edit' => $email_countdown_timer_id ) );
                            ?>
                            <tr><th scope="row"><code><?php echo esc_html( $email_countdown_timer_id ); ?></code></th>
                                <td><?php echo esc_html( Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_timer ) ); ?></td>
                                <td><span class="ect-badge"><?php echo esc_html( $email_countdown_timer_status ); ?></span></td>
                                <td><div class="ect-row-actions"><a class="button" href="<?php echo esc_url( $email_countdown_timer_edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Edit %s', 'email-countdown-timer' ), $email_countdown_timer_id ) ); ?>"><?php echo esc_html__( 'Edit', 'email-countdown-timer' ); ?></a>
                                    <a href="<?php echo esc_url( $email_countdown_timer_edit_url . '#ect-embed' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Get embed code for %s', 'email-countdown-timer' ), $email_countdown_timer_id ) ); ?>"><?php echo esc_html__( 'Get Embed Code', 'email-countdown-timer' ); ?></a></div></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <nav class="ect-pagination" aria-label="<?php echo esc_attr__( 'Timer pages', 'email-countdown-timer' ); ?>">
                    <span><?php echo esc_html( sprintf( /* translators: 1: current page, 2: total pages. */ __( 'Page %1$d of %2$d', 'email-countdown-timer' ), $email_countdown_timer_ui['page'], $email_countdown_timer_ui['pages'] ) ); ?></span>
                    <?php if ( $email_countdown_timer_ui['page'] > 1 ) : ?><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 's' => $email_countdown_timer_ui['search'], 'paged' => $email_countdown_timer_ui['page'] - 1 ) ) ); ?>"><?php echo esc_html__( 'Previous', 'email-countdown-timer' ); ?></a><?php endif; ?>
                    <?php if ( $email_countdown_timer_ui['page'] < $email_countdown_timer_ui['pages'] ) : ?><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 's' => $email_countdown_timer_ui['search'], 'paged' => $email_countdown_timer_ui['page'] + 1 ) ) ); ?>"><?php echo esc_html__( 'Next', 'email-countdown-timer' ); ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    <?php else : ?>
        <div class="ect-heading-row"><h2><?php echo esc_html( '' === $email_countdown_timer_ui['original'] ? __( 'New Timer', 'email-countdown-timer' ) : sprintf( /* translators: %s: timer ID. */ __( 'Edit: %s', 'email-countdown-timer' ), $email_countdown_timer_ui['original'] ) ); ?></h2>
            <span id="ect-save-state" class="ect-badge" data-dirty="<?php echo esc_attr__( 'Unsaved changes', 'email-countdown-timer' ); ?>" data-clean="<?php echo esc_attr__( 'Saved configuration', 'email-countdown-timer' ); ?>"><?php echo esc_html( $email_countdown_timer_ui['saved'] && empty( $email_countdown_timer_ui['errors'] ) ? __( 'Saved configuration', 'email-countdown-timer' ) : __( 'Not saved', 'email-countdown-timer' ) ); ?></span></div>
        <div id="ect-errors" class="notice notice-error" tabindex="-1" <?php if ( empty( $email_countdown_timer_ui['errors'] ) ) : ?>hidden<?php endif; ?>>
            <h3><?php echo esc_html__( 'Review the following fields. Your changes have not been saved.', 'email-countdown-timer' ); ?></h3>
            <ul><?php foreach ( $email_countdown_timer_ui['errors'] as $email_countdown_timer_key => $email_countdown_timer_error ) : ?><li><a href="#ect-<?php echo esc_attr( 'form' === $email_countdown_timer_key ? 'timer_id' : $email_countdown_timer_key ); ?>"><?php echo esc_html( $email_countdown_timer_error ); ?></a></li><?php endforeach; ?></ul>
        </div>
        <div class="ect-editor-grid">
            <div>
                <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( '' !== $email_countdown_timer_ui['original'] ? array( 'edit' => $email_countdown_timer_ui['original'] ) : array( 'view' => 'new' ) ) ); ?>" id="ect-editor">
                    <input type="hidden" name="ecd_action" value="email_countdown_timer_save">
                    <input type="hidden" name="original_id" value="<?php echo esc_attr( $email_countdown_timer_ui['original'] ); ?>">
                    <?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                    <section class="ect-card" aria-labelledby="ect-schedule-title"><h3 id="ect-schedule-title"><?php echo esc_html__( 'Schedule', 'email-countdown-timer' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'timer_id', __( 'Timer ID', 'email-countdown-timer' ), 'text', __( 'Used in embed URLs. It cannot be changed after creation.', 'email-countdown-timer' ), array_merge( array( 'required' => 'required', 'maxlength' => '200' ), '' !== $email_countdown_timer_ui['original'] ? array( 'readonly' => 'readonly' ) : array() ) ); ?>
                        <?php $email_countdown_timer_ui['data']['deadline'] = str_replace( ' ', 'T', Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'deadline' ) ); ?>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'deadline', __( 'Deadline', 'email-countdown-timer' ), isset( $email_countdown_timer_ui['errors']['deadline'] ) ? 'text' : 'datetime-local', __( 'Date and time in the time zone below, including seconds.', 'email-countdown-timer' ), array( 'required' => 'required', 'step' => '1' ) ); ?>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'tz', __( 'Time Zone', 'email-countdown-timer' ), 'text', __( 'For example Europe/Warsaw, UTC or America/New_York. Existing values are preserved.', 'email-countdown-timer' ) ); ?>
                        <label class="ect-check"><input id="ect-hide_days" aria-describedby="ect-error-hide_days" type="checkbox" name="hide_days" value="1" <?php checked( ! empty( $email_countdown_timer_ui['data']['hide_days'] ) ); ?>><?php echo esc_html__( 'Hide days when fewer than 24 hours remain', 'email-countdown-timer' ); ?></label>
                        <p class="ect-error" id="ect-error-hide_days"><?php echo esc_html( $email_countdown_timer_ui['errors']['hide_days'] ?? '' ); ?></p>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-appearance-title"><h3 id="ect-appearance-title"><?php echo esc_html__( 'Appearance', 'email-countdown-timer' ); ?></h3>
                        <fieldset><legend><?php echo esc_html__( 'Colors', 'email-countdown-timer' ); ?></legend><div class="ect-colors">
                        <?php foreach ( array( 'bg' => __( 'Background', 'email-countdown-timer' ), 'dc' => __( 'Digits', 'email-countdown-timer' ), 'lc' => __( 'Labels', 'email-countdown-timer' ) ) as $email_countdown_timer_key => $email_countdown_timer_label ) : ?>
                            <div><?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, $email_countdown_timer_key, $email_countdown_timer_label, 'text', __( 'HEX color', 'email-countdown-timer' ), array( 'pattern' => '#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})', 'required' => 'required' ) ); ?>
                                <label hidden data-ect-picker><?php echo esc_html( sprintf( /* translators: %s: color role. */ __( 'Choose %s color', 'email-countdown-timer' ), $email_countdown_timer_label ) ); ?><input type="color" data-color-for="ect-<?php echo esc_attr( $email_countdown_timer_key ); ?>" value="#000000"></label></div>
                        <?php endforeach; ?>
                        </div></fieldset>
                        <p id="ect-contrast" class="description" hidden data-template="<?php echo esc_attr__( 'Contrast on background: digits {digits}:1; labels {labels}:1. Aim for at least 4.5:1. Colors are not changed automatically.', 'email-countdown-timer' ); ?>"></p>
                        <details class="ect-disclosure" data-ect-disclosure open><summary><?php echo esc_html__( 'Typography and Size', 'email-countdown-timer' ); ?></summary><div>
                            <div class="ect-field"><label for="ect-font"><?php echo esc_html__( 'Font', 'email-countdown-timer' ); ?></label>
                                <?php $email_countdown_timer_fonts = Email_Countdown_Timer_Config::fonts(); $email_countdown_timer_font = Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'font' ); ?>
                                <select id="ect-font" name="font" aria-describedby="ect-help-font ect-error-font" <?php if ( isset( $email_countdown_timer_ui['errors']['font'] ) ) : ?>aria-invalid="true"<?php endif; ?>><option value=""><?php echo esc_html__( 'Default bitmap font', 'email-countdown-timer' ); ?></option>
                                    <?php if ( '' !== $email_countdown_timer_font && ! in_array( $email_countdown_timer_font, $email_countdown_timer_fonts, true ) ) : ?><option value="<?php echo esc_attr( $email_countdown_timer_font ); ?>" selected><?php echo esc_html( sprintf( /* translators: %s: missing font filename. */ __( 'Unavailable: %s (keep saved value)', 'email-countdown-timer' ), $email_countdown_timer_font ) ); ?></option><?php endif; ?>
                                    <?php foreach ( $email_countdown_timer_fonts as $email_countdown_timer_file ) : ?><option value="<?php echo esc_attr( $email_countdown_timer_file ); ?>" <?php selected( $email_countdown_timer_font, $email_countdown_timer_file ); ?>><?php echo esc_html( $email_countdown_timer_file ); ?></option><?php endforeach; ?>
                                </select><p id="ect-help-font" class="description"><?php echo esc_html__( 'Use Data Settings to copy legacy fonts into persistent local storage. Legacy /fonts/ remains readable. Bitmap fallback has fixed sizes and printable ASCII labels only; use a trusted TTF/OTF for other characters.', 'email-countdown-timer' ); ?></p><p class="ect-error" id="ect-error-font"><?php echo esc_html( $email_countdown_timer_ui['errors']['font'] ?? '' ); ?></p></div>
                            <div class="ect-two-fields">
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'size_digit', __( 'Digit Size (px)', 'email-countdown-timer' ), 'number', __( 'Applies to a local TTF/OTF font.', 'email-countdown-timer' ), array( 'min' => '1', 'max' => '200', 'required' => 'required' ) ); ?>
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'size_label', __( 'Label Size (px)', 'email-countdown-timer' ), 'number', __( 'Applies to a local TTF/OTF font.', 'email-countdown-timer' ), array( 'min' => '1', 'max' => '100', 'required' => 'required' ) ); ?>
                            </div>
                            <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'fixed_width', __( 'Minimum Image Width (px)', 'email-countdown-timer' ), 'number', __( '0 means automatic. This sets a minimum width, not image scaling. Height follows content with padding.', 'email-countdown-timer' ), array( 'min' => '0', 'max' => '4000', 'required' => 'required' ) ); ?>
                        </div></details>
                        <details class="ect-disclosure" data-ect-disclosure open><summary><?php echo esc_html__( 'Custom Labels', 'email-countdown-timer' ); ?></summary><div class="ect-two-fields">
                            <?php foreach ( array( 'label_d' => __( 'Days', 'email-countdown-timer' ), 'label_h' => __( 'Hours', 'email-countdown-timer' ), 'label_m' => __( 'Minutes', 'email-countdown-timer' ), 'label_s' => __( 'Seconds', 'email-countdown-timer' ) ) as $email_countdown_timer_key => $email_countdown_timer_label ) : ?>
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, $email_countdown_timer_key, $email_countdown_timer_label, 'text', __( 'An empty label is allowed. Maximum 256 bytes.', 'email-countdown-timer' ) ); ?>
                            <?php endforeach; ?>
                        </div></details>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-accessibility-title"><h3 id="ect-accessibility-title"><?php echo esc_html__( 'Accessibility', 'email-countdown-timer' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'alt', __( 'Image alternative text (alt)', 'email-countdown-timer' ), 'text', __( 'Describe the timer purpose, not its changing seconds. Used by the website shortcode and new Email HTML. Leave empty only when nearby text provides the same information. Save, then copy updated HTML; already sent emails cannot be changed. Maximum 1000 bytes.', 'email-countdown-timer' ) ); ?>
                    </section>
                    <div class="ect-save-row"><button type="submit" class="button button-primary"><?php echo esc_html( '' !== $email_countdown_timer_ui['original'] ? __( 'Save Changes', 'email-countdown-timer' ) : __( 'Create Timer', 'email-countdown-timer' ) ); ?></button><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>"><?php echo esc_html__( 'Back to Timers', 'email-countdown-timer' ); ?></a></div>
                </form>
                <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                    <details class="ect-card ect-danger"><summary><?php echo esc_html__( 'Delete Timer', 'email-countdown-timer' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'edit' => $email_countdown_timer_ui['original'] ) ) ); ?>" id="ect-delete">
                            <input type="hidden" name="ecd_action" value="email_countdown_timer_delete"><input type="hidden" name="timer_id" value="<?php echo esc_attr( $email_countdown_timer_ui['original'] ); ?>"><?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                            <p><?php echo esc_html( sprintf( /* translators: %s: timer ID. */ __( 'Permanently delete %s? Existing emails and pages using this timer will stop receiving countdown images.', 'email-countdown-timer' ), $email_countdown_timer_ui['original'] ) ); ?></p>
                            <label class="ect-check"><input name="confirm_delete" type="checkbox" value="1" required><?php echo esc_html__( 'I understand this cannot be undone.', 'email-countdown-timer' ); ?></label>
                            <button type="submit" class="button ect-delete-button"><?php echo esc_html__( 'Delete permanently', 'email-countdown-timer' ); ?></button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
            <aside aria-label="<?php echo esc_attr__( 'Preview and embedding', 'email-countdown-timer' ); ?>">
                <section class="ect-card"><h3><?php echo esc_html__( 'Saved Preview', 'email-countdown-timer' ); ?></h3>
                    <p id="ect-preview-help"><?php echo esc_html__( 'Shows the saved configuration, not unsaved changes. Save to update.', 'email-countdown-timer' ); ?></p>
                    <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                        <?php $email_countdown_timer_image_url = add_query_arg( array( 'ecd_action' => 'render', 'ecd' => $email_countdown_timer_ui['original'], 'mode' => 'email' ), home_url( '/' ) ); $email_countdown_timer_static_url = add_query_arg( 'mode', 'static', $email_countdown_timer_image_url ); ?>
                        <div class="ect-preview"><img id="ect-preview" src="<?php echo esc_url( $email_countdown_timer_static_url ); ?>" data-static="<?php echo esc_url( $email_countdown_timer_static_url ); ?>" data-animated="<?php echo esc_url( $email_countdown_timer_image_url ); ?>" alt="<?php echo esc_attr( Email_Countdown_Timer_Config::alt( $email_countdown_timer_ui['saved'], __( 'Saved countdown image. The absolute deadline is provided below.', 'email-countdown-timer' ) ) ); ?>"></div>
                        <p><strong><?php echo esc_html__( 'Ends:', 'email-countdown-timer' ); ?></strong> <?php echo esc_html( Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_ui['saved'] ) ); ?></p>
                        <?php if ( class_exists( 'Imagick' ) ) : ?><button type="button" class="button" id="ect-animation" hidden data-play="<?php echo esc_attr__( 'Preview Animation', 'email-countdown-timer' ); ?>" data-stop="<?php echo esc_attr__( 'Stop Preview', 'email-countdown-timer' ); ?>"><?php echo esc_html__( 'Preview Animation', 'email-countdown-timer' ); ?></button><?php else : ?><p class="description"><?php echo esc_html__( 'Imagick is unavailable. GIF output is static.', 'email-countdown-timer' ); ?></p><?php endif; ?>
                        <p id="ect-image-error" class="ect-error" hidden><?php echo esc_html__( 'Preview could not load. Check GD, font support and the saved image URL.', 'email-countdown-timer' ); ?></p>
                    <?php else : ?><p class="ect-empty"><?php echo esc_html__( 'Create this timer to see its image and embed codes.', 'email-countdown-timer' ); ?></p><?php endif; ?>
                </section>
                <section class="ect-card" id="ect-embed" tabindex="-1"><h3><?php echo esc_html__( 'Embed Codes', 'email-countdown-timer' ); ?></h3>
                    <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                        <?php
                        $email_countdown_timer_deadline = Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_ui['saved'] );
                        $email_countdown_timer_email_html = '<img class="email-countdown-timer-image" loading="eager" data-no-lazy="1" referrerpolicy="no-referrer" src="' . esc_url( $email_countdown_timer_image_url ) . '" alt="' . esc_attr( Email_Countdown_Timer_Config::alt( $email_countdown_timer_ui['saved'], sprintf( /* translators: %s: absolute deadline and time zone. */ __( 'Countdown ends %s', 'email-countdown-timer' ), $email_countdown_timer_deadline ) ) ) . '" style="display:block;max-width:100%;height:auto;border:0;">' . "\n<p>" . esc_html( sprintf( /* translators: %s: absolute deadline and time zone. */ __( 'Ends: %s', 'email-countdown-timer' ), $email_countdown_timer_deadline ) ) . '</p>';
                        $email_countdown_timer_shortcode = '[ecd_timer id="' . esc_attr( $email_countdown_timer_ui['original'] ) . '"]';
                        ?>
                        <div data-ect-embed-tools hidden><label for="ect-embed-format"><?php echo esc_html__( 'Embed format', 'email-countdown-timer' ); ?></label><select id="ect-embed-format"><option value="url"><?php echo esc_html__( 'Image URL', 'email-countdown-timer' ); ?></option><option value="html"><?php echo esc_html__( 'Email HTML', 'email-countdown-timer' ); ?></option><option value="shortcode"><?php echo esc_html__( 'Website Shortcode', 'email-countdown-timer' ); ?></option></select></div>
                        <label for="ect-embed-code" id="ect-embed-label"><?php echo esc_html__( 'Image URL', 'email-countdown-timer' ); ?></label>
                        <textarea id="ect-embed-code" rows="4" readonly spellcheck="false"><?php echo esc_textarea( $email_countdown_timer_image_url ); ?></textarea>
                        <button type="button" class="button" id="ect-copy" hidden data-success="<?php echo esc_attr__( 'Copied to clipboard.', 'email-countdown-timer' ); ?>" data-fallback="<?php echo esc_attr__( 'Copy was unavailable. The code is selected; use your keyboard or device copy command.', 'email-countdown-timer' ); ?>"><?php echo esc_html__( 'Copy Code', 'email-countdown-timer' ); ?></button><p id="ect-copy-result" class="description"></p>
                        <details data-ect-manual open><summary><?php echo esc_html__( 'All formats (manual copy)', 'email-countdown-timer' ); ?></summary>
                            <label for="ect-manual-html"><?php echo esc_html__( 'Email HTML', 'email-countdown-timer' ); ?></label><textarea id="ect-manual-html" rows="6" readonly><?php echo esc_textarea( $email_countdown_timer_email_html ); ?></textarea>
                            <label for="ect-manual-shortcode"><?php echo esc_html__( 'Website Shortcode', 'email-countdown-timer' ); ?></label><textarea id="ect-manual-shortcode" rows="2" readonly><?php echo esc_textarea( $email_countdown_timer_shortcode ); ?></textarea>
                        </details>
                        <p class="description"><?php echo esc_html__( 'Email clients may cache, prefetch or block images. Include the absolute deadline as text in your message.', 'email-countdown-timer' ); ?></p>
                    <?php else : ?><p><?php echo esc_html__( 'Embed codes are available after the first successful save.', 'email-countdown-timer' ); ?></p><?php endif; ?>
                </section>
            </aside>
        </div>
        <dialog id="ect-delete-dialog" aria-labelledby="ect-dialog-title" aria-describedby="ect-dialog-description"><h2 id="ect-dialog-title"><?php echo esc_html__( 'Delete Timer?', 'email-countdown-timer' ); ?></h2><p id="ect-dialog-description"><?php echo esc_html( sprintf( /* translators: %s: timer ID. */ __( 'Permanently delete %s? This cannot be undone and will break existing embeds.', 'email-countdown-timer' ), $email_countdown_timer_ui['original'] ) ); ?></p><div class="ect-inline"><button type="button" class="button" id="ect-delete-cancel" autofocus><?php echo esc_html__( 'Cancel', 'email-countdown-timer' ); ?></button><button type="button" class="button ect-delete-button" id="ect-delete-confirm"><?php echo esc_html__( 'Delete permanently', 'email-countdown-timer' ); ?></button></div></dialog>
    <?php endif; ?>
</div>
