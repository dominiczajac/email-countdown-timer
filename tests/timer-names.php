<?php
/** Modern forms hide technical IDs and validate required unique display names. */
if ( PHP_SAPI !== 'cli' || ! defined( 'ABSPATH' ) || ! function_exists( 'ok' ) ) exit(1);
$names_snapshot = $options;
$names_post = array( 'ecd_action'=>'email_countdown_timer_save', 'creation_mode'=>'quick', 'appearance_preset'=>'classic', 'deadline'=>'2035-12-31T23:59:59', 'tz'=>'UTC', 'name_workflow'=>'1', 'timer_id'=>'do-not-use-this-id', 'campaign_name'=>'Launch timer' );
$options['easy_countdown_timers'] = array();
ok( str_starts_with($submit_admin($names_post),'redirect:'), 'modern creation succeeds with a required name' );
$names_created = $options['easy_countdown_timers'];$names_id = array_key_first($names_created);
ok( preg_match('/^timer-[a-f0-9]{12}$/D',$names_id) && $names_id!=='do-not-use-this-id' && !str_contains($names_id,'Launch'), 'server generates an opaque ID independently of posted ID and private name' );
$names_before = $options;
foreach (array('', '  ', 'Launch timer', 'launch TIMER') as $names_bad) {
    $submit_admin(array_replace($names_post,array('campaign_name'=>$names_bad)));
    ok($options===$names_before && isset(Email_Countdown_Timer_Admin::state()['errors']['campaign_name']), 'empty and duplicate names cannot write, including case/space normalization');
}
$options['easy_countdown_timers'][$names_id]['archived']=true;
$names_before=$options;$submit_admin($names_post);
ok($options===$names_before && isset(Email_Countdown_Timer_Admin::state()['errors']['campaign_name']), 'archived names remain reserved');
$names_edit=array_merge(array_map('strval',$names_created[$names_id]),array('ecd_action'=>'email_countdown_timer_save','original_id'=>$names_id,'timer_id'=>'malicious-new-id','name_workflow'=>'1','campaign_name'=>'Renamed launch'));
ok(str_starts_with($submit_admin($names_edit),'redirect:') && isset($options['easy_countdown_timers'][$names_id]) && !isset($options['easy_countdown_timers']['malicious-new-id']), 'renaming cannot rewrite the original public ID');
ok($options['easy_countdown_timers'][$names_id]['tz']==='UTC', 'renaming preserves saved timezone');
ok(Email_Countdown_Timer_Admin::presets()['black-friday']['font']===Email_Countdown_Timer_Fonts::BARLOW_CONDENSED_BLACK, 'Black Friday chooses the genuine weight-900 face');
$options=$names_snapshot;$reset_admin();$_GET=$_POST=array();
