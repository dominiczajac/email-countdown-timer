<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Isolated WordPress API doubles + real GD/Imagick when available; not a full WordPress installation. */
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static function ($code, $message, $file, $line) {
    if (error_reporting() & $code) throw new ErrorException($message, 0, $code, $file, $line);
    return false;
});
ob_start(); // Endpoint tests intentionally write headers.
define('ABSPATH', __DIR__.'/');
$options = $transients = $scripts = [];
$admin = true; $nonce = true; $writes = 0; $checks = 0; $statusCode = 200;
class ECD_Test_Stop extends RuntimeException {}
function add_action(...$args) {}
function add_shortcode(...$args) {}
function add_menu_page(...$args) {}
function current_user_can($cap) { return $GLOBALS['admin']; }
function check_admin_referer($action) { if (!$GLOBALS['nonce']) throw new ECD_Test_Stop('nonce'); }
function get_option($key, $default=[]) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload=false) { $GLOBALS['options'][$key]=$value; return true; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key]=$value; $GLOBALS['writes']++; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function sanitize_title($text) { return trim(preg_replace('/[^a-z0-9_%\-]+/', '-', strtolower(strip_tags($text))), '-'); }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function sanitize_hex_color($text) { return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iD', $text) ? $text : null; }
function wp_unslash($text) { return is_array($text) ? array_map('wp_unslash', $text) : (is_string($text) ? stripslashes($text) : $text); }
function __($text, $domain) { return $GLOBALS['test_translations'][$text] ?? $text; }
function esc_html__($text, $domain) { return esc_html(__($text, $domain)); }
function esc_attr__($text, $domain) { return esc_attr(__($text, $domain)); }
function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_textarea($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function wp_enqueue_style($handle, ...$args) { $GLOBALS['styles'][$handle]=$args; }
function esc_url($text) { return esc_attr($text); }
function wp_date($format) { return date($format); }
function admin_url($path) { return 'https://example.test/wp-admin/'.$path; }
function home_url($path) { return 'https://example.test'.$path; }
function plugins_url($path, $file) { return 'https://example.test/wp-content/plugins/email-countdown-timer/'.$path; }
function add_query_arg($args, $arg2=null, $arg3=null) {
    if (!is_array($args)) { $args=[$args=>$arg2]; $url=$arg3; } else $url=$arg2;
    $parts=parse_url($url); parse_str($parts['query']??'', $query);
    foreach ($args as $key=>$value) { if ($value === false) unset($query[$key]); else $query[$key]=$value; }
    return explode('?', $url)[0].'?'.http_build_query($query);
}
function shortcode_atts($defaults, $atts) { return array_merge($defaults, array_intersect_key($atts, $defaults)); }
function wp_unique_id($prefix) { static $id=0; return $prefix.(++$id); }
function wp_enqueue_script($handle, ...$args) { $GLOBALS['scripts'][$handle]=$args; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function selected($a, $b) { if ($a===$b) echo 'selected'; }
function checked($value) { if ($value) echo 'checked'; }
function submit_button($text) { echo '<button>'.esc_html($text).'</button>'; }
function wp_die($message, ...$args) { throw new ECD_Test_Stop('die:'.$message); }
function wp_safe_redirect($url) { throw new ECD_Test_Stop('redirect:'.$url); }
function status_header($code) { $GLOBALS['statusCode']=$code; }
function ok($value, $message) { $GLOBALS['checks']++; if (!$value) throw new RuntimeException('FAIL: '.$message); }
function rejects(callable $fn) { try { $fn(); } catch (InvalidArgumentException|RuntimeException $e) { return true; } return false; }
require __DIR__.'/render-lock-double.php';
require __DIR__.'/../email-countdown-timer.php';
// CLI-only adapters keep the historical regression tests and frozen oracle unchanged.
class_alias('Email_Countdown_Timer_Config', 'ECD_Config');
class_alias('Email_Countdown_Timer_Renderer', 'ECD_Renderer');
class_alias('Email_Countdown_Timer_Plugin', 'ECD_Plugin_Colons_Fix');
define('ECD_PLUGIN_DIR', EMAIL_COUNTDOWN_TIMER_DIR);
$plugin=new ECD_Plugin_Colons_Fix();
// Explicitly preserve the saved v12.1 labels in the legacy rendering fixture.
$base=ECD_Config::normalize(['deadline'=>'2027-12-31T23:59:59', 'label_d'=>'Dni', 'label_h'=>'Godz', 'label_m'=>'Min', 'label_s'=>'Sek']);
ok($base['label_d']==='Dni' && $base['label_h']==='Godz' && $base['tz']==='Europe/Warsaw', 'saved legacy labels and time zone preserved');
ok(ECD_Config::deadline($base)===(new DateTimeImmutable($base['deadline'], new DateTimeZone('Europe/Warsaw')))->getTimestamp(), 'timezone preserved');
foreach (['2027-02-30T12:00', '+1 hour', '', '2027-12-31T99:00'] as $date) ok(rejects(fn()=>ECD_Config::normalize(['deadline'=>$date])), 'invalid date');
foreach (['font'=>'../outside.ttf', 'fixed_width'=>1000000, 'size_digit'=>[], 'lc'=>'red', 'label_s'=>str_repeat('x',257), 'tz'=>'not/a-zone'] as $key=>$value) {
    ok(rejects(fn()=>ECD_Config::normalize(array_replace($base, [$key=>$value]))), 'reject '.$key);
}
ok(ECD_Config::text(['id'=>['payload']], 'id')==='', 'array query rejected');
ok(ECD_Config::fontPath('../outside.ttf')===null, 'font traversal');
ok(rejects(fn()=>ECD_Config::checkCanvas(4000,4000)), 'canvas budget');
$options['easy_countdown_timers']=['sale'=>$base];
$_POST=['ecd_action'=>'save_timer', 'timer_id'=>'sale'];
$before=$options; $admin=false; $plugin->handleFormSave(); ok($options===$before, 'unauthorized write denied');
$admin=true; $nonce=false;
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) { ok($e->getMessage()==='nonce', 'nonce required'); }
ok($options===$before, 'invalid nonce unchanged'); $nonce=true;
$_POST=array_merge($base, ['ecd_action'=>'save_timer', 'timer_id'=>'sale', 'label_d'=>"D\\'ni"]);
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) { ok(str_starts_with($e->getMessage(),'redirect:'), 'save redirects'); }
ok($options['easy_countdown_timers']['sale']['label_d']==="D'ni", 'unslash once');
$_GET=['edit'=>'sale']; ob_start(); $plugin->renderAdminPage(); $html=ob_get_clean();
ok(strpos($html, 'readonly')!==false && strpos($html, 'disabled')===false, 'editable form submits readonly ID');
ok(strpos($html, '2027-12-31T23:59:59')!==false, 'deadline seconds preserved');
$_GET=['edit'=>['x']]; ob_start(); $plugin->renderAdminPage(); ob_end_clean(); ok(true,'array edit input');
$short=$plugin->renderShortcode(['id'=>'sale']); $short2=$plugin->renderShortcode(['id'=>'sale']);
ok(strpos($short,'data-ecd-src=')!==false && strpos($short,'mode=anim')!==false && strpos($short,'<script')===false, 'safe shortcode');
ok($short!==$short2 && count($scripts)===1, 'unique IDs, single script handle');
ok($plugin->renderShortcode(['id'=>['x']])==='', 'array shortcode input');
$_POST=['ecd_action'=>'save_timer','timer_id'=>'sale','delete_timer'=>'1'];
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) {}
ok(!isset($options['easy_countdown_timers']['sale']), 'delete');
$method=new ReflectionMethod($plugin, 'generateImage'); $method->setAccessible(true);
$_GET=['ecd'=>'unknown']; ob_start(); $method->invoke($plugin); $pixel=ob_get_clean();
ok($statusCode===404 && str_starts_with($pixel,"\x89PNG") && $writes===0, 'unknown timer cheap 404');
$options['easy_countdown_timers']=['sale'=>$base];
$skip='';
if (getenv('ECD_REQUIRE_IMAGICK') !== false) ok(class_exists('Imagick') === (getenv('ECD_REQUIRE_IMAGICK') === '1'), 'expected Imagick capability');
if (function_exists('imagecreatetruecolor')) {
    require __DIR__.'/legacy-frame.php';
    $renderer=new ECD_Renderer(); $legacy=new ECD_Legacy_Frame();
    $labels=['d'=>'Dni','h'=>'Godz','m'=>'Min','s'=>'Sek'];
    foreach ([0,1,59,60,3599,3600,86399,86400,86401,8640000] as $remaining) {
        foreach ([false,true] as $hide) foreach ([0,600] as $width) {
            $args=[$remaining,'#FFFFFF','#000000','#666666','',40,12,$hide,$labels,$width];
            $a=$renderer->drawFrame(...$args); $b=$legacy->drawFrame(...$args);
            ob_start(); imagepng($a); $new=ob_get_clean(); ob_start(); imagepng($b); $old=ob_get_clean();
            ok($new===$old, 'pixel-identical legacy layout '.$remaining); unset($a,$b);
        }
    }
    $fontCandidates = ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf'];
    foreach ($fontCandidates as $systemFont) {
        if (!is_file($systemFont)) continue;
        $fontDir=ECD_PLUGIN_DIR.'fonts';
        if (!is_dir($fontDir)) mkdir($fontDir);
        $fontName='ecd-test-'.bin2hex(random_bytes(6)).'.ttf';
        copy($systemFont, $fontDir.'/'.$fontName);
        try {
            $renderer=new ECD_Renderer();
            for ($i=0; $i<60; $i++) {
                $args=[86410-$i,'#FFF','#000','#666',$fontName,40,12,true,$labels,600];
                $a=$renderer->drawFrame(...$args); $b=$legacy->drawFrame(...$args);
                ob_start(); imagepng($a); $new=ob_get_clean(); ob_start(); imagepng($b); $old=ob_get_clean();
                ok($new===$old, 'TTF pixel equality across day boundary'); unset($a,$b);
            }
            $boxes=new ReflectionProperty($renderer,'boxes'); $boxes->setAccessible(true);
            ok(count($boxes->getValue($renderer))<100,'font metric memoization (<100 vs >600 calls)');
            $outside=tempnam(sys_get_temp_dir(),'ecd-font-');
            $link=$fontDir.'/ecd-outside.ttf';
            if (function_exists('symlink') && !file_exists($link)) {
                symlink($outside,$link);
                try { ok(ECD_Config::fontPath('ecd-outside.ttf')===null,'symlink escape denied'); }
                finally { unlink($link); }
            }
            unlink($outside);
        } finally { unlink($fontDir.'/'.$fontName); }
        break;
    }
    $now=time(); $png=$renderer->render($base,$now+3600,$now,'png'); ok(getimagesizefromstring($png)['mime']==='image/png','PNG');
    $gif=$renderer->render($base,$now+86410,$now,'gif'); ok(getimagesizefromstring($gif)['mime']==='image/gif','GIF');
    if (class_exists('Imagick')) {
        $animation=new Imagick(); $animation->readImageBlob($gif);
        ok($animation->getNumberImages()===60, '60 GIF frames');
        $coalesced=$animation->coalesceImages();
        foreach ($coalesced as $index=>$frame) {
            ok($frame->getImageDelay()===100,'one-second frame');
            $expected=$renderer->drawFrame(max(0,86410-$index),'#FFFFFF','#000000','#666666','',40,12,false,$labels);
            ob_start(); imagegif($expected); $expectedBlob=ob_get_clean(); unset($expected);
            $reference=new Imagick(); $reference->readImageBlob($expectedBlob);
            ok($frame->compareImages($reference, Imagick::METRIC_ABSOLUTEERRORMETRIC)[1]===0.0,'GIF frame pixels');
            $reference->clear();
        }
        $coalesced->clear(); $animation->clear();
    }
    $_GET=['ecd'=>'sale','mode'=>'email']; $writes=0; $fixedNow=1790580000;
    ob_start(); $method->invoke($plugin,false,$fixedNow); $first=ob_get_clean();
    $_GET['_t']='unique-client-cache-buster'; ob_start(); $method->invoke($plugin,false,$fixedNow); $second=ob_get_clean();
    ok($first===$second && $writes===1, 'shared cache ignores cache-buster');
    $options['easy_countdown_timers']['sale']['lc']='#FF0000'; ob_start(); $method->invoke($plugin,false,$fixedNow); $third=ob_get_clean();
    ok($third!==$second && $writes===2 && count($transients)===1, 'label color invalidates same cache slot');
    ob_start(); $method->invoke($plugin,false,$fixedNow+15); ob_end_clean();
    ok($writes===3 && count($transients)===1, 'next bucket reuses storage slot');
    $before=$writes; ob_start(); $method->invoke($plugin,true); $head=ob_get_clean(); ok($head==='' && $writes===$before,'HEAD no rendering');
} else {
    $skip='; SKIP native image tests (GD not installed)';
    if (getenv('ECD_REQUIRE_GD')==='1') throw new RuntimeException('GD required in CI');
}
require __DIR__.'/english-ui.php';
require __DIR__.'/internationalization.php';
require __DIR__.'/admin-ui.php';
require __DIR__.'/alt-privacy-lock.php';
$summary='PASS '.$checks.' assertions on PHP '.PHP_VERSION.$skip."\n";
ob_end_clean(); echo $summary;
