=== Easy Countdown ===
Contributors: ddoomm
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.6.0
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Countdown images for email and WordPress, with local fonts and an end image.

== Description ==

Create countdowns locally. Copy Email HTML or an Image URL into your mailing platform, or use [ecd_timer id="promotion"] on WordPress. No external account or API key is required.

**Features**

* Native block with image or opt-in live text, timer selection and alignment.
* Private campaign names, suggested IDs, deadlines and time zones.
* 60 one-second GIF frames when animation requirements are met.
* Custom colors, labels, sizes, an included Lato Regular font and local TTF/OTF files.
* Optional, proportionally fitted image after expiry.
* Alternative text, a visible email deadline and proportional image dimensions.
* Saved previews, animation on request and copyable embed codes.
* Quick Create presets and a dashboard widget for users who manage timers.
* English, Polish, German, Spanish and French label presets.
* Translation-ready English admin with WordPress controls.
* Opt-in cleanup on uninstall; settings are retained by default.

**Requirements and limitations**

Requires WordPress 6.4+, PHP 8.1+ and GD with PNG/GIF support. Use supported PHP in production. Imagick enables animation; FreeType enables fonts. Animation requires MySQL/MariaDB advisory locks on a consistent connection. Without Imagick or usable locking, output is static.

Use a public HTTPS image URL. Email clients/proxies may block, prefetch or cache images, or show a still frame. A downloaded GIF cannot refresh itself. Retain the visible deadline and test your complete campaign.

**Privacy**

Easy Countdown adds no visitor analytics, individual open counters, tracking cookies, fingerprinting or developer telemetry. It stores campaign settings, selected media IDs, uninstall preference, font-copy ownership records and short-lived shared image caches locally. It does not send email or contact Google Fonts.

Hosting, WordPress, other plugins and email proxies have separate processing and logs. Keep personal data and secrets out of public content and URLs. WordPress's Privacy Policy Guide contains suggested wording, not automatic publication or legal certification.

