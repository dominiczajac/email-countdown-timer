=== ActNow – Countdown Timers ===
Contributors: ddoomm
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.9.0
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create one countdown in WordPress and use it in emails and on websites. No external account required.

== Description ==

Create countdowns locally for WordPress, external websites and email. No external account or API key.

**Features**

* Block editor previews and external website iframes.
* Required timer names, internal IDs, deadlines and time zones.
* 60 one-second GIF frames when animation requirements are met.
* Custom colors, labels, sizes, five local font faces and local TTF/OTF files.
* Optional, proportionally fitted image after expiry.
* Alt text and reserved image space.
* Holiday/occasion presets, Quick Create and a dashboard widget.
* On-demand status checks, an anonymous image test and a redacted support report.
* English, Polish, German, Spanish and French label presets.
* Translation-ready English admin.
* Opt-in cleanup on uninstall; settings are retained by default.

**Requirements and limitations**

Requires WordPress 6.4+, PHP 8.1+ and GD. FreeType enables fonts; Imagick animates GIFs. Raster cache misses use session locks; if unavailable, iframes use system fonts. The iframe ticks locally without Imagick; email GIFs retain their static fallback.

Use a public HTTPS image URL. Email clients/proxies may block, prefetch or cache images, or show a still frame. A downloaded GIF cannot refresh itself. Retain the visible deadline and test your complete campaign.

**Privacy**

ActNow adds no visitor analytics, individual open counters, tracking cookies, fingerprinting or developer telemetry. Settings, media IDs, uninstall preference, font-copy ownership and temporary image/glyph caches stay local. It does not send email or contact Google Fonts.

Hosting, WordPress and email providers keep their own logs. Keep personal data out of public URLs. Privacy Policy Guide text needs your review.

