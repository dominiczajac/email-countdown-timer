<?php
/** English interface regression checks, loaded by tests/run.php. */
if (PHP_SAPI !== 'cli' || !defined('ABSPATH') || !function_exists('ok')) { http_response_code(404); exit; }

$english = ECD_Config::normalize(['deadline'=>'2027-12-31T23:59:59']);
$englishLabels = ['label_d'=>'Days', 'label_h'=>'Hours', 'label_m'=>'Minutes', 'label_s'=>'Seconds'];
foreach ($englishLabels as $field=>$label) ok($english[$field] === $label, 'English default '.$field);
ok($english['tz'] === 'Europe/Warsaw', 'language change does not change the time zone');

$admin = true; $nonce = true; $_GET = []; $_POST = [];
$options['easy_countdown_timers'] = [];
ob_start(); $plugin->renderAdminPage(); $englishHtml = ob_get_clean();
foreach (['New Timer','Timer ID','Deadline','Time Zone','Width (px)','Colors','Background:',
    'Digits:','Labels','Font','Digit Size','Label Size','Options','Hide days when fewer than 24 hours remain',
    'Create Timer','Your Timers','No timers yet.'] as $text) {
    ok(str_contains($englishHtml, $text), 'English create form: '.$text);
}
foreach ($englishLabels as $field=>$label) {
    ok((bool)preg_match('/name="'.preg_quote($field, '/').'" value="'.preg_quote($label, '/').'"/', $englishHtml), 'English form default '.$field);
}
ok(!preg_match('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', strip_tags($englishHtml)), 'no Polish text in empty admin page');

// UI translation must not migrate stored labels or overwrite custom/empty values.
$stored = array_replace($english, ['label_d'=>'Dni','label_h'=>'Godz','label_m'=>'Min','label_s'=>'Sek']);
ok(ECD_Config::normalize($stored) === $stored, 'stored Polish labels preserved');
$custom = array_replace($english, ['label_d'=>'Campaign days','label_h'=>'','label_m'=>'Minuty','label_s'=>'Seconds left']);
ok(ECD_Config::normalize($custom) === $custom, 'custom and deliberately empty labels preserved');
$options['easy_countdown_timers'] = ['legacy'=>$stored]; $_GET = ['edit'=>'legacy'];
$beforeOptions = $options;
ob_start(); $plugin->renderAdminPage(); $editHtml = ob_get_clean();
foreach (['Edit: legacy','Save Changes','Delete permanently','Back','Embed Codes','Email Image URL (GIF):'] as $text) {
    ok(str_contains($editHtml, $text), 'English edit form: '.$text);
}
foreach (['label_d'=>'Dni','label_h'=>'Godz','label_m'=>'Min','label_s'=>'Sek'] as $field=>$label) {
    ok((bool)preg_match('/name="'.preg_quote($field, '/').'" value="'.preg_quote($label, '/').'"/', $editHtml), 'saved label visible unchanged '.$field);
}
ok($options === $beforeOptions, 'viewing the translated admin does not write timer data');
$_POST = array_merge($stored, ['ecd_action'=>'save_timer','timer_id'=>'legacy']);
unset($_POST['hide_days']); // An unchecked checkbox is omitted by the browser.
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) { ok(str_starts_with($e->getMessage(), 'redirect:'), 'legacy save redirects'); }
ok($options['easy_countdown_timers']['legacy'] === $stored, 'saving existing timer preserves its labels');

// Exercise the actual create path when no label overrides are supplied.
$_POST = ['ecd_action'=>'save_timer','timer_id'=>'english','deadline'=>'2027-12-31T23:59:59'];
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) { ok(str_starts_with($e->getMessage(), 'redirect:'), 'English create redirects'); }
ok($options['easy_countdown_timers']['english'] === $english, 'new saved timer uses English defaults');
$_POST = ['ecd_action'=>'save_timer','timer_id'=>''];
$message = '';
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) { $message = $e->getMessage(); }
ok($message === 'die:Invalid timer ID.', 'English invalid ID error');

foreach ([
    [['size_digit'=>[]], 'Invalid field type: size_digit'],
    [['fixed_width'=>4001], 'Value outside the safe range: fixed_width'],
    [['lc'=>'red'], 'Invalid color: lc'],
    [['label_d'=>str_repeat('x',257)], 'Label is too long (maximum 256 bytes).'],
    [['font'=>'../outside.ttf'], 'Invalid font filename.'],
    [['deadline'=>'+1 hour'], 'Enter a specific deadline date and time.'],
    [['deadline'=>'2027-02-30T12:00'], 'Invalid date or time zone.'],
    [['tz'=>'not/a-zone'], 'Invalid date or time zone.'],
] as [$overrides, $expected]) {
    $message = '';
    try { ECD_Config::normalize(array_replace($english, $overrides)); }
    catch (InvalidArgumentException $e) { $message = $e->getMessage(); }
    ok($message === $expected, 'English validation: '.$expected);
}

// Keep historical Polish image fixtures above intact and also test new English images.
if (function_exists('imagecreatetruecolor')) {
    $now = 1790580000;
    $englishRenderer = new ECD_Renderer();
    $englishPng = $englishRenderer->render($english, $now+86410, $now, 'png');
    ok(getimagesizefromstring($englishPng)['mime'] === 'image/png', 'English PNG encodes');
    $englishGif = $englishRenderer->render($english, $now+86410, $now, 'gif');
    ok(getimagesizefromstring($englishGif)['mime'] === 'image/gif', 'English GIF encodes');
    if (class_exists('Imagick')) {
        $sequence = new Imagick(); $sequence->readImageBlob($englishGif);
        ok($sequence->getNumberImages() === 60, 'English GIF retains 60 frames');
        $decoded = $sequence->coalesceImages();
        foreach ($decoded as $index=>$frame) {
            ok($frame->getImageDelay() === 100, 'English GIF frame lasts one second');
            $expectedImage = $englishRenderer->drawFrame(86410-$index, '#FFFFFF','#000000','#666666','',40,12,false,
                ['d'=>'Days','h'=>'Hours','m'=>'Minutes','s'=>'Seconds']);
            ob_start(); imagegif($expectedImage); $expectedBlob = ob_get_clean(); unset($expectedImage);
            $reference = new Imagick(); $reference->readImageBlob($expectedBlob);
            ok($frame->compareImages($reference, Imagick::METRIC_ABSOLUTEERRORMETRIC)[1] === 0.0, 'English GIF frame labels and pixels');
            $reference->clear();
        }
        $decoded->clear(); $sequence->clear();
    }
}
