<?php
/** English admin UI; included only by the controller. */
if (!defined('ABSPATH')) exit;

        if (!current_user_can('manage_options')) return;
        $timers = $this->getTimers();
        $editId = ECD_Config::id(ECD_Config::text($_GET, 'edit'));
        $data = ($editId && isset($timers[$editId])) ? $timers[$editId] : [];
        try { $data = $data ? ECD_Config::normalize($data) : []; } catch (InvalidArgumentException $e) { $data = []; }
        
        $deadline = $data['deadline'] ?? wp_date('Y-12-31\T23:59:59');
        $deadline = str_replace(' ', 'T', $deadline);
        $tz = !empty($data['tz']) ? $data['tz'] : 'Europe/Warsaw';
        $bg = $data['bg'] ?? '#FFFFFF';
        $dc = $data['dc'] ?? '#000000';
        $lc = $data['lc'] ?? '#666666';
        $currentFont = $data['font'] ?? '';
        $sDigit = $data['size_digit'] ?? 40;
        $sLabel = $data['size_label'] ?? 12;
        $hideDays = !empty($data['hide_days']);
        $fixedW = isset($data['fixed_width']) ? (int)$data['fixed_width'] : 0;
        
        $lD = $data['label_d'] ?? 'Days';
        $lH = $data['label_h'] ?? 'Hours';
        $lM = $data['label_m'] ?? 'Minutes';
        $lS = $data['label_s'] ?? 'Seconds';

        $fonts = ECD_Config::fonts();
        ?>
        <div class="wrap">
            <h1>Easy Countdown v12.1.2</h1>
            <div style="display:flex; flex-wrap:wrap; gap:20px;">
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; min-width:300px;">
                    <h2><?php echo esc_html($editId ? "Edit: $editId" : "New Timer"); ?></h2>
                    <form method="post" action="">
                        <input type="hidden" name="ecd_action" value="save_timer">
                        <?php wp_nonce_field('ecd_save_timer_nonce'); ?>
                        <table class="form-table">
                            <tr><th>Timer ID</th><td><input type="text" name="timer_id" value="<?php echo esc_attr($editId ?? ''); ?>" required <?php echo $editId ? 'readonly' : ''; ?>></td></tr>
                            <tr><th>Deadline</th><td><input type="datetime-local" step="1" name="deadline" value="<?php echo esc_attr($deadline); ?>" required></td></tr>
                            <tr><th>Time Zone</th><td><input type="text" name="tz" value="<?php echo esc_attr($tz); ?>" placeholder="Europe/Warsaw"></td></tr>
                            
                            <tr><th>Width (px)</th><td>
                                <input type="number" name="fixed_width" value="<?php echo $fixedW; ?>" placeholder="0">
                                <p class="description">Enter a minimum width, such as 600. Use 0 for automatic sizing. <br>Height fits the content with 10px padding.</p>
                            </td></tr>

                            <tr><th>Colors</th><td>Background: <input type="color" name="bg" value="<?php echo esc_attr($bg); ?>"> Digits: <input type="color" name="dc" value="<?php echo esc_attr($dc); ?>"> Labels: <input type="color" name="lc" value="<?php echo esc_attr($lc); ?>"></td></tr>
                            
                            <tr><th>Labels</th><td>
                                <div style="display:flex; gap:10px;">
                                    <div><input type="text" name="label_d" value="<?php echo esc_attr($lD); ?>" style="width:50px"><br><small>Days</small></div>
                                    <div><input type="text" name="label_h" value="<?php echo esc_attr($lH); ?>" style="width:50px"><br><small>Hours</small></div>
                                    <div><input type="text" name="label_m" value="<?php echo esc_attr($lM); ?>" style="width:50px"><br><small>Minutes</small></div>
                                    <div><input type="text" name="label_s" value="<?php echo esc_attr($lS); ?>" style="width:50px"><br><small>Seconds</small></div>
                                </div>
                            </td></tr>

                            <tr><th>Font</th><td>
                                <?php if(empty($fonts)): ?><p style="color:red; margin:0;">No TTF/OTF font files found in <code>/fonts/</code>.</p><?php else: ?>
                                <select name="font"><option value="">-- Default system font --</option><?php foreach($fonts as $f): ?><option value="<?php echo esc_attr($f); ?>" <?php selected($currentFont, $f); ?>><?php echo esc_html($f); ?></option><?php endforeach; ?></select>
                                <?php endif; ?>
                                <div style="margin-top:10px;">Digit Size: <input type="number" name="size_digit" value="<?php echo $sDigit; ?>" style="width:60px"> px<br>Label Size: <input type="number" name="size_label" value="<?php echo $sLabel; ?>" style="width:60px"> px</div>
                            </td></tr>
                            <tr><th>Options</th><td><label><input type="checkbox" name="hide_days" value="1" <?php checked($hideDays); ?>> Hide days when fewer than 24 hours remain</label></td></tr>
                            <?php if($editId): ?><tr><th>Deletion</th><td><label style="color:red"><input type="checkbox" name="delete_timer" value="1"> Delete permanently</label></td></tr><?php endif; ?>
                        </table>
                        <?php submit_button($editId ? 'Save Changes' : 'Create Timer'); ?>
                        <?php if($editId): ?><a href="?page=ecd-timers" class="button">Back</a><?php endif; ?>
                    </form>
                </div>
                <div style="flex:2; min-width:400px;">
                    <h2>Your Timers</h2>
                    <?php if(empty($timers)): ?><p>No timers yet.</p><?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th style="width:100px">ID / Deadline</th><th>Embed Codes</th><th style="width:60px">Action</th></tr></thead>
                            <tbody>
                            <?php foreach($timers as $tid => $t): 
                                $mailUrl = add_query_arg(['ecd_action' => 'render', 'ecd' => $tid, 'mode' => 'email'], home_url('/'));
                                $fw = !empty($t['fixed_width']) && is_scalar($t['fixed_width']) ? (string)$t['fixed_width'] : 'auto';
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($tid); ?></strong><br><small><?php echo esc_html(ECD_Config::text($t, 'deadline')); ?></small></td>
                                    <td>
                                        <div style="margin-bottom:10px;"><strong>Shortcode (Website):</strong><br><code>[ecd_timer id="<?php echo esc_attr($tid); ?>"]</code></div>
                                        <div><strong>Email Image URL (GIF):</strong><br>
                                        <input type="text" value="<?php echo esc_attr($mailUrl); ?>" class="large-text code" readonly onclick="this.select()"></div>
                                        <div><small>Width: <strong><?php echo esc_html($fw); ?></strong></small></div>
                                    </td>
                                    <td><a href="<?php echo esc_url(add_query_arg(['page' => 'ecd-timers', 'edit' => $tid], admin_url('admin.php'))); ?>" class="button button-small">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
