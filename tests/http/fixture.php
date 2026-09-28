<?php
/** Destructive synthetic-fixture actions; local CI only, never a production path. */
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI || getenv('ECD_HTTP_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') exit(1);
global $wpdb;
$task=$args[0]??'';
$key='ecd_v1211_'.hash('sha256','http-timer|gif');
$name='ect:'.substr(hash('sha256',DB_NAME.'|'.$wpdb->options.'|'.$key),0,60);
if ($task==='seed') {
    update_option('easy_countdown_timers',['http-timer'=>Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59','fixed_width'=>600,'font'=>'fixture.ttf','alt'=>'Synthetic countdown'])],false);
} elseif ($task==='expire') {
    $timers=get_option('easy_countdown_timers');
    $timers['http-timer']['deadline']='2000-01-01T00:00:00';$timers['http-timer']['tz']='UTC';
    update_option('easy_countdown_timers',$timers,false);
    foreach(['gif','png','webp'] as $fmt) delete_transient('ecd_v1211_'.hash('sha256','http-timer|'.$fmt));
    $c=Email_Countdown_Timer_Config::normalize($timers['http-timer']);
    echo hash('sha256',(new Email_Countdown_Timer_Renderer())->render_static($c,Email_Countdown_Timer_Config::deadline($c),time(),'gif'));
} elseif ($task==='reset') {
    foreach(['gif','png','webp'] as $fmt) delete_transient('ecd_v1211_'.hash('sha256','http-timer|'.$fmt));
} elseif ($task==='stale') {
    $cache=get_transient($key);
    if (!is_array($cache)) WP_CLI::error('Seed image cache before stale test');
    $cache['bucket']=intdiv(time(),15)-1;set_transient($key,$cache,60);
} elseif ($task==='hold') {
    if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$name))!=='1') WP_CLI::error('Could not hold synthetic lock');
    echo "LOCK_HELD\n";flush();sleep(8);
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name));
} elseif ($task==='privacy') {
    $count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s",'%ECT_HTTP_PRIVATE_CANARY%'));
    if ((int)$count!==0) WP_CLI::error('Request canary unexpectedly persisted');
    echo "NO_REQUEST_CANARY_IN_OPTIONS\n";
} elseif ($task==='versions') {
    echo json_encode(['php'=>PHP_VERSION,'wordpress'=>get_bloginfo('version'),'database'=>$wpdb->db_version(),'external_object_cache'=>wp_using_ext_object_cache(),'imagick'=>phpversion('imagick'),'gd'=>gd_info()['GD Version']]);
} else WP_CLI::error('Unknown synthetic task');
