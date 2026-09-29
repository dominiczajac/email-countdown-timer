=== Easy Countdown ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.5.1
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create local countdown images for email and WordPress, with custom fonts, alternative text and an optional image shown after the deadline.

== Description ==

Easy Countdown generates countdown images on your WordPress server. Create a timer, then copy its image URL, email HTML or [ecd_timer id="promotion"] shortcode. The plugin does not send email or track individual opens.

* 60 one-second GIF frames when Imagick and database session locks are available.
* Per-timer deadlines/time zones, colors, labels and local TTF/OTF fonts.
* An optional Media Library image after the deadline, including GIF frames crossing it.
* Escaped alternative text and a visible deadline in newly copied email HTML.
* Reserved website image dimensions and a proportional box to limit layout movement.
* A translation-ready admin with static preview, explicit animation controls and no background polling.
* Optional removal of owned data on uninstall; off by default. Deactivation keeps settings.

GD is required; Imagick enables animation and FreeType enables custom fonts. Animation needs MySQL/MariaDB advisory locks on a consistent connection. Unavailable locking produces current static output on a cache miss, not an unprotected animation.

Email services may prefetch, cache or block images; some settings show only a still frame. Always include the absolute deadline in visible text. The plugin cannot replace bytes already retained by an email proxy or make a completed GIF refresh itself.

== Installation ==

1. Upload the installation ZIP through Plugins > Add New > Upload Plugin.
2. Open Easy Countdown > Create Timer, choose an ID, deadline and time zone, and save.
3. Optionally choose a local image in After Countdown, then save again.
4. Copy the image URL/email HTML or shortcode. Test your actual email template and logged-out page.

Moving from email-countdown-timer/: back up data/fonts, deactivate the old copy without uninstalling, then install/activate easy-countdown. Never run both. Both names share settings: do not uninstall either with cleanup enabled while the other needs those data. Copy legacy plugin-local fonts to persistent storage before replacing/deleting code directories.

== Frequently Asked Questions ==

= How does the end image work? =
Choose a Media Library image in Create/Edit Timer > After Countdown, or enter its attachment ID without JavaScript. Save Changes applies the choice. Use 0 to retain the zero countdown.

Accepts local JPEG/PNG/GIF/WebP attachments up to 4 MiB, 4096 pixels per side and 4 million pixels total. SVG, remote-only/offloaded files, private/trashed attachments and symlinked paths are rejected. Animated sources contribute their first frame. Output is re-encoded without source metadata and fitted proportionally to the timer canvas/background. Use a similar aspect ratio.

New requests and GIF frames at/after the deadline show the image. A completed GIF cannot fetch it itself. Missing/removed media fall back to zeros. The plugin never deletes Media Library files.

= What alternative text should I use? =
Use a description suitable for the countdown and end-image states: HTML alt cannot change during GIF playback. Empty alt is appropriate only with equivalent nearby text; old records without alt keep an automatic description. Recopy email HTML and purge affected website HTML caches after editing. A plain image URL or previously sent email cannot acquire a new HTML alt by replacing image bytes.

= Where should I put fonts? =
Upload trusted, licensed static TTF/OTF files via SFTP to the persistent directory shown in Data Settings, normally wp-content/uploads/email-countdown-timer/fonts/. Multisite adds site-ID. No Google Fonts importer or bundled fonts are included. Keep license notices and check server-rendering permission. See the repository Local Font Storage guide before updating old plugin-local fonts.

= Can visitors download the font files? =
Possibly, unless the server denies access. Data Settings > Font File Access installs fixed Apache deny rules without replacing existing files. nginx needs host-managed rules. Writing .htaccess does not prove enforcement: verify denied GET/HEAD font requests and working timer images. Protection does not grant a font license.

= Why is a GIF static? =
Imagick may be absent, the timer may have ended, or the lock may be unavailable/busy. Requests wait up to one second for the lock; a longer render can make simultaneous followers receive one current frame. That fallback does not replace the animation cache. Without working advisory locks, misses consistently produce static output. Use Data Settings > Rendering Diagnostics and test hosting capacity before a campaign.

= Which optimizer assets should I exclude? =
Only when necessary, exclude easy-countdown/assets/countdown.js from delayed JavaScript and email-countdown-timer-image from lazy loading. Requests with ecd_action=render must bypass page/edge caching and static image conversion. Keep normal page optimization enabled. See the repository Optimization and Caching guide; not every commercial version/configuration is tested.

