<?php
/** Additive tests; the legacy renderer and its expected pixels stay frozen. */
if (PHP_SAPI !== 'cli' || !function_exists('ok')) { exit(1); }
$config=Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59']);
ok(!array_key_exists('alt',$config), 'legacy absent alt remains absent');
ok(Email_Countdown_Timer_Config::alt($config,'Fallback')==='Fallback','legacy output uses contextual fallback');
foreach (['', 'Offer ends Friday', 'Żółć 日本語', 'Quotes " & apostrophe \''] as $alt) {
    $c=Email_Countdown_Timer_Config::normalize($config+['alt'=>$alt]);
    ok($c['alt']===$alt,'alt plain text/empty/multilingual roundtrip');
    $options['easy_countdown_timers']=['alt-case'=>$c];
    $short=$plugin->renderShortcode(['id'=>'alt-case']);
    ok(str_contains($short,'alt="'.esc_attr($alt).'"'),'shortcode uses escaped saved alt');
    $_GET=['edit'=>'alt-case']; $reset_admin(); ob_start();$plugin->renderAdminPage();$html=ob_get_clean();
    ok(str_contains($html,'id="ect-alt"') && str_contains($html,'for="ect-alt"'),'visible associated alt label');
    ok(str_contains($html,esc_textarea('alt="'.esc_attr($alt).'"')),'email HTML includes saved alt, not a query parameter');
}
foreach ([[], str_repeat('x',1001), null, 123] as $alt) {
    ok(rejects(fn()=>Email_Countdown_Timer_Config::normalize($config+['alt'=>$alt])),'reject malformed/oversized alt');
}
$payload='" onerror="alert(1)';
$options['easy_countdown_timers']=['alt-case'=>$config+['alt'=>$payload]];
$short=$plugin->renderShortcode(['id'=>'alt-case']);
ok(!str_contains($short,' onerror="') && str_contains($short,esc_attr($payload)),'alt cannot create HTML event attribute');
ok(str_contains($short,'class="email-countdown-timer-image"') && str_contains($short,'loading="eager"') && str_contains($short,'data-no-lazy="1"'),'image-specific optimization opt-out markers');
ok(str_contains($short,'referrerpolicy="no-referrer"'),'shortcode requests suppress referrer where supported');
$valid=array_merge(array_map('strval',$config),['ecd_action'=>'email_countdown_timer_save','timer_id'=>'alt-case','original_id'=>'alt-case','alt'=>'Updated alt']);
ok(str_starts_with($submit_admin($valid),'redirect:') && $options['easy_countdown_timers']['alt-case']['alt']==='Updated alt','admin saves alternative text');
$empty=array_replace($valid,['alt'=>'']);$submit_admin($empty);
ok($options['easy_countdown_timers']['alt-case']['alt']==='','admin intentional empty alt preserved');
$before=$options;$submit_admin(array_replace($valid,['alt'=>str_repeat('x',1001)]));
ok($options===$before && isset(Email_Countdown_Timer_Admin::state()['errors']['alt']),'alt error is field-specific and does not write');
$submit_admin(array_replace($valid,['alt'=>['bad']]));
ok($options===$before && isset(Email_Countdown_Timer_Admin::state()['errors']['alt']),'array alt rejected without writes');
$reset_admin();
require_once __DIR__.'/../includes/class-email-countdown-timer-render-lock.php';
$wpdb->allow=true;$a=Email_Countdown_Timer_Render_Lock::acquire('known-image');
ok($a!==null && $a->owns(),'lock acquired and ownership verified');
$originalName=end($wpdb->names);ok(strlen($originalName)<=64,'database lock name fits MySQL limit');
$a->release();ok(!$a->owns() && $wpdb->owner===null,'release is explicit and idempotent');$a->release();
$wpdb->options='other_options';$b=Email_Countdown_Timer_Render_Lock::acquire('known-image');
ok(end($wpdb->names)!==$originalName,'different site uses distinct lock');$b->release();$wpdb->options='test_options';
$c=Email_Countdown_Timer_Render_Lock::acquire('known-image');$wpdb->connection++;
ok(!$c->owns(),'connection change invalidates ownership');$wpdb->owner=$wpdb->connection;$c->release();
ok($wpdb->owner===$wpdb->connection,'old release cannot clear replacement owner');$wpdb->owner=null;
$wpdb->allow=false;ok(Email_Countdown_Timer_Render_Lock::acquire('known-image')===null,'lock contention fails closed');$wpdb->allow=true;
if (function_exists('imagecreatetruecolor')) {
    $options['easy_countdown_timers']=['alt-case'=>$config+['alt'=>'First']];$_GET=['ecd'=>'alt-case','mode'=>'email'];$transients=[];$writes=0;
    $method=new ReflectionMethod($plugin,'generateImage');ob_start();$method->invoke($plugin,false,1790596800);$first=ob_get_clean();
    $options['easy_countdown_timers']['alt-case']['alt']='Metadata only';ob_start();$method->invoke($plugin,false,1790596800);$second=ob_get_clean();
    ok($first===$second && $writes===1,'alt is excluded from image-cache signature');
    $transients=[];$wpdb->allow=false;ob_start();$method->invoke($plugin,false,1790596800);$busy=ob_get_clean();
    ok($statusCode===503 && $writes===1 && str_starts_with($busy,"\x89PNG"),'busy lock returns cheap 503 without rendering/writing');$wpdb->allow=true;
    $ect_ext_cache=true;ob_start();$method->invoke($plugin,false,1790596800);ob_end_clean();
    ok($ect_force_read===true,'shared cache read bypasses request-local miss');$ect_ext_cache=false;
}
$_GET=$_POST=[];$reset_admin();