[Source code and documentation](https://github.com/dominiczajac/email-countdown-timer)

== Installation ==

1. Install the ActNow ZIP through Plugins > Add New > Upload Plugin and activate it.
2. Use Quick Create in ActNow or its dashboard widget: name, deadline, time zone, countdown preset and label language. Create Timer opens all settings.
3. Adjust the appearance and alternative text. Optionally select a local image in After Countdown.
4. Embed Codes provides the WordPress block/shortcode, external website iframe and Email HTML/Image URL.
5. Check the public image while logged out and send a test using your actual mailing platform.

Switching from Easy Countdown? Back up data and fonts, deactivate the old plugin, then activate ActNow. Keep the old copy deactivated until custom fonts are copied with Data Settings. Both versions share data. Avoid uninstalling the old copy with data cleanup enabled.

== Frequently Asked Questions ==

= How do I use the block editor? =
Insert ActNow – Countdown Timers and select a timer. Administrators can open Quick Create in another tab; select Refresh campaigns on return. The block saves a reference and shows an active preview; refresh/pause controls stay in its sidebar. Website display > Live text adds local ticking and Pause/Resume with theme typography. Image remains the default. Reduced motion starts paused; no-JS shows a snapshot. Clear page caches after campaign changes.


= Does the timer start separately for each recipient? =
Each campaign has one shared, fixed deadline. It does not identify recipients, maintain individual sessions or count email opens.

= How does the image after expiry work? =
Choose a local expiry image and save. End-only URLs are public before expiry. New expired requests and GIF frames crossing the deadline use it. JPEG/PNG/GIF/WebP only: max 4 MiB, 4096px per side, 4 million pixels. Output is re-encoded and fitted proportionally; animated sources use frame one. SVG, remote files, symlinks and restricted media are rejected. Attachment/immediate-parent passwords prevent publication regardless of viewer cookies. Missing/restricted images use zeros. Media is never deleted. Previously downloaded/cached images cannot be revoked or refreshed.

= Why is the GIF not animated? =
Animation needs Imagick, an unexpired timer and an available lock. Requests wait up to one second; a slow render can return a current static frame without replacing the animation cache. Check Data Settings > Rendering Diagnostics and host capacity. Client animation and image-blocking preferences still apply.

= What should I enter in Alternative Text? =
Describe countdown and expiry: alt stays fixed during GIF playback. Use empty alt only with equivalent nearby text. Recopy Email HTML and purge website HTML caches after edits; changing image bytes cannot rewrite sent HTML.

= Will the timer move my page layout? =
Shortcodes reserve measured, proportional image space; themes can override it. After font/geometry changes, purge page HTML and recopy manual/email embeds. This does not guarantee whole-page Core Web Vitals.

= Where do I install fonts? =
Lato, IBM Plex Mono, Barlow Condensed (Regular/Black) and Crimson Text are local OFL 1.1 fonts with original notices in fonts/. Appearance presets apply colors/font explicitly; labels and deadlines stay yours. Without FreeType, bitmap is used. Keep custom TTF/OTF under actnow-countdown-timers/fonts/ in WordPress uploads. ActNow still reads older local fonts.

Font files may be publicly downloadable. Data Settings > Font File Access can add Apache deny rules without overwriting files; nginx needs host rules. Verify blocked font GET/HEAD requests and working timers. [Access rules and limits](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/FONT-HTTP-ACCESS.md).

= Troubleshooting: which optimization assets should I exclude? =
When necessary, exclude actnow-countdown-timers/assets/countdown.js from delayed JavaScript and email-countdown-timer-image from lazy loading. Bypass page/edge caching and image conversion for ecd_action=render. Keep ordinary page optimization enabled. [Optimizer guidance](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/OPTIMIZATION-COMPATIBILITY.md).

= Troubleshooting: the image is missing or stale behind Cloudflare =
Create a Cache Rule scoped to your hostname and the render query parameter. Replace example.com with your public image hostname:

(http.host eq "example.com" and any(http.request.uri.args["ecd_action"][*] eq "render"))

Set Cache eligibility to Bypass cache and place this exception after conflicting forced-cache rules. Purge old timer responses and retain the ecd and mode parameters. Do not bypass caching for the entire homepage or every page containing a timer.

Check that repeated GETs return an actual image with no-store/no-transform and a cache status of DYNAMIC or BYPASS. Live Cloudflare settings are site-specific and were not tested.

= Which other plugins and email applications were tested? =
Named cache, builder, commerce, theme and multisite profiles have [versioned reports](https://github.com/dominiczajac/email-countdown-timer/issues/20), including retained failures. Paid optimizers, Cloudflare zones and real sender/client delivery remain unverified. Local Thunderbird tests covered image blocking, playback and expiry. Test your sender/clients and retain a visible deadline.

= What happens when I deactivate or uninstall? =
Deactivation retains data. Opt-in, per-site uninstall cleanup removes owned options/cache and unchanged files created by the font-copy tool. Font copies owned by a still-installed previous version are kept. Media, manual/replaced fonts and font-access rules are kept. No cron is scheduled.

== Screenshots ==

1. Use Quick Create on the WordPress dashboard.
2. Set a deadline, time zone, local font and saved appearance.
3. Copy Website/Email embeds with image and deadline guidance.
4. Select a campaign in the native block and opt into live text.
5. Run local diagnostics and copy a redacted report.
6. Read suggested wording in the WordPress Privacy Policy Guide.

== Changelog ==

= 12.9.0 =
* Rename the plugin to ActNow – Countdown Timers. Keep existing timers, links and blocks.
* Write font rules only in the ActNow uploads directory and retain access to legacy fonts.

= 12.8.0 =
* Add locally ticking raster previews and a sandboxed external website iframe.
* Preserve saved fonts without font downloads or per-second server requests.
* Keep editor controls in the sidebar; align the dashboard title and remove the save badge.

= 12.7.0 =
* Simplify creation with required unique timer names and internal IDs.
* Initialize new time zones locally; animate saved previews automatically.
* Add Barlow Condensed Black for Black Friday; simplify email HTML.
* Use Polish labels Dni, Godzin, Minut, Sekund.

= 12.6.1 =

* Respect static PNG/WebP preferences and WebP refusal; retain animated GIF.
* Bundle three more local fonts; appearance presets apply colors and typography.
* Keep canvas width when days hide below 24h, preventing enlarged email digits.

= 12.6.0 =
* Add campaign management, local diagnostics, language presets and website live text.

= 12.5.3 =
* Bundle Lato Regular under SIL OFL 1.1 with verified font/license notices.

= 12.5.2 =
* Integrate reserved dimensions and the end-image password fix; preserve existing URLs and campaigns.
* Update contributor, screenshots and Cloudflare guidance.

== Upgrade Notice ==

= 12.9.0 =
Back up data and fonts, deactivate Easy Countdown without uninstalling it, then install ActNow. Existing timers, embed links and blocks carry over. Update path-based asset exclusions to the new slug and clear affected page caches.
