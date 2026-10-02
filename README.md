# Easy Countdown

Locally generated countdown images for email campaigns and WordPress pages. Set a fixed deadline, choose colors, labels and a local font, then copy email HTML, an image URL or `[ecd_timer id="promotion"]`. The plugin does not send email, require a countdown SaaS account or track individual opens.

**Source version:** 12.5.2 · **License:** GPL-3.0-only · **Admin menu:** Easy Countdown

## Requirements

| Component | Requirement |
|---|---|
| WordPress | 6.4 or later; CI tests the minimum and current stable |
| PHP | Compatibility minimum 8.1; use an upstream-supported PHP version for production |
| GD | Required with PNG/GIF support; WebP is used when available |
| Imagick | Required for animated GIFs; GD alone produces static images |
| FreeType | Required for custom TTF/OTF fonts |
| Database | Consistent MySQL/MariaDB session/primary for serialized animation generation; unavailable locking means cache misses remain uncached static images, even with Imagick |
| Network | A publicly reachable HTTPS image URL for email recipients |

There are no bundled runtime Composer/npm dependencies or font binaries. Custom database proxies, native library builds, commercial optimizer versions and email clients require installation-specific testing. The [static fallback guide](docs/STATIC-FALLBACK.md) describes degraded operation, not complete SQLite or database-proxy compatibility.

## Existing WordPress.org submission

The display name is **Easy Countdown** and the assigned submission slug, translation domain and installation directory are **`easy-countdown`**. The GitHub repository keeps its current name. This package is intended as an update to that existing submission, not a second plugin submission; no directory approval is claimed. `Contributors: ddoomm` identifies the maintainer's confirmed WordPress.org account. The submitted package must be the same archive validated by CI and the maintainer's live checks; see [finalization and live checks](docs/REVIEW-HANDOFF-12.5.2.md).

## Image after the countdown

Password-protected attachments and attachments whose immediate parent has a password are not eligible as public end images, regardless of the viewer's login or password cookie. New origin responses use the existing zero-countdown fallback; already downloaded copies cannot be revoked.

Choose a local image in **Create/Edit Timer > After Countdown**. The native Media Library picker is available on this editor only; attachment-ID entry also works without JavaScript. Save to apply. A zero/empty selection keeps the previous zero-countdown behavior. Existing records and open older forms retain their state.

New requests at/after the deadline show the chosen image. When a generated 60-second GIF crosses that deadline, its remaining frames show the image as well. The image is fitted to the timer canvas without distortion, using the configured background. JPEG/PNG/GIF/WebP only: up to 4 MiB, 4096 pixels per side and 4 million pixels. Animated inputs use the first frame. Remote-only assets, SVG and symlinked files are rejected; output is re-encoded locally without copying original metadata. A removed attachment falls back to zeros. Media Library images remain user-owned and are never deleted by this plugin.

A previously delivered GIF cannot request new bytes after its playback ends, and email services may cache an earlier response. The feature follows the actual deadline, not simply the end of every 60-second animation. Use visible deadline text and an `alt` suitable for both states. [Full end-image guide](docs/wiki/End-Image.md).

## Install or update

**Slug transition:** if the earlier GitHub copy is installed under `email-countdown-timer/`, back up data/fonts and deactivate it without uninstalling before activating the new `easy-countdown/` copy. Both names share data; do not activate both or uninstall either with cleanup enabled while the other needs that data. The new copy detects the known active legacy basename and refuses duplicate initialization. This does not rename files on your live site automatically.

Use the installation ZIP, not a working directory containing developer/test files. In WordPress, open **Plugins > Add New > Upload Plugin** and install it. For an update, replace the installed plugin rather than uninstalling it; uninstall may remove data if you enabled that option. Back up your database and custom fonts first, and disable any older duplicate implementation or snippet.

