<?php
/** Read-only checks of the installed example font on disposable real WordPress. */
if (!defined('WP_CLI') || !WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Disposable local WordPress required.');
}
require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
$checks = 0;
$expect = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException('Bundled font: ' . $message);
};
$name = Email_Countdown_Timer_Fonts::BUNDLED_FILE;
$file = EMAIL_COUNTDOWN_TIMER_DIR . 'fonts/' . $name;
$expect(is_file($file) && !is_link($file), 'exact package contains a regular local file');
$expect(hash_file('sha256', $file) === 'd636e4683231f931eda222d588e944d082bfd3bdba02f928bee461c0f185b251', 'original font bytes');
$expect(hash_file('sha256', EMAIL_COUNTDOWN_TIMER_DIR . 'fonts/OFL.txt') === '74ba064d03f1f1c4a952da936c3eb71866c34404916734de3cae73b34357e59e', 'original license shipped');
$before = get_option('easy_countdown_timers', []);
$expect(in_array($name, Email_Countdown_Timer_Config::fonts(), true), 'discovered by existing selector');
$expect(Email_Countdown_Timer_Config::fontPath($name) === realpath($file), 'resolver uses local bundle');
$expect(Email_Countdown_Timer_Admin::defaults()['font'] === '', 'new timer bitmap default unchanged');
$config = Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-01-01T00:00:00','tz'=>'UTC']);
$expect($config['font'] === '', 'legacy missing selection remains bitmap');
$font_config = array_replace($config, ['font'=>$name,'label_d'=>'Dzień','label_h'=>'Godziny','label_m'=>'Minuty','label_s'=>'Sekundy']);
$expect(function_exists('imagettfbbox') && function_exists('imagettftext'), 'FreeType present for native checks');
$expect(Email_Countdown_Timer_Admin::preflight($font_config) === [], 'Polish labels pass real font preflight');
$renderer = new Email_Countdown_Timer_Renderer();
$deadline = Email_Countdown_Timer_Config::deadline($config);
foreach ([0, 59, 86400, 8640000] as $remaining) {
    $now = $deadline - $remaining;
    $layout = $renderer->measure($font_config, $deadline, $now);
    $bitmap = $renderer->render_static($config, $deadline, $now, 'png');
    foreach (['png', 'gif', 'webp'] as $format) {
        if ($format === 'webp' && !function_exists('imagewebp')) continue;
        $bytes = $renderer->render_static($font_config, $deadline, $now, $format);
        $image = imagecreatefromstring($bytes);
        $expect($image instanceof GdImage, 'native ' . $format . ' decodes');
        $expect(imagesx($image) === $layout['width'] && imagesy($image) === $layout['height'], 'measured dimensions match ' . $format);
        if ($format === 'png') $expect($bytes !== $bitmap, 'font selection actually changes pixels');
        unset($image);
    }
    $expect($renderer->render_static($config, $deadline, $now, 'png') === $bitmap, 'bitmap unchanged after example render');
}
if (class_exists('Imagick')) {
    $animation = new Imagick();
    $animation->readImageBlob($renderer->render($font_config, $deadline, $deadline - 120, 'gif'));
    $expect($animation->getNumberImages() === 60, 'still 60 animation frames');
    foreach ($animation as $frame) $expect($frame->getImageDelay() === 100, 'one-second frame timing');
    $animation->clear();
}
$oversized = array_replace($font_config, ['size_digit'=>200,'size_label'=>100,'fixed_width'=>4000]);
$expect(isset(Email_Countdown_Timer_Admin::preflight($oversized)['font']), 'pixel budget still enforced');
$expect(get_option('easy_countdown_timers', []) === $before, 'no campaign settings changed');
WP_CLI::success("BUNDLED FONT: $checks assertions, installed package and real GD/FreeType.");
