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
        <div><h1><?php echo esc_html__( 'ActNow – Countdown Timers', 'actnow-countdown-timers' ); ?></h1>
            <p><?php echo esc_html__( 'Create countdown images for your website and email campaigns.', 'actnow-countdown-timers' ); ?></p></div>
        <nav aria-label="<?php echo esc_attr__( 'Countdown navigation', 'actnow-countdown-timers' ); ?>">
            <a href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>"><?php echo esc_html__( 'Timers', 'actnow-countdown-timers' ); ?></a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=email-countdown-timer-data' ) ); ?>"><?php echo esc_html__( 'Data Settings', 'actnow-countdown-timers' ); ?></a>
        </nav>
    </div>
    <?php $email_countdown_timer_messages = array( 'saved' => __( 'Timer saved.', 'actnow-countdown-timers' ), 'deleted' => __( 'Timer deleted.', 'actnow-countdown-timers' ), 'archived' => __( 'Timer archived. Existing website and email links still work.', 'actnow-countdown-timers' ), 'restored' => __( 'Timer restored to the main list.', 'actnow-countdown-timers' ) ); ?>
    <?php if ( isset( $email_countdown_timer_messages[ $email_countdown_timer_ui['status'] ] ) && empty( $email_countdown_timer_ui['errors'] ) ) : ?>
        <div class="notice notice-success"><p><?php echo esc_html( $email_countdown_timer_messages[ $email_countdown_timer_ui['status'] ] ); ?></p></div>
    <?php endif; ?>
    <?php if ( ! function_exists( 'imagecreatetruecolor' ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html__( 'GD is unavailable. Settings can be saved, but image generation and geometry validation are unavailable until GD is enabled.', 'actnow-countdown-timers' ); ?></p></div>
    <?php elseif ( 'editor' === $email_countdown_timer_ui['view'] && ( ! Email_Countdown_Timer_Config::fontPath( Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'font' ) ) || ! function_exists( 'imagettftext' ) || ! function_exists( 'imagettfbbox' ) ) ) : ?>
        <div class="notice notice-info"><p><?php echo esc_html__( 'The active renderer uses the bitmap fallback: fixed text sizes and printable ASCII labels. Select an available local TTF/OTF for scalable or multilingual text.', 'actnow-countdown-timers' ); ?></p></div>
    <?php endif; ?>
    <?php if ( 'list' === $email_countdown_timer_ui['view'] ) : ?>
        <section class="ect-card" aria-labelledby="ect-quick-title">
            <h2 id="ect-quick-title"><?php echo esc_html__( 'Quick Create', 'actnow-countdown-timers' ); ?></h2>
            <p><?php echo esc_html__( 'Choose a unique timer name, deadline and preset. Create the timer to get website and email embeds; you can fine-tune every setting afterwards.', 'actnow-countdown-timers' ); ?></p>
            <?php Email_Countdown_Timer_Admin::quick_create_form(); ?>
        </section>
        <section class="ect-card" aria-labelledby="ect-list-title">
            <div class="ect-heading-row"><h2 id="ect-list-title"><?php echo esc_html__( 'Your Timers', 'actnow-countdown-timers' ); ?></h2>
                <a class="button button-primary" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'view' => 'new' ) ) ); ?>"><?php echo esc_html__( 'Create Timer', 'actnow-countdown-timers' ); ?></a></div>
            <nav class="ect-row-actions" aria-label="<?php echo esc_attr__( 'Campaign lists', 'actnow-countdown-timers' ); ?>">
                <a href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>" <?php if ( 'active' === $email_countdown_timer_ui['filter'] ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( sprintf( /* translators: %d: number of unarchived timers. */ __( 'Timers (%d)', 'actnow-countdown-timers' ), $email_countdown_timer_ui['active_count'] ) ); ?></a>
                <a href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'filter' => 'archived' ) ) ); ?>" <?php if ( 'archived' === $email_countdown_timer_ui['filter'] ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( sprintf( /* translators: %d: number of archived timers. */ __( 'Archived (%d)', 'actnow-countdown-timers' ), $email_countdown_timer_ui['archived_count'] ) ); ?></a>
            </nav>
            <p class="description"><?php echo esc_html__( 'Archiving hides a campaign from this list and the dashboard. Existing links and Gutenberg selections still work. Restore it from Archived; deletion remains a separate action.', 'actnow-countdown-timers' ); ?></p>
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ect-search">
                <input type="hidden" name="page" value="ecd-timers">
                <input type="hidden" name="filter" value="<?php echo esc_attr( $email_countdown_timer_ui['filter'] ); ?>">
                <label for="ect-search"><?php echo esc_html__( 'Search names or timer IDs', 'actnow-countdown-timers' ); ?></label>
                <div class="ect-inline"><input id="ect-search" name="s" type="search" value="<?php echo esc_attr( $email_countdown_timer_ui['search'] ); ?>"><button class="button" type="submit"><?php echo esc_html__( 'Search', 'actnow-countdown-timers' ); ?></button></div>
            </form>
            <?php if ( empty( $email_countdown_timer_ui['timers'] ) ) : ?>
                <p class="ect-empty"><?php echo esc_html( '' !== $email_countdown_timer_ui['search'] ? __( 'No timers match this search.', 'actnow-countdown-timers' ) : ( 'archived' === $email_countdown_timer_ui['filter'] ? __( 'No archived timers.', 'actnow-countdown-timers' ) : __( 'No timers yet. Use Quick Create above, or open the full editor with Create Timer.', 'actnow-countdown-timers' ) ) ); ?></p>
            <?php else : ?>
                <div class="ect-table-wrap" role="region" aria-labelledby="ect-list-title" tabindex="0">
                    <table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html__( 'Saved timers, deadlines and actions', 'actnow-countdown-timers' ); ?></caption>
                        <thead><tr><th scope="col"><?php echo esc_html__( 'Timer name', 'actnow-countdown-timers' ); ?></th><th scope="col"><?php echo esc_html__( 'Deadline / Time Zone', 'actnow-countdown-timers' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'actnow-countdown-timers' ); ?></th><th scope="col"><?php echo esc_html__( 'Actions', 'actnow-countdown-timers' ); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ( $email_countdown_timer_ui['timers'] as $email_countdown_timer_id => $email_countdown_timer_timer ) : ?>
                            <?php
                            try {
                                $email_countdown_timer_status = Email_Countdown_Timer_Config::deadline( $email_countdown_timer_timer ) > time() ? __( 'Active', 'actnow-countdown-timers' ) : __( 'Expired', 'actnow-countdown-timers' );
                            } catch ( Exception $email_countdown_timer_exception ) {
                                $email_countdown_timer_status = __( 'Needs attention', 'actnow-countdown-timers' );
                            }
                            if ( Email_Countdown_Timer_Admin::archived( $email_countdown_timer_timer ) ) {
                                $email_countdown_timer_status = __( 'Archived', 'actnow-countdown-timers' );
                            }
                            $email_countdown_timer_edit_url = Email_Countdown_Timer_Admin::url( array( 'edit' => $email_countdown_timer_id ) );
                            ?>
                            <tr data-timer-key="<?php echo esc_attr( $email_countdown_timer_id ); ?>"><th scope="row"><?php echo esc_html( Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_timer, (string) $email_countdown_timer_id, $email_countdown_timer_ui['name_collisions'] ) ); ?></th>
                                <td><?php echo esc_html( Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_timer ) ); ?></td>
                                <td><span class="ect-badge"><?php echo esc_html( $email_countdown_timer_status ); ?></span></td>
                                <td><div class="ect-row-actions"><a class="button" href="<?php echo esc_url( $email_countdown_timer_edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Edit %s', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_timer, (string) $email_countdown_timer_id, $email_countdown_timer_ui['name_collisions'] ) ) ); ?>"><?php echo esc_html__( 'Edit', 'actnow-countdown-timers' ); ?></a>
                                    <a href="<?php echo esc_url( $email_countdown_timer_edit_url . '#ect-embed' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Get embed code for %s', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_timer, (string) $email_countdown_timer_id, $email_countdown_timer_ui['name_collisions'] ) ) ); ?>"><?php echo esc_html__( 'Get Embed Code', 'actnow-countdown-timers' ); ?></a>
                                    <a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'duplicate' => $email_countdown_timer_id ) ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ __( 'Duplicate %s', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_timer, (string) $email_countdown_timer_id, $email_countdown_timer_ui['name_collisions'] ) ) ); ?>"><?php echo esc_html__( 'Duplicate', 'actnow-countdown-timers' ); ?></a>
                                    <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'filter' => $email_countdown_timer_ui['filter'] ) ) ); ?>">
                                        <?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                                        <input type="hidden" name="timer_id" value="<?php echo esc_attr( $email_countdown_timer_id ); ?>">
                                        <input type="hidden" name="ecd_action" value="<?php echo esc_attr( 'archived' === $email_countdown_timer_ui['filter'] ? 'email_countdown_timer_restore' : 'email_countdown_timer_archive' ); ?>">
                                        <button type="submit" class="button" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: timer ID. */ 'archived' === $email_countdown_timer_ui['filter'] ? __( 'Restore %s', 'actnow-countdown-timers' ) : __( 'Archive %s', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_timer, (string) $email_countdown_timer_id, $email_countdown_timer_ui['name_collisions'] ) ) ); ?>"><?php echo esc_html( 'archived' === $email_countdown_timer_ui['filter'] ? __( 'Restore', 'actnow-countdown-timers' ) : __( 'Archive', 'actnow-countdown-timers' ) ); ?></button>
                                    </form>
                                </div></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <nav class="ect-pagination" aria-label="<?php echo esc_attr__( 'Timer pages', 'actnow-countdown-timers' ); ?>">
                    <span><?php echo esc_html( sprintf( /* translators: 1: current page, 2: total pages. */ __( 'Page %1$d of %2$d', 'actnow-countdown-timers' ), $email_countdown_timer_ui['page'], $email_countdown_timer_ui['pages'] ) ); ?></span>
                    <?php if ( $email_countdown_timer_ui['page'] > 1 ) : ?><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'filter' => $email_countdown_timer_ui['filter'], 's' => $email_countdown_timer_ui['search'], 'paged' => $email_countdown_timer_ui['page'] - 1 ) ) ); ?>"><?php echo esc_html__( 'Previous', 'actnow-countdown-timers' ); ?></a><?php endif; ?>
                    <?php if ( $email_countdown_timer_ui['page'] < $email_countdown_timer_ui['pages'] ) : ?><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'filter' => $email_countdown_timer_ui['filter'], 's' => $email_countdown_timer_ui['search'], 'paged' => $email_countdown_timer_ui['page'] + 1 ) ) ); ?>"><?php echo esc_html__( 'Next', 'actnow-countdown-timers' ); ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>
    <?php else : ?>
        <?php if ( '' !== $email_countdown_timer_ui['duplicate_source'] ) : ?>
            <div class="notice notice-info"><p><?php echo esc_html( sprintf( /* translators: %s: source timer ID. */ __( 'Copying %s into a new, unsaved campaign. Review the new ID and deadline, then create it. The original campaign is unchanged.', 'actnow-countdown-timers' ), $email_countdown_timer_ui['duplicate_source'] ) ); ?></p></div>
        <?php elseif ( $email_countdown_timer_ui['saved'] && Email_Countdown_Timer_Admin::archived( $email_countdown_timer_ui['saved'] ) ) : ?>
            <div class="notice notice-info"><p><?php echo esc_html__( 'This campaign is archived. Existing links still work. Saving changes keeps it archived; restore it from the Archived list.', 'actnow-countdown-timers' ); ?></p></div>
        <?php endif; ?>
        <div class="ect-heading-row"><h2><?php echo esc_html( '' === $email_countdown_timer_ui['original'] ? __( 'New Timer', 'actnow-countdown-timers' ) : sprintf( /* translators: %s: timer ID. */ __( 'Edit timer: %s', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_ui['saved'] ?? $email_countdown_timer_ui['data'], $email_countdown_timer_ui['original'], $email_countdown_timer_ui['name_collisions'] ) ) ); ?></h2>
