=== Easy Countdown ===
Tags: countdown, email, timer, gif
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 12.5.0
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create local countdown images for email and WordPress, with custom fonts, alternative text and an optional image shown after the deadline.

== Description ==

Easy Countdown generates countdown images on your own WordPress server. Create a timer, then copy its image URL or email HTML, or use [ecd_timer id="promotion"] on a WordPress page. No external countdown service is required. The plugin does not send email or track individual opens.

* Animated GIFs with 60 one-second frames when Imagick and database session locks are available.
* Per-timer deadlines and time zones, custom colors, labels and local TTF/OTF fonts.
* An optional Media Library image after the deadline, including GIF frames that cross the deadline.
* Alternative text for shortcodes, saved previews and newly copied email HTML.
* A server-rendered, English and translation-ready admin interface, with a static saved preview and explicit animation controls.
* Optional removal of owned plugin data on uninstall; off by default. Deactivation keeps your configuration.

GD is required. Imagick enables animation; FreeType enables custom fonts. Animation requires MySQL/MariaDB advisory locks on a consistent connection. Unavailable locking gives current static output on a cache miss, not an unprotected full animation. These requirements are independent of whether the Media Library image is configured.

Email services can prefetch, cache or block images, and some clients show only the first GIF frame. Always include the absolute deadline as visible text. The plugin cannot change an image already retained by an email proxy, or make a previously completed GIF refresh itself.

== Installation ==

1. Upload the Easy Countdown installation ZIP through Plugins > Add New > Upload Plugin.
2. Open Easy Countdown > Create Timer, choose an ID, deadline and time zone, and save.
3. Optionally choose a local image in After Countdown, then save again.
4. Copy the image URL/email HTML or website shortcode. Test in your actual email platform and on a logged-out page.

The WordPress.org submission slug and package directory are easy-countdown. The existing GitHub repository keeps its email-countdown-timer name. The internal main PHP filename, options, shortcode and query parameters remain compatible.

Moving from the GitHub package installed in email-countdown-timer/: back up the database and fonts, deactivate that copy without uninstalling it, then install/activate easy-countdown. Do not run both copies. Existing timers and image URLs remain. Do not uninstall the old copy with data removal enabled: both names use the same stored data. Copy plugin-local fonts to persistent storage before deleting/replacing either code directory.

== Frequently Asked Questions ==

= How does the end image work? =
In Create/Edit Timer > After Countdown choose an image with the native Media Library selector, or enter its attachment ID. Use 0 to keep the default zero countdown. Save Changes applies the choice; unsaved changes do not affect sent campaigns.

The image must be a local JPEG, PNG, GIF or WebP attachment, no larger than 4 MiB, 4096 pixels per side or 4 million pixels in total. SVG, remote-only/offloaded files, private/trashed attachments and symlinked paths are not accepted. Use trusted files. An animated source contributes only its first frame. Output is re-encoded, not redirected to the original file, and source metadata is not copied.

The end image is fitted into the countdown canvas without stretching, using the timer background for unused space. A wide image with a similar aspect ratio works best. On a new request at/after the deadline it replaces the zero image. If the deadline occurs inside the generated 60-second GIF, remaining frames show it. If the deadline is later than that GIF's playback window, the old GIF cannot update itself: another request is needed. Email caches may keep old output despite cache headers. Removing the attachment falls back to the zero countdown. The plugin never deletes Media Library files when a timer or the plugin is removed.

= What alternative text should I use? =
Use a description suitable for both the timer and its end image. One HTML alt attribute cannot change during GIF playback. Empty alt is appropriate only when nearby text supplies the same information. Older records without alt keep their automatic description. Recopy email HTML after editing and purge affected website HTML caches. A sent email or plain image URL cannot have its HTML alt changed by replacing image bytes.

= Where should I put fonts? =
Use trusted, licensed static TTF/OTF files in the persistent directory shown in Data Settings, normally wp-content/uploads/email-countdown-timer/fonts/. That existing storage name is retained across the easy-countdown rename. Multisite adds a site-ID directory. No font is bundled or downloaded automatically from Google. Copy font license notices manually. See the repository Local Font Storage guide for older plugin-local fonts and upgrade instructions.

= Can visitors download the original font files? =
Possibly, unless the server blocks direct access. Data Settings > Font File Access can install fixed Apache deny rules without overwriting existing files. nginx needs a host-managed rule. Writing .htaccess is not verification of server enforcement. Ask your host to verify denied GET/HEAD font requests and working timer images. Protection does not grant a font license.

= Why is a GIF static? =
Imagick may be missing, the timer may have ended with an end image, or the generation lock may be unavailable or busy. Image requests wait up to one second for a lock. When generating a large GIF takes longer, some simultaneous requests receive one current frame while the owner finishes. That fallback does not replace the animation cache. Hosts without usable advisory locks consistently use static output on misses. Data Settings > Rendering Diagnostics shows lock availability; test your hosting capacity before a campaign.

= Which optimization assets should I exclude? =
Only when needed, exclude easy-countdown/assets/countdown.js from delayed JavaScript and email-countdown-timer-image from image lazy loading. Older installations use email-countdown-timer/assets/countdown.js. Requests with ecd_action=render must bypass page caching and image conversion. Do not disable optimization globally. FlyingPress without CDN still needs appropriate local cache settings. Not every proprietary version/configuration is integration-tested; see the repository Optimization and Caching guide.

= Does this plugin collect user data? =
The shipped code adds no visitor analytics, open counters, tracking cookies, persistent browser storage or developer telemetry. It stores timer settings, optional attachment IDs, uninstall preference, copied-font ownership hashes and short-lived shared images locally. Media selection uses WordPress core's administrator-only Media Library. It does not add an external image service, new upload endpoint or remote downloads. Hosting, WordPress, other plugins and email-image proxies can separately process requests. Suggested wording is available in WordPress's Privacy Policy Guide. Do not put personal data or secrets in public timer fields, images or URLs.

= What happens on uninstall? =
Data is retained unless Delete all plugin data when uninstalling was explicitly enabled. Opt-in removes owned database data and unchanged files owned by the font-copy tool, per site in multisite. It does not delete Media Library images, manually managed/replaced fonts, shared cache, other plugins' cron, backups or infrastructure logs. Font access rules stay in place for remaining manual files. The plugin schedules no cron.

== Changelog ==

= 12.5.0 =
* Align display name, text domain and distribution directory with the existing Easy Countdown submission.
* Add an optional local end image, native media selection and exact deadline/cache handling.
* Preserve existing public image URLs, shortcodes, stored timers and persistent font paths.

== Upgrade Notice ==

= 12.5.0 =
Back up data/fonts. If the old email-countdown-timer directory is installed, deactivate it without uninstalling before activating easy-countdown. Both share data; never enable both. Existing WordPress.org submissions should receive this updated package, not a duplicate submission.
