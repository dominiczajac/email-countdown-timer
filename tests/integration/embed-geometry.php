<?php
/** Read-only integration against actual core and native GD. */
if (PHP_SAPI !== 'cli' || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') { exit(1); }
$config = Email_Countdown_Timer_Config::normalize(array('deadline'=>'2035-12-31T23:59:59','fixed_width'=>600));
$html = Email_Countdown_Timer_Embed::reserve('<img src="https://example.invalid/?ecd_action=render&amp;ecd=test" alt="&quot;safe&quot;">', $config);
$tag = new WP_HTML_Tag_Processor($html);
if (!$tag->next_tag('IMG') || (int)$tag->get_attribute('width')!==600 || (int)$tag->get_attribute('height')<1 || $tag->get_attribute('alt')!=='"safe"' || !str_contains($tag->get_attribute('style'),'aspect-ratio:')) { throw new RuntimeException('Native embed geometry failed'); }
echo 'EMBED GEOMETRY: actual WordPress HTML API PASS'.PHP_EOL;
// Use actual core HTML output in the admin browser's scale regression fixture.
$runner_temp = getenv('RUNNER_TEMP');
if (!is_multisite() && !wp_using_ext_object_cache() && is_string($runner_temp) && '' !== $runner_temp) {
    $parts = [];
    foreach (Email_Countdown_Timer_Fonts::bundled() as $choice => $face) {
        $c = Email_Countdown_Timer_Config::normalize(['deadline'=>gmdate('Y-m-d\TH:i:s',time()+86400),'tz'=>'UTC','hide_days'=>1,'font'=>$choice]);
        $deadline = Email_Countdown_Timer_Config::deadline($c);
        $images = [];
        foreach (['before'=>86400,'after'=>86399] as $phase=>$remaining) {
            $blob = (new Email_Countdown_Timer_Renderer())->render_static($c,$deadline,$deadline-$remaining,'png');
            $images[] = '<div data-phase="'.$phase.'">'.Email_Countdown_Timer_Embed::reserve('<img src="data:image/png;base64,'.base64_encode($blob).'" alt="Synthetic boundary '.$phase.'">',$c,true).'</div>';
        }
        $parts[] = '<section data-font="'.esc_attr($choice).'"><h2>'.esc_html(Email_Countdown_Timer_Fonts::label($choice)).'</h2>'.implode('',$images).'</section>';
    }
    $folder = $runner_temp.'/admin-evidence';
    if (!is_dir($folder)) wp_mkdir_p($folder);
    file_put_contents($folder.'/font-boundary.html','<!doctype html><meta charset="utf-8"><style>body{margin:16px}section{padding:8px;border:1px solid #ccc;margin:8px 0;max-width:100%;box-sizing:border-box}</style>'.implode('',$parts));
}
