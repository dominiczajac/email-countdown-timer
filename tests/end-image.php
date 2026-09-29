<?php
/** Adversarial local attachment tests plus native renderer/cache deadline regressions. */
if ( PHP_SAPI !== 'cli' ) { exit(1); }
$end_root = sys_get_temp_dir() . '/ect-end-' . bin2hex(random_bytes(6));
mkdir($end_root);
$end_posts = $end_files = [];
function get_post($id) { return $GLOBALS['end_posts'][$id] ?? null; }
function get_post_status($post) { return isset($post->post_parent) && $post->post_parent ? ($GLOBALS['end_posts'][$post->post_parent]->post_status ?? 'draft') : $post->post_status; }
function get_attached_file($id, $unfiltered=false) { if (!$unfiltered) throw new RuntimeException('Must not use offload filters'); return $GLOBALS['end_files'][$id] ?? false; }
function wp_get_upload_dir() { return ['basedir'=>$GLOBALS['end_root'], 'error'=>false]; }
ob_start();
require __DIR__.'/run.php';
ob_clean();
$start = $checks;
try {
    foreach ([null,[],true,-1,'1.2','1e2','../2',str_repeat('9',30)] as $id) ok(rejects(fn()=>Email_Countdown_Timer_End_Image::id($id)), 'malformed ID rejected');
    ok(Email_Countdown_Timer_End_Image::id('')===0 && Email_Countdown_Timer_End_Image::id('23')===23,'empty optional ID and canonical integer accepted');
    ok(Email_Countdown_Timer_End_Image::resolve(0)===null && Email_Countdown_Timer_End_Image::resolve(999)===null,'none or missing attachment not resolved');
    ok(!array_key_exists('expiry_image_id', Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-01-01T00:00:00'])),'legacy absence is preserved');
    ok(Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-01-01T00:00:00','expiry_image_id'=>'0'])['expiry_image_id']===0,'explicit no-image normalized');
    $small=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    file_put_contents($end_root.'/sample.png',$small);
    $end_posts[7]=(object)['post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image/png'];
    $end_files[7]=$end_root.'/sample.png';
    ok(Email_Countdown_Timer_End_Image::resolve(7)!==null,'valid local raster header resolved');
    foreach (['https://example.invalid/font.png','php://filter/resource=x', $end_root.'/../not-there.png'] as $bad) {
        $end_files[7]=$bad; ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'remote/wrapper/traversal rejected');
    }
    $end_files[7]=$end_root.'/sample.png';
    foreach (['trash','private','draft'] as $state) {
        $end_posts[7]->post_status=$state; ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'nonpublic attachment state rejected');
    }
    $end_posts[7]->post_status='inherit';$end_posts[9]=(object)['post_status'=>'private'];$end_posts[7]->post_parent=9;ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'attachment inheriting a private parent rejected');unset($end_posts[7]->post_parent);$end_posts[7]->post_type='post';
    ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'nonattachment rejected');$end_posts[7]->post_type='attachment';
    $end_posts[7]->post_mime_type='image/jpeg';ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'claimed/actual MIME mismatch rejected');$end_posts[7]->post_mime_type='image/png';
    file_put_contents($end_root.'/fake.png','<svg><script>no</script></svg>');$end_files[7]=$end_root.'/fake.png';
    ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'SVG and malformed binary are rejected');
    file_put_contents($end_root.'/oversize.png',substr_replace($small,pack('NN',4096,4096),16,8));$end_files[7]=$end_root.'/oversize.png';
    ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'oversize declared pixel budget rejected before decoding');
    $huge=fopen($end_root.'/large.png','wb');fwrite($huge,$small);ftruncate($huge,4194305);fclose($huge);$end_files[7]=$end_root.'/large.png';
    ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'input file byte limit enforced');
    symlink($end_root.'/sample.png',$end_root.'/link.png');$end_files[7]=$end_root.'/link.png';
    ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'contained symlink rejected');
    $outside=tempnam(sys_get_temp_dir(),'ect-out-');file_put_contents($outside,$small);$end_files[7]=$outside;
    ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'regular file outside uploads rejected');unlink($outside);
    $end_files[7]=$end_root.'/sample.png';$admin=false;
    ok(!Email_Countdown_Timer_End_Image::validate_selection(7),'selection requires permission');$admin=true;
    if (function_exists('imagecreatetruecolor')) {
        $image=imagecreatetruecolor(64,16);imagefill($image,0,0,imagecolorallocate($image,210,30,90));imagepng($image,$end_root.'/sample.png');unset($image);clearstatcache();
        $asset=Email_Countdown_Timer_End_Image::resolve(7);ok(Email_Countdown_Timer_End_Image::validate_selection(7),'valid image decodes before saving');
        $c=Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-01-01T00:00:05','expiry_image_id'=>7]);
        $deadline=Email_Countdown_Timer_Config::deadline($c);$renderer=new Email_Countdown_Timer_Renderer();
        $ordinary=$c;unset($ordinary['expiry_image_id']);
        ok($renderer->render($c,$deadline,$deadline-100,'png')===$renderer->render($ordinary,$deadline,$deadline-100,'png'),'no end asset leaves pre-deadline pixels unchanged');
        $ended=$renderer->render($c,$deadline,$deadline,'png',$asset);
        ok($ended!==$renderer->render($ordinary,$deadline,$deadline,'png'),'at exact deadline image replaces zeros');
        ok($ended===$renderer->render_static($c,$deadline,$deadline,'png',$asset),'static fallback uses the same end image');
        $decoded=imagecreatefromstring($ended);$pixel=imagecolorat($decoded,intdiv(imagesx($decoded),2),intdiv(imagesy($decoded),2));
        ok(($pixel & 0xffffff)===0xd21e5a,'center pixel is selected end image');unset($decoded);
        $options['easy_countdown_timers']=['ended'=>$c];$_GET=['ecd'=>'ended','mode'=>'email'];$method=new ReflectionMethod($plugin,'generateImage');
        $transients=[];$writes=0;ob_start();$method->invoke($plugin,false,$deadline-1);$before=ob_get_clean();
        ob_start();$method->invoke($plugin,false,$deadline);$at=ob_get_clean();
        ok($at!==$before && $writes===2,'deadline invalidates pre-deadline cache even within one bucket');
        ob_start();$method->invoke($plugin,false,$deadline+30);$later=ob_get_clean();
        ok($later===$at && $writes===2,'end image reuses existing cache beyond 15 seconds');
        if (class_exists('Imagick')) {
            $a=new Imagick();$a->readImageBlob($at);ok($a->getNumberImages()===1,'post-deadline GIF is static');$a->clear();
            $gif=$renderer->render($c,$deadline,$deadline-2,'gif',$asset);$a=new Imagick();$a->readImageBlob($gif);$all=$a->coalesceImages();
            ok($all->getNumberImages()===60,'crossing GIF retains 60 frames');
            $colors=[];foreach($all as $frame){$colors[]=$frame->getImagePixelColor(intdiv($frame->getImageWidth(),2),intdiv($frame->getImageHeight(),2))->getColor();}
            $ref=new Imagick();$ref->readImageBlob($renderer->render_static($c,$deadline,$deadline,'gif',$asset));$color=$ref->getImagePixelColor(intdiv($ref->getImageWidth(),2),intdiv($ref->getImageHeight(),2))->getColor();ok($colors[2]===$color && $colors[59]===$color,'exact deadline and final GIF frame show selected raster');$ref->clear();$a->clear();$all->clear();
        }
        unlink($end_root.'/sample.png');
        ok(Email_Countdown_Timer_End_Image::resolve(7)===null,'deleted selection not reused');
        ob_start();$method->invoke($plugin,false,$deadline+31);$missing=ob_get_clean();
        ok($missing!==$at,'missing end image invalidates shared image cache');
    }
} finally {
    foreach (glob($end_root.'/*') as $file) unlink($file);
    rmdir($end_root);
}
ob_end_clean();
echo 'PASS '.($checks-$start).' end-image checks'.(function_exists('imagecreatetruecolor')?' including native raster output':' (native GD/Imagick skipped locally)')."\n";
