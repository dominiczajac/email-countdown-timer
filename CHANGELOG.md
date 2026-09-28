# Changelog

## 12.1.2 — 2026-09-28

Translated the admin interface, buttons, help text, and validation messages into English. New timers and missing label values default to `Days`, `Hours`, `Minutes`, and `Seconds`. Existing saved labels, including custom or empty values, are not translated or migrated automatically.

The renderer, time-zone default, option name, shortcode, image URLs, safety limits, and 60-frame timing are unchanged. Added regression tests for English UI text, validation errors, new-timer creation, saved-label preservation, and English GIF frames; the frozen legacy renderer and historical image fixtures remain intact. Updated the current documentation without rewriting the historical 12.1.1 audit.

## 12.1.1 — 2026-09-28

The first structured repository version based on the supplied Easy Countdown v12.1 code.

### Security

Fixed the unescaped edit heading. Added validation of input types, dates, time zones, colors, and ranges; `wp_unslash()` before saving; safe URLs and redirects; font-path restrictions; and a pixel budget. Existing capability and nonce checks are preserved. The public endpoint rejects unknown IDs and invalid configurations, accepts only GET/HEAD, and does not raise PHP's execution time limit.

### Performance and reliability

Stable cache slots replace separate keys for each time bucket. The signature now includes the label color. Added font-metrics memoization, reused the already-rendered first frame, and replaced multiple listeners with one script. Without Imagick, the plugin returns a static GIF instead of an empty response. Resource cleanup and buffer handling have been improved.

### Compatibility and documentation

Preserved the Polish admin panel, data, shortcode, URLs, 60-frame sequence, and renderer geometry. New safe ranges and the rejection of invalid data are documented in the Wiki. Added regression tests, PHP 8.1–8.5 CI, a README, Wiki documentation, and an audit report. The package declares a minimum PHP version of 8.1 and WordPress 6.4 in its header. No automatic updater or confirmation of WordPress.org publication has been added.

The repository's existing GNU GPL v3 license file is preserved; the code declares `GPL-3.0-only`.
