<?php
/** New public HTML view: native glyphs without public font paths or files. */
ob_start();
require __DIR__.'/run.php';
$start=$checks;
foreach(Email_Countdown_Timer_Fonts::bundled() as $choice=>$face) {
 $raw=array_replace($base,array('font'=>$choice,'deadline'=>'2035-01-01T00:00:00','campaign_name'=>'PRIVATE-FRAME-NAME','label_d'=>'Dni','label_h'=>'Godzin','label_m'=>'Minut','label_s'=>'Sekund'));
 $end=Email_Countdown_Timer_Config::deadline($raw);
 foreach([86401,86399,60,0] as $left) {
  $p=Email_Countdown_Timer_Frame::payload('frame-test',$raw,$end-$left);
  ok($p['remaining']===$left,'frame server time is exact');
  ok(!str_contains(json_encode($p),'PRIVATE-FRAME-NAME') && !str_contains(json_encode($p),EMAIL_COUNTDOWN_TIMER_DIR),'frame excludes names and disk paths');
  foreach(array_merge(array_values($p['digits']),$p['labels']) as $s) {
   $bytes=base64_decode(substr($s['uri'],strlen('data:image/png;base64,')),true);$im=imagecreatefromstring($bytes);
   ok($im instanceof GdImage && imagesx($im)===$s['w'] && imagesy($im)===$s['h'],'every sprite is a decodable bounded PNG');
  }
  Email_Countdown_Timer_Config::checkCanvas($p['w'],$p['h']);
 }
}
$empty_frame=Email_Countdown_Timer_Frame::payload('empty',array_replace($base,array('font'=>Email_Countdown_Timer_Fonts::BUNDLED_CHOICE,'label_h'=>'')));
ok($empty_frame['labels'][1]['w']>=1,'empty labels render safely');
$wide=Email_Countdown_Timer_Frame::payload('wide',array_replace($base,array('font'=>'','label_d'=>str_repeat('A',139),'label_h'=>str_repeat('A',139),'label_m'=>str_repeat('A',139),'label_s'=>str_repeat('A',139))));
ok($wide['w']<=4000 && $wide['h']<=1000,'valid boundary canvas stays within the original limits');
$saved_db=$GLOBALS['wpdb'];$GLOBALS['wpdb']=new stdClass();
try {
 $degraded=Email_Countdown_Timer_Frame::document('unsupported-frame',$base);
 preg_match('~<script type="application/json" id="ect-frame-data">(.*?)</script>~s',$degraded,$matches);
 ok(json_decode($matches[1],true)['text']===true,'unsupported locking retains an active system-font frame');
}
finally { $GLOBALS['wpdb']=$saved_db; }
$GLOBALS['wpdb']->allow=false; $before_writes=$writes;
try {
 $busy=Email_Countdown_Timer_Frame::document('busy-frame',$base);
 preg_match('~<script type="application/json" id="ect-frame-data">(.*?)</script>~s',$busy,$matches);
 ok(json_decode($matches[1],true)['text']===true && $writes===$before_writes,'busy lock uses active text without generating or publishing rasters');
} finally { $GLOBALS['wpdb']->allow=true; }
$large=array_replace($base,array('font'=>Email_Countdown_Timer_Fonts::BUNDLED_CHOICE));
ok(strlen(serialize(Email_Countdown_Timer_Frame::payload('small-budget',$large)))>4096,'budget fixture really exceeds the selected cache ceiling');
$GLOBALS['test_filters']['email_countdown_timer_cache_entry_max_bytes'][]=static function(){return 4096;};
try {
 $small=Email_Countdown_Timer_Frame::document('small-budget',$large);
 preg_match('~<script type="application/json" id="ect-frame-data">(.*?)</script>~s',$small,$matches);
 ok(json_decode($matches[1],true)['text']===true,'oversize raster payload falls back to an active text frame');
 $entry=$GLOBALS['transients']['ecd_v1211_'.hash('sha256','small-budget|frame')]??null;
 ok($entry===null || strlen(serialize($entry))<=4096,'stored fallback respects the host cache ceiling');
} finally { unset($GLOBALS['test_filters']['email_countdown_timer_cache_entry_max_bytes']); }
$raw=array_replace($base,array('alt'=>'" onload="alert(1)','campaign_name'=>'PRIVATE-FRAME-NAME'));
$html=Email_Countdown_Timer_Frame::document('frame-test',$raw);
preg_match('~<script type="application/json" id="ect-frame-data">(.*?)</script>~s',$html,$matches);
ok(!array_key_exists('fallback',json_decode($matches[1],true)),'fallback PNG is not duplicated in the clock JSON');
$frame_key='ecd_v1211_'.hash('sha256','frame-test|frame');
$GLOBALS['transients'][$frame_key]['data']['test_completed_payload']='already-generated';
foreach([true,false] as $external) {
 $GLOBALS['ect_ext_cache']=$external;
 $GLOBALS['ect_transient_local'][$frame_key]=false;
 $before_writes=$writes;
 $shared=Email_Countdown_Timer_Frame::document('frame-test',$raw);
 ok(str_contains($shared,'already-generated'),'post-lock read reuses completed payload despite a cached local miss');
 ok($writes===$before_writes,'cache follower does not regenerate or overwrite the completed entry');
 unset($GLOBALS['ect_transient_local'][$frame_key]);
}
$GLOBALS['ect_ext_cache']=false;
ok(!str_contains($html,' onload="alert') && !str_contains($html,'PRIVATE-FRAME-NAME'),'frame HTML escapes metadata and excludes private name');
ok(str_contains($html,'application/json') && str_contains($html,'nonce=') && str_contains($html,'ect-frame-canvas'),'document includes isolated clock and inert escaped data');
ok(str_contains(Email_Countdown_Timer_Frame::url('frame-test'),'ecd_action=frame'),'external URL is public frame route');
echo 'FRAME PASS: '.($checks-$start)." native assertions\n";

ob_end_flush();