**Before the first update from 12.3.1 or older, copy/back up plugin-local fonts before WordPress replaces the old directory.** New code cannot recover files removed before it runs. Preserve font filenames and license notices. Persistent storage normally lives at `wp-content/uploads/email-countdown-timer/fonts/`; multisite adds `site-ID/`, and custom uploads paths can differ. See [local font migration](docs/LOCAL-FONT-STORAGE.md).

Open **Easy Countdown > Create Timer**, select a unique ID, set the deadline and time zone, and save. New timers inherit the WordPress site time zone, including UTC offsets; existing campaigns and legacy missing-zone records keep their original interpretation. Reload older editor tabs after updating: obsolete `save_timer` submissions are rejected without changing data. The editor offers **Embed Codes**, saved static preview and explicit **Preview Animation / Stop Preview** controls. Send a test message through the actual email platform and check your page while logged out.

## Display and accessibility

The editor supports colors, dimensions, custom labels, local fonts and **Image alternative text (alt)**. The combined geometry is validated before saving without encoding a preview. Native bitmap text has fixed sizes; new saves require printable ASCII labels when no usable FreeType font is present. A TTF/OTF file must contain the glyphs required by your labels.

Saved `alt` is escaped in the website shortcode, preview and newly copied email HTML. Explicit empty text remains `alt=""`; use it only when surrounding content already conveys the information. Missing legacy metadata uses a contextual fallback. Editing a timer cannot change the HTML of an already sent email. Recopy email HTML, or set alt in your email editor when using only an image URL. Purge affected HTML page caches after a shortcode description changes.

The admin interface is English and translation-ready. It uses native WordPress controls, visible labels, keyboard/focus handling, retained invalid input and confirmed deletion. Saving/manual copying remain available without JavaScript. Campaign labels are saved content, not automatically translated when the administrator's locale changes. Automated checks do not establish complete WCAG conformance.

## Embedding

```text
[ecd_timer id="promotion"]
```

Email HTML can be copied from the editor with an image description and visible absolute deadline. Images are generated from the current server time, with 15-second shared freshness buckets. Animated GIFs contain 60 one-second frames. A single downloaded GIF does not recalculate forever: email providers can prefetch, cache or block it, and some clients only display the first frame. **Always include the actual deadline as visible text.** Do not insert recipient IDs, email addresses or confidential information into public timer fields or URLs.

The public query names, shortcode and `easy_countdown_timers` option remain compatible with previous releases. Do not change URLs already used in campaigns. [Embedding details](docs/wiki/Embedding.md).

## Local fonts

**Protect the font directory from direct HTTP downloads.** **Data Settings > Font File Access > Install Font Access Rules (Apache)** creates fixed deny rules without replacing existing files; it does not verify their enforcement. nginx requires host-managed configuration. Site administrators on multisite can protect their own font root; changing the shared legacy root requires network-administrator permission. [Host rules, GET/HEAD verification and licensing boundaries](docs/FONT-HTTP-ACCESS.md).

Upload trusted, licensed static TTF/OTF files through SFTP to persistent per-site storage; no browser upload endpoint is provided. For older plugin-local files, use the [existing migration tool and instructions](docs/LOCAL-FONT-STORAGE.md); legacy filename precedence is preserved. Keep license notices and verify permission for server-side rendering.

**Automatic Google Fonts import is deferred and not implemented.** No catalog request, API-key field or Google font request is enabled. Fonts are read by the server renderer; visitors receive image bytes, not web-font downloads.

## Performance and optimizer compatibility

The panel uses a small scoped stylesheet/script, system typography, static saved previews and no background polling, per-keystroke rendering or animated list thumbnails. Warm image hits take no generation lock. A cold animation uses a shared database session lock and rechecks cache after waiting, so concurrent requests can share one generated image.

