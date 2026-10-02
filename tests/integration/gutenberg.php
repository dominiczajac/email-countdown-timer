<?php
/** Actual core registration, permissions and rendering; synthetic local data only. */
if ( PHP_SAPI !== 'cli' || ! defined( 'WP_CLI' ) || ! WP_CLI || getenv( 'ECD_INTEGRATION_DISPOSABLE' ) !== '1' || wp_get_environment_type() !== 'local' ) { exit(1); }
$checks = 0;
$expect = static function ( $value, $message ) use ( &$checks ) { ++$checks; if ( ! $value ) { WP_CLI::error( 'Gutenberg: ' . $message ); } };
$original = get_option( 'easy_countdown_timers', array() );
$original_user = get_current_user_id();
$users = $posts = array();
$site_id = null;
try {
    $type = WP_Block_Type_Registry::get_instance()->get_registered( 'easy-countdown/timer' );
    $expect( $type && $type->api_version === 3 && is_callable( $type->render_callback ), 'registered native dynamic block API 3' );
    $expect( $type->editor_script_handles === array( 'easy-countdown-block-editor' ), 'editor-only script metadata' );
    $expect( empty( $type->script_handles ) && empty( $type->view_script_handles ) && empty( $type->style_handles ), 'no new frontend asset' );
    $c = Email_Countdown_Timer_Config::normalize( array( 'deadline' => '2035-12-31T23:59:59', 'fixed_width' => 400, 'alt' => 'Campaign "A" & deadline' ) );
    update_option( 'easy_countdown_timers', array( 'block-test' => $c, 'empty-alt' => array_replace( $c, array( 'alt' => '' ) ) ), false );
    $snapshot = get_option( 'easy_countdown_timers' );
    $render = static function ( $attributes ) { return render_block( array( 'blockName' => 'easy-countdown/timer', 'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ); };
    foreach ( array( '', 'missing', array(), null, 'BLOCK-TEST', str_repeat('x',201) ) as $id ) {
        $expect( $render( array( 'timerId' => $id ) ) === '', 'empty/unknown/invalid timer suppressed' );
    }
    foreach ( array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ) as $align => $position ) {
        $html = $render( array( 'timerId' => 'block-test', 'alignment' => $align ) );
        $expect( str_contains( $html, 'wp-block-easy-countdown-timer' ) && str_contains( $html, 'justify-content:' . $position ), 'native wrapper/alignment ' . $align );
        $tag = new WP_HTML_Tag_Processor( $html ); $expect( $tag->next_tag('IMG'), 'shared image exists' );
        $expect( $tag->get_attribute('alt') === $c['alt'], 'saved alt is escaped and preserved' );
        $expect( (int)$tag->get_attribute('width') === 400 && (int)$tag->get_attribute('height') > 0 && str_contains($tag->get_attribute('style'),'aspect-ratio:'), 'reserved dimensions' );
        $expect( str_contains($tag->get_attribute('src'),'mode=anim') && $tag->get_attribute('data-no-lazy') === '1', 'same animation endpoint and cache/lazy-load contract' );
    }
    $a = new WP_HTML_Tag_Processor( $render( array( 'timerId'=>'block-test' ) ) ); $a->next_tag('IMG');
    $b = new WP_HTML_Tag_Processor( $render( array( 'timerId'=>'empty-alt' ) ) ); $b->next_tag('IMG');
    $expect( $a->get_attribute('id') !== $b->get_attribute('id') && $b->get_attribute('alt') === '', 'multiple images unique IDs; empty alt preserved' );
    $expect( wp_script_is('ecd-refresh','enqueued') && ! wp_script_is('easy-countdown-block-editor','enqueued'), 'only existing refresh script enqueued by public render' );
    foreach ( array('author','subscriber','administrator') as $role ) {
        $id = wp_insert_user(array('user_login'=>'ect-block-'.$role.'-'.wp_generate_password(8,false), 'user_pass'=>wp_generate_password(32), 'role'=>$role));
        $expect( !is_wp_error($id), 'create synthetic '.$role ); $users[$role]=$id;
    }
    $post_id = wp_insert_post(array('post_title'=>'Block permission fixture','post_type'=>'post','post_author'=>$users['author'],'post_status'=>'draft'),true);
    $expect(!is_wp_error($post_id),'create editable post'); $posts[]=$post_id;
    $context = new WP_Block_Editor_Context(array('post'=>get_post($post_id)));
    foreach ( array(0, $users['subscriber']) as $id ) {
        wp_set_current_user($id);
        $expect( !isset(Email_Countdown_Timer_Block::editor_settings(array('easyCountdown'=>'stale'),$context)['easyCountdown']), 'denied user cannot get list' );
    }
    wp_set_current_user($users['author']);
    $settings=Email_Countdown_Timer_Block::editor_settings(array('unrelated'=>'kept'),$context);
    $expect($settings['unrelated']==='kept' && count($settings['easyCountdown']['timers'])===2,'author gets choices for own editable post');
    $expect($settings['easyCountdown']['manageUrl']==='' && !current_user_can('manage_options'),'author gets no settings privilege/link');
    $expect(array_keys($settings['easyCountdown']['timers'][0])===array('value','label'),'no raw configuration in choice');
    $expect(!isset(Email_Countdown_Timer_Block::editor_settings(array(),new WP_Block_Editor_Context())['easyCountdown']),'author cannot get site editor settings');
    $other=wp_insert_post(array('post_title'=>'Other author','post_status'=>'publish','post_author'=>$users['administrator']),true);$posts[]=$other;
    $expect(!isset(Email_Countdown_Timer_Block::editor_settings(array(),new WP_Block_Editor_Context(array('post'=>get_post($other))))['easyCountdown']),'author cannot get list for another authors post');
    wp_set_current_user($users['administrator']);
    $settings=Email_Countdown_Timer_Block::editor_settings(array(),$context)['easyCountdown'];
    $expect(str_contains($settings['manageUrl'],'page=ecd-timers'),'admin settings link');
    $expect(isset(Email_Countdown_Timer_Block::editor_settings(array(),new WP_Block_Editor_Context())['easyCountdown']),'admin site editor supported');
    $expect(get_option('easy_countdown_timers')===$snapshot,'no campaign mutation');
    $changed=$snapshot;$changed['block-test']['alt']='Updated without resaving post';update_option('easy_countdown_timers',$changed,false);
    $expect(str_contains($render(array('timerId'=>'block-test')),'Updated without resaving post'),'dynamic saved changes');
    unset($changed['block-test']);update_option('easy_countdown_timers',$changed,false);
    $expect($render(array('timerId'=>'block-test'))==='','deleted timer no broken image');
    update_option('easy_countdown_timers',array_fill_keys(array_map(static fn($i)=>'timer-'.$i,range(1,251)),$c),false);
    $settings=Email_Countdown_Timer_Block::editor_settings(array(),$context)['easyCountdown'];
    $expect(count($settings['timers'])===250 && $settings['truncated'],'bounded editor list');
    $expect(str_contains($render(array('timerId'=>'timer-251')),'<img'),'unlisted existing ID still renders');
    if(is_multisite()){
        $site_id=wpmu_create_blog('block-isolation.example.invalid','/','Block isolated site',1,array('public'=>0),get_current_network_id());
        $expect(!is_wp_error($site_id),'create second isolated site');
        switch_to_blog($site_id);
        try { $expect($render(array('timerId'=>'timer-251'))==='','no cross-site campaign lookup'); }
        finally {restore_current_blog();}
    }
} finally {
    update_option('easy_countdown_timers',$original,false);wp_set_current_user($original_user);
    foreach($posts as $id){if(!is_wp_error($id))wp_delete_post($id,true);}
    require_once ABSPATH.'wp-admin/includes/user.php';
    foreach($users as $id){wp_delete_user($id);}
    if(is_int($site_id)){wp_delete_site($site_id);}
}
echo 'GUTENBERG INTEGRATION PASS: '.$checks." assertions\n";
