=== Easy Countdown ===
Contributors: ddoomm
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.6.0
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create countdown images on your own server for email campaigns and WordPress pages, with local fonts and a custom end image.

== Description ==

Create fixed-deadline countdown images locally. Copy an image URL or Email HTML into your mailing platform, or use [ecd_timer id="promotion"] on a WordPress page. No external countdown account or API key is required.

**Features**

* Native Easy Countdown block with a timer selector, static preview and alignment.
* Multiple timers with independent deadlines and time zones.
* 60 one-second GIF frames when animation requirements are met.
* Custom colors, labels, sizes and local TTF/OTF fonts.
* Optional, proportionally fitted image after expiry.
* Alternative text, a visible email deadline and proportional image dimensions.
* Reserved website image space without another frontend library or polling.
* Saved previews, animation on request and copyable embed codes.
* English, translation-ready administration using WordPress controls.
* Opt-in cleanup on uninstall; settings are retained by default.

**Requirements and limitations**

Requires WordPress 6.4+, PHP 8.1+ and GD with PNG/GIF support. Use supported PHP in production. Imagick enables animation; FreeType enables fonts. Animation requires MySQL/MariaDB advisory locks on a consistent connection. Without Imagick or usable locking, output is static.

Use a public HTTPS image URL. Email clients/proxies may block, prefetch or cache images, or show a still frame. A downloaded GIF cannot refresh itself. Retain the visible deadline and test your complete campaign.

**Privacy**

Easy Countdown adds no visitor analytics, individual open counters, tracking cookies, fingerprinting or developer telemetry. It stores campaign settings, selected media IDs, uninstall preference, font-copy ownership records and short-lived shared image caches locally. It does not send email or contact Google Fonts.

Hosting, WordPress, other plugins and email proxies have separate processing and logs. Keep personal data, recipient identifiers and secrets out of public fields, images and URLs. WordPress's Privacy Policy Guide contains suggested wording, not automatic publication or legal certification.