[Source code and documentation](https://github.com/dominiczajac/email-countdown-timer)

== Installation ==

1. Install the Easy Countdown ZIP through Plugins > Add New > Upload Plugin and activate it.
2. Use Quick Create in Easy Countdown or its dashboard widget: ID, deadline, time zone, appearance and label language. Create Timer opens all settings.
3. Adjust the appearance and alternative text. Optionally select a local image in After Countdown.
4. Embed Codes > Website provides the block/shortcode. Email provides HTML and an Image URL. Replace promotion in [ecd_timer id="promotion"].
5. Check the public image while logged out and send a test using your actual mailing platform.

Back up data/fonts and update without uninstalling. Deactivate a legacy email-countdown-timer copy before activating easy-countdown: both share data. Never run both or uninstall with cleanup enabled while data is needed. Copy plugin-local fonts before replacing code directories.

== Frequently Asked Questions ==

= How do I use the block editor? =
Insert Easy Countdown and select a timer. Administrators can open Quick Create in another tab; select Refresh campaigns on return. The block saves a campaign reference and uses a static preview. Website display > Live text adds local ticking and Pause/Resume with theme typography. Image remains the default. Reduced motion starts paused; no-JS shows a snapshot. Clear page caches after campaign changes.


= Does the timer start separately for each recipient? =
Each campaign has one shared, fixed deadline. It does not identify recipients, maintain individual sessions or count email opens.

= How does the image after expiry work? =
Choose a local expiry image and save. New expired requests and GIF frames crossing the deadline use it. JPEG/PNG/GIF/WebP only: max 4 MiB, 4096px per side, 4 million pixels. Output is re-encoded and fitted proportionally; animated sources use frame one. SVG, remote files, symlinks and restricted media are rejected. Attachment/immediate-parent passwords prevent publication regardless of viewer cookies. Missing/restricted images use zeros. Media is never deleted. Previously downloaded/cached images cannot be revoked or refreshed.

= Why is the GIF not animated? =
Animation needs Imagick, an unexpired timer and an available lock. Requests wait up to one second; a slow render can return a current static frame without replacing the animation cache. Check Data Settings > Rendering Diagnostics and host capacity. Client animation and image-blocking preferences still apply.

= What should I enter in Alternative Text? =
Describe countdown and expiry: alt stays fixed during GIF playback. Use empty alt only with equivalent nearby text. Recopy Email HTML and purge website HTML caches after edits; changing image bytes cannot rewrite sent HTML.

= Will the timer move my page layout? =
Shortcodes reserve measured, proportional image space; themes can override it. After font/geometry changes, purge page HTML and recopy manual/email embeds. This does not guarantee whole-page Core Web Vitals.

= Where do I install fonts? =
New timers use included Lato with FreeType, otherwise bitmap. Saved timers are unchanged. Lato is SIL OFL 1.1 (fonts/OFL.txt). Keep custom TTF/OTF in Data Settings’ persistent directory; no Google importer. [Font storage](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/LOCAL-FONT-STORAGE.md).

Font files may be publicly downloadable. Data Settings > Font File Access can add Apache deny rules without overwriting files; nginx needs host rules. Verify blocked font GET/HEAD requests and working timers. [Access rules and limits](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/FONT-HTTP-ACCESS.md).

= Troubleshooting: which optimization assets should I exclude? =
When necessary, exclude easy-countdown/assets/countdown.js from delayed JavaScript and email-countdown-timer-image from lazy loading. Bypass page/edge caching and image conversion for ecd_action=render. Keep ordinary page optimization enabled. Older installations use email-countdown-timer/assets/countdown.js. [Optimizer guidance](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/OPTIMIZATION-COMPATIBILITY.md).

= Troubleshooting: the image is missing or stale behind Cloudflare =
Create a Cache Rule scoped to your hostname and the render query parameter. Replace example.com with your public image hostname:

(http.host eq "example.com" and any(http.request.uri.args["ecd_action"][*] eq "render"))

Set Cache eligibility to Bypass cache and place this exception after conflicting forced-cache rules. Purge old timer responses and retain the ecd and mode parameters. Do not bypass caching for the entire homepage or every page containing a timer.

Repeated GETs to the same timer URL should return an actual image, not HTML. Check for no-store/no-transform and normally CF-Cache-Status DYNAMIC or BYPASS rather than HIT/STALE. A single MISS is insufficient. Investigate HTML challenges in Security Events; do not disable site-wide protection. Ordinary Bot Fight Mode cannot be bypassed with WAF Skip. Custom Workers and image transformations need separate review. No live Cloudflare-zone test is claimed.

= Which other plugins and email applications were tested? =
Recorded profiles cover caching, SEO and builder plugins. Intermittent admin/PHP failures remain documented, including cases without Easy Countdown. This is not universal certification. [Versions and results](https://github.com/dominiczajac/email-countdown-timer/pull/13). Local Thunderbird testing covered image blocking, playback and expiry; Gmail, Outlook, Apple Mail and real delivery remain unverified. Test your sender/clients and retain a visible deadline.

= What happens when I deactivate or uninstall? =
Deactivation retains data. Opt-in, per-site uninstall cleanup removes owned options/cache and unchanged files created by the font-copy tool. It keeps media, manual/replaced fonts, unrelated data/cron, backups, infrastructure logs and font-access rules. No cron is scheduled.

== Screenshots ==

1. Configure the deadline, time zone and appearance, then preview the saved timer and copy an embed.
2. Choose an optional image after expiry and provide alternative text for the public timer.
3. Use the timer editor in a single-column layout on a narrow screen.
4. Find Easy Countdown's suggested wording in the WordPress Privacy Policy Guide.

== Changelog ==

= 12.6.0 =
* Add the native block, static preview and opt-in live text with Pause/Resume.
* Preserve shortcode rendering, reserved dimensions and campaign editing permissions.
* Add four color presets and prefer Lato for new timers with FreeType. Saved timers stay unchanged.
* Add Quick Create/dashboard shortcuts, block list refresh and separate Website/Email embeds.
* Add label presets independent of admin locale. Keep custom/empty labels and validate accented output.

= 12.5.3 =
* Bundle optional local Lato Regular under SIL OFL 1.1, with integrity-checked font and license notices.

= 12.5.2 =
* Integrate reserved website/email dimensions and the end-image password fix while preserving existing URLs and campaigns.
* Update contributor, screenshots and Cloudflare guidance. Full history is in CHANGELOG.md.

== Upgrade Notice ==

= 12.6.0 =
Add existing timers with the native Easy Countdown block or keep using shortcodes. New timers select included Lato when FreeType works; existing saved settings remain unchanged. Back up data/fonts and update without uninstalling.
