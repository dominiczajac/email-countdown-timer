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
$raw=array_replace($base,array('alt'=>'" onload="alert(1)','campaign_name'=>'PRIVATE-FRAME-NAME'));
$html=Email_Countdown_Timer_Frame::document('frame-test',$raw);
ok(!str_contains($html,' onload="alert') && !str_contains($html,'PRIVATE-FRAME-NAME'),'frame HTML escapes metadata and excludes private name');
ok(str_contains($html,'application/json') && str_contains($html,'nonce=') && str_contains($html,'ect-frame-canvas'),'document includes isolated clock and inert escaped data');
ok(str_contains(Email_Countdown_Timer_Frame::url('frame-test'),'ecd_action=frame'),'external URL is public frame route');
echo 'FRAME PASS: '.($checks-$start)." native assertions\n";

ob_end_flush();