[Source code and documentation](https://github.com/dominiczajac/email-countdown-timer)

== Installation ==

1. Install the Easy Countdown ZIP through Plugins > Add New > Upload Plugin and activate it.
2. Open Easy Countdown > Create Timer. Set a unique ID, deadline and time zone, then save.
3. Adjust the appearance and alternative text. Optionally select a local image in After Countdown.
4. Copy the image URL or email HTML into your email editor, or insert [ecd_timer id="promotion"] on a WordPress page, replacing promotion with your timer ID.
5. Check the public image while logged out and send a test using your actual mailing platform.

Back up data/fonts and update without uninstalling. Deactivate a legacy email-countdown-timer copy before activating easy-countdown: both share data. Never run both or uninstall with cleanup enabled while data is needed. Copy plugin-local fonts before replacing code directories.

== Frequently Asked Questions ==

= How do I use the block editor? =
Insert Easy Countdown and choose a saved timer; set alignment in its sidebar. Administrators can open timer settings in a new tab. The block stores only the ID/alignment, not another campaign configuration. Deleted timers display nothing. Reopen the editor to refresh the list. Preview is static; the website uses the saved campaign. Shortcodes and classic-editor usage remain supported. Deactivation hides dynamic blocks until reactivation.


= Does the timer start separately for each recipient? =
No. Each timer uses one fixed deadline shared by the campaign. It does not identify recipients, maintain individual sessions or count email opens.

= How does the image after expiry work? =
Choose a local image in After Countdown and save. Requests after expiry, and GIF frames crossing expiry, show it instead of zeros. Accepts JPEG/PNG/GIF/WebP up to 4 MiB, 4096px per side and 4 million pixels. Output is re-encoded and proportionally fitted; animated sources supply their first frame. SVG, remote files, symlinks and restricted media are rejected. Attachment/immediate-parent passwords prevent publication regardless of viewer cookies. Missing/restricted images fall back to zeros; media files are never deleted. Already-downloaded or proxy-cached images cannot be revoked or refreshed by the plugin.

= Why is the GIF not animated? =
Imagick may be missing, the timer may have ended, or the lock may be busy/unavailable. Requests wait up to one second; a slower render can make simultaneous requests receive a current static frame, without overwriting animation cache. Use Data Settings > Rendering Diagnostics and test host capacity. Client animation and image-blocking preferences also apply.

= What should I enter in Alternative Text? =
Describe both countdown and expired states: alt cannot change during GIF playback. Leave it empty only with equivalent nearby text. Recopy Email HTML and clear affected website HTML caches after editing. Sent HTML cannot be rewritten by changing image bytes.

= Will the timer move my page layout? =
New shortcode HTML reserves measured dimensions and a fixed proportional box before image download, with asynchronous decoding. Theme CSS can override these styles. Purge page HTML after geometry/font changes and recopy manual/email embeds. This does not guarantee whole-page Core Web Vitals.

= Where do I install fonts? =
Upload trusted, licensed static TTF/OTF files through SFTP to the directory in Data Settings, normally wp-content/uploads/email-countdown-timer/fonts/. Multisite uses separate site-ID directories. No fonts or Google Fonts importer are included. Keep license notices and confirm server-rendering rights. [Font storage and migration](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/LOCAL-FONT-STORAGE.md).

Font files may be publicly downloadable. Data Settings > Font File Access can add Apache deny rules without overwriting files; nginx needs host rules. Verify blocked font GET/HEAD requests and working timers. [Access rules and limits](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/FONT-HTTP-ACCESS.md).

= Troubleshooting: which optimization assets should I exclude? =
When necessary, exclude easy-countdown/assets/countdown.js from delayed JavaScript and email-countdown-timer-image from lazy loading. Bypass page/edge caching and image conversion for ecd_action=render. Keep ordinary page optimization enabled. Older installations use email-countdown-timer/assets/countdown.js. [Optimizer guidance](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/OPTIMIZATION-COMPATIBILITY.md).

= Troubleshooting: the image is missing or stale behind Cloudflare =
Create a Cache Rule scoped to your hostname and the render query parameter. Replace example.com with your public image hostname:

(http.host eq "example.com" and any(http.request.uri.args["ecd_action"][*] eq "render"))

Set Cache eligibility to Bypass cache and place this exception after conflicting forced-cache rules. Purge old timer responses and retain the ecd and mode parameters. Do not bypass caching for the entire homepage or every page containing a timer.

Repeated GETs to the same timer URL should return an actual image, not HTML. Check for no-store/no-transform and normally CF-Cache-Status DYNAMIC or BYPASS rather than HIT/STALE. A single MISS is insufficient. Investigate HTML challenges in Security Events; do not disable site-wide protection. Ordinary Bot Fight Mode cannot be bypassed with WAF Skip. Custom Workers and image transformations need separate review. No live Cloudflare-zone test is claimed.

= Which other plugins and email applications were tested? =
Recorded profiles cover caching, SEO and builder plugins. Timer checks passed, but intermittent admin/PHP failures remain documented, including cases without Easy Countdown. No universal compatibility certification. [Exact environments and results](https://github.com/dominiczajac/email-countdown-timer/pull/13). Local Thunderbird testing covered image blocking, playback and expiry; Gmail, Outlook, Apple Mail and mailbox delivery remain unverified. Test your sender and clients.

= What happens when I deactivate or uninstall? =
Deactivation retains data. Uninstall cleanup is opt-in, per site, and removes only owned options/cache and unchanged font-copy-tool files. Media, manual/replaced fonts, unrelated data/cron, backups and infrastructure logs remain. Font-access rules remain; no cron is scheduled.

== Screenshots ==

1. Configure the deadline, time zone and appearance, then preview the saved timer and copy an embed.
2. Choose an optional image after expiry and provide alternative text for the public timer.
3. Use the timer editor in a single-column layout on a narrow screen.
4. Find Easy Countdown's suggested wording in the WordPress Privacy Policy Guide.

== Changelog ==

= 12.6.0 =
* Add a dynamic Gutenberg block for existing timers, with static preview and alignment.
* Preserve shortcode rendering, reserved dimensions and campaign editing permissions.
* Reject end images protected by an attachment or immediate-parent password before shared-cache lookup and decoding.
* Preserve existing timer URLs, shortcodes and saved campaign settings.

== Upgrade Notice ==

= 12.6.0 =
Back up data/fonts and update without uninstalling. Purge affected page HTML and recopy manual/email embeds for the new dimensions. Password-protected end images are no longer published by the timer. Existing image URLs and saved campaigns are preserved.
