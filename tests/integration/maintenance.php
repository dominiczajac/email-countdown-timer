<?php
/** Site-timezone defaults tested against actual WordPress options, not mocks. */
if (!defined('WP_CLI') || !WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE')!=='1' || wp_get_environment_type()!=='local') exit(1);
require_once EMAIL_COUNTDOWN_TIMER_DIR.'includes/class-email-countdown-timer-admin.php';
$before_tz=get_option('timezone_string');$before_offset=get_option('gmt_offset');$before_timers=get_option('easy_countdown_timers');$checks=0;
$check=static function($value,$message)use(&$checks){++$checks;if(!$value)throw new RuntimeException($message);};
try {
    $legacy=['deadline'=>'2030-12-31T23:59:59'];
    foreach([['America/New_York',0,'America/New_York'],['',5.75,'+05:45'],['UTC',0,'UTC']]as[$zone,$offset,$expected]){
        update_option('timezone_string',$zone);update_option('gmt_offset',$offset);
        update_option('easy_countdown_timers',['legacy-zone'=>$legacy]);$_GET=['view'=>'new'];
        $check(Email_Countdown_Timer_Admin::state()['data']['tz']===$expected,'New timer uses actual WordPress timezone');
        $_GET=['edit'=>'legacy-zone'];
        $check(Email_Countdown_Timer_Admin::state()['data']['tz']==='Europe/Warsaw','Old missing timezone preserves original meaning');
        $check(Email_Countdown_Timer_Config::deadline($legacy)===(new DateTimeImmutable($legacy['deadline'],new DateTimeZone('Europe/Warsaw')))->getTimestamp(),'Public legacy deadline independent of site setting');
    }
}finally{
    update_option('timezone_string',$before_tz);update_option('gmt_offset',$before_offset);
    if(false===$before_timers)delete_option('easy_countdown_timers');else update_option('easy_countdown_timers',$before_timers,false);
    $_GET=[];
}
WP_CLI::success("MAINTENANCE: $checks real timezone assertions.");
