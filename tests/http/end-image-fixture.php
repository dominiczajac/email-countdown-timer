<?php
/** Test-only local fixture; no public failure/time override is shipped. */
if (PHP_SAPI!=='cli'||getenv('ECD_HTTP_DISPOSABLE')!=='1'||wp_get_environment_type()!=='local'||$GLOBALS['wpdb']->base_prefix!=='ecthttp_')exit(1);
$task=$args[0]??'seed';
if($task==='seed'){
    $up=wp_upload_dir();$path=$up['path'].'/ect-public-end-fixture.png';
    $im=imagecreatetruecolor(64,16);imagefill($im,0,0,imagecolorallocate($im,20,190,100));imagepng($im,$path);unset($im);
    $id=wp_insert_attachment(['post_title'=>'End image HTTP fixture','post_mime_type'=>'image/png','post_status'=>'inherit'],$path);
    $c=Email_Countdown_Timer_Config::normalize(['deadline'=>gmdate('Y-m-d\TH:i:s',time()-5),'tz'=>'UTC','expiry_image_id'=>$id]);
    $timers=get_option('easy_countdown_timers',[]);$timers['end-http']=$c;update_option('easy_countdown_timers',$timers,false);
    foreach(['png','gif','webp']as$fmt)delete_transient('ecd_v1211_'.hash('sha256','end-http|'.$fmt));
    $asset=Email_Countdown_Timer_End_Image::resolve($id);$body=(new Email_Countdown_Timer_Renderer())->render_static($c,Email_Countdown_Timer_Config::deadline($c),time(),'gif',$asset);
    echo hash('sha256',$body);
}elseif($task==='trash'){
    $c=get_option('easy_countdown_timers')['end-http'];wp_update_post(['ID'=>$c['expiry_image_id'],'post_status'=>'trash']);
}elseif($task==='hold'){
    require_once EMAIL_COUNTDOWN_TIMER_DIR.'includes/class-email-countdown-timer-render-lock.php';
    $key='ecd_v1211_'.hash('sha256','end-http|gif');delete_transient($key);
    $lock=Email_Countdown_Timer_Render_Lock::attempt($key,0)['lock'];if(!$lock)exit(1);
    echo "LOCK_HELD\n";flush();sleep(10);$lock->release();
}
