<?php
/** CI-only observer installed as a temporary mu-plugin, NEVER distributed. */
if (!defined('ABSPATH') || getenv('ECD_HTTP_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') return;
$ect_test_dir=getenv('ECD_HTTP_EVIDENCE');
if (!$ect_test_dir || !is_dir($ect_test_dir)) return;
foreach (['gif','png','webp'] as $ect_format) {
    $ect_key='ecd_v1211_'.hash('sha256','http-timer|'.$ect_format);
    add_filter('pre_set_transient_'.$ect_key, static function ($value) use ($ect_test_dir, $ect_format) {
        file_put_contents($ect_test_dir.'/publications.jsonl', json_encode(['pid'=>getmypid(),'format'=>$ect_format,'time'=>microtime(true)])."\n", FILE_APPEND|LOCK_EX);
        if (is_file($ect_test_dir.'/fail-publication')) throw new RuntimeException('Synthetic publication failure');
        return $value;
    });
}
add_filter('pre_http_request', static function ($pre, $args, $url) use ($ect_test_dir) {
    file_put_contents($ect_test_dir.'/outbound.jsonl', json_encode(['host'=>wp_parse_url($url,PHP_URL_HOST)])."\n",FILE_APPEND|LOCK_EX);
    return new WP_Error('ect_test_network_blocked','External HTTP is blocked in this disposable test.');
}, 1, 3);
