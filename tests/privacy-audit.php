<?php
/** Static guardrail, not a complete information-flow/security audit. */
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
$files=array_merge(glob($root.'/includes/*.php'),[$root.'/email-countdown-timer.php',$root.'/uninstall.php']);
$deny=['setcookie','setrawcookie','session_start','wp_remote_get','wp_remote_post','wp_remote_request','wp_safe_remote_get','wp_safe_remote_post','curl_exec','fsockopen','stream_socket_client','mail','wp_mail','error_log','file_put_contents','fwrite','update_user_meta','add_user_meta','get_current_user_id'];
foreach ($files as $file) {
    $tokens=token_get_all(file_get_contents($file));
    foreach($tokens as $token) {
        if (!is_array($token)) continue;
        if ($token[0]===T_STRING && in_array(strtolower($token[1]),$deny,true)) throw new RuntimeException('Privacy-sensitive API needs review: '.basename($file).':'.$token[2]);
        if ($token[0]===T_VARIABLE && $token[1]==='$_COOKIE') throw new RuntimeException('Cookie access needs review');
        if ($token[0]===T_CONSTANT_ENCAPSED_STRING && in_array(trim($token[1],"\"'"),['REMOTE_ADDR','HTTP_USER_AGENT','HTTP_REFERER'],true)) throw new RuntimeException('Visitor metadata access needs review');
    }
}
foreach (glob($root.'/assets/*.js') as $file) {
    if (preg_match('/\b(localStorage|sessionStorage|indexedDB|sendBeacon|XMLHttpRequest|WebSocket)\b|document\.cookie|\bfetch\s*\(/',file_get_contents($file))) throw new RuntimeException('Client persistence or network API needs review');
}
echo 'PASS privacy-sensitive API guardrail for '.count($files)." runtime PHP files and local JS; not a GDPR or security certification\n";
