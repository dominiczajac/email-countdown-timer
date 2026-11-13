<?php
/** Public read-only presentation: raster glyphs, never downloadable font files. */
if ( ! defined( 'ABSPATH' ) ) exit;
final class Email_Countdown_Timer_Frame {
    public static function url( string $id, bool $paused = false ): string {
        return add_query_arg( array( 'ecd_action'=>'frame', 'ecd'=>$id, 'paused'=>$paused ? '1' : '0' ), home_url( '/' ) );
    }

    public static function dimensions( array $raw ): array {
        try {
            $c = Email_Countdown_Timer_Config::normalize( $raw );
            $box = (new Email_Countdown_Timer_Renderer())->measure( $c, Email_Countdown_Timer_Config::deadline($c), time() );
            return array( 'width'=>$box['width'], 'height'=>max(160, $box['height'] + 100) );
        } catch ( Throwable $error ) { return array('width'=>600,'height'=>160); }
    }

    private static function png( $image ): string {
        ob_start();
        try { if (!imagepng($image)) throw new RuntimeException('PNG unavailable.'); return 'data:image/png;base64,'.base64_encode((string)ob_get_contents()); }
        finally { ob_end_clean(); }
    }

    private static function sprite( string $text, int $size, ?string $font, string $color, string $bg, int $bitmap ): array {
        if ($font) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($box === false) throw new RuntimeException('Font metrics unavailable.');
            $minx=min($box[0],$box[2],$box[4],$box[6]); $miny=min($box[1],$box[3],$box[5],$box[7]);
            $w=max($box[0],$box[2],$box[4],$box[6])-$minx+2; $h=max($box[1],$box[3],$box[5],$box[7])-$miny+2;
        } else { $w=strlen($text)*imagefontwidth($bitmap)+2; $h=imagefontheight($bitmap)+2; $minx=0; $miny=0; }
        Email_Countdown_Timer_Config::checkCanvas($w,$h);
        $im=imagecreatetruecolor($w,$h);
        $alloc=static function($hex) use($im) { $hex=ltrim($hex,'#'); if(strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; return imagecolorallocate($im,hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))); };
        imagefill($im,0,0,$alloc($bg)); $ink=$alloc($color);
        if($text!=='') {
            if($font) imagettftext($im,$size,0,1-$minx,1-$miny,$ink,$font,$text);
            else imagestring($im,$bitmap,1,1,$text,$ink);
        }
        return array('uri'=>self::png($im),'w'=>$w,'h'=>$h);
    }

    public static function payload( string $id, array $raw, ?int $now = null ): array {
        $c=Email_Countdown_Timer_Config::normalize($raw); $deadline=Email_Countdown_Timer_Config::deadline($c); $now=$now ?? time();
        if(!function_exists('imagecreatetruecolor')) throw new RuntimeException('GD unavailable.');
        $renderer=new Email_Countdown_Timer_Renderer(); $box=$renderer->measure($c,$deadline,$now);
        $font=$box['font']; $digits=array(); $labels=array(); $dw=0; $dh=0; $lh=0; $pixels=0;
        foreach(str_split('0123456789:') as $char) {
            $s=self::sprite($char,$c['size_digit'],$font,$c['dc'],$c['bg'],5); $digits[$char]=$s;
            if($char!==':') $dw=max($dw,$s['w']); $dh=max($dh,$s['h']); $pixels+=$s['w']*$s['h'];
        }
        foreach(array('d','h','m','s') as $unit) {
            $s=self::sprite($c['label_'.$unit],$c['size_label'],$font,$c['lc'],$c['bg'],3); $labels[]=$s; $lh=max($lh,$s['h']); $pixels+=$s['w']*$s['h'];
        }
        if($pixels>2400000) throw new RuntimeException('Glyph budget exceeded.');
        $remaining=max(0,$deadline-$now); $dayDigits=max(2,strlen((string)intdiv($remaining,86400))); $cols=array();
        foreach($labels as $i=>$label) $cols[]=max($label['w'],($i===0?$dayDigits:2)*$dw);
        $width=max($box['width'],array_sum($cols)+3*(20+$digits[':']['w'])+20); $height=$dh+$lh+30;
        Email_Countdown_Timer_Config::checkCanvas($width,$height);
        return array('remaining'=>$remaining,'hideDays'=>(bool)$c['hide_days'],'w'=>$width,'h'=>$height,'dw'=>$dw,'dh'=>$dh,'cols'=>$cols,'digits'=>$digits,'labels'=>$labels,'bg'=>$c['bg'],
            'fallback'=>'data:image/png;base64,'.base64_encode($renderer->render_static($c,$deadline,$now,'png',empty($c['expiry_image_id'])?null:Email_Countdown_Timer_End_Image::resolve((int)$c['expiry_image_id']))),
            'end'=>empty($c['expiry_image_id'])?'':add_query_arg(array('ecd_action'=>'render','ecd'=>$id,'mode'=>'end'),home_url('/')));
    }

    public static function document( string $id, array $raw, bool $paused = false ): string {
        $data=self::payload($id,$raw); $data['paused']=$paused;
        $alt=Email_Countdown_Timer_Config::alt($raw,__('Countdown','easy-countdown'));
        $nonce=base64_encode(random_bytes(18));
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce'; style-src 'nonce-$nonce'; img-src 'self' data:; frame-ancestors *; base-uri 'none'; form-action 'none'");
        header_remove('X-Frame-Options');
        ob_start(); ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html__('Countdown timer','easy-countdown'); ?></title>
<style nonce="<?php echo esc_attr($nonce); ?>">html,body{margin:0;padding:0;background:transparent;font:13px system-ui,sans-serif}main{text-align:center}canvas,img{display:block;max-width:100%;height:auto;margin:auto}[hidden]{display:none!important}button{font:inherit;min-height:30px;margin:4px;cursor:pointer}.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}</style></head><body><main>
<img id="ect-frame-fallback" src="<?php echo esc_attr($data['fallback']); ?>" alt="<?php echo esc_attr($alt); ?>">
<canvas id="ect-frame-canvas" width="<?php echo esc_attr((string)$data['w']); ?>" height="<?php echo esc_attr((string)$data['h']); ?>" role="img" aria-label="<?php echo esc_attr($alt); ?>" hidden></canvas>
<button id="ect-frame-pause" type="button" data-pause="<?php echo esc_attr__('Pause countdown','easy-countdown'); ?>" data-resume="<?php echo esc_attr__('Resume countdown','easy-countdown'); ?>" hidden><?php echo esc_html__('Pause countdown','easy-countdown'); ?></button>
<p class="sr"><?php echo esc_html__('Deadline:','easy-countdown').' '.esc_html(Email_Countdown_Timer_Config::text($raw,'deadline')).' '.esc_html(Email_Countdown_Timer_Config::text($raw,'tz')); ?></p>
<script type="application/json" id="ect-frame-data"><?php echo wp_json_encode($data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex escaped for this inert data element. ?></script>
<script nonce="<?php echo esc_attr($nonce); ?>"><?php
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.EscapeOutput.OutputNotEscaped -- Read only this reviewed plugin-owned script; no request-derived path or remote source. CSP nonce permits it in the standalone frame, including sandboxed/cross-origin embeds.
        echo file_get_contents(EMAIL_COUNTDOWN_TIMER_DIR.'assets/frame.js');
?></script></main></body></html>
<?php return (string)ob_get_clean();
    }

    public static function listen(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only presentation of an existing public timer; no writes, sessions or arbitrary settings.
        if(Email_Countdown_Timer_Config::text($_GET,'ecd_action')!=='frame') return;
        header('Content-Type: text/html; charset=UTF-8'); header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
        $method=Email_Countdown_Timer_Config::text($_SERVER,'REQUEST_METHOD','GET');
        if(!in_array($method,array('GET','HEAD'),true)) { header('Allow: GET, HEAD'); status_header(405); exit; }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated canonical public identifier; does not authorize a mutation.
        $id=Email_Countdown_Timer_Config::text($_GET,'ecd'); $timers=get_option('easy_countdown_timers',array());
        if($id==='' || strlen($id)>200 || Email_Countdown_Timer_Config::id($id)!==$id || !is_array($timers) || !isset($timers[$id]) || !is_array($timers[$id])) { status_header(404); exit; }
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only initial playback preference, not saved configuration.
            $html=self::document($id,$timers[$id],Email_Countdown_Timer_Config::text($_GET,'paused')==='1');
            if($method!=='HEAD') echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete document escapes text/attributes and uses hex-encoded JSON above.
        } catch(Throwable $error) { status_header(503); }
        exit;
    }
}
