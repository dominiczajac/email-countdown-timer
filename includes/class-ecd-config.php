<?php
/** Validation shared by admin writes and public rendering. */
if (!defined('ABSPATH')) exit;
final class ECD_Config {
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
            if (!is_scalar($value)) throw new InvalidArgumentException('Invalid field type: '.$key);
            $c[$key] = is_string($default) ? sanitize_text_field((string)$value) : $value;
        }
        foreach (['size_digit'=>[1,200], 'size_label'=>[1,100], 'fixed_width'=>[0,4000]] as $key=>$range) {
            $value = filter_var($c[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $range[0] || $value > $range[1]) {
                throw new InvalidArgumentException('Value outside the safe range: '.$key);
            }
            $c[$key] = $value;
        }
        $c['hide_days'] = empty($c['hide_days']) ? 0 : 1;
        foreach (['bg','dc','lc'] as $key) {
            $c[$key] = sanitize_hex_color($c[$key]);
            if (!$c[$key]) throw new InvalidArgumentException('Invalid color: '.$key);
        }
        foreach (['label_d','label_h','label_m','label_s'] as $key) {
            if (strlen($c[$key]) > 256) throw new InvalidArgumentException('Label is too long (maximum 256 bytes).');
        }
        if ($c['tz'] === '') $c['tz'] = 'Europe/Warsaw';
        self::deadline($c);
        // Reject traversal, including a symlink escaping the font directory. Missing fonts retain the legacy fallback.
        if ($c['font'] !== '' && (basename($c['font']) !== $c['font'] || strpos($c['font'], '\\') !== false ||
            !preg_match('/\.(ttf|otf)$/i', $c['font']))) {
            throw new InvalidArgumentException('Invalid font filename.');
        }
        return $c;
    }
    public static function deadline(array $c): int {
        $text = self::text($c, 'deadline');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?$/D', $text)) {
            throw new InvalidArgumentException('Enter a specific deadline date and time.');
        }
        try {
            $tz = new DateTimeZone(self::text($c, 'tz', 'Europe/Warsaw'));
            $format = strlen($text) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
            $dt = DateTimeImmutable::createFromFormat($format, str_replace('T', ' ', $text), $tz);
            $errors = DateTimeImmutable::getLastErrors();
            if ($dt === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
                throw new InvalidArgumentException('Invalid date.');
            }
            return $dt->getTimestamp();
        } catch (Exception $e) {
            throw new InvalidArgumentException('Invalid date or time zone.');
        }
    }
    public static function fontPath(string $font): ?string {
        if ($font === '' || basename($font) !== $font || strpos($font, '\\') !== false || !preg_match('/\.(ttf|otf)$/i', $font)) return null;
        $dir = realpath(ECD_PLUGIN_DIR.'fonts');
        if ($dir === false) return null;
        $path = realpath($dir.DIRECTORY_SEPARATOR.$font);
        return $path !== false && dirname($path) === $dir && is_file($path) && is_readable($path) ? $path : null;
    }
    public static function fonts(): array {
        $files = is_dir(ECD_PLUGIN_DIR.'fonts') ? scandir(ECD_PLUGIN_DIR.'fonts') : [];
        return array_values(array_filter($files ?: [], static fn($f) => self::fontPath($f) !== null));
    }
    public static function checkCanvas(int $width, int $height): void {
        if ($width < 1 || $height < 1 || $width > 4000 || $height > 1000 || $width*$height > 400000) {
            throw new RuntimeException('Canvas exceeds the pixel budget.');
        }
    }
}
