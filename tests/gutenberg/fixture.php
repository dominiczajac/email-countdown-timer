<?php
/** Disposable editor fixture. No real content, email or external service. */
if (PHP_SAPI !== 'cli' || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local' || $GLOBALS['wpdb']->base_prefix !== 'ectblock_') { exit(1); }
$config=Email_Countdown_Timer_Config::normalize(array('deadline'=>'2035-12-31T23:59:59','tz'=>'UTC','fixed_width'=>400,'alt'=>'Gutenberg campaign A'));
update_option('easy_countdown_timers',array('gutenberg-a'=>$config,'gutenberg-b'=>array_replace($config,array('fixed_width'=>300,'alt'=>'Gutenberg campaign B'))),false);
$id=wp_insert_post(array('post_type'=>'post','post_title'=>'Synthetic Gutenberg acceptance','post_status'=>'publish','post_author'=>1,'post_content'=>''),true);
if(is_wp_error($id)){WP_CLI::error($id->get_error_message());}
echo $id;