After at most one second of lock acquisition waiting, a busy or unavailable lock produces one frame at the current server time in the requested image format. It never returns a stale animated countdown or publishes a static fallback into the animation cache. A passed deadline shows the configured end image, or zeros when no valid end image is available. When a large GIF takes longer than the one-second wait, some followers can receive a static image while the owner finishes. If a completed image loses lock ownership, it can be returned only while still fresh and without crossing the deadline; it is never published to shared cache. Otherwise it is replaced by a current static frame. Missing GD, invalid data or encoder errors may still produce a sanitized error response. This is not a global rate limit or a timeout for the native encoder. **Data Settings > Rendering Diagnostics** provides an on-demand lock probe without visitor logging.

FlyingPress without CDN still requires correct local page-cache/lazy-load settings. Compatibility hints are narrow and do not disable normal page caching. If necessary, exclude `easy-countdown/assets/countdown.js` from delayed execution and `email-countdown-timer-image` from lazy loading; requests with `ecd_action=render` must not be page/edge cached or converted into static files. See [FlyingPress/WP Rocket instructions](docs/wiki/Optimization-and-Caching.md). Commercial optimizer binaries are not installed in CI, so this is not a guarantee for every version or settings combination.

## Layout stability and email rendering

New shortcode output includes renderer-derived `width`/`height`, an explicit CSS aspect ratio and proportional containment. The browser can reserve space before the image arrives; even a later image with different intrinsic dimensions uses that reserved box. Dimensions are measured without encoding or requesting an image and memoized only within the current request. No new frontend library, stylesheet, polling or visitor measurement is added. Image decoding is asynchronous; existing eager/no-lazy hints are retained for countdown freshness. No timer is automatically promoted to high fetch priority.

Custom theme CSS can still override this reservation. Purge the affected HTML page cache after changing geometry/fonts and recopy old HTML embeds to receive the new attributes. An unavailable native renderer or invalid saved configuration cannot provide a reliable calculated size. This is a targeted CLS mitigation, not a guarantee of whole-page LCP/INP/CLS scores.

New Email HTML uses explicit proportional dimensions, a maximum default display width of 600 pixels, inline styles, alt and an absolute deadline outside the image. Place it inside your email template's content cell. No script, CSS animation, web font or remote service is required in the message. The chosen end image is fitted to the same image canvas.

Remote-image blocking, animation preferences, dark mode, provider caching and prefetching remain controlled by the recipient/client. Do not bypass them. Keep important terms and the real deadline in readable text; an already downloaded GIF cannot fetch a new state. [Email verification scope and checklist](docs/EMAIL-RENDERING.md) and [dependency boundaries](docs/DEPENDENCIES-AND-COMPATIBILITY.md).

## Troubleshooting: Cloudflare

If an image stays stale, disappears or returns HTML behind Cloudflare, create a **Cache Rule** matching only the dynamic image endpoint. For example, replace the host below with your actual public hostname:

```text
(http.host eq "example.com" and any(http.request.uri.args["ecd_action"][*] eq "render"))
```

Set **Cache eligibility: Bypass cache** and place the exception after broader rules that force caching. Do not exclude your whole homepage or every page containing a timer. Keep `ecd` and `mode` intact. Remove any previously cached timer response and test the same image URL repeatedly without adding a request `no-cache` header or changing query parameters. A valid email response is HTTP 200, an actual GIF and `Cache-Control` containing `no-store` and `no-transform`; normally `CF-Cache-Status` is `DYNAMIC` or `BYPASS`, not `HIT`/`STALE`. One `MISS` is not proof of bypass.

A `cf-mitigated: challenge` / HTML response is a security challenge, not an image. Mail image fetchers cannot be required to solve an interactive challenge. Investigate Security Events and use the narrowest appropriate exception; do not disable the site's protection globally. Ordinary Bot Fight Mode cannot be skipped through a WAF Skip rule. Custom Workers, forced edge TTLs and image-transformation pipelines need separate review. With FlyingPress, avoid stacking Rocket Loader on top of another script-delay engine unless tested. This guidance is based on vendor documentation, not a test of your Cloudflare zone.

