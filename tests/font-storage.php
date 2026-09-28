<?php
/** File-system tests in owned temporary paths; fonts are never shipped. */
if ( PHP_SAPI !== 'cli' ) { exit(1); }
ob_start();
require __DIR__ . '/run.php';
function wp_get_upload_dir() { return array( 'basedir' => $GLOBALS['font_test_root'] ?? '', 'error' => false ); }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0755, true ); }
function wp_tempnam( $name, $dir ) { if (!str_ends_with($dir, DIRECTORY_SEPARATOR)) throw new RuntimeException('WordPress temp directory requires trailing separator'); return tempnam( $dir, '.ect-test-' ); }
function wp_delete_file( $path ) { return unlink( $path ); }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function is_multisite() { return $GLOBALS['font_multisite'] ?? false; }
function get_current_blog_id() { return $GLOBALS['font_site'] ?? 1; }
$start_checks = $checks;
$font_test_root = sys_get_temp_dir() . '/ect-font-tests-' . bin2hex(random_bytes(8));
mkdir($font_test_root);
$legacy = EMAIL_COUNTDOWN_TIMER_DIR . 'fonts';
$created_legacy = !is_dir($legacy);
if ($created_legacy) mkdir($legacy);
$name = 'ect-storage-' . bin2hex(random_bytes(8)) . '.ttf';
$original = $legacy . '/' . $name;
$owned = [];
try {
    ok(Email_Countdown_Timer_Fonts::persistent_directory() === null && !is_dir($font_test_root.'/email-countdown-timer'), 'font reads do not mkdir');
    foreach (['../x.ttf', "x\0.ttf", 'dir\\x.ttf', 'file.php', 'x.woff2'] as $bad) ok(!Email_Countdown_Timer_Fonts::valid_name($bad), 'reject unsafe font name');
    file_put_contents($original, "\0\1\0\0" . str_repeat('synthetic-test-not-a-font', 3));
    $owned[]=$original;
    $before=$options;
    $admin=false;
    try { Email_Countdown_Timer_Fonts::handle_copy(); throw new RuntimeException('authorization missed'); }
    catch (ECD_Test_Stop $e) { ok(str_starts_with($e->getMessage(),'die:'), 'font copy requires administrator'); }
    $admin=true;$nonce=false;$_SERVER['REQUEST_METHOD']='POST';
    try { Email_Countdown_Timer_Fonts::handle_copy(); throw new RuntimeException('nonce missed'); }
    catch (ECD_Test_Stop $e) { ok($e->getMessage()==='nonce', 'font copy requires nonce'); }
    $nonce=true;$_SERVER['REQUEST_METHOD']='GET';
    try { Email_Countdown_Timer_Fonts::handle_copy(); throw new RuntimeException('method missed'); }
    catch (ECD_Test_Stop $e) { ok(str_starts_with($e->getMessage(),'die:'), 'font copy requires POST'); }
    ok($options===$before, 'rejected copy writes no ownership data');
    $result=Email_Countdown_Timer_Fonts::copy_legacy();
    $dir=Email_Countdown_Timer_Fonts::persistent_directory();$destination=$dir.'/'.$name;
    ok(is_file($destination) && is_file($original) && hash_file('sha256',$destination)===hash_file('sha256',$original), 'copy preserves original bytes and source');
    ok(($options[Email_Countdown_Timer_Fonts::OPTION][$name]??'')===hash_file('sha256',$original), 'only copied content has ownership record');
    ok(Email_Countdown_Timer_Config::fontPath($name)===realpath($original), 'legacy priority does not change active font');
    ok(count(array_keys(Email_Countdown_Timer_Config::fonts(),$name,true))===1, 'deduplicate names across roots');
    unlink($original);
    ok(Email_Countdown_Timer_Config::fontPath($name)===realpath($destination), 'persistent font survives removal of legacy file');
    file_put_contents($original,"\0\1\0\0".str_repeat('different',5));
    $hash=hash_file('sha256',$destination); $result=Email_Countdown_Timer_Fonts::copy_legacy();
    ok($result['skipped']>=1 && hash_file('sha256',$destination)===$hash, 'different same-name destination never overwritten');
    $manual=$dir.'/manual-'.$name;file_put_contents($manual,'manual-file');
    $changed=$dir.'/changed-'.$name;file_put_contents($changed,'changed-content');
    $options[Email_Countdown_Timer_Fonts::OPTION]['changed-'.$name]=hash('sha256','old-content');
    $external=$font_test_root.'/external.ttf';file_put_contents($external,'do-not-delete');
    symlink($external,$dir.'/link-'.$name);
    $options[Email_Countdown_Timer_Fonts::OPTION]['link-'.$name]=hash('sha256','do-not-delete');
    ok(Email_Countdown_Timer_Config::fontPath('link-'.$name)===null,'font symlink escape rejected');
    Email_Countdown_Timer_Fonts::delete_owned();
    ok(!is_file($destination) && is_file($manual) && is_file($changed) && is_file($external), 'cleanup removes owned unchanged copy only');
    ok(is_link($dir.'/link-'.$name),'cleanup never follows symlink');
    $font_multisite=true;$font_site=2;
    $site2=Email_Countdown_Timer_Fonts::persistent_directory(true);
    $font_site=3;$site3=Email_Countdown_Timer_Fonts::persistent_directory(true);
    ok($site2!==$site3 && str_ends_with($site2,'site-2') && str_ends_with($site3,'site-3'),'per-site roots stay isolated with shared uploads');
    $font_multisite=false;
    $safe_base=$font_test_root;$font_test_root.='/bad-root';mkdir($font_test_root);
    symlink($safe_base,$font_test_root.'/email-countdown-timer');
    ok(Email_Countdown_Timer_Fonts::persistent_directory(true)===null, 'reject symlink inside persistent root');
    $font_test_root=$safe_base;
    if (function_exists('imagecreatetruecolor')) {
        $c=Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59']);
        $renderer=new Email_Countdown_Timer_Renderer();
        foreach ([0,59,86400,8640000] as $remain) {
            $measurement=$renderer->measure($c,1790000000+$remain,1790000000);
            $im=$renderer->drawFrame($remain,$c['bg'],$c['dc'],$c['lc'],'',40,12,false,['d'=>'Days','h'=>'Hours','m'=>'Minutes','s'=>'Seconds']);
            ok($measurement['width']===imagesx($im) && $measurement['height']===imagesy($im),'measured and allocated dimensions agree');unset($im);
        }
        $bad=array_replace($c,['label_d'=>str_repeat('D',256),'label_h'=>str_repeat('H',256),'label_m'=>str_repeat('M',256),'label_s'=>str_repeat('S',256)]);
        ok(isset(Email_Countdown_Timer_Admin::preflight($bad)['font']),'joint geometry limit caught before save');
        ok(isset(Email_Countdown_Timer_Admin::preflight(array_replace($c,['label_d'=>'Dzień','font'=>'not-found.ttf']))['label_d']), 'missing selected font still enforces bitmap label boundary');
        $saved=$options;$_SERVER['REQUEST_METHOD']='POST';
        $post=array_merge(array_map('strval',$bad),['ecd_action'=>'email_countdown_timer_save','timer_id'=>'too-wide','original_id'=>'']);
        $submit_admin($post);
        ok($options===$saved && isset(Email_Countdown_Timer_Admin::state()['errors']['font']),'modern form rejects impossible layout without write');
        foreach (['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf','/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf'] as $system_font) {
            if (!is_file($system_font)) continue;
            $font_name='real-'.$name;copy($system_font,$dir.'/'.$font_name);
            $ttf=array_replace($c,['font'=>$font_name,'label_d'=>'Dzień']);
            ok(Email_Countdown_Timer_Admin::preflight($ttf)===[], 'available persistent TTF accepts multilingual label');
            ok(isset(Email_Countdown_Timer_Admin::preflight(array_replace($ttf,['size_digit'=>200,'size_label'=>100,'fixed_width'=>4000]))['font']), 'TTF aggregate pixel limit is enforced before encoding');
            break;
        }
    }
} finally {
    foreach ($owned as $path) if (is_file($path)) unlink($path);
    if ($created_legacy && is_dir($legacy)) rmdir($legacy);
    // This test alone owns this randomly generated temp root. Do not follow symlinks.
    $remove=function($dir) use (&$remove) { foreach (scandir($dir) as $name) { if ($name==='.'||$name==='..') continue; $p=$dir.'/'.$name; if (is_dir($p)&&!is_link($p)) $remove($p); else unlink($p); } rmdir($dir); };
    $remove($font_test_root);
}
ob_end_clean();
echo 'PASS '.($checks-$start_checks).' font-storage/preflight assertions'.(!function_exists('imagecreatetruecolor')?'; SKIP native geometry (GD absent)':'')."\n";
