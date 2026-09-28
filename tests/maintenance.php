<?php
/** Additive maintenance tests, including intentionally retired write behavior. */
if (PHP_SAPI !== 'cli') exit(1);
ob_start();require __DIR__.'/run.php';$start=$checks;
$admin=true;$nonce=true;$_SERVER['REQUEST_METHOD']='POST';
$before=$options;
foreach ([[],['delete_timer'=>'1'],['alt'=>'replace']] as $extra) {
    $_POST=$extra+['ecd_action'=>'save_timer','timer_id'=>'existing','deadline'=>'2030-01-01T00:00:00'];
    try { $plugin->handleFormSave(); throw new RuntimeException('Retired form accepted'); }
    catch (ECD_Test_Stop $e) { ok(str_contains($e->getMessage(),'form version is no longer supported'),'obsolete mutation gets an explicit error'); }
    ok($options===$before,'obsolete save/delete never modifies options');
}
$nonce=false;
try {$plugin->handleFormSave();}catch(ECD_Test_Stop $e){ok($e->getMessage()==='nonce','retired route still checks nonce');}
$nonce=true;$admin=false;$plugin->handleFormSave();ok($options===$before,'retired unauthorized write changes nothing');$admin=true;
$legacy=Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59']);
$legacy_missing=$legacy;unset($legacy_missing['tz']);
foreach (['America/New_York','+05:45','UTC'] as $zone) {
    $ect_site_tz=$zone;$reset_admin();$_POST=[];$_GET=['view'=>'new'];
    ok(Email_Countdown_Timer_Admin::state()['data']['tz']===$zone,'new timer inherits site zone/offset: '.$zone);
    $options['easy_countdown_timers']=['old'=>$legacy_missing,'explicit'=>$legacy+['alt'=>'']];
    $_GET=['edit'=>'old'];ok(Email_Countdown_Timer_Admin::state()['data']['tz']==='Europe/Warsaw','old missing timezone keeps legacy meaning');
    $configured=array_replace($legacy,['tz'=>'Asia/Tokyo']);$options['easy_countdown_timers']['explicit']=$configured;
    $_GET=['edit'=>'explicit'];ok(Email_Countdown_Timer_Admin::state()['data']['tz']==='Asia/Tokyo','saved zone independent of changed site setting');
    $post=array_merge(array_map('strval',$configured),['ecd_action'=>'email_countdown_timer_save','timer_id'=>'explicit','original_id'=>'explicit']);unset($post['tz']);
    $submit_admin($post);ok($options['easy_countdown_timers']['explicit']['tz']==='Asia/Tokyo','missing timezone in edit POST preserves saved zone');
    $create=['ecd_action'=>'email_countdown_timer_save','timer_id'=>'new-zone','original_id'=>'','deadline'=>'2030-12-31T23:59:59','tz'=>''];
    $submit_admin($create);ok($options['easy_countdown_timers']['new-zone']['tz']===$zone,'blank new timezone uses site zone');
    ok(Email_Countdown_Timer_Config::normalize($legacy_missing)['tz']==='Europe/Warsaw','public legacy normalization stays unchanged');
}
$ect_site_tz='Europe/Warsaw';$reset_admin();
$fresh=new ReflectionMethod($plugin,'completed_image_is_fresh');
foreach ([[150,151,200,true],[150,159,155,false],[149,150,200,false],[151,150,200,false],[150,150,150,true],[150,164,200,true],[150,165,200,false]] as [$start_time,$now,$deadline,$expected]) {
    ok($fresh->invoke(null,$start_time,$now,$deadline)===$expected,'completed blob reuse respects bucket, clock and deadline');
}
if(function_exists('imagecreatetruecolor')) {
    $render=new ReflectionMethod($plugin,'generateImage');$now=1790596800;
    $options['easy_countdown_timers']=['lock-lost'=>$legacy];$_GET=['ecd'=>'lock-lost','mode'=>'email'];$transients=[];$writes=0;
    $wpdb->lose_on_check=true;
    try {
        ob_start();$render->invoke($plugin,false,$now);$blob=ob_get_clean();
        $expected=(new Email_Countdown_Timer_Renderer())->render($legacy,Email_Countdown_Timer_Config::deadline($legacy),$now,'gif');
        ok($blob===$expected && $writes===0,'lost owner returns its completed blob without cache publication');
        if(class_exists('Imagick')) {$im=new Imagick();$im->readImageBlob($blob);ok($im->getNumberImages()===60,'fresh lost-lock response retains animation');$im->clear();}
    }finally{$wpdb->lose_on_check=false;$wpdb->owner=null;}
}
$_GET=$_POST=[];$reset_admin();ob_end_clean();
echo 'PASS '.($checks-$start).' maintenance assertions'.(!function_exists('imagecreatetruecolor')?'; SKIP native lock-loss images (GD absent)':'')."\n";
