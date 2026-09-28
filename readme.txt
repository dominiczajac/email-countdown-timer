=== Email Countdown Timer ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: trunk
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Images counting down to a fixed deadline, generated on your own WordPress server.

== Description ==

Code version: 12.1.1. The Easy Countdown admin panel provides a deadline, time zone, colors, labels, local fonts, and the [ecd_timer id="promotion"] shortcode.

GD is required. Imagick enables animated GIFs with 60 frames; without it, GIFs are static. Email clients may prefetch and cache images before a message is opened. Always include the deadline as text as well.

Full instructions are available in README.md and docs/wiki/. The presence of this file does not mean the plugin has been accepted into the WordPress.org directory. We do not declare Tested up to without testing a full WordPress installation.

== Installation ==

1. Copy the plugin to wp-content/plugins/email-countdown-timer/.
2. When upgrading, disable the previous plugin or snippet of this implementation.
3. Activate Email Countdown Timer, open Easy Countdown, and create a timer.
4. Copy the shortcode for your WordPress page or the image URL for your email template.

== Changelog ==

= 12.1.1 =
Validation and escaping, font-path and image-size limits, cache-key fixes, metrics memoization, regression tests, and documentation. v12.1 data and interfaces are preserved; invalid or excessively large configurations are rejected.
