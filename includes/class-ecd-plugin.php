<?php
/** Controller retaining the original option, admin slug, shortcode and endpoint. */
if (!defined('ABSPATH')) exit;
class Email_Countdown_Timer_Plugin {
    private const OPTION_KEY = 'easy_countdown_timers';
    private const VERSION = EMAIL_COUNTDOWN_TIMER_VERSION;
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    public function __construct() {
        if ($this->isImageRequest()) {
            // Dynamic images only; the bootstrap also sets DONOTCACHEPAGE.
            add_filter('flying_press_is_cacheable', '__return_false');
        }
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_init', [$this, 'handleFormSave']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('init', [$this, 'listenForImageRequest']);
        add_shortcode('ecd_timer', [$this, 'renderShortcode']);
    }
    public function registerAdminMenu(): void {
        require_once __DIR__.'/class-email-countdown-timer-admin.php';
        $hook = add_menu_page(__('Easy Countdown', 'easy-countdown'), __('Easy Countdown', 'easy-countdown'), 'manage_options', 'ecd-timers', [$this, 'renderAdminPage'], 'dashicons-clock', 100);
        Email_Countdown_Timer_Admin::add_screen($hook);
    }
    public function enqueueAdminAssets(string $hook): void {
        if (class_exists('Email_Countdown_Timer_Admin', false)) Email_Countdown_Timer_Admin::enqueue($hook);
    }
    private function getTimers(): array {
        $timers = get_option(self::OPTION_KEY, []);
        return is_array($timers) ? array_filter($timers, 'is_array') : [];
    }
    public function handleFormSave(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing only; the admin controller verifies capability, POST and nonce before reading form data.
        if (in_array(Email_Countdown_Timer_Config::text($_POST, 'ecd_action'), ['email_countdown_timer_save', 'email_countdown_timer_delete'], true)) {
            require_once __DIR__.'/class-email-countdown-timer-admin.php';
            Email_Countdown_Timer_Admin::handle_post();
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This only identifies the form; nonce is verified before any write below.
        if (Email_Countdown_Timer_Config::text($_POST, 'ecd_action') !== 'save_timer' || !current_user_can('manage_options')) return;
        check_admin_referer('ecd_save_timer_nonce');
        // Old forms cannot bypass the current editor's validation and delete confirmation.
        // Keep the old nonce/capability gate, but perform no legacy mutation or redirect.
        wp_die(esc_html__('This form version is no longer supported. Reload Easy Countdown and use the current editor. No timer data was changed.', 'easy-countdown'), '', ['response'=>409]);
        return;
    }
    public function renderAdminPage(): void {
        require_once __DIR__.'/class-email-countdown-timer-admin.php';
        Email_Countdown_Timer_Admin::render();
    }
    public function renderShortcode($atts): string {
        $a = shortcode_atts(['id'=>''], is_array($atts) ? $atts : []);
        $id = Email_Countdown_Timer_Config::id(Email_Countdown_Timer_Config::text($a, 'id'));
        if ($id === '') return '';
        $timers = $this->getTimers();
        $alt = Email_Countdown_Timer_Config::alt($timers[$id] ?? [], __('Countdown', 'easy-countdown'));
        $base = add_query_arg(['ecd_action'=>'render', 'ecd'=>$id, 'mode'=>'anim'], home_url('/'));
        wp_enqueue_script('ecd-refresh', plugins_url('assets/countdown.js', EMAIL_COUNTDOWN_TIMER_FILE), [], self::VERSION, true);
        return sprintf('<img class="email-countdown-timer-image" loading="eager" data-no-lazy="1" referrerpolicy="no-referrer" id="%s" src="%s" data-ecd-src="%s" alt="%s" style="display:block; max-width:100%%; height:auto;">',
            esc_attr(wp_unique_id('ecd_')), esc_url(add_query_arg('_t', time(), $base)), esc_url($base), esc_attr($alt));
    }
    private function isImageRequest(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; no mutation or request value is output.
        return Email_Countdown_Timer_Config::text($_GET, 'ecd_action') === 'render';
    }
    public function listenForImageRequest(): void {
        if (!$this->isImageRequest()) return;
        // Do not spin forever on a non-removable output buffer.
        while (ob_get_level() > 0) {
            $status = ob_get_status();
            if (!(($status['flags'] ?? 0) & PHP_OUTPUT_HANDLER_REMOVABLE)) break;
            ob_end_clean();
        }
        $method = Email_Countdown_Timer_Config::text($_SERVER, 'REQUEST_METHOD', 'GET');
        if (!in_array($method, ['GET','HEAD'], true)) {
            header('Allow: GET, HEAD');
            $this->pixel(405, false);
            exit;
        }
        try { $this->generateImage($method === 'HEAD'); }
        catch (Throwable $e) { $this->pixel(503, $method === 'HEAD'); }
        exit;
    }
    private function cacheKey(string $id, string $format): string {
        // One reusable slot per timer/format, not new DB rows every 15 seconds.
        return 'ecd_v1211_'.hash('sha256', $id.'|'.$format);
    }
    private function generateImage(bool $head = false, ?int $now = null): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only image endpoint; values are normalized, no user session or state change.
        $id = Email_Countdown_Timer_Config::id(wp_unslash(Email_Countdown_Timer_Config::text($_GET, 'ecd')));
        $timers = $this->getTimers();
        if ($id === '' || !isset($timers[$id])) { $this->pixel(404, $head); return; }
        $imageInput = $timers[$id];
        // HTML-only metadata must not break binary validation or partition image caches.
        unset($imageInput['alt']);
        try { $config = Email_Countdown_Timer_Config::normalize($imageInput); }
        catch (InvalidArgumentException $e) { $this->pixel(422, $head); return; }
        if (!function_exists('imagecreatetruecolor')) { $this->pixel(503, $head); return; }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only image endpoint; values are normalized, no user session or state change.
        $mode = Email_Countdown_Timer_Config::text($_GET, 'mode');
        $fmt = in_array($mode, ['email','anim'], true) ? 'gif' : 'png';
        if ($fmt === 'png' && function_exists('imagewebp') && strpos(Email_Countdown_Timer_Config::text($_SERVER, 'HTTP_ACCEPT'), 'image/webp') !== false) $fmt = 'webp';
        if ($head) { $this->outputHeaders($fmt); return; }
        $deadline = Email_Countdown_Timer_Config::deadline($config);
        $fixedTime = $now;
        $now = $now ?? time();
        [$signature, $bucket, $ending] = $this->imageState($config, $deadline, $now, $fmt);
        $key = $this->cacheKey($id, $fmt);
        $blob = $this->cachedImage($key, $signature, $bucket);
        if ($blob !== null) { $this->outputImage($blob, $fmt); return; }
        require_once __DIR__.'/class-email-countdown-timer-render-lock.php';
        $attempt = Email_Countdown_Timer_Render_Lock::attempt($key, 1);
        $lock = $attempt['lock'];
        if ($lock === null) {
            $this->staticFallback($config, $deadline, $fixedTime ?? time(), $fmt, $attempt['state']);
            return;
        }
        try {
            // Another process may have filled the slot while this request waited.
            $renderNow = $fixedTime ?? time();
            [$signature, $bucket, $ending] = $this->imageState($config, $deadline, $renderNow, $fmt);
            $blob = $this->cachedImage($key, $signature, $bucket, true);
            if ($blob === null) {
                $blob = (new Email_Countdown_Timer_Renderer())->render($config, $deadline, $renderNow, $fmt, $ending);
                if ($blob === '') {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal fixed error; the public controller returns only a fixed PNG with status 503.
                    throw new RuntimeException('Image generation or lock ownership failed.');
                }
                if (!$lock->owns()) {
                    $responseNow = $fixedTime ?? time();
                    // Ownership gates shared publication, not delivery of this request's
                    // already completed image. Never reuse a previous request's stale GIF.
                    if (self::completed_image_is_fresh($renderNow, $responseNow, $deadline)) {
                        header('X-Email-Countdown-Mode: uncached-lock-lost');
                        $this->outputImage($blob, $fmt);
                    } else {
                        $this->staticFallback($config, $deadline, $responseNow, $fmt, 'error');
                    }
                    return;
                }
                set_transient($key, ['signature'=>$signature, 'bucket'=>$bucket, 'data'=>base64_encode($blob)], 60);
            }
            $this->outputImage($blob, $fmt);
        } finally {
            $lock->release();
        }
    }
    private function imageState(array $config, int $deadline, int $now, string $format): array {
        $font = Email_Countdown_Timer_Config::fontPath($config['font']);
        $imageConfig = $config;
        unset($imageConfig['alt']);
        $endID = (int)($config['expiry_image_id'] ?? 0);
        $ending = $endID > 0 && ($now >= $deadline || ($format === 'gif' && class_exists('Imagick') && $deadline - $now < 60))
            ? Email_Countdown_Timer_End_Image::resolve($endID) : null;
        $expired = $endID > 0 && $now >= $deadline;
        // A pre-deadline hit must never hide the end image within the same time bucket.
        $signature = hash('sha256', serialize([$imageConfig, $deadline, $format, $font, $font ? filemtime($font) : 0, class_exists('Imagick'), self::VERSION, $expired, $ending]));
        return [$signature, $expired && $ending !== null ? -1 : intdiv($now, 15), $ending];
    }
    private static function completed_image_is_fresh(int $started, int $now, int $deadline): bool {
        // Same freshness interval, no clock rollback, and no crossed campaign deadline.
        return $now >= $started && intdiv($now, 15) === intdiv($started, 15)
            && !($started < $deadline && $now >= $deadline);
    }
    private function staticFallback(array $config, int $deadline, int $now, string $format, string $reason): void {
        // One current frame, never a stale animation or an unprotected 60-frame job.
        // Do not cache it in the animated slot or change the requested MIME type.
        [, , $ending] = $this->imageState($config, $deadline, $now, $format);
        $blob = (new Email_Countdown_Timer_Renderer())->render_static($config, $deadline, $now, $format, $ending);
        $reason = in_array($reason, ['busy','unsupported','error'], true) ? $reason : 'error';
        header('X-Email-Countdown-Mode: static-'.$reason);
        $this->outputImage($blob, $format);
    }
    private function cachedImage(string $key, string $signature, int $bucket, bool $fresh = false): ?string {
        if ($fresh && wp_using_ext_object_cache()) {
            // Force a backend read rather than reuse this request's earlier miss.
            $cache = wp_cache_get($key, 'transient', true);
        } else {
            if ($fresh) {
                // TTL transients are non-autoloaded. Invalidate only Options API
                // lookup caches; never delete another process's persistent image.
                wp_cache_delete('_transient_'.$key, 'options');
                wp_cache_delete('_transient_timeout_'.$key, 'options');
                wp_cache_delete('notoptions', 'options');
            }
            $cache = get_transient($key);
        }
        if (is_array($cache) && ($cache['signature'] ?? '') === $signature && ($cache['bucket'] ?? -1) === $bucket && is_string($cache['data'] ?? null)) {
            $blob = base64_decode($cache['data'], true);
            if ($blob !== false && $blob !== '') return $blob;
        }
        return null;
    }
    private function outputImage(string $blob, string $format): void {
        $this->outputHeaders($format);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary encoder output only; HTML escaping corrupts images. The response has an image MIME type and nosniff.
        echo $blob;
    }
    private function pixel(int $status, bool $head): void {
        status_header($status);
        $this->outputHeaders('png');
        if ($status === 503) header('Retry-After: 15');
        if (!$head) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Decoded hardcoded transparent PNG bytes, never user input.
            echo base64_decode(self::PIXEL);
        }
    }
    private function outputHeaders(string $ext): void {
        $types = ['webp'=>'image/webp', 'gif'=>'image/gif', 'png'=>'image/png'];
        header('Content-Type: '.$types[$ext]);
        header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
        header('X-Content-Type-Options: nosniff');
        header('Vary: Accept', false);
    }
}
