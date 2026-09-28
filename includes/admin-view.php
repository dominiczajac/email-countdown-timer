<?php
/** English admin UI; included only by the controller. */
if (!defined('ABSPATH')) exit;

        if (!current_user_can('manage_options')) return;
        $email_countdown_timer_timers = $this->getTimers();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin selection; capability checked above and ID normalized.
        $email_countdown_timer_editId = Email_Countdown_Timer_Config::id(Email_Countdown_Timer_Config::text($_GET, 'edit'));
        $email_countdown_timer_data = ($email_countdown_timer_editId && isset($email_countdown_timer_timers[$email_countdown_timer_editId])) ? $email_countdown_timer_timers[$email_countdown_timer_editId] : [];
        try { $email_countdown_timer_data = $email_countdown_timer_data ? Email_Countdown_Timer_Config::normalize($email_countdown_timer_data) : []; } catch (InvalidArgumentException $email_countdown_timer_e) { $email_countdown_timer_data = []; }

        $email_countdown_timer_deadline = $email_countdown_timer_data['deadline'] ?? wp_date('Y-12-31\T23:59:59');
        $email_countdown_timer_deadline = str_replace(' ', 'T', $email_countdown_timer_deadline);
        $email_countdown_timer_tz = !empty($email_countdown_timer_data['tz']) ? $email_countdown_timer_data['tz'] : 'Europe/Warsaw';
        $email_countdown_timer_bg = $email_countdown_timer_data['bg'] ?? '#FFFFFF';
        $email_countdown_timer_dc = $email_countdown_timer_data['dc'] ?? '#000000';
        $email_countdown_timer_lc = $email_countdown_timer_data['lc'] ?? '#666666';
        $email_countdown_timer_currentFont = $email_countdown_timer_data['font'] ?? '';
        $email_countdown_timer_sDigit = $email_countdown_timer_data['size_digit'] ?? 40;
        $email_countdown_timer_sLabel = $email_countdown_timer_data['size_label'] ?? 12;
        $email_countdown_timer_hideDays = !empty($email_countdown_timer_data['hide_days']);
        $email_countdown_timer_fixedW = isset($email_countdown_timer_data['fixed_width']) ? (int)$email_countdown_timer_data['fixed_width'] : 0;

        $email_countdown_timer_lD = $email_countdown_timer_data['label_d'] ?? 'Days';
        $email_countdown_timer_lH = $email_countdown_timer_data['label_h'] ?? 'Hours';
        $email_countdown_timer_lM = $email_countdown_timer_data['label_m'] ?? 'Minutes';
        $email_countdown_timer_lS = $email_countdown_timer_data['label_s'] ?? 'Seconds';

        $email_countdown_timer_fonts = Email_Countdown_Timer_Config::fonts();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Easy Countdown v12.1.3', 'email-countdown-timer'); ?></h1>
            <div style="display:flex; flex-wrap:wrap; gap:20px;">
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; min-width:300px;">
                    <h2><?php echo $email_countdown_timer_editId ? esc_html(sprintf( /* translators: %s: timer identifier. */ __('Edit: %s', 'email-countdown-timer'), $email_countdown_timer_editId)) : esc_html__('New Timer', 'email-countdown-timer'); ?></h2>
                    <form method="post" action="">
                        <input type="hidden" name="ecd_action" value="save_timer">
                        <?php wp_nonce_field('ecd_save_timer_nonce'); ?>
                        <table class="form-table">
                            <tr><th><?php echo esc_html__('Timer ID', 'email-countdown-timer'); ?></th><td><input type="text" name="timer_id" value="<?php echo esc_attr($email_countdown_timer_editId ?? ''); ?>" required <?php echo $email_countdown_timer_editId ? 'readonly' : ''; ?>></td></tr>
                            <tr><th><?php echo esc_html__('Deadline', 'email-countdown-timer'); ?></th><td><input type="datetime-local" step="1" name="deadline" value="<?php echo esc_attr($email_countdown_timer_deadline); ?>" required></td></tr>
                            <tr><th><?php echo esc_html__('Time Zone', 'email-countdown-timer'); ?></th><td><input type="text" name="tz" value="<?php echo esc_attr($email_countdown_timer_tz); ?>" placeholder="Europe/Warsaw"></td></tr>

                            <tr><th><?php echo esc_html__('Width (px)', 'email-countdown-timer'); ?></th><td>
                                <input type="number" name="fixed_width" value="<?php echo esc_attr($email_countdown_timer_fixedW); ?>" placeholder="0">
                                <p class="description"><?php echo esc_html__('Enter a minimum width, such as 600. Use 0 for automatic sizing.', 'email-countdown-timer'); ?> <br><?php echo esc_html__('Height fits the content with 10px padding.', 'email-countdown-timer'); ?></p>
                            </td></tr>

                            <tr><th><?php echo esc_html__('Colors', 'email-countdown-timer'); ?></th><td><?php echo esc_html__('Background:', 'email-countdown-timer'); ?> <input type="color" name="bg" value="<?php echo esc_attr($email_countdown_timer_bg); ?>"> <?php echo esc_html__('Digits:', 'email-countdown-timer'); ?> <input type="color" name="dc" value="<?php echo esc_attr($email_countdown_timer_dc); ?>"> <?php echo esc_html__('Labels:', 'email-countdown-timer'); ?> <input type="color" name="lc" value="<?php echo esc_attr($email_countdown_timer_lc); ?>"></td></tr>

                            <tr><th><?php echo esc_html__('Labels', 'email-countdown-timer'); ?></th><td>
                                <div style="display:flex; gap:10px;">
                                    <div><input type="text" name="label_d" value="<?php echo esc_attr($email_countdown_timer_lD); ?>" style="width:50px"><br><small><?php echo esc_html__('Days', 'email-countdown-timer'); ?></small></div>
                                    <div><input type="text" name="label_h" value="<?php echo esc_attr($email_countdown_timer_lH); ?>" style="width:50px"><br><small><?php echo esc_html__('Hours', 'email-countdown-timer'); ?></small></div>
                                    <div><input type="text" name="label_m" value="<?php echo esc_attr($email_countdown_timer_lM); ?>" style="width:50px"><br><small><?php echo esc_html__('Minutes', 'email-countdown-timer'); ?></small></div>
                                    <div><input type="text" name="label_s" value="<?php echo esc_attr($email_countdown_timer_lS); ?>" style="width:50px"><br><small><?php echo esc_html__('Seconds', 'email-countdown-timer'); ?></small></div>
                                </div>
                            </td></tr>

                            <tr><th><?php echo esc_html__('Font', 'email-countdown-timer'); ?></th><td>
                                <?php if(empty($email_countdown_timer_fonts)): ?><p style="color:red; margin:0;"><?php echo esc_html__('No TTF/OTF font files found in', 'email-countdown-timer'); ?> <code>/fonts/</code>.</p><?php else: ?>
                                <select name="font"><option value=""><?php echo esc_html__('-- Default system font --', 'email-countdown-timer'); ?></option><?php foreach($email_countdown_timer_fonts as $email_countdown_timer_f): ?><option value="<?php echo esc_attr($email_countdown_timer_f); ?>" <?php selected($email_countdown_timer_currentFont, $email_countdown_timer_f); ?>><?php echo esc_html($email_countdown_timer_f); ?></option><?php endforeach; ?></select>
                                <?php endif; ?>
                                <div style="margin-top:10px;"><?php echo esc_html__('Digit Size:', 'email-countdown-timer'); ?> <input type="number" name="size_digit" value="<?php echo esc_attr($email_countdown_timer_sDigit); ?>" style="width:60px"> px<br><?php echo esc_html__('Label Size:', 'email-countdown-timer'); ?> <input type="number" name="size_label" value="<?php echo esc_attr($email_countdown_timer_sLabel); ?>" style="width:60px"> px</div>
                            </td></tr>
                            <tr><th><?php echo esc_html__('Options', 'email-countdown-timer'); ?></th><td><label><input type="checkbox" name="hide_days" value="1" <?php checked($email_countdown_timer_hideDays); ?>> <?php echo esc_html__('Hide days when fewer than 24 hours remain', 'email-countdown-timer'); ?></label></td></tr>
                            <?php if($email_countdown_timer_editId): ?><tr><th><?php echo esc_html__('Deletion', 'email-countdown-timer'); ?></th><td><label style="color:red"><input type="checkbox" name="delete_timer" value="1"> <?php echo esc_html__('Delete permanently', 'email-countdown-timer'); ?></label></td></tr><?php endif; ?>
                        </table>
                        <?php submit_button($email_countdown_timer_editId ? __('Save Changes', 'email-countdown-timer') : __('Create Timer', 'email-countdown-timer')); ?>
                        <?php if($email_countdown_timer_editId): ?><a href="?page=ecd-timers" class="button"><?php echo esc_html__('Back', 'email-countdown-timer'); ?></a><?php endif; ?>
                    </form>
                </div>
                <div style="flex:2; min-width:400px;">
                    <h2><?php echo esc_html__('Your Timers', 'email-countdown-timer'); ?></h2>
                    <?php if(empty($email_countdown_timer_timers)): ?><p><?php echo esc_html__('No timers yet.', 'email-countdown-timer'); ?></p><?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th style="width:100px"><?php echo esc_html__('ID / Deadline', 'email-countdown-timer'); ?></th><th><?php echo esc_html__('Embed Codes', 'email-countdown-timer'); ?></th><th style="width:60px"><?php echo esc_html__('Action', 'email-countdown-timer'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach($email_countdown_timer_timers as $email_countdown_timer_tid => $email_countdown_timer_t):
                                $email_countdown_timer_mailUrl = add_query_arg(['ecd_action' => 'render', 'ecd' => $email_countdown_timer_tid, 'mode' => 'email'], home_url('/'));
                                $email_countdown_timer_fw = !empty($email_countdown_timer_t['fixed_width']) && is_scalar($email_countdown_timer_t['fixed_width']) ? (string)$email_countdown_timer_t['fixed_width'] : 'auto';
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($email_countdown_timer_tid); ?></strong><br><small><?php echo esc_html(Email_Countdown_Timer_Config::text($email_countdown_timer_t, 'deadline')); ?></small></td>
                                    <td>
                                        <div style="margin-bottom:10px;"><strong><?php echo esc_html__('Shortcode (Website):', 'email-countdown-timer'); ?></strong><br><code>[ecd_timer id="<?php echo esc_attr($email_countdown_timer_tid); ?>"]</code></div>
                                        <div><strong><?php echo esc_html__('Email Image URL (GIF):', 'email-countdown-timer'); ?></strong><br>
                                        <input type="text" value="<?php echo esc_attr($email_countdown_timer_mailUrl); ?>" class="large-text code" readonly onclick="this.select()"></div>
                                        <div><small><?php echo esc_html__('Width:', 'email-countdown-timer'); ?> <strong><?php echo esc_html($email_countdown_timer_fw); ?></strong></small></div>
                                    </td>
                                    <td><a href="<?php echo esc_url(add_query_arg(['page' => 'ecd-timers', 'edit' => $email_countdown_timer_tid], admin_url('admin.php'))); ?>" class="button button-small"><?php echo esc_html__('Edit', 'email-countdown-timer'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