Sources: [Cache Rules settings](https://developers.cloudflare.com/cache/how-to/cache-rules/settings/), [rule order](https://developers.cloudflare.com/cache/how-to/cache-rules/order/), [query argument field](https://developers.cloudflare.com/ruleset-engine/rules-language/fields/reference/http.request.uri.args/), [challenge detection](https://developers.cloudflare.com/cloudflare-challenges/challenge-types/challenge-pages/detect-response/), [bot false positives](https://developers.cloudflare.com/bots/troubleshooting/false-positives/).

## Compatibility evidence, not a universal guarantee

Easy Countdown 12.5.0 was exercised with pinned combinations of WP Super Cache 3.1.3, Autoptimize 3.1.16, Yoast SEO 28.6 and Contact Form 7 6.1.7; Elementor 4.3.2, Rank Math 1.0.279, Query Monitor 4.0.7 and Limit Login Attempts Reloaded 3.3.10; and WooCommerce 11.1.2 with W3 Total Cache 2.10.6. In the selected passing runs no image-rendering conflict was observed. These are specific settings and coexistence scenarios, not every feature of those products or all their releases. An intermittent commerce admin timeout occurred on the PHP CLI test server and remains documented, not erased by a successful rerun.

A separate nginx/PHP-FPM experiment tests all eight on/off combinations of WooCommerce, W3 Total Cache and Easy Countdown with repeated admin, media, REST and public requests. It is an isolated experiment, not production load certification. [Test source and full qualifications in PR #13](https://github.com/dominiczajac/email-countdown-timer/pull/13). Paid FlyingPress/WP Rocket binaries, real Cloudflare zones and proprietary email applications have not been tested here.

## Privacy and removal

The shipped code adds no visitor cookies, persistent browser storage, individual impression/open counters, fingerprinting or developer telemetry. It stores administrator-authored campaign settings (including optional end-image attachment IDs), uninstall preference, copied-font ownership hashes and short-lived shared image caches locally. No Google service is contacted. HTTP delivery still exposes connection data to infrastructure; WordPress, other plugins, hosting and mail-image proxies have their own processing and logs. [Technical privacy disclosure](docs/wiki/Privacy-and-Local-Fonts.md).

The **WordPress Privacy Policy Guide** contains suggested wording and administrator guidance. The plugin does not edit or publish your privacy-policy page. Review providers, logs and retention before using the suggestion; this is not whole-site GDPR/ePrivacy certification.

**Data Settings > Delete all plugin data when uninstalling** is off by default and per site on multisite. Deactivation preserves data. Opt-in uninstall removes owned options/cache and unchanged font files recorded by the copy tool. Media Library attachments are never deleted. Font-access rule/index files remain for manual fonts. Manual/replaced fonts, unrelated data, other plugins' cron jobs and backups remain. The plugin schedules no cron jobs. [Data removal](docs/wiki/Data-Removal.md).

## Development and packaging

Read [AGENTS.md](AGENTS.md), [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md). Work through focused PRs and current-head checks; never bypass `main` protection or change the frozen renderer test oracle to hide a regression.

```sh
bash scripts/build-zip.sh --output dist/easy-countdown.zip --report dist/distribution.json
python3 tests/test-build.py
php tests/run.php
php tests/uninstall.php
```

The build uses an explicit runtime manifest, verifies metadata and refuses symlinks/missing/unlisted runtime files. Plugin Check, real WordPress lifecycle, browser and HTTP tests install this ZIP. Fonts, tests, CI and development docs are not bundled. Python is a packaging dependency only. [Build and verification instructions](docs/DISTRIBUTION-BUILD.md).

[Wiki sources](docs/wiki/Home.md) · [Changelog](CHANGELOG.md) · [Directory readiness](docs/WORDPRESS-ORG-READINESS.md) · [Static fallback](docs/STATIC-FALLBACK.md)

GitHub is the development repository. This README is not a claim of WordPress.org acceptance, native GitHub Wiki publication or production deployment. Historical verification reports identify their actual revisions; do not treat their measurements as benchmarks of your host or of later code.
