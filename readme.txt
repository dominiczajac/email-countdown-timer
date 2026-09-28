=== Email Countdown Timer ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.3.0
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Images counting down to a fixed deadline, generated on your own WordPress server.

== Description ==

Code version: 12.3.0. The Easy Countdown admin panel provides a deadline, time zone, colors, labels, alternative text, local fonts, and the [ecd_timer id="promotion"] shortcode.

The admin interface, help text, and validation messages are in English and translation-ready. New timers default to Days, Hours, Minutes, and Seconds. Existing saved labels are preserved; edit their Labels fields to translate them.

GD is required. Imagick enables animated GIFs with 60 frames; without it, GIFs are static. Email clients may prefetch and cache images before a message is opened. Always include the deadline as text as well.

Data Settings includes optional deletion of plugin data during WordPress uninstall. It is off by default and never deletes data on deactivation. See the repository documentation for multisite and persistent-cache details.

In Create/Edit Timer > Accessibility, Alternative Text (alt) controls the website shortcode, saved preview and newly copied Email HTML. Leave it blank for automatic descriptions. An image URL cannot carry an HTML alt attribute; update existing email/image-block markup separately. Sent messages cannot be rewritten. Clear cached page HTML after changing an existing shortcode's description.

The shortcode adds targeted LazyLoad markers. Dynamic image responses use no-store headers and image-only page-cache signals. FlyingPress/WP Rocket exclusions and staging checks are documented at:
https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/OPTIMIZATION-COMPATIBILITY.md
Compatibility with every paid optimizer version/configuration is not certified.

The plugin implements no visitor analytics, email-open tracking, telemetry, tracking cookies or persistent browser storage. It does not collect visitor IP addresses or recipient identities. The hosting server, WordPress, CDN and email-image proxies still process requests and may log them. Do not put personal/confidential data in public timer settings. Full storage/retention and infrastructure boundaries:
https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/PRIVACY.md

Fonts are local TTF/OTF files manually copied to wp-content/plugins/email-countdown-timer/fonts/ using SFTP or the hosting file manager. Back them up before updates. Automatic Google Fonts import is planned, NOT included; no Google request is made by this version. Instructions:
https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/wiki/Fonts.md

Full instructions are available in the repository README.md and docs/wiki/: https://github.com/dominiczajac/email-countdown-timer

The presence of this file does not mean the plugin has been accepted into the WordPress.org directory. WordPress 7.1.2 has been exercised in disposable single-site integration tests; verify your hosting configuration before deployment.

== Installation ==

1. Copy the plugin to wp-content/plugins/email-countdown-timer/.
2. When upgrading, disable the previous plugin or snippet of this implementation.
3. Activate Email Countdown Timer, open Easy Countdown, and create a timer.
4. Copy the shortcode for your WordPress page or the image URL for your email template.

== Changelog ==

= 12.3.0 =
Configurable image alternative text with safe output and old-form preservation; image-only optimization safeguards; duplicate-safe refresh script; precise optimizer, font and privacy documentation. No telemetry, Google downloader, renderer/timing change or production deployment.

= 12.2.0 =
Lightweight accessible admin refresh: separate timer list/editor, retained invalid inputs, saved static preview, explicit animation, copy helpers and confirmed deletion. System fonts and scoped assets; no remote font downloader.

= 12.1.3 =
Optional data removal on uninstall, real WordPress/MySQL/Redis lifecycle tests, translatable English interface, distinct internal prefixes, and Plugin Check preflight. Saved timer labels, public URLs and GIF timing are unchanged.

= 12.1.2 =
English admin interface, help text, validation messages, and default labels for new timers. Existing saved labels, time zones, image URLs, and rendering behavior remain unchanged.

= 12.1.1 =
Validation and escaping, font-path and image-size limits, cache-key fixes, metrics memoization, regression tests, and documentation. v12.1 data and interfaces are preserved; invalid or excessively large configurations are rejected.
