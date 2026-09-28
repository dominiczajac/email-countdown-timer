<?php
/** Validation shared by admin writes and public rendering. */
if (!defined('ABSPATH')) exit;
final class Email_Countdown_Timer_Config {
    private static function invalid(string $message): never {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain internal exception; the controller escapes messages once at wp_die, while image requests return only a fixed error PNG.
        throw new InvalidArgumentException($message);
    }
    public static function text(array $input, string $key, string $default = ''): string {
        return isset($input[$key]) && is_scalar($input[$key]) ? (string)$input[$key] : $default;
    }
    public static function id(string $id): string {
        return strlen($id) <= 200 ? sanitize_title($id) : '';
    }
    public static function normalize(array $input): array {
        $defaults = ['deadline'=>'', 'tz'=>'Europe/Warsaw', 'bg'=>'#FFFFFF', 'dc'=>'#000000', 'lc'=>'#666666',
            'font'=>'', 'size_digit'=>40, 'size_label'=>12, 'hide_days'=>0, 'label_d'=>'Days', 'label_h'=>'Hours',
            'label_m'=>'Minutes', 'label_s'=>'Seconds', 'fixed_width'=>0];
        $c = [];
        foreach ($defaults as $key=>$default) {
            $value = $input[$key] ?? $default;
            if (!is_scalar($value)) {
                self::invalid(sprintf( /* translators: %s: internal field name. */ __('Invalid field type: %s', 'email-countdown-timer'), $key));
            }
            $c[$key] = is_string($default) ? sanitize_text_field((string)$value) : $value;
        }
        foreach (['size_digit'=>[1,200], 'size_label'=>[1,100], 'fixed_width'=>[0,4000]] as $key=>$range) {
            $value = filter_var($c[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $range[0] || $value > $range[1]) {
                self::invalid(sprintf( /* translators: %s: internal field name. */ __('Value outside the safe range: %s', 'email-countdown-timer'), $key));
            }
            $c[$key] = $value;
        }
        $c['hide_days'] = empty($c['hide_days']) ? 0 : 1;
        foreach (['bg','dc','lc'] as $key) {
            $c[$key] = sanitize_hex_color($c[$key]);
            if (!$c[$key]) {
                self::invalid(sprintf( /* translators: %s: internal field name. */ __('Invalid color: %s', 'email-countdown-timer'), $key));
            }
        }
        foreach (['label_d','label_h','label_m','label_s'] as $key) {
            if (strlen($c[$key]) > 256) self::invalid(__('Label is too long (maximum 256 bytes).', 'email-countdown-timer'));
        }
        if ($c['tz'] === '') $c['tz'] = 'Europe/Warsaw';
        self::deadline($c);
        // Reject traversal, including a symlink escaping the font directory. Missing fonts retain the legacy fallback.
        if ($c['font'] !== '' && (basename($c['font']) !== $c['font'] || strpos($c['font'], '\\') !== false ||
            !preg_match('/\.(ttf|otf)$/i', $c['font']))) {
            self::invalid(__('Invalid font filename.', 'email-countdown-timer'));
        }
        // Alternative text is HTML metadata, never a renderer parameter. Preserve
        // absence in legacy records and distinguish it from an explicitly empty value.
        if (array_key_exists('alt', $input)) {
            if (!is_string($input['alt']) || strlen($input['alt']) > 1000) {
                self::invalid(__('Use plain alternative text (maximum 1000 bytes).', 'email-countdown-timer'));
            }
            $c['alt'] = sanitize_text_field($input['alt']);
        }
        return $c;
    }
    public static function alt(array $config, string $fallback): string {
        return array_key_exists('alt', $config) && is_string($config['alt'])
            ? sanitize_text_field($config['alt']) : $fallback;
    }
    public static function deadline(array $c): int {
        $text = self::text($c, 'deadline');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?$/D', $text)) {
            self::invalid(__('Enter a specific deadline date and time.', 'email-countdown-timer'));
        }
        try {
            $tz = new DateTimeZone(self::text($c, 'tz', 'Europe/Warsaw'));
            $format = strlen($text) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
            $dt = DateTimeImmutable::createFromFormat($format, str_replace('T', ' ', $text), $tz);
            $errors = DateTimeImmutable::getLastErrors();
            if ($dt === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
                self::invalid(__('Invalid date.', 'email-countdown-timer'));
            }
            return $dt->getTimestamp();
        } catch (Exception $e) {
            self::invalid(__('Invalid date or time zone.', 'email-countdown-timer'));
        }
    }
    public static function fontPath(string $font): ?string {
        if ($font === '' || basename($font) !== $font || strpos($font, '\\') !== false || !preg_match('/\.(ttf|otf)$/i', $font)) return null;
        $dir = realpath(EMAIL_COUNTDOWN_TIMER_DIR.'fonts');
        if ($dir === false) return null;
        $path = realpath($dir.DIRECTORY_SEPARATOR.$font);
        return $path !== false && dirname($path) === $dir && is_file($path) && is_readable($path) ? $path : null;
    }
    public static function fonts(): array {
        $files = is_dir(EMAIL_COUNTDOWN_TIMER_DIR.'fonts') ? scandir(EMAIL_COUNTDOWN_TIMER_DIR.'fonts') : [];
        return array_values(array_filter($files ?: [], static fn($f) => self::fontPath($f) !== null));
    }
    public static function checkCanvas(int $width, int $height): void {
        if ($width < 1 || $height < 1 || $width > 4000 || $height > 1000 || $width*$height > 400000) {
            throw new RuntimeException('Canvas exceeds the pixel budget.');
        }
    }
}
