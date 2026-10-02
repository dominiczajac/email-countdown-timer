<?php
/** Synthetic fixtures and assertions against actual WordPress and native GD. */
if (PHP_SAPI !== 'cli' || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectdelivery_') { exit(1); }
require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
$checks = array();
$check = static function ($ok, $name) use (&$checks) { if (!$ok) { throw new RuntimeException($name); } $checks[] = $name; };
$data = Email_Countdown_Timer_Config::normalize(array('deadline'=>'2035-12-31T23:59:59','fixed_width'=>0,'hide_days'=>1,'alt'=>'Offer countdown'));
$timers = array('layout-live'=>$data);
$expired = $data; $expired['deadline']='2001-01-01T00:00:05';
$up=wp_upload_dir(); $path=$up['path'].'/delivery-end.png';
$im=imagecreatetruecolor(600,53); imagefill($im,0,0,imagecolorallocate($im,25,180,90)); imagepng($im,$path); unset($im);
$id=wp_insert_attachment(array('post_title'=>'Synthetic end image','post_mime_type'=>'image/png','post_status'=>'inherit','post_author'=>1),$path);
$expired['expiry_image_id']=$id; $timers['layout-ended']=$expired;
update_option('easy_countdown_timers',$timers,false);
$html=do_shortcode('[ecd_timer id="layout-live"]');
$p=new WP_HTML_Tag_Processor($html); $check($p->next_tag('IMG'),'shortcode has image');
$size=(new Email_Countdown_Timer_Renderer())->measure($data,Email_Countdown_Timer_Config::deadline($data),time());
$check((int)$p->get_attribute('width')===$size['width'] && (int)$p->get_attribute('height')===$size['height'],'attributes match renderer geometry');
$check(str_contains($p->get_attribute('style'),'aspect-ratio:'),'stable explicit ratio');
$check($p->get_attribute('decoding')==='async','nonblocking decode hint');
$check($p->get_attribute('loading')==='eager' && $p->get_attribute('data-no-lazy')==='1','optimizer hints preserved');
$empty=$data; $empty['alt']=''; update_option('easy_countdown_timers',array_merge($timers,array('layout-empty'=>$empty)),false);
$p=new WP_HTML_Tag_Processor(do_shortcode('[ecd_timer id="layout-empty"]'));$p->next_tag('IMG');$check($p->get_attribute('alt')==='','intentional empty alt preserved');
$bad=$data;$bad['deadline']='invalid';$check(Email_Countdown_Timer_Embed::reserve('<img alt="unchanged">',$bad)==='<img alt="unchanged">','invalid metadata cannot break page');
$wide=$data;$wide['fixed_width']=2000;
$p=new WP_HTML_Tag_Processor(Email_Countdown_Timer_Embed::reserve('<img src="https://example.invalid/a?x=1&amp;y=2" alt="&quot;safe&quot;">',$wide,true));$p->next_tag('IMG');
$check((int)$p->get_attribute('width')===600,'email maximum display width is 600');
$check($p->get_attribute('alt')==='"safe"','core parser preserves alt escaping');
$check($p->get_attribute('src')==='https://example.invalid/a?x=1&y=2','core parser preserves query URL');
$make_mail=static function($key,$c){
    $deadline=Email_Countdown_Timer_Admin::deadline_text($c);
    return Email_Countdown_Timer_Embed::reserve('<img class="email-countdown-timer-image" loading="eager" data-no-lazy="1" referrerpolicy="no-referrer" src="'.esc_url(add_query_arg(array('ecd_action'=>'render','ecd'=>$key,'mode'=>'email'),home_url('/'))).'" alt="'.esc_attr($c['alt']).'">' . '<p>Ends: '.esc_html($deadline).'</p>',$c,true);
};
$dir=getenv('ECD_DELIVERY_EVIDENCE');
$fixtures=array('shortcode'=>$html,'email_live'=>$make_mail('layout-live',$data),'email_ended'=>$make_mail('layout-ended',$expired),'checks'=>$checks,'size'=>$size);
file_put_contents($dir.'/fixture.json',wp_json_encode($fixtures,JSON_PRETTY_PRINT));
echo 'PASS '.count($checks).' native embed checks'.PHP_EOL;
