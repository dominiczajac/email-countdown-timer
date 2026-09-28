<?php
/** Additive lock-state/fallback regressions; no production database is used here. */
if (PHP_SAPI !== 'cli') exit(1);
ob_start();
require __DIR__.'/run.php';
$start = $checks;
$wpdb->allow = false;
$result = Email_Countdown_Timer_Render_Lock::attempt('outcomes');
ok($result['state']==='busy' && $result['lock']===null, 'busy is distinct from a backend error');
ok(!$wpdb->suppressed,'database error-display state restored after busy result');
$wpdb->allow = true;
foreach (['null','query','malformed','exception'] as $failure) {
    $wpdb->failure = $failure;
    $result = Email_Countdown_Timer_Render_Lock::attempt('outcomes');
    ok($result['state']==='error' && $result['lock']===null,'backend failure never returns a fake lock: '.$failure);
    ok(!$wpdb->suppressed,'database error-display state restored: '.$failure);
}
$wpdb->failure = '';
$result = Email_Countdown_Timer_Render_Lock::attempt('outcomes',0);
ok($result['state']==='acquired' && $result['lock']->owns(),'successful result has a real owned lock');
$result['lock']->release();
ok(!$result['lock']->owns(),'released lock cannot authorize publication');
$render = new ReflectionMethod($plugin,'generateImage');
$config = Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59','fixed_width'=>600]);
$deadline = Email_Countdown_Timer_Config::deadline($config);
if (function_exists('imagecreatetruecolor')) {
    foreach (['busy','query','null','exception'] as $failure) {
        $wpdb->failure = $failure==='busy'?'':$failure; $wpdb->allow = $failure!=='busy';
        foreach ([-1,0,1] as $delta) {
            $now=$deadline+$delta;
            $options['easy_countdown_timers']=['fallback'=>$config];$_GET=['ecd'=>'fallback','mode'=>'email'];
            $transients=[];$writes=0;$statusCode=200;
            ob_start();$render->invoke($plugin,false,$now);$actual=ob_get_clean();
            $expected=(new Email_Countdown_Timer_Renderer())->render_static($config,$deadline,$now,'gif');
            ok($actual===$expected && $writes===0 && $statusCode===200,'current fallback matches exact deadline and is not cached');
            if (class_exists('Imagick')) {
                $decoded=new Imagick();$decoded->readImageBlob($actual);
                ok($decoded->getNumberImages()===1,'fallback has one frame, never a duplicate full animation');$decoded->clear();
            }
        }
    }
}
$wpdb->failure='';$wpdb->allow=true;
$queries=count($wpdb->names);
define('DB_ENGINE','sqlite');
$result=Email_Countdown_Timer_Render_Lock::attempt('outcomes');
ok($result['state']==='unsupported' && $result['lock']===null && count($wpdb->names)===$queries,'known SQLite mode skips unsupported lock SQL');
ob_end_clean();
echo 'PASS '.($checks-$start).' lock outcome/fallback assertions'.(!function_exists('imagecreatetruecolor')?'; SKIP image decoding (GD absent)':'')."\n";
