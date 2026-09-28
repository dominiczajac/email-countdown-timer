<?php
/** Legacy Polish admin UI; included only by the controller. */
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
        
        $lD = $data['label_d'] ?? 'Dni';
        $lH = $data['label_h'] ?? 'Godz';
        $lM = $data['label_m'] ?? 'Min';
        $lS = $data['label_s'] ?? 'Sek';

        $fonts = ECD_Config::fonts();
        ?>
        <div class="wrap">
            <h1>Easy Countdown v12.1.1</h1>
            <div style="display:flex; flex-wrap:wrap; gap:20px;">
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; flex:1; min-width:300px;">
                    <h2><?php echo esc_html($editId ? "Edytuj: $editId" : "Nowy Licznik"); ?></h2>
                    <form method="post" action="">
                        <input type="hidden" name="ecd_action" value="save_timer">
                        <?php wp_nonce_field('ecd_save_timer_nonce'); ?>
                        <table class="form-table">
                            <tr><th>ID Licznika</th><td><input type="text" name="timer_id" value="<?php echo esc_attr($editId ?? ''); ?>" required <?php echo $editId ? 'readonly' : ''; ?>></td></tr>
                            <tr><th>Data Końca</th><td><input type="datetime-local" step="1" name="deadline" value="<?php echo esc_attr($deadline); ?>" required></td></tr>
                            <tr><th>Strefa Czasowa</th><td><input type="text" name="tz" value="<?php echo esc_attr($tz); ?>" placeholder="Europe/Warsaw"></td></tr>
                            
                            <tr><th>Szerokość (px)</th><td>
                                <input type="number" name="fixed_width" value="<?php echo $fixedW; ?>" placeholder="0">
                                <p class="description">Wpisz np. 600 dla stałej szerokości. 0 = dopasowanie automatyczne. <br>Wysokość dopasuje się "na styk" (10px margines).</p>
                            </td></tr>

                            <tr><th>Kolory</th><td>Tło: <input type="color" name="bg" value="<?php echo esc_attr($bg); ?>"> Cyfry: <input type="color" name="dc" value="<?php echo esc_attr($dc); ?>"> Tekst: <input type="color" name="lc" value="<?php echo esc_attr($lc); ?>"></td></tr>
                            
                            <tr><th>Etykiety</th><td>
                                <div style="display:flex; gap:10px;">
                                    <div><input type="text" name="label_d" value="<?php echo esc_attr($lD); ?>" style="width:50px"><br><small>Dni</small></div>
                                    <div><input type="text" name="label_h" value="<?php echo esc_attr($lH); ?>" style="width:50px"><br><small>Godz</small></div>
                                    <div><input type="text" name="label_m" value="<?php echo esc_attr($lM); ?>" style="width:50px"><br><small>Min</small></div>
                                    <div><input type="text" name="label_s" value="<?php echo esc_attr($lS); ?>" style="width:50px"><br><small>Sek</small></div>
                                </div>
                            </td></tr>

                            <tr><th>Czcionka</th><td>
                                <?php if(empty($fonts)): ?><p style="color:red; margin:0;">Brak plików .ttf w folderze <code>/fonts/</code>.</p><?php else: ?>
                                <select name="font"><option value="">-- Domyślna systemowa --</option><?php foreach($fonts as $f): ?><option value="<?php echo esc_attr($f); ?>" <?php selected($currentFont, $f); ?>><?php echo esc_html($f); ?></option><?php endforeach; ?></select>
                                <?php endif; ?>
                                <div style="margin-top:10px;">Rozmiar Cyfr: <input type="number" name="size_digit" value="<?php echo $sDigit; ?>" style="width:60px"> px<br>Rozmiar Etykiet: <input type="number" name="size_label" value="<?php echo $sLabel; ?>" style="width:60px"> px</div>
                            </td></tr>
                            <tr><th>Opcje</th><td><label><input type="checkbox" name="hide_days" value="1" <?php checked($hideDays); ?>> Ukryj dni, jeśli zostało < 24h</label></td></tr>
                            <?php if($editId): ?><tr><th>Usuwanie</th><td><label style="color:red"><input type="checkbox" name="delete_timer" value="1"> Usuń trwale</label></td></tr><?php endif; ?>
                        </table>
                        <?php submit_button($editId ? 'Zapisz Zmiany' : 'Utwórz Licznik'); ?>
                        <?php if($editId): ?><a href="?page=ecd-timers" class="button">Wróć</a><?php endif; ?>
                    </form>
                </div>
                <div style="flex:2; min-width:400px;">
                    <h2>Twoje Liczniki</h2>
                    <?php if(empty($timers)): ?><p>Brak liczników.</p><?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr><th style="width:100px">ID / Data</th><th>Kody do wklejenia</th><th style="width:60px">Akcja</th></tr></thead>
                            <tbody>
                            <?php foreach($timers as $tid => $t): 
                                $mailUrl = add_query_arg(['ecd_action' => 'render', 'ecd' => $tid, 'mode' => 'email'], home_url('/'));
                                $fw = !empty($t['fixed_width']) && is_scalar($t['fixed_width']) ? (string)$t['fixed_width'] : 'auto';
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($tid); ?></strong><br><small><?php echo esc_html(ECD_Config::text($t, 'deadline')); ?></small></td>
                                    <td>
                                        <div style="margin-bottom:10px;"><strong>Shortcode (WWW):</strong><br><code>[ecd_timer id="<?php echo esc_attr($tid); ?>"]</code></div>
                                        <div><strong>Link do mailingu (GIF):</strong><br>
                                        <input type="text" value="<?php echo esc_attr($mailUrl); ?>" class="large-text code" readonly onclick="this.select()"></div>
                                        <div><small>Szerokość: <strong><?php echo esc_html($fw); ?></strong></small></div>
                                    </td>
                                    <td><a href="<?php echo esc_url(add_query_arg(['page' => 'ecd-timers', 'edit' => $tid], admin_url('admin.php'))); ?>" class="button button-small">Edytuj</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
