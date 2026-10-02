<?php
/** Arm a short synthetic campaign immediately before the native-client test. */
if (PHP_SAPI !== 'cli' || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectdelivery_') { exit(1); }
require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
$timers = get_option('easy_countdown_timers', array());
$data = $timers['layout-ended'];
$data['tz'] = 'UTC';
$data['deadline'] = gmdate('Y-m-d\TH:i:s', time() + 32);
$data['fixed_width'] = 600;
$data['hide_days'] = 0;
$timers['native-crossing'] = $data;
update_option('easy_countdown_timers', $timers, false);
$url = add_query_arg(array('ecd_action'=>'render','ecd'=>'native-crossing','mode'=>'email'), home_url('/'));
$html = Email_Countdown_Timer_Embed::reserve('<img src="'.esc_url($url).'" alt="'.esc_attr($data['alt']).'">', $data, true);
$html .= '<p>Ends: '.esc_html(Email_Countdown_Timer_Admin::deadline_text($data)).'</p>';
file_put_contents(getenv('ECD_DELIVERY_EVIDENCE').'/native-crossing.json', wp_json_encode(array('html'=>$html,'deadline'=>Email_Countdown_Timer_Config::deadline($data),'width'=>600), JSON_PRETTY_PRINT));
