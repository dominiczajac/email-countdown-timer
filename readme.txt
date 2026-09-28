=== Email Countdown Timer ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.4.1
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Images counting down to a fixed deadline, generated on your own WordPress server.

== Description ==

Code version: 12.4.1. The Easy Countdown admin panel provides a deadline, time zone, colors, labels, local fonts, and the [ecd_timer id="promotion"] shortcode.

The admin interface, help text, and validation messages are in English and translation-ready. New timers default to Days, Hours, Minutes, and Seconds. Existing saved labels are preserved; edit their Labels fields to translate them.

GD is required. Imagick enables animated GIFs with 60 frames; without it, GIFs are static. Email clients may prefetch and cache images before a message is opened. Always include the deadline as text as well.

Data Settings includes optional deletion of plugin data during WordPress uninstall. It is off by default and never deletes data on deactivation. See the repository documentation for multisite and persistent-cache details.

Full instructions are available in the repository README.md and docs/wiki/: https://github.com/dominiczajac/email-countdown-timer

The presence of this file does not mean the plugin has been accepted into the WordPress.org directory. WordPress 7.1.2 has been exercised in disposable single-site integration tests; verify your hosting configuration before deployment.

== Frequently Asked Questions ==

= What happens when rendering is busy? =
On a cache miss, the plugin waits up to one second for its database generation lock. If it is busy, unsupported or fails, a current single-frame image is returned instead of a stale animation. The fallback is not stored in the animation cache. An actual rendering failure still returns an error. Data Settings includes a live, non-persistent lock diagnostic. This is not a replacement for hosting capacity planning or request rate limits.


= Can I set image alternative text? =
Yes. Save Image alternative text (alt) in the editor and copy new Email HTML. Website shortcodes use the saved value; purge affected HTML page caches after editing. Empty alt is allowed when surrounding text already provides the same information. Alt cannot be changed inside a previously sent email by changing the image.

= What should I exclude in FlyingPress or WP Rocket? =
If needed, exclude email-countdown-timer/assets/countdown.js from delayed execution and email-countdown-timer-image from image lazy loading. Dynamic requests with ecd_action=render must not be page/edge cached or converted to static images. Do not disable optimization globally. See the repository compatibility guide; proprietary plugin versions are not integration-tested by our CI.

= Are fonts downloaded from Google? =
No automatic importer is included. Upload a licensed static TTF/OTF to uploads/email-countdown-timer/fonts/ and choose it in Typography and Size. Multisite adds site-ID/ inside fonts/. Data Settings can copy existing plugin-local fonts without deleting originals. Before the first update from 12.3.1 or older, copy/backup those fonts manually. With uninstall cleanup enabled, only unchanged files created by the copy tool are removed; manual files are retained. A future opt-in importer to persistent storage is only a design proposal.

= Does the plugin track visitors? =
The code adds no tracking cookies, visitor identifiers, impression counters or telemetry. It stores administrator-entered timer settings and short-lived shared image caches locally. Hosting/CDN/WordPress/other plugins can separately process connection data. This is not a site-wide GDPR certification. See the privacy guide.

== Installation ==

1. Copy the plugin to wp-content/plugins/email-countdown-timer/.
2. When upgrading, disable the previous plugin or snippet of this implementation.
3. Activate Email Countdown Timer, open Easy Countdown, and create a timer.
4. Copy the shortcode for your WordPress page or the image URL for your email template.

== Changelog ==

= 12.4.1 =
* Current static fallback for busy or unavailable generation locks.
* Distinct lock outcomes and administrator diagnostics; no stale animation reuse.
* Extended full HTTP tests for single-frame fallback and expiration boundaries.


= 12.4.0 =
* Persistent per-site local fonts and an explicit non-destructive legacy copy tool.
* Exact renderer geometry validation before saving, without encoding a preview.
* Clear bitmap character/font warnings and ownership-scoped font cleanup.
* Back up legacy plugin-local fonts before upgrading from an older version.


= 12.3.1 =
* Reconcile alt/privacy changes with serialized rendering and HTTP coverage.
* Add narrow FlyingPress safeguards and preserve explicit empty alternative text.
* Google Fonts automatic import remains deferred.

= 12.3.0 =
Alternative text for shortcode/email images, narrowly scoped optimizer compatibility, session-owned image-generation locks, privacy guidance and full HTTP tests. Requires MySQL/MariaDB advisory locks on a consistent session. Busy generators return 503 with Retry-After. No visitor telemetry or automatic Google font downloads.

= 12.2.0 =
Lightweight accessible admin refresh: separate timer list/editor, retained invalid inputs, saved static preview, explicit animation, copy helpers and confirmed deletion. System fonts and scoped assets; no remote font downloader.

= 12.1.3 =
Optional data removal on uninstall, real WordPress/MySQL/Redis lifecycle tests, translatable English interface, distinct internal prefixes, and Plugin Check preflight. Saved timer labels, public URLs and GIF timing are unchanged.

= 12.1.2 =
English admin interface, help text, validation messages, and default labels for new timers. Existing saved labels, time zones, image URLs, and rendering behavior remain unchanged.

= 12.1.1 =
Validation and escaping, font-path and image-size limits, cache-key fixes, metrics memoization, regression tests, and documentation. v12.1 data and interfaces are preserved; invalid or excessively large configurations are rejected.