= Troubleshooting: the timer is stale or missing behind Cloudflare =
Create a Cache Rule with this expression, replacing example.com with your public hostname:

(http.host eq "example.com" and any(http.request.uri.args["ecd_action"][*] eq "render"))

Set Cache eligibility to Bypass cache, after general forced-cache rules. Purge old timer responses. Do not exclude the whole homepage or pages containing timers. Keep ecd/mode query parameters intact. Repeated GETs to the same email URL should return a real GIF, no-store/no-transform and normally CF-Cache-Status DYNAMIC or BYPASS, not HIT/STALE. One MISS is not proof.

An HTML challenge (cf-mitigated: challenge) cannot be solved by a mail image fetcher. Investigate Security Events and adjust only the responsible protection; ordinary Bot Fight Mode cannot be skipped using WAF Skip. Workers, forced TTLs and image transformations need separate review. No live Cloudflare-zone test is claimed. [Detailed troubleshooting](https://github.com/dominiczajac/email-countdown-timer#troubleshooting-cloudflare).

= Will inserting a timer move the page layout? =
New shortcode HTML reserves measured width/height and a fixed proportional box before download, with asynchronous decoding. No frontend library or polling is added. Theme CSS can override these styles. Purge page HTML cache after geometry/font changes; recopy manually embedded HTML to get new attributes. Whole-page Core Web Vitals are not guaranteed.

= Which other plugins were tested? =
Tested profiles included WP Super Cache, Autoptimize, Yoast SEO, Contact Form 7; Elementor, Rank Math, Query Monitor, Limit Login Attempts Reloaded; and WooCommerce with W3 Total Cache. No repeatable timer-image conflict was found in selected runs. FPM baseline/W3 profiles passed; a WooCommerce timeout occurred even with Easy Countdown off. Separate native PHP crashes remain unresolved. A passing rerun is not a fix. [Versions, settings and qualified results](https://github.com/dominiczajac/email-countdown-timer/pull/13). This is not a guarantee for every configuration/future release; paid FlyingPress/WP Rocket and real Cloudflare services are outside that matrix.

= Does the image look identical in every email application? =
No. New Email HTML uses inline styles, proportional width/height (up to 600 pixels wide), alt and a text deadline. Recipients/providers control image blocking, animation, dark mode and prefetch/cache. Never rely only on animation or bypass privacy settings. Test the full campaign through your own sender. Browser simulations are not Gmail/Outlook/Apple Mail tests. [Email rendering guide](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/EMAIL-RENDERING.md).

= Does the plugin collect user data? =
The shipped code adds no visitor analytics, individual open counters, tracking cookies, persistent browser storage or author telemetry. Stored locally: campaign settings, optional media IDs, uninstall preference, copied-font ownership hashes and shared short-lived image cache. Hosting, WordPress, other plugins and mail-image proxies have separate processing/logs. Review the Privacy Policy Guide suggestion. Never put personal data or secrets in public fields, images or URLs.

= What happens on uninstall? =
Data stays unless Delete all plugin data when uninstalling was explicitly enabled. Opt-in removes owned options/cache and unchanged font-copy-tool files, per site on multisite. It does not delete Media Library images, manual/replaced fonts, shared cache, other plugins' cron, backups or infrastructure logs. Font-access rules remain for manual files. The plugin schedules no cron jobs.

== Changelog ==

= 12.5.1 =
* Reserve shortcode layout using measured dimensions and a fixed aspect ratio.
* Add proportional dimensions and conservative inline styles to new Email HTML.
* Document Cloudflare troubleshooting, qualified compatibility and dependencies.

= 12.5.0 =
* Align name, text domain and package directory with the existing Easy Countdown submission.
* Add a local end image, native media selection and deadline/cache handling.
* Preserve public URLs, shortcodes, timers and persistent font paths.

== Upgrade Notice ==

= 12.5.1 =
Purge affected page HTML cache and recopy manual/email embeds to use reserved dimensions. Stored campaign settings and image URLs are unchanged.

= 12.5.0 =
Back up data/fonts. Deactivate the old email-countdown-timer copy without uninstalling before activating easy-countdown. Both share data; never run both. Update the existing WordPress.org submission, not a duplicate.
