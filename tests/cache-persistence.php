<?php
/** Cache failures must not turn a valid fresh render into an error or stale image. */
if (PHP_SAPI !== 'cli') exit(1);
ob_start();
require __DIR__.'/run.php';
$start = $checks;
$events = [];
$test_actions['email_countdown_timer_image_cache_result'][] = static function (...$args) use (&$events) { $events[] = $args; };
$config = Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59', 'fixed_width'=>600]);
$deadline = Email_Countdown_Timer_Config::deadline($config);
$now = 1790596800;
$store = new ReflectionMethod($plugin, 'storeImageCache');
$key = 'ecd_v1211_'.hash('sha256', 'persistence|png');
$signature = hash('sha256', 'synthetic image configuration');
$bucket = intdiv($now, 15);
$blob = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$transients = []; $writes = 0;
ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='stored', 'successful writes are recognized');
ok($writes===1 && $transients[$key]['data']===base64_encode($blob), 'normal cache entry is unchanged');
ok($events[0]===['stored', strlen(serialize($transients[$key])), 0], 'hook contains fixed state and exact envelope byte count only');
$test_set_transient = static fn()=>false;
ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='retained', 'false for an identical retained value is not a failure');
$transients[$key]['signature'] = 'stale';
ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='write-failed', 'stale retained value does not count as persistence');
unset($transients[$key]);
ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='write-failed', 'a rejected missing entry is recognized');
$ect_ext_cache = true; $ect_force_read = false;
$store->invoke($plugin, $key, $signature, $bucket, $blob);
ok($ect_force_read===true, 'false writes are verified against the external backend');
$ect_ext_cache = false;
unset($test_set_transient);
$bytes = strlen(serialize(['signature'=>$signature, 'bucket'=>$bucket, 'data'=>base64_encode($blob)]));
$test_filters['email_countdown_timer_cache_entry_max_bytes'] = [static fn()=>$bytes];
ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='stored', 'an entry at the configured byte limit is accepted');
$test_filters['email_countdown_timer_cache_entry_max_bytes'] = [static fn()=>$bytes-1];
$before = $writes;
ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='too-large' && $writes===$before, 'oversize entries are not submitted to the backend');
ok(end($events)===['too-large', $bytes, $bytes-1], 'size rejection reports envelope bytes and configured limit');
foreach ([0, -1, '1', null, []] as $limit) {
    $test_filters['email_countdown_timer_cache_entry_max_bytes'] = [static fn()=>$limit];
    ok($store->invoke($plugin, $key, $signature, $bucket, $blob)==='stored', 'disabled or malformed limits preserve the existing default');
}
$test_filters = [];
if (function_exists('imagecreatetruecolor')) {
    $options['easy_countdown_timers'] = ['persistence'=>$config];
    $_GET = ['ecd'=>'persistence', 'mode'=>'email'];
    $method = new ReflectionMethod($plugin, 'generateImage');
    $expected = (new Email_Countdown_Timer_Renderer())->render($config, $deadline, $now, 'gif');
    foreach (['write-failed', 'too-large'] as $failure) {
        $transients = []; $events = []; $writes = 0; $statusCode = 200;
        if ($failure==='write-failed') $test_set_transient = static fn()=>false;
        else $test_filters['email_countdown_timer_cache_entry_max_bytes'] = [static fn()=>1];
        ob_start(); $method->invoke($plugin, false, $now); $actual = ob_get_clean();
        ok($actual===$expected && $statusCode===200, 'cache rejection still serves the exact fresh requested image: '.$failure);
        ok($events[0][0]===$failure && count($events)===1, 'one cache outcome is emitted per publication attempt: '.$failure);
        ok($wpdb->owner===null, 'cache rejection releases the render lock: '.$failure);
        ok(count($transients)===0, 'no stale or static substitute is cached after rejection: '.$failure);
        if (class_exists('Imagick')) {
            $decoded = new Imagick(); $decoded->readImageBlob($actual);
            ok($decoded->getNumberImages()===60, 'cache rejection preserves all sixty frames: '.$failure);
            $decoded->clear();
        }
        unset($test_set_transient); $test_filters = [];
    }
    $transients = []; $events = [];
    ob_start(); $method->invoke($plugin, false, $now); ob_end_clean();
    $queries = count($wpdb->names); $before = $writes;
    ob_start(); $method->invoke($plugin, false, $now); ob_end_clean();
    ok($writes===$before && count($wpdb->names)===$queries && count($events)===1, 'warm hits still bypass lock, render, write and publication hook');
}
ob_end_clean();
echo 'PASS '.($checks-$start).' cache-persistence assertions'.(!function_exists('imagecreatetruecolor')?'; SKIP native render (GD absent)':'')."\n";
