=== Email Countdown Timer ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.4.2
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create locally generated countdown images for emails and WordPress pages, with custom colors, labels, fonts and alternative text.

== Description ==

Email Countdown Timer generates images counting down to a fixed date and time on your own WordPress server. Create a campaign in Easy Countdown, then copy an image URL or HTML for your email editor, or use [ecd_timer id="promotion"] on a WordPress page. The plugin does not send email or require an external countdown service.

* Animated GIFs with 60 one-second frames when Imagick is available; static images with GD alone.
* A deadline and time zone for each campaign, custom colors and labels, and a local TTF/OTF font selector.
* Alternative text for website shortcodes, saved previews and newly copied email HTML.
* A lightweight English, translation-ready admin interface with keyboard-accessible controls, validation and manual copying when JavaScript is unavailable.
* A static saved preview; animation starts only on request. No preview rendering on each keystroke or background polling.
* Persistent local font storage and an explicit, non-destructive tool for copying legacy fonts.
* Optional removal of owned plugin data on uninstall, disabled by default. Deactivation keeps your settings.

GD is required to render images. Imagick enables animation, and FreeType enables custom fonts. Shared image caching and database session locks reduce duplicate GIF generation. If the generation lock is busy or unavailable, a current single-frame image is returned instead of a stale animation. Rendering errors can still prevent an image; test your hosting before a campaign.

A countdown image is not a promise of exact live delivery: email services may prefetch, cache or block remote images, and some clients show only the first GIF frame. Always include the actual deadline as visible text. Keep the image URL available for the lifetime of the campaign.

== Installation ==

1. In Plugins > Add New > Upload Plugin, select the installation ZIP and activate Email Countdown Timer.
2. Open Easy Countdown > Create Timer. Choose a unique ID, set the deadline and time zone, then save.
3. Use Embed Codes to copy email HTML, an image URL or the website shortcode.
4. Send a test message through your actual email platform and check the page while logged out.

When updating, replace the installed plugin rather than uninstalling it. Back up your database and custom fonts first. Before the first update from 12.3.1 or older, copy fonts out of the plugin directory: WordPress can remove that directory before the new version runs. Disable any older duplicate snippet or implementation, not unrelated plugins.

== Frequently Asked Questions ==

= Where should I put custom fonts? =
Use trusted, licensed static TTF/OTF files in the persistent directory under your site's uploads base: normally wp-content/uploads/email-countdown-timer/fonts/. Multisite adds a site-ID subdirectory; custom upload locations can differ. Data Settings shows the available location. Create it through your hosting file manager if necessary and preserve filenames and license notices.

If legacy plugin-local fonts still exist after installation, Data Settings > Copy Legacy Fonts to Persistent Storage copies them without deleting originals or overwriting a conflicting file. A same-named legacy file takes precedence while it remains. Copy license notices manually. See the repository Local Font Storage guide for migration and retention details.

= Are Google Fonts imported automatically? =
No. Automatic importing is deferred. You may obtain a licensed TTF/OTF yourself and upload it through SFTP or your hosting file manager. There is no browser font-upload endpoint, Google request or API-key field. Fonts are read on the server to draw the image, not downloaded by email recipients.

= Why do custom labels or sizes fail validation? =
The combined font, text and dimensions must fit the image limits. Without an available FreeType font, the bitmap fallback uses fixed sizes and new saves require printable ASCII labels. Choose a suitable local font for other characters; the file must include the required glyphs.

= Can I set image alternative text? =
Yes. Set Image alternative text (alt) in the editor. Saved empty text produces alt="" and is appropriate only when nearby text already conveys the same information. Older records without this field use an automatic description. Recopy email HTML after editing; a sent email or plain image URL cannot have its HTML alt changed by changing the image. Purge cached website HTML after editing a shortcode's alt.

= What should I exclude in FlyingPress or WP Rocket? =
Only when needed, exclude email-countdown-timer/assets/countdown.js from delayed execution and email-countdown-timer-image from image lazy loading. Requests with ecd_action=render must bypass page caching and image conversion. Do not disable optimization globally. FlyingPress without CDN still needs correct local cache settings. See the repository Optimization and Caching guide; not every proprietary plugin version or settings combination has been tested.

= Why is a GIF static? =
Imagick may be unavailable, or a generation lock may be busy or unsupported. Data Settings > Rendering Diagnostics checks locking. A fallback uses the current time and does not replace the cached animation. The hosting still needs adequate CPU, memory and native libraries.

= Does the plugin track visitors or email opens? =
The plugin adds no visitor analytics, open counters, tracking cookies, browser storage or developer telemetry. It stores campaign configuration, an uninstall preference, copied-font ownership hashes and temporary shared images locally. Hosting, WordPress, other plugins and email-image proxies may separately process request data. Suggested wording is available in WordPress's Privacy Policy Guide; adapt it to your providers. Do not add recipient IDs, email addresses or secrets to image URLs or public timer fields.

= What happens when I uninstall? =
Data is retained unless you first enable Delete all plugin data when uninstalling in Data Settings. Opt-in uninstall removes owned database data and unchanged files owned by the font-copy tool. Manually uploaded or replaced fonts are not deleted. The choice is per site on multisite. Shared caches, other plugins' cron jobs and backups are not erased. The plugin schedules no cron jobs.

== Changelog ==

= 12.4.2 =
* One verified distribution ZIP is used for packaging and installation tests.
* Add suggested text to the WordPress Privacy Policy Guide without editing a published policy.
* Update installation, local-font, privacy and troubleshooting instructions.

= 12.4.1 =
* Distinguish generation-lock outcomes and return a current static image when locking is busy or unavailable.
* Add administrator rendering diagnostics.

= 12.4.0 =
* Add persistent local font storage and explicit legacy copying.
* Validate combined image geometry and explain bitmap-font limits before saving.

Earlier changes and detailed guides: https://github.com/dominiczajac/email-countdown-timer
