<?php
// WP-CLI fixtures only. The public plugin remains byte-identical to the target ZIP.
if (PHP_SAPI !== 'cli' || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectcompat_') { exit(1); }
$mode = $args[0] ?? '';
if ($mode === 'seed') {
    // Preconfigured-site profiles do not exercise first-install product tours.
    // WooCommerce documents this transient removal for extension test/demo sites.
    delete_transient('_wc_activation_redirect');
    delete_transient('elementor_activation_redirect');
    require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
    $base = Email_Countdown_Timer_Config::normalize(Email_Countdown_Timer_Admin::defaults());
    $base['deadline'] = '2035-12-31T23:59:59'; $base['alt'] = 'Compatibility countdown';
    $up = wp_upload_dir(); $path = $up['path'] . '/compat-end.png';
    $im = imagecreatetruecolor(96,32); imagefill($im,0,0,imagecolorallocate($im,20,190,100)); imagepng($im,$path); unset($im);
    $id = wp_insert_attachment(['post_title'=>'Compatibility end image','post_mime_type'=>'image/png','post_status'=>'inherit','post_author'=>1],$path);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$path));
    $ended = $base; $ended['deadline']='2001-01-01T00:00:05'; $ended['expiry_image_id']=$id;
    $other = $base; $other['bg']='#112244';
    update_option('easy_countdown_timers',['compat-live'=>$base,'compat-other'=>$other,'compat-end'=>$ended],false);
    $page = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Compatibility page', 'post_content'=>'[ect_compat_origin]<h2>Countdown fixture</h2>[ecd_timer id="compat-live"][ecd_timer id="compat-end"]']);
    update_option('show_on_front','page'); update_option('page_on_front',$page);
    $extra = [];
    if (class_exists('autoptimizeConfig')) {
        foreach (['autoptimize_html'=>'on','autoptimize_js'=>'on','autoptimize_js_aggregate'=>'on','autoptimize_css'=>'on','autoptimize_css_aggregate'=>'on','autoptimize_js_defer_not_aggregate'=>'on'] as $key=>$value) { update_option($key,$value); }
    }
    if (class_exists('WC_Product_Simple')) {
        update_option('woocommerce_coming_soon','no'); update_option('woocommerce_allow_tracking','no');
        $product = new WC_Product_Simple(); $product->set_name('Compatibility sample product'); $product->set_status('publish'); $product->set_regular_price('12'); $product->set_virtual(true); $product->set_description('[ecd_timer id="compat-live"]');
        $product->save(); $extra['product_url']=get_permalink($product->get_id());
    }
    if (defined('ELEMENTOR_VERSION')) {
        $ep = wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Compatibility Elementor']);
        update_post_meta($ep,'_elementor_edit_mode','builder'); update_post_meta($ep,'_elementor_version',ELEMENTOR_VERSION);
        update_post_meta($ep,'_elementor_data',wp_slash(wp_json_encode([['id'=>'a102bd3','elType'=>'container','settings'=>[], 'elements'=>[['id'=>'b304cf1','elType'=>'widget','widgetType'=>'shortcode','settings'=>['shortcode'=>'[ecd_timer id="compat-live"]'],'elements'=>[]]]]])));
        $extra['elementor_url']=get_permalink($ep);
    }
    $render = new Email_Countdown_Timer_Renderer();
    $ending = Email_Countdown_Timer_End_Image::resolve($id);
    $expected = hash('sha256',$render->render($ended,Email_Countdown_Timer_Config::deadline($ended),time(),'gif',$ending));
    echo wp_json_encode(['attachment_id'=>$id,'page_id'=>$page,'end_sha256'=>$expected,'extra'=>$extra]);
} elseif ($mode === 'expire' || $mode === 'restore') {
    $timers = get_option('easy_countdown_timers');
    $timers['compat-live']['deadline'] = $mode === 'expire' ? '2001-01-01T00:00:05' : '2035-12-31T23:59:59';
    $timers['compat-live']['expiry_image_id'] = $mode === 'expire' ? $timers['compat-end']['expiry_image_id'] : 0;
    update_option('easy_countdown_timers',$timers,false);
    // Deliberately do not flush any cache: the content identity/expiry must invalidate it.
} elseif ($mode === 'reverse') {
    update_option('active_plugins',array_reverse(get_option('active_plugins',[])));
} elseif ($mode === 'inventory') {
    global $wpdb,$cache_enabled,$super_cache_enabled;
    $ao=[];
    foreach (['autoptimize_html','autoptimize_js','autoptimize_js_aggregate','autoptimize_css','autoptimize_css_aggregate'] as $k) { $ao[$k]=get_option($k); }
    $w3=[]; if (class_exists('W3TC\\Dispatcher')) { $c=\W3TC\Dispatcher::config(); foreach (['pgcache.enabled','pgcache.engine','browsercache.enabled','lazyload.enabled'] as $k) { $w3[$k]=$c->get($k); } }
    echo wp_json_encode(['php'=>PHP_VERSION,'wordpress'=>get_bloginfo('version'),'database'=>$wpdb->get_var('SELECT VERSION()'),'profile'=>getenv('ECD_COMPAT_PROFILE'),'server'=>'loopback PHP CLI server, eight workers (not PHP-FPM)','gd'=>gd_info()['GD Version']??null,'imagick'=>phpversion('imagick'),'wp_cache'=>defined('WP_CACHE')&&WP_CACHE,'supercache'=>['cache_enabled'=>$cache_enabled??null,'super_cache_enabled'=>$super_cache_enabled??null],'autoptimize'=>$ao,'w3'=>$w3,'active_plugins'=>get_option('active_plugins')]);
}
