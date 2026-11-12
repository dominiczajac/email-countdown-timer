<?php
/** Real Options API semantics; synthetic transients in a disposable WordPress. */
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') exit(1);
$checks = 0;
$expect = static function ($value, $message) use (&$checks) { ++$checks; if (!$value) WP_CLI::error('Cache persistence: '.$message); };
$plugin = new Email_Countdown_Timer_Plugin();
$store = new ReflectionMethod($plugin, 'storeImageCache');
$key = 'ecd_v1211_'.hash('sha256', 'cache-persistence-'.bin2hex(random_bytes(8)));
$signature = hash('sha256', 'synthetic cache fixture');
$bucket = intdiv(time(), 15);
$blob = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$events = [];
$observe = static function (...$args) use (&$events) { $events[] = $args; };
$limit = static fn()=>1;
$reject = static fn($new, $old)=>$old;
$update_hook = 'pre_update_option__transient_'.$key;
add_action('email_countdown_timer_image_cache_result', $observe, 10, 3);
try {
    $expect($store->invoke($plugin, $key, $signature, $bucket, $blob)==='stored', 'initial backend write');
    $entry = get_transient($key);
    $expect(base64_decode($entry['data'], true)===$blob, 'actual backend retains exact image bytes');
    $expect($events[0]===['stored', strlen(serialize($entry)), 0], 'redacted fixed-field outcome');
    $same = $store->invoke($plugin, $key, $signature, $bucket, $blob);
    $expect($same===(wp_using_ext_object_cache() ? 'stored' : 'retained'), 'actual unchanged-value semantics for this backend');
    if (!wp_using_ext_object_cache()) {
        $stale = $entry; $stale['bucket']--;
        set_transient($key, $stale, 60);
        add_filter($update_hook, $reject, 10, 2);
        $expect($store->invoke($plugin, $key, $signature, $bucket, $blob)==='write-failed', 'Options API rejection cannot be mistaken for a current image');
        remove_filter($update_hook, $reject);
        $expect(get_transient($key)===$stale, 'failure verification never deletes the stored entry');
    }
    add_filter('email_countdown_timer_cache_entry_max_bytes', $limit);
    $expect($store->invoke($plugin, $key, $signature, $bucket, $blob)==='too-large', 'configured envelope budget');
    $expect(end($events)[2]===1, 'configured budget is observed without configuration or visitor content');
} finally {
    remove_filter($update_hook, $reject);
    remove_filter('email_countdown_timer_cache_entry_max_bytes', $limit);
    remove_action('email_countdown_timer_image_cache_result', $observe);
    delete_transient($key);
}
WP_CLI::success('Cache persistence: '.$checks.' checks; backend='.(wp_using_ext_object_cache() ? 'external' : 'database'));
