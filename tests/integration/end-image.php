<?php
/** Real WordPress attachment and renderer checks; synthetic disposable data only. */
if ( PHP_SAPI !== 'cli' || ! defined('WP_CLI') || ! WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local' ) exit(1);
$checks=0;
$expect=static function($value,$message)use(&$checks){++$checks;if(!$value) WP_CLI::error('End image: '.$message);};
wp_set_current_user(1);
$uploads=wp_upload_dir();$name='ect-end-fixture-'.bin2hex(random_bytes(6)).'.png';$path=$uploads['path'].'/'.$name;
$image=imagecreatetruecolor(80,20);imagefill($image,0,0,imagecolorallocate($image,70,140,210));imagepng($image,$path);unset($image);
$id=wp_insert_attachment(['post_title'=>'Synthetic end image','post_status'=>'inherit','post_mime_type'=>'image/png'],$path,0,true);
$expect(!is_wp_error($id),'create local attachment');
$saved=get_option('easy_countdown_timers',[]);
try {
    $asset=Email_Countdown_Timer_End_Image::resolve($id);
    $expect($asset!==null && Email_Countdown_Timer_End_Image::validate_selection($id),'real attachment ID validates');
    $remote=static fn($file)=>'https://example.invalid/offloaded.png';
    add_filter('get_attached_file',$remote);
    $expect(Email_Countdown_Timer_End_Image::resolve($id)===$asset,'offload filter cannot cause an outbound download');
    remove_filter('get_attached_file',$remote);
    $c=Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-01-01T00:00:05','tz'=>'UTC','expiry_image_id'=>$id]);
    $deadline=Email_Countdown_Timer_Config::deadline($c);
    $renderer=new Email_Countdown_Timer_Renderer();$plugin=new Email_Countdown_Timer_Plugin();$method=new ReflectionMethod($plugin,'generateImage');
    update_option('easy_countdown_timers',['end-fixture'=>$c],false);
    $_GET=['ecd'=>'end-fixture','mode'=>'email'];
    ob_start();$method->invoke($plugin,false,$deadline-1);$before=ob_get_clean();
    ob_start();$method->invoke($plugin,false,$deadline);$at=ob_get_clean();
    $gif=new Imagick();$gif->readImageBlob($at);
    $expect($gif->getNumberImages()===1,'exact deadline returns one end frame');$gif->clear();
    $expect($before!==$at,'pre-deadline cache cannot mask end image in same 15-second bucket');
    $expected=$renderer->render_static($c,$deadline,$deadline,'gif',$asset);
    $expect($at===$expected,'endpoint body equals selected local image rendered in requested format');
    ob_start();$method->invoke($plugin,false,$deadline+20);$later=ob_get_clean();
    $expect($later===$at,'expired image output stable across buckets');
    $normal=$c;unset($normal['expiry_image_id']);
    $expect($renderer->render($c,$deadline,$deadline-90,'png')===$renderer->render($normal,$deadline,$deadline-90,'png'),'active pixels unaffected');
    $crossing=$renderer->render($c,$deadline,$deadline-2,'gif',$asset);
    $gif=new Imagick();$gif->readImageBlob($crossing);$frames=$gif->coalesceImages();
    $expect($frames->getNumberImages()===60,'crossing animation still has 60 frames');
    $ref=new Imagick();$ref->readImageBlob($expected);$color=$ref->getImagePixelColor(intdiv($ref->getImageWidth(),2),intdiv($ref->getImageHeight(),2))->getColor();
    foreach([2,59] as $index){$frames->setIteratorIndex($index);$actual=$frames->getImagePixelColor(intdiv($frames->getImageWidth(),2),intdiv($frames->getImageHeight(),2))->getColor();$expect($actual===$color,'end raster displayed in frame '.$index);}
    $frames->clear();$gif->clear();$ref->clear();
    $expect($renderer->render_static($c,$deadline,$deadline,'gif',$asset)===$at,'expired static fallback preserves chosen image');
    // Public eligibility is independent of viewer credentials and is checked on warm hits.
    (static function () use ($expect, $id, $c, $deadline, $renderer, $plugin, $method): void {
        $parent = wp_insert_post(['post_title'=>'Password policy fixture', 'post_status'=>'publish'], true);
        $expect(!is_wp_error($parent), 'create password policy parent');
        $cookie_key = 'wp-postpass_' . COOKIEHASH;
        $saved_cookies = $_COOKIE;
        $saved_get = $_GET;
        $saved_accept = $_SERVER['HTTP_ACCEPT'] ?? null;
        $saved_parent = (int) get_post($id)->post_parent;
        require_once ABSPATH . WPINC . '/class-phpass.php';
        $hasher = new PasswordHash(8, true);
        $capture = static function (callable $operation): string {
            ob_start();
            try { $operation(); return (string) ob_get_contents(); }
            finally { ob_end_clean(); }
        };
        $request = static fn(int $now): string => $capture(static fn() => $method->invoke($plugin, false, $now));
        $fallback = new ReflectionMethod($plugin, 'staticFallback');
        $cases = [['png', $deadline], ['gif', $deadline], ['gif', $deadline - 2]];
        if (function_exists('imagewebp')) $cases[] = ['webp', $deadline];
        try {
            foreach (['attachment', 'parent'] as $restriction) {
                foreach (['fixture-password', '0'] as $password) {
                    $owner = $restriction === 'parent' ? $parent : $id;
                    foreach ($cases as [$format, $now]) {
                        wp_set_current_user(1);
                        wp_update_post(['ID'=>$parent, 'post_password'=>'']);
                        wp_update_post(['ID'=>$id, 'post_password'=>'', 'post_parent'=>$restriction === 'parent' ? $parent : 0]);
                        unset($_COOKIE[$cookie_key]);
                        $_GET = ['ecd'=>'end-fixture', 'mode'=>$format === 'gif' ? 'email' : 'static'];
                        $_SERVER['HTTP_ACCEPT'] = $format === 'webp' ? 'image/webp' : '';
                        $key = 'ecd_v1211_' . hash('sha256', 'end-fixture|' . $format);
                        delete_transient($key);
                        $public_asset = Email_Countdown_Timer_End_Image::resolve($id);
                        $expect($public_asset !== null, 'public control resolves before restriction');
                        $public = $request($now);
                        $expected = $renderer->render($c, $deadline, $now, $format, $public_asset);
                        $denied = $renderer->render($c, $deadline, $now, $format);
                        $expect($public === $expected && $public !== $denied, 'public control returns the selected raster');
                        $expect(is_array(get_transient($key)), 'real image cache is warm before password change');
                        wp_update_post(['ID'=>$owner, 'post_password'=>$password]);
                        $expect(get_post($owner)->post_password === $password, 'WordPress persisted the tested password');
                        wp_set_current_user(0);
                        $expect(Email_Countdown_Timer_End_Image::resolve($id) === null, 'anonymous resolver rejects password restriction');
                        $expect($request($now) === $denied, 'password revocation bypasses previously warm image cache');
                        delete_transient($key);
                        $expect($request($now) === $denied, 'cold rendering does not disclose password-restricted raster');
                        $expect($renderer->render($c, $deadline, $now, $format, $public_asset) === $denied, 'previously resolved asset is rejected again at decode time');
                        $static = $capture(static fn() => $fallback->invoke($plugin, $c, $deadline, $now, $format, 'busy'));
                        $expect($static === $renderer->render_static($c, $deadline, $now, $format), 'current static fallback excludes restricted media');
                        // Match the native WordPress post-password cookie, not a dummy cookie.
                        $_COOKIE[$cookie_key] = $hasher->HashPassword($password);
                        $expect(!post_password_required($owner), 'native password gate accepts the test cookie');
                        foreach ([0, 1] as $viewer) {
                            wp_set_current_user($viewer);
                            $expect(Email_Countdown_Timer_End_Image::resolve($id) === null, 'valid cookie and admin login never grant shared publication');
                            $expect(!Email_Countdown_Timer_End_Image::validate_selection($id), 'restricted selection denied even for administrator');
                            $expect($request($now) === $denied, 'viewer credentials do not personalize shared image output');
                        }
                        wp_set_current_user(1);
                        wp_update_post(['ID'=>$owner, 'post_password'=>'']);
                        unset($_COOKIE[$cookie_key]);
                        $expect(Email_Countdown_Timer_End_Image::validate_selection($id), 'public selection works again after password removal');
                        $expect($request($now) === $public, 'password removal restores public output without flushing cache');
                        $expect(get_post($id) !== null && is_file($public_asset['file']), 'publication checks do not delete media');
                    }
                }
            }
        } finally {
            wp_set_current_user(1);
            wp_update_post(['ID'=>$id, 'post_password'=>'', 'post_parent'=>$saved_parent]);
            wp_delete_post($parent, true);
            $_COOKIE = $saved_cookies;
            $_GET = $saved_get;
            if ($saved_accept === null) unset($_SERVER['HTTP_ACCEPT']);
            else $_SERVER['HTTP_ACCEPT'] = $saved_accept;
            foreach (['png', 'gif', 'webp'] as $format) delete_transient('ecd_v1211_' . hash('sha256', 'end-fixture|' . $format));
        }
    })();
    wp_update_post(['ID'=>$id,'post_status'=>'trash']);
    $expect(Email_Countdown_Timer_End_Image::resolve($id)===null,'trashed attachment is not publicly rendered');
    ob_start();$method->invoke($plugin,false,$deadline+21);$removed=ob_get_clean();
    $expect($removed!==$at,'cached copy invalidated when attachment trashed');
    $expect(get_post($id)!==null && is_file($path),'plugin does not delete media');
    wp_update_post(['ID'=>$id,'post_status'=>'inherit']);
    wp_set_current_user(0);$expect(!Email_Countdown_Timer_End_Image::validate_selection($id),'anonymous cannot choose an attachment');wp_set_current_user(1);
} finally {
    update_option('easy_countdown_timers',$saved,false);
    foreach(['png','gif','webp'] as $fmt) delete_transient('ecd_v1211_'.hash('sha256','end-fixture|'.$fmt));
    wp_delete_attachment($id,true);
}
WP_CLI::success('END IMAGE: '.$checks.' assertions on real WordPress.');