</div>
        <div id="ect-errors" class="notice notice-error" tabindex="-1" <?php if ( empty( $email_countdown_timer_ui['errors'] ) ) : ?>hidden<?php endif; ?>>
            <h3><?php echo esc_html__( 'Review the following fields. Your changes have not been saved.', 'actnow-countdown-timers' ); ?></h3>
            <ul><?php foreach ( $email_countdown_timer_ui['errors'] as $email_countdown_timer_key => $email_countdown_timer_error ) : ?><li><a href="#ect-<?php echo esc_attr( in_array( $email_countdown_timer_key, array( 'form', 'timer_id' ), true ) ? 'campaign_name' : $email_countdown_timer_key ); ?>"><?php echo esc_html( $email_countdown_timer_error ); ?></a></li><?php endforeach; ?></ul>
        </div>
        <div class="ect-editor-grid">
            <div>
                <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( '' !== $email_countdown_timer_ui['original'] ? array( 'edit' => $email_countdown_timer_ui['original'] ) : array( 'view' => 'new' ) ) ); ?>" id="ect-editor">
                    <input type="hidden" name="ecd_action" value="email_countdown_timer_save">
                    <input type="hidden" name="original_id" value="<?php echo esc_attr( $email_countdown_timer_ui['original'] ); ?>">
                    <input type="hidden" name="name_workflow" value="1">
                    <?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                    <section class="ect-card" aria-labelledby="ect-schedule-title"><h3 id="ect-schedule-title"><?php echo esc_html__( 'Schedule', 'actnow-countdown-timers' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'campaign_name', __( 'Timer name', 'actnow-countdown-timers' ), 'text', __( 'For your administration only. Not used in public images, embeds or the generated ID. Up to 200 bytes.', 'actnow-countdown-timers' ), array( 'maxlength' => '200', 'required' => 'required' ) ); ?>
                        <input id="ect-timer_id" name="timer_id" type="hidden" readonly value="<?php echo esc_attr( $email_countdown_timer_ui['id'] ); ?>">
                        <?php $email_countdown_timer_ui['data']['deadline'] = str_replace( ' ', 'T', Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'deadline' ) ); ?>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'deadline', __( 'Deadline', 'actnow-countdown-timers' ), isset( $email_countdown_timer_ui['errors']['deadline'] ) ? 'text' : 'datetime-local', __( 'Date and time in the time zone below, including seconds.', 'actnow-countdown-timers' ), array( 'required' => 'required', 'step' => '1' ) ); ?>
                        <?php Email_Countdown_Timer_Admin::timezone_input( 'ect-tz', Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'tz' ), $email_countdown_timer_ui['errors']['tz'] ?? '' ); ?>
                        <?php if ( '' === $email_countdown_timer_ui['original'] && '' === $email_countdown_timer_ui['duplicate_source'] && empty( $email_countdown_timer_ui['errors'] ) ) : ?><input type="hidden" data-ect-auto-timezone="ect-tz"><?php endif; ?>
                        <label class="ect-check"><input id="ect-hide_days" aria-describedby="ect-error-hide_days" type="checkbox" name="hide_days" value="1" <?php checked( ! empty( $email_countdown_timer_ui['data']['hide_days'] ) ); ?>><?php echo esc_html__( 'Hide days when fewer than 24 hours remain', 'actnow-countdown-timers' ); ?></label>
                        <p class="ect-error" id="ect-error-hide_days"><?php echo esc_html( $email_countdown_timer_ui['errors']['hide_days'] ?? '' ); ?></p>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-appearance-title"><h3 id="ect-appearance-title"><?php echo esc_html__( 'Appearance', 'actnow-countdown-timers' ); ?></h3>
                        <div data-ect-presets hidden>
                            <p class="description"><?php echo esc_html__( 'Presets apply colors and an available included font. Review the fields and save explicitly. If the preset font or FreeType is unavailable, your selected font is kept.', 'actnow-countdown-timers' ); ?></p>
                            <div class="ect-inline" role="group" aria-label="<?php echo esc_attr__( 'Appearance presets', 'actnow-countdown-timers' ); ?>">
                                <?php foreach ( Email_Countdown_Timer_Admin::presets() as $email_countdown_timer_key => $email_countdown_timer_preset ) : ?>
                                    <button class="button" type="button" data-ect-preset="<?php echo esc_attr( $email_countdown_timer_key ); ?>" data-bg="<?php echo esc_attr( $email_countdown_timer_preset['bg'] ); ?>" data-dc="<?php echo esc_attr( $email_countdown_timer_preset['dc'] ); ?>" data-lc="<?php echo esc_attr( $email_countdown_timer_preset['lc'] ); ?>" data-font="<?php echo esc_attr( Email_Countdown_Timer_Admin::preset_font( $email_countdown_timer_preset ) ); ?>"><?php echo esc_html( $email_countdown_timer_preset['label'] ); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" data-ect-preset-result role="status" aria-live="polite" data-applied="<?php echo esc_attr__( 'Preset applied. Review the fields and save explicitly.', 'actnow-countdown-timers' ); ?>"></p>
                        </div>
                        <fieldset><legend><?php echo esc_html__( 'Colors', 'actnow-countdown-timers' ); ?></legend><div class="ect-colors">
                        <?php foreach ( array( 'bg' => __( 'Background', 'actnow-countdown-timers' ), 'dc' => __( 'Digits', 'actnow-countdown-timers' ), 'lc' => __( 'Labels', 'actnow-countdown-timers' ) ) as $email_countdown_timer_key => $email_countdown_timer_label ) : ?>
                            <div><?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, $email_countdown_timer_key, $email_countdown_timer_label, 'text', __( 'HEX color', 'actnow-countdown-timers' ), array( 'pattern' => '#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})', 'required' => 'required' ) ); ?>
                                <label hidden data-ect-picker><?php echo esc_html( sprintf( /* translators: %s: color role. */ __( 'Choose %s color', 'actnow-countdown-timers' ), $email_countdown_timer_label ) ); ?><input type="color" data-color-for="ect-<?php echo esc_attr( $email_countdown_timer_key ); ?>" value="#000000"></label></div>
                        <?php endforeach; ?>
                        </div></fieldset>
                        <p id="ect-contrast" class="description" hidden data-template="<?php echo esc_attr__( 'Contrast on background: digits {digits}:1; labels {labels}:1. Aim for at least 4.5:1. Colors are not changed automatically.', 'actnow-countdown-timers' ); ?>"></p>
                        <details class="ect-disclosure" data-ect-disclosure open><summary><?php echo esc_html__( 'Typography and Size', 'actnow-countdown-timers' ); ?></summary><div>
                            <div class="ect-field"><label for="ect-font"><?php echo esc_html__( 'Font', 'actnow-countdown-timers' ); ?></label>
                                <?php $email_countdown_timer_fonts = Email_Countdown_Timer_Config::fonts(); $email_countdown_timer_font = Email_Countdown_Timer_Admin::value( $email_countdown_timer_ui, 'font' ); ?>
                                <select id="ect-font" name="font" aria-describedby="ect-help-font ect-error-font" <?php if ( isset( $email_countdown_timer_ui['errors']['font'] ) ) : ?>aria-invalid="true"<?php endif; ?>><option value=""><?php echo esc_html__( 'Default bitmap font', 'actnow-countdown-timers' ); ?></option>
                                    <?php if ( '' !== $email_countdown_timer_font && ! in_array( $email_countdown_timer_font, $email_countdown_timer_fonts, true ) ) : ?><option value="<?php echo esc_attr( $email_countdown_timer_font ); ?>" selected><?php echo esc_html( sprintf( /* translators: %s: missing font filename. */ __( 'Unavailable: %s (keep saved value)', 'actnow-countdown-timers' ), $email_countdown_timer_font ) ); ?></option><?php endif; ?>
                                    <?php foreach ( $email_countdown_timer_fonts as $email_countdown_timer_file ) : ?><option value="<?php echo esc_attr( $email_countdown_timer_file ); ?>" <?php selected( $email_countdown_timer_font, $email_countdown_timer_file ); ?>><?php echo esc_html( Email_Countdown_Timer_Fonts::label( $email_countdown_timer_file ) ); ?></option><?php endforeach; ?>
                                </select><p id="ect-help-font" class="description"><?php echo esc_html__( 'Five faces are included locally: Lato Regular, IBM Plex Mono Regular, Barlow Condensed Regular and Black (900), and Crimson Text Regular. Select a face and save to use scalable text. FreeType is required. Keep your own licensed TTF/OTF files in the persistent directory shown in Data Settings. Bitmap fallback has fixed sizes and printable ASCII labels only.', 'actnow-countdown-timers' ); ?></p><p class="ect-error" id="ect-error-font"><?php echo esc_html( $email_countdown_timer_ui['errors']['font'] ?? '' ); ?></p></div>
                            <div class="ect-two-fields">
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'size_digit', __( 'Digit Size (px)', 'actnow-countdown-timers' ), 'number', __( 'Applies to a local TTF/OTF font.', 'actnow-countdown-timers' ), array( 'min' => '1', 'max' => '200', 'required' => 'required' ) ); ?>
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'size_label', __( 'Label Size (px)', 'actnow-countdown-timers' ), 'number', __( 'Applies to a local TTF/OTF font.', 'actnow-countdown-timers' ), array( 'min' => '1', 'max' => '100', 'required' => 'required' ) ); ?>
                            </div>
                            <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'fixed_width', __( 'Minimum Image Width (px)', 'actnow-countdown-timers' ), 'number', __( '0 means automatic. This sets a minimum width, not image scaling. Height follows content with padding.', 'actnow-countdown-timers' ), array( 'min' => '0', 'max' => '4000', 'required' => 'required' ) ); ?>
                        </div></details>
                        <details class="ect-disclosure" data-ect-disclosure open><summary><?php echo esc_html__( 'Custom Labels', 'actnow-countdown-timers' ); ?></summary><div>
                            <div data-ect-label-presets hidden>
                                <label for="ect-label-language"><?php echo esc_html__( 'Countdown label language', 'actnow-countdown-timers' ); ?></label>
                                <div class="ect-inline"><select id="ect-label-language" aria-describedby="ect-label-language-help">
                                    <option value=""><?php echo esc_html__( 'Choose a label preset', 'actnow-countdown-timers' ); ?></option>
                                    <?php foreach ( Email_Countdown_Timer_Admin::label_presets() as $email_countdown_timer_language => $email_countdown_timer_label_preset ) : ?>
                                        <option value="<?php echo esc_attr( $email_countdown_timer_language ); ?>" data-labels="<?php echo esc_attr( wp_json_encode( $email_countdown_timer_label_preset['labels'] ) ); ?>"><?php echo esc_html( $email_countdown_timer_label_preset['label'] ); ?></option>
                                    <?php endforeach; ?>
                                </select><button id="ect-apply-labels" class="button" type="button" disabled><?php echo esc_html__( 'Apply label preset', 'actnow-countdown-timers' ); ?></button></div>
                                <p class="description" id="ect-label-language-help"><?php echo esc_html__( 'Apply replaces only the four label fields. Review and save to keep the changes. Existing labels never follow the admin language. Accents require an appropriate local font; bitmap supports printable ASCII only.', 'actnow-countdown-timers' ); ?></p>
                                <p data-ect-label-result role="status" aria-live="polite" data-applied="<?php echo esc_attr__( 'Labels applied. Review the text and save to keep these changes.', 'actnow-countdown-timers' ); ?>"></p>
                            </div>
                            <div class="ect-two-fields">
                            <?php foreach ( array( 'label_d' => __( 'Days', 'actnow-countdown-timers' ), 'label_h' => __( 'Hours', 'actnow-countdown-timers' ), 'label_m' => __( 'Minutes', 'actnow-countdown-timers' ), 'label_s' => __( 'Seconds', 'actnow-countdown-timers' ) ) as $email_countdown_timer_key => $email_countdown_timer_label ) : ?>
                                <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, $email_countdown_timer_key, $email_countdown_timer_label, 'text', __( 'An empty label is allowed. Maximum 256 bytes.', 'actnow-countdown-timers' ) ); ?>
                            <?php endforeach; ?>
                            </div>
                        </div></details>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-after-title"><h3 id="ect-after-title"><?php echo esc_html__( 'After Countdown', 'actnow-countdown-timers' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'expiry_image_id', __( 'End image attachment ID', 'actnow-countdown-timers' ), 'number', __( 'Optional. Choose an image from this site’s Media Library, or enter its attachment ID. Use 0 to keep the zero countdown. Local JPEG, PNG, GIF or WebP only, up to 4 MiB / 4 million pixels. Animated source files use their first frame.', 'actnow-countdown-timers' ), array( 'min' => '0', 'step' => '1' ) ); ?>
                        <div class="ect-inline" data-ect-end-tools hidden>
                            <button type="button" class="button" id="ect-end-choose" data-title="<?php echo esc_attr__( 'Choose an End Image', 'actnow-countdown-timers' ); ?>" data-select="<?php echo esc_attr__( 'Use This Image', 'actnow-countdown-timers' ); ?>"><?php echo esc_html__( 'Choose Image', 'actnow-countdown-timers' ); ?></button>
                            <button type="button" class="button" id="ect-end-remove"><?php echo esc_html__( 'Remove End Image', 'actnow-countdown-timers' ); ?></button>
                        </div>
                        <p id="ect-end-status" role="status" data-selected="<?php echo esc_attr__( 'Image selected. Save the timer to apply it.', 'actnow-countdown-timers' ); ?>" data-removed="<?php echo esc_attr__( 'End image removed. Save the timer to apply this change.', 'actnow-countdown-timers' ); ?>"></p>
                        <p class="description"><?php echo esc_html__( 'Choose a public image: Media Library and end-only timer URLs can expose it before the deadline. Standard image/email output switches at expiry. It is fitted to the timer canvas without stretching; use a similar aspect ratio for a readable result. New expired requests and GIF frames crossing the deadline show it. Email caches can retain an earlier image; a GIF already frozen after its 60 seconds cannot refresh itself. Keep the deadline in text and choose alt text suitable for both states.', 'actnow-countdown-timers' ); ?></p>
                    </section>
                    <section class="ect-card" aria-labelledby="ect-accessibility-title"><h3 id="ect-accessibility-title"><?php echo esc_html__( 'Accessibility', 'actnow-countdown-timers' ); ?></h3>
                        <?php Email_Countdown_Timer_Admin::input( $email_countdown_timer_ui, 'alt', __( 'Image alternative text (alt)', 'actnow-countdown-timers' ), 'text', __( 'Describe the timer purpose, not its changing seconds. Used by the website shortcode and new Email HTML. Leave empty only when nearby text provides the same information. Save, then copy updated HTML; already sent emails cannot be changed. Maximum 1000 bytes.', 'actnow-countdown-timers' ) ); ?>
                    </section>
                    <div class="ect-save-row"><button type="submit" class="button button-primary"><?php echo esc_html( '' !== $email_countdown_timer_ui['original'] ? __( 'Save Changes', 'actnow-countdown-timers' ) : __( 'Create Timer', 'actnow-countdown-timers' ) ); ?></button><a class="button" href="<?php echo esc_url( Email_Countdown_Timer_Admin::url() ); ?>"><?php echo esc_html__( 'Back to Timers', 'actnow-countdown-timers' ); ?></a></div>
                </form>
                <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                    <details class="ect-card ect-danger"><summary><?php echo esc_html__( 'Delete Timer', 'actnow-countdown-timers' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( Email_Countdown_Timer_Admin::url( array( 'edit' => $email_countdown_timer_ui['original'] ) ) ); ?>" id="ect-delete">
                            <input type="hidden" name="ecd_action" value="email_countdown_timer_delete"><input type="hidden" name="timer_id" value="<?php echo esc_attr( $email_countdown_timer_ui['original'] ); ?>"><?php wp_nonce_field( 'email_countdown_timer_admin' ); ?>
                            <p><?php echo esc_html( sprintf( /* translators: %s: timer ID. */ __( 'Permanently delete %s? Existing emails and pages using this timer will stop receiving countdown images.', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_ui['saved'] ?? array(), $email_countdown_timer_ui['original'], $email_countdown_timer_ui['name_collisions'] ) ) ); ?></p>
                            <label class="ect-check"><input name="confirm_delete" type="checkbox" value="1" required><?php echo esc_html__( 'I understand this cannot be undone.', 'actnow-countdown-timers' ); ?></label>
                            <button type="submit" class="button ect-delete-button"><?php echo esc_html__( 'Delete permanently', 'actnow-countdown-timers' ); ?></button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
            <aside aria-label="<?php echo esc_attr__( 'Preview and embedding', 'actnow-countdown-timers' ); ?>">
                <section class="ect-card"><h3><?php echo esc_html__( 'Saved Preview', 'actnow-countdown-timers' ); ?></h3>
                    <p id="ect-preview-help"><?php echo esc_html__( 'Shows the saved configuration, not unsaved changes. Save to update.', 'actnow-countdown-timers' ); ?></p>
                    <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                        <?php $email_countdown_timer_image_url = add_query_arg( array( 'ecd_action' => 'render', 'ecd' => $email_countdown_timer_ui['original'], 'mode' => 'email' ), home_url( '/' ) ); $email_countdown_timer_static_url = add_query_arg( 'mode', 'static', $email_countdown_timer_image_url ); ?>
                        <?php $email_countdown_timer_frame_size = Email_Countdown_Timer_Frame::dimensions($email_countdown_timer_ui['saved']); ?>
                        <iframe id="ect-preview" src="<?php echo esc_url(Email_Countdown_Timer_Frame::url($email_countdown_timer_ui['original'])); ?>" title="<?php echo esc_attr__('Saved countdown preview','actnow-countdown-timers'); ?>" width="<?php echo esc_attr((string)$email_countdown_timer_frame_size['width']); ?>" height="<?php echo esc_attr((string)$email_countdown_timer_frame_size['height']); ?>" style="border:0;max-width:100%" sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe>
                        <p id="ect-image-error" class="ect-error" role="alert" hidden><?php echo esc_html__('Countdown preview is unavailable. Reload or check rendering diagnostics.','actnow-countdown-timers'); ?></p>
                        <p><strong><?php echo esc_html__('Ends:','actnow-countdown-timers'); ?></strong> <?php echo esc_html(Email_Countdown_Timer_Admin::deadline_text($email_countdown_timer_ui['saved'])); ?></p>
                    <?php else : ?><p class="ect-empty"><?php echo esc_html__( 'Create this timer to see its image and embed codes.', 'actnow-countdown-timers' ); ?></p><?php endif; ?>
                </section>
                <section class="ect-card" id="ect-embed" tabindex="-1"><h3><?php echo esc_html__( 'Embed Codes', 'actnow-countdown-timers' ); ?></h3>
                    <?php if ( $email_countdown_timer_ui['saved'] ) : ?>
                        <?php
                        $email_countdown_timer_deadline = Email_Countdown_Timer_Admin::deadline_text( $email_countdown_timer_ui['saved'] );
                        $email_countdown_timer_email_html = '<img class="email-countdown-timer-image" loading="eager" data-no-lazy="1" referrerpolicy="no-referrer" src="' . esc_url( $email_countdown_timer_image_url ) . '" alt="' . esc_attr( Email_Countdown_Timer_Config::alt( $email_countdown_timer_ui['saved'], sprintf( /* translators: %s: absolute deadline and time zone. */ __( 'Countdown ends %s', 'actnow-countdown-timers' ), $email_countdown_timer_deadline ) ) ) . '" style="display:block;max-width:100%;height:auto;border:0;">';
                        $email_countdown_timer_email_html = Email_Countdown_Timer_Embed::reserve( $email_countdown_timer_email_html, $email_countdown_timer_ui['saved'], true );
                        $email_countdown_timer_shortcode = '[ecd_timer id="' . esc_attr( $email_countdown_timer_ui['original'] ) . '"]';
                        ?>
                        <section class="ect-disclosure" aria-labelledby="ect-website-title">
                            <h4 id="ect-website-title"><?php echo esc_html__( 'Website', 'actnow-countdown-timers' ); ?></h4>
                            <p><?php echo esc_html__( 'In the block editor, add the ActNow – Countdown Timers block and select this timer. In other editors, paste the shortcode below. Website embeds refresh when the page becomes visible again.', 'actnow-countdown-timers' ); ?></p>
                            <label for="ect-manual-shortcode"><?php echo esc_html__( 'Website Shortcode', 'actnow-countdown-timers' ); ?></label><textarea id="ect-manual-shortcode" rows="2" readonly spellcheck="false"><?php echo esc_textarea( $email_countdown_timer_shortcode ); ?></textarea>
                            <button type="button" class="button" data-ect-copy="ect-manual-shortcode" hidden data-success="<?php echo esc_attr__( 'Copied to clipboard.', 'actnow-countdown-timers' ); ?>" data-fallback="<?php echo esc_attr__( 'Copy was unavailable. The code is selected; use your keyboard or device copy command.', 'actnow-countdown-timers' ); ?>"><?php echo esc_html__( 'Copy shortcode', 'actnow-countdown-timers' ); ?></button><p id="ect-manual-shortcode-result" class="description"></p>
                        </section>
                        <section class="ect-disclosure" aria-labelledby="ect-iframe-title">
                            <h4 id="ect-iframe-title"><?php echo esc_html__('External website (iframe)','actnow-countdown-timers'); ?></h4>
                            <p><?php echo esc_html__('Paste this HTML on another website. The countdown runs locally in the frame with the saved fonts, colors and labels. Iframes are for websites, not email. Your hosting must allow this frame to load on other sites.','actnow-countdown-timers'); ?></p>
                            <?php $email_countdown_timer_frame_html = '<iframe src="'.esc_url(Email_Countdown_Timer_Frame::url($email_countdown_timer_ui['original'])).'" title="'.esc_attr__('Countdown timer','actnow-countdown-timers').'" width="'.(int)$email_countdown_timer_frame_size['width'].'" height="'.(int)$email_countdown_timer_frame_size['height'].'" style="border:0;max-width:100%;" loading="lazy" sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe>'; ?>
                            <label for="ect-manual-iframe"><?php echo esc_html__('Iframe HTML','actnow-countdown-timers'); ?></label><textarea id="ect-manual-iframe" rows="5" readonly><?php echo esc_textarea($email_countdown_timer_frame_html); ?></textarea>
                            <button type="button" class="button" data-ect-copy="ect-manual-iframe" hidden data-success="<?php echo esc_attr__('Copied to clipboard.','actnow-countdown-timers'); ?>" data-fallback="<?php echo esc_attr__('Select and copy manually.','actnow-countdown-timers'); ?>"><?php echo esc_html__('Copy iframe HTML','actnow-countdown-timers'); ?></button><p id="ect-manual-iframe-result" class="description"></p>
                        </section>
                        <section class="ect-disclosure" aria-labelledby="ect-email-title">
                            <h4 id="ect-email-title"><?php echo esc_html__( 'Email', 'actnow-countdown-timers' ); ?></h4>
                            <p><?php echo esc_html__( 'Paste Email HTML into your mailing platform’s HTML editor, or use the Image URL in an image block. WordPress shortcodes do not work in email.', 'actnow-countdown-timers' ); ?></p>
                            <label for="ect-manual-html"><?php echo esc_html__( 'Email HTML', 'actnow-countdown-timers' ); ?></label><textarea id="ect-manual-html" rows="6" readonly><?php echo esc_textarea( $email_countdown_timer_email_html ); ?></textarea>
                            <button type="button" class="button" data-ect-copy="ect-manual-html" hidden data-success="<?php echo esc_attr__( 'Copied to clipboard.', 'actnow-countdown-timers' ); ?>" data-fallback="<?php echo esc_attr__( 'Copy was unavailable. The code is selected; use your keyboard or device copy command.', 'actnow-countdown-timers' ); ?>"><?php echo esc_html__( 'Copy Email HTML', 'actnow-countdown-timers' ); ?></button><p id="ect-manual-html-result" class="description"></p>
                            <label for="ect-embed-code"><?php echo esc_html__( 'Image URL', 'actnow-countdown-timers' ); ?></label><textarea id="ect-embed-code" rows="4" readonly spellcheck="false"><?php echo esc_textarea( $email_countdown_timer_image_url ); ?></textarea>
                            <button type="button" class="button" data-ect-copy="ect-embed-code" hidden data-success="<?php echo esc_attr__( 'Copied to clipboard.', 'actnow-countdown-timers' ); ?>" data-fallback="<?php echo esc_attr__( 'Copy was unavailable. The code is selected; use your keyboard or device copy command.', 'actnow-countdown-timers' ); ?>"><?php echo esc_html__( 'Copy image URL', 'actnow-countdown-timers' ); ?></button><p id="ect-embed-code-result" class="description"></p>
                            <p class="description"><?php echo esc_html__( 'Email GIFs play for 60 seconds; an already downloaded image cannot refresh itself. Clients may cache, prefetch or block images. Always include the absolute deadline as visible text, and send a test email.', 'actnow-countdown-timers' ); ?></p>
                        </section>
                    <?php else : ?><p><?php echo esc_html__( 'Embed codes are available after the first successful save.', 'actnow-countdown-timers' ); ?></p><?php endif; ?>
                </section>
            </aside>
        </div>
        <dialog id="ect-delete-dialog" aria-labelledby="ect-dialog-title" aria-describedby="ect-dialog-description"><h2 id="ect-dialog-title"><?php echo esc_html__( 'Delete Timer?', 'actnow-countdown-timers' ); ?></h2><p id="ect-dialog-description"><?php echo esc_html( sprintf( /* translators: %s: timer ID. */ __( 'Permanently delete %s? This cannot be undone and will break existing embeds.', 'actnow-countdown-timers' ), Email_Countdown_Timer_Admin::display_name( $email_countdown_timer_ui['saved'] ?? array(), $email_countdown_timer_ui['original'], $email_countdown_timer_ui['name_collisions'] ) ) ); ?></p><div class="ect-inline"><button type="button" class="button" id="ect-delete-cancel" autofocus><?php echo esc_html__( 'Cancel', 'actnow-countdown-timers' ); ?></button><button type="button" class="button ect-delete-button" id="ect-delete-confirm"><?php echo esc_html__( 'Delete permanently', 'actnow-countdown-timers' ); ?></button></div></dialog>
    <?php endif; ?>
</div>
