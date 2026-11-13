<?php
if(!defined('WP_CLI') || !WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE')!=='1' || wp_get_environment_type()!=='local') throw new RuntimeException('Disposable test installation required.');
$c=Email_Countdown_Timer_Config::normalize(array('deadline'=>'2035-12-31T23:59:59','tz'=>'UTC','font'=>Email_Countdown_Timer_Fonts::BARLOW_CONDENSED_BLACK,'fixed_width'=>600,'hide_days'=>1,'label_d'=>'Dni','label_h'=>'Godzin','label_m'=>'','label_s'=>'Sekund'));
$timers=get_option('easy_countdown_timers',array());$timers['frame-live']=$c+array('campaign_name'=>'PRIVATE-FRAME-ONLY');
$timers['frame-invalid']=array_replace($c,array('fixed_width'=>5000));
$timers['frame-expire']=array_replace($c,array('deadline'=>gmdate('Y-m-d\TH:i:s',time()+4),'expiry_image_id'=>(int)getenv('ECD_BROWSER_END_ID')));
update_option('easy_countdown_timers',$timers,false);
