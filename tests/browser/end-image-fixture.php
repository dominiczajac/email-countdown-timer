<?php
if(PHP_SAPI!=='cli'||getenv('ECD_INTEGRATION_DISPOSABLE')!=='1'||wp_get_environment_type()!=='local'||$GLOBALS['wpdb']->base_prefix!=='ecdui_')exit(1);
$up=wp_upload_dir();$path=$up['path'].'/easy-countdown-end-fixture.png';
$image=imagecreatetruecolor(96,32);imagefill($image,0,0,imagecolorallocate($image,20,190,100));imagepng($image,$path);unset($image);
$id=wp_insert_attachment(['post_title'=>'Easy Countdown end-image fixture','post_mime_type'=>'image/png','post_status'=>'inherit','post_author'=>1],$path);
require_once ABSPATH.'wp-admin/includes/image.php';
wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$path));
echo $id;
