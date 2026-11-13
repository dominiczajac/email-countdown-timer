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
$options['easy_countdown_timers']['other-timer'] = array_replace($names_created[$names_id], array('campaign_name'=>'Reserved name'));
$submit_admin(array_replace($names_edit,array('campaign_name'=>'Reserved name')));
ob_start(); $plugin->renderAdminPage(); $failed_name_view=ob_get_clean();
foreach (array('Existing emails and pages', 'This cannot be undone') as $warning) {
    ok(str_contains($failed_name_view, 'Permanently delete Renamed launch? '.$warning) && !str_contains($failed_name_view, 'Permanently delete Reserved name?'), 'failed rename deletion warning identifies the saved timer: '.$warning);
}
$options['easy_countdown_timers']['legacy-a']=array_replace($names_created[$names_id],array('campaign_name'=>'Old shared name'));
$options['easy_countdown_timers']['legacy-b']=$options['easy_countdown_timers']['legacy-a'];
$legacy_name_post=array_replace($names_edit,array('original_id'=>'legacy-a','campaign_name'=>'Old shared name'));
$legacy_before=$options;$submit_admin($legacy_name_post);
ok($options===$legacy_before && isset(Email_Countdown_Timer_Admin::state()['errors']['campaign_name']), 'legacy colliding names require a unique name on the next modern save');
ob_start(); $plugin->renderAdminPage(); $collision_view=ob_get_clean();
ok(str_contains($collision_view,'Edit timer: Old shared name (legacy-a)') && substr_count($collision_view,'Permanently delete Old shared name (legacy-a)?')===2, 'colliding saved names remain distinguishable in editor and both destructive confirmations');
$reset_admin();$_GET=$_POST=array();
ob_start(); $plugin->renderAdminPage(); $collision_list=ob_get_clean();
ok(str_contains($collision_list,'Old shared name (legacy-a)') && str_contains($collision_list,'Old shared name (legacy-b)'), 'list gives distinct identities to colliding legacy records');
ob_start(); Email_Countdown_Timer_Admin::render_dashboard_widget(); $collision_dashboard=ob_get_clean();
ok(str_contains($collision_dashboard,'Old shared name (legacy-a)') && str_contains($collision_dashboard,'Old shared name (legacy-b)'), 'dashboard gives distinct identities to colliding legacy records');
ok(str_starts_with($submit_admin(array_replace($legacy_name_post,array('campaign_name'=>'Unique legacy name'))),'redirect:') && $options['easy_countdown_timers']['legacy-a']['campaign_name']==='Unique legacy name', 'renaming a colliding legacy record restores normal editing without changing its ID');
ok(Email_Countdown_Timer_Admin::display_name(array(), 'legacy-a') === 'Unnamed timer (legacy-a)' && Email_Countdown_Timer_Admin::display_name(array(), 'legacy-b') === 'Unnamed timer (legacy-b)', 'unnamed legacy records keep distinct fallback identities until explicitly named');
ok(Email_Countdown_Timer_Admin::display_name(array('campaign_name'=>'0'), 'legacy-a') === '0', 'a valid zero-like timer name is displayed rather than replaced by a fallback');
$options=$names_snapshot;$reset_admin();$_GET=$_POST=array();
