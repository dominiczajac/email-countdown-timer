<?php
/** Controller retaining the original option, admin slug, shortcode and endpoint. */
if (!defined('ABSPATH')) exit;
class Email_Countdown_Timer_Plugin {
    private const OPTION_KEY = 'easy_countdown_timers';
    private const VERSION = '12.3.0';
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    public function __construct() {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_init', [$this, 'handleFormSave']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('init', [$this, 'listenForImageRequest']);
        add_shortcode('ecd_timer', [$this, 'renderShortcode']);
    }
    public function registerAdminMenu(): void {
        require_once __DIR__.'/class-email-countdown-timer-admin.php';
        $hook = add_menu_page(__('Easy Countdown', 'email-countdown-timer'), __('Easy Countdown', 'email-countdown-timer'), 'manage_options', 'ecd-timers', [$this, 'renderAdminPage'], 'dashicons-clock', 100);
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
        $id = Email_Countdown_Timer_Config::id(wp_unslash(Email_Countdown_Timer_Config::text($_POST, 'timer_id')));
        if ($id === '') { wp_die(esc_html__('Invalid timer ID.', 'email-countdown-timer'), '', ['response'=>400]); return; }
        $timers = $this->getTimers();
        $delete = Email_Countdown_Timer_Config::text($_POST, 'delete_timer') === '1';
        if ($delete) unset($timers[$id]);
        else {
            $input = wp_unslash($_POST);
            if (!array_key_exists('alt', $input) && isset($timers[$id]['alt'])) $input['alt'] = $timers[$id]['alt'];
            $input['hide_days'] = isset($_POST['hide_days']) ? 1 : 0;
            try { $timers[$id] = Email_Countdown_Timer_Config::normalize($input); }
            catch (InvalidArgumentException $e) { wp_die(esc_html($e->getMessage()), '', ['response'=>400]); return; }
        }
        update_option(self::OPTION_KEY, $timers, false);
        foreach (['png','gif','webp'] as $format) delete_transient($this->cacheKey($id, $format));
        wp_safe_redirect(add_query_arg(['page'=>'ecd-timers', 'status'=>'saved', 'edit'=>$delete ? false : $id], admin_url('admin.php')));
        exit;
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
        $alt = Email_Countdown_Timer_Config::alt($timers[$id] ?? [], __('Countdown', 'email-countdown-timer'));
        $base = add_query_arg(['ecd_action'=>'render', 'ecd'=>$id, 'mode'=>'anim'], home_url('/'));
        wp_enqueue_script('ecd-refresh', plugins_url('assets/countdown.js', EMAIL_COUNTDOWN_TIMER_FILE), [], self::VERSION, true);
        return sprintf('<img class="email-countdown-timer-image" loading="eager" data-no-lazy="1" referrerpolicy="no-referrer" id="%s" src="%s" data-ecd-src="%s" alt="%s" style="display:block; max-width:100%%; height:auto;">',
            esc_attr(wp_unique_id('ecd_')), esc_url(add_query_arg('_t', time(), $base)), esc_url($base), esc_attr($alt));
    }
    public function listenForImageRequest(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only image endpoint; values are normalized, no user session or state change.
        if (Email_Countdown_Timer_Config::text($_GET, 'ecd_action') !== 'render') return;
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
        try { $config = Email_Countdown_Timer_Config::normalize($timers[$id]); }
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
        $bucket = intdiv($now, 15);
        $font = Email_Countdown_Timer_Config::fontPath($config['font']);
        $imageConfig = $config;
        unset($imageConfig['alt']);
        $signature = hash('sha256', serialize([$imageConfig, $deadline, $fmt, $font, $font ? filemtime($font) : 0, class_exists('Imagick'), self::VERSION]));
        $key = $this->cacheKey($id, $fmt);
        $blob = $this->cachedImage($key, $signature, $bucket);
        if ($blob !== null) { $this->outputImage($blob, $fmt); return; }
        require_once __DIR__.'/class-email-countdown-timer-render-lock.php';
        $lock = Email_Countdown_Timer_Render_Lock::acquire($key);
        if ($lock === null) { $this->pixel(503, false); return; }
        try {
            // Another process may have filled the slot while this request waited.
            $renderNow = $fixedTime ?? time();
            $bucket = intdiv($renderNow, 15);
            $blob = $this->cachedImage($key, $signature, $bucket, true);
            if ($blob === null) {
                $blob = (new Email_Countdown_Timer_Renderer())->render($config, $deadline, $renderNow, $fmt);
                if ($blob === '' || !$lock->owns()) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal fixed error; the public controller returns only a fixed PNG with status 503.
                    throw new RuntimeException('Image generation or lock ownership failed.');
                }
                set_transient($key, ['signature'=>$signature, 'bucket'=>$bucket, 'data'=>base64_encode($blob)], 60);
            }
            $this->outputImage($blob, $fmt);
        } finally {
            $lock->release();
        }
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
