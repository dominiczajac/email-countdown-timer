<?php
/** Translation hooks must not mutate campaign data or inject HTML. */
if (PHP_SAPI !== 'cli' || !defined('ABSPATH') || !function_exists('ok')) { exit(1); }
$test_translations = [
    'New Timer' => '<strong>Translated heading</strong>',
    'Invalid font filename.' => '<script>not executable</script>',
    'Countdown' => 'Timer" onerror="bad',
    'Days' => 'Translated day data must not leak',
];
$admin = true; $nonce = true; $_GET = []; $_POST = [];
$options['easy_countdown_timers'] = [];
ob_start(); $plugin->renderAdminPage(); $translated = ob_get_clean();
ok(str_contains($translated, '&lt;strong&gt;Translated heading&lt;/strong&gt;'), 'translated heading escaped');
ok(!str_contains($translated, '<strong>Translated heading'), 'translation cannot inject heading markup');
ok(Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59'])['label_d'] === 'Days', 'locale does not change new campaign data');
$short = $plugin->renderShortcode(['id'=>'example']);
ok(str_contains($short, 'Timer&quot; onerror=&quot;bad'), 'translated alt escaped as an attribute');
$_POST = ['ecd_action'=>'save_timer','timer_id'=>'test','deadline'=>'2030-12-31T23:59:59','font'=>'../bad.ttf'];
$message = '';
try { $plugin->handleFormSave(); } catch (ECD_Test_Stop $e) { $message=$e->getMessage(); }
ok($message === 'die:&lt;script&gt;not executable&lt;/script&gt;', 'translated error escaped exactly once at HTML sink');
ok(!isset($options['easy_countdown_timers']['test']), 'invalid translated form does not write data');
$test_translations = [];
