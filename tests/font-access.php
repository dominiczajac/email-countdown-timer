<?php
/** Real filesystem checks, using only a randomly owned temporary root. */
if (PHP_SAPI !== 'cli') exit(1);
$root=sys_get_temp_dir().'/ect-font-access-'.bin2hex(random_bytes(8));mkdir($root);mkdir($root.'/code');mkdir($root.'/code/fonts');mkdir($root.'/uploads');
define('ABSPATH',__DIR__.'/');define('EMAIL_COUNTDOWN_TIMER_DIR',$root.'/code/');
$allowed=true;$nonce=true;$checks=0;$multisite=false;$site=1;
function wp_get_upload_dir(){return ['basedir'=>$GLOBALS['root'].'/uploads','error'=>false];}
function wp_mkdir_p($path){return is_dir($path)||mkdir($path,0755,true);}
function is_multisite(){return $GLOBALS['multisite'];}function get_current_blog_id(){return $GLOBALS['site'];}
function current_user_can($cap){return $GLOBALS['allowed'];}function add_action(...$args){}
class Font_Access_Stop extends RuntimeException{}
function wp_die($message,$title='', $args=[]){throw new Font_Access_Stop('die:'.($args['response']??0));}
function esc_html__($message,$domain){return htmlspecialchars($message,ENT_QUOTES);}
function check_admin_referer($action){if(!$GLOBALS['nonce'])throw new Font_Access_Stop('nonce');}
function admin_url($path){return 'https://example.invalid/wp-admin/'.$path;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function wp_safe_redirect($url){throw new Font_Access_Stop($url);}
function check($ok,$message){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($message);}
require __DIR__.'/../includes/class-email-countdown-timer-fonts.php';
require __DIR__.'/../includes/class-email-countdown-timer-font-access.php';
try {
    $_SERVER['REQUEST_METHOD']='POST';$allowed=false;
    try{Email_Countdown_Timer_Font_Access::handle();}catch(Font_Access_Stop $e){check($e->getMessage()==='die:403','unauthorized write rejected');}
    $allowed=true;$nonce=false;
    try{Email_Countdown_Timer_Font_Access::handle();}catch(Font_Access_Stop $e){check($e->getMessage()==='nonce','nonce required');}
    $nonce=true;$_SERVER['REQUEST_METHOD']='GET';
    try{Email_Countdown_Timer_Font_Access::handle();}catch(Font_Access_Stop $e){check($e->getMessage()==='die:405','method required');}
    check(!is_dir($root.'/uploads/email-countdown-timer'),'rejected actions do not mkdir');
    $r=Email_Countdown_Timer_Font_Access::install();$dir=Email_Countdown_Timer_Fonts::persistent_directory();
    check($r===['created'=>4,'existing'=>0,'failed'=>0],'exactly two rules in each font root');
    foreach([$dir,$root.'/code/fonts'] as $path){
        foreach(Email_Countdown_Timer_Font_Access::templates() as $name=>$content)check(file_get_contents($path.'/'.$name)===$content,'exact code-owned content written');
    }
    check(!file_exists($root.'/uploads/.htaccess')&&!file_exists($root.'/code/.htaccess'),'unrelated parent paths untouched');
    $r=Email_Countdown_Timer_Font_Access::install();check($r['created']===0&&$r['existing']===4,'repeat does not overwrite');
    file_put_contents($dir.'/.htaccess','# custom administrator rule');
    unlink($dir.'/index.html');file_put_contents($root.'/sentinel','KEEP');symlink($root.'/sentinel',$dir.'/index.html');
    $r=Email_Countdown_Timer_Font_Access::install();check($r['existing']===4,'existing rules including symlink are not replaced');
    check(file_get_contents($dir.'/.htaccess')==='# custom administrator rule'&&is_link($dir.'/index.html')&&file_get_contents($root.'/sentinel')==='KEEP','custom file and symlink target preserved');
    $multisite=true;$site=2;$r=Email_Countdown_Timer_Font_Access::install();$site2=Email_Countdown_Timer_Fonts::persistent_directory();
    $site=3;$r=Email_Countdown_Timer_Font_Access::install();$site3=Email_Countdown_Timer_Fonts::persistent_directory();
    check($site2!==$site3&&is_file($site2.'/.htaccess')&&is_file($site3.'/.htaccess'),'multisite rules are site-scoped');
    $_SERVER['REQUEST_METHOD']='POST';
    try{Email_Countdown_Timer_Font_Access::handle();}catch(Font_Access_Stop $e){check(str_contains($e->getMessage(),'font_rules=written'),'authorized action redirects with unverified write status');}
}finally{
    $remove=function($path)use(&$remove){foreach(scandir($path)as$name){if($name==='.'||$name==='..')continue;$p=$path.'/'.$name;if(is_dir($p)&&!is_link($p))$remove($p);else unlink($p);}rmdir($path);};$remove($root);
}
echo "PASS $checks font-access assertions\n";
