<?php
/** Disposable real WordPress, filesystem and database font-copy integration. */
if (!defined('WP_CLI') || !WP_CLI || getenv('ECD_INTEGRATION_DISPOSABLE') !== '1' || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Disposable local WordPress required.');
}
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($message);
};
$source = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
if (!is_file($source)) throw new RuntimeException('Runner font fixture unavailable.');
$name = 'integration-' . bin2hex(random_bytes(8)) . '.ttf';
$legacy = EMAIL_COUNTDOWN_TIMER_DIR . 'fonts';
wp_mkdir_p($legacy);
$original = $legacy . '/' . $name;
$destination = null;
$manual = null;
$previous = get_option(Email_Countdown_Timer_Fonts::OPTION, null);
try {
    copy($source, $original);
    $copied = Email_Countdown_Timer_Fonts::copy_legacy();
    $directory = Email_Countdown_Timer_Fonts::persistent_directory();
    $assert(is_string($directory), 'Persistent directory exists');
    $destination = $directory . '/' . $name;
    $assert($copied['copied'] === 1 && is_file($original) && is_file($destination), 'Real copy preserves source');
    $assert(hash_file('sha256', $source) === hash_file('sha256', $destination), 'Real copy preserves bytes');
    $assert((get_option(Email_Countdown_Timer_Fonts::OPTION, [])[$name] ?? '') === hash_file('sha256', $source), 'Manifest persisted');
    $config = Email_Countdown_Timer_Config::normalize(['deadline'=>'2030-12-31T23:59:59','font'=>$name,'label_d'=>'Dzień']);
    $renderer = new Email_Countdown_Timer_Renderer();
    $deadline = Email_Countdown_Timer_Config::deadline($config);
    $now = $deadline - 123456;
    $before = $renderer->render($config, $deadline, $now, 'png');
    unlink($original);
    $assert(Email_Countdown_Timer_Config::fontPath($name) === realpath($destination), 'Persistent lookup survives plugin-local removal');
    $assert($renderer->render($config, $deadline, $now, 'png') === $before, 'Pixels unchanged after relocation');
    require_once EMAIL_COUNTDOWN_TIMER_DIR . 'includes/class-email-countdown-timer-admin.php';
    $assert(Email_Countdown_Timer_Admin::preflight($config) === [], 'Persistent Unicode font passes preflight');
    $invalid = array_replace($config, ['size_digit'=>200,'size_label'=>100,'fixed_width'=>4000]);
    $assert(isset(Email_Countdown_Timer_Admin::preflight($invalid)['font']), 'Combined geometry rejected');
    $manual = $directory . '/manual-' . $name;
    copy($source, $manual);
    Email_Countdown_Timer_Fonts::delete_owned();
    $assert(!is_file($destination) && is_file($manual), 'Owned cleanup retains manual file');
    $assert(get_option(Email_Countdown_Timer_Fonts::OPTION, null) === null, 'Ownership option removed');
    if (is_multisite()) $assert(str_ends_with($directory, 'site-' . get_current_blog_id()), 'Multisite directory explicitly site-scoped');
} finally {
    foreach ([$original, $destination, $manual] as $file) if ($file && is_file($file)) unlink($file);
    if (null !== $previous) update_option(Email_Countdown_Timer_Fonts::OPTION, $previous, false);
    else delete_option(Email_Countdown_Timer_Fonts::OPTION);
}
WP_CLI::success("PERSISTENT FONTS: $checks assertions, real WordPress/DB/filesystem.");
