<?php
/** Read-only integration against actual core and native GD. */
if (PHP_SAPI !== 'cli' || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') { exit(1); }
$config = Email_Countdown_Timer_Config::normalize(array('deadline'=>'2035-12-31T23:59:59','fixed_width'=>600));
$html = Email_Countdown_Timer_Embed::reserve('<img src="https://example.invalid/?ecd_action=render&amp;ecd=test" alt="&quot;safe&quot;">', $config);
$tag = new WP_HTML_Tag_Processor($html);
if (!$tag->next_tag('IMG') || (int)$tag->get_attribute('width')!==600 || (int)$tag->get_attribute('height')<1 || $tag->get_attribute('alt')!=='"safe"' || !str_contains($tag->get_attribute('style'),'aspect-ratio:')) { throw new RuntimeException('Native embed geometry failed'); }
echo 'EMBED GEOMETRY: actual WordPress HTML API PASS'.PHP_EOL;
