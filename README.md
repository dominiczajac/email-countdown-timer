# Email Countdown Timer

A WordPress plugin that generates countdown images for a fixed deadline: animated GIFs for email and web pages, plus static PNG/WebP images. Images are generated on your own WordPress server. The plugin does not send email and requires no external SaaS service or API key.

**Source version:** 12.2.0 · **License:** GPL-3.0-only · **Admin menu:** Easy Countdown

## Requirements

| Component | Requirement |
|---|---|
| WordPress | Minimum 6.4; CI exercises 6.4 and current stable (7.1.2 in the recorded runs) |
| PHP | Minimum 8.1; isolated regression CI covers 8.1–8.5 |
| GD | Required with PNG/GIF support; WebP depends on the GD build |
| Imagick | Required for animation; GIF responses are static without it |
| FreeType | Required for custom TTF/OTF fonts |
| Network | A publicly accessible WordPress HTTPS URL for email images |

No Composer/npm dependencies are required at runtime. Font files are not bundled. Test your actual hosting and email clients before a campaign.

## Installation and first timer

1. Place the files in `wp-content/plugins/email-countdown-timer/`, with `email-countdown-timer.php` directly inside that directory. Activate **Email Countdown Timer**.
2. Open **Easy Countdown > Create Timer**, set an ID such as `promotion`, the deadline, time zone, colors and labels, and save.
3. In the editor, use **Embed Codes** to copy the image URL, email HTML or website shortcode. The list's **Get Embed Code** action opens this section.

When replacing an older plugin or snippet, back up the database and custom fonts, then disable the old implementation. Running both can duplicate hooks and output. Version 12.1.3 uses distinct internal `Email_Countdown_Timer_` classes; these are not a compatibility API for custom integrations. The saved `easy_countdown_timers` option, shortcode and image URLs are preserved. See [Installation](docs/wiki/Installation.md).

## Lightweight admin panel

Version 12.2.0 separates the searchable, paginated timer list from the editor. It uses system typography, native WordPress buttons, labeled controls, field-level errors, focus management and a separate confirmed delete action. The main form still works without JavaScript; manual copy formats and field sections are expanded in that mode.

The saved preview is a static image. **Preview Animation** is an explicit action and **Stop Preview** returns to the static image. Unsaved inputs do not trigger rendering or overwrite campaign data. There is no autosave, polling or animated list thumbnail. The custom stylesheet and script load only on the plugin's screens.

**Google Fonts import is not included.** The font selector lists local TTF/OTF files in the existing plugin `fonts/` directory. The [proposed persistent importer](docs/GOOGLE-FONTS-DESIGN.md) is separate work; backing up current custom fonts before an upgrade remains necessary.

[Admin workflow](docs/wiki/Admin-Interface.md) explains copying, errors, accessibility and deletion. Automated browser checks are not a screen-reader audit or a WCAG certification.

## Embedding

On a WordPress page:

```text
[ecd_timer id="promotion"]
```

In an HTML email, replace the domain and ID with the URL copied from your panel:

```html
<img src="https://example.com/?ecd_action=render&amp;ecd=promotion&amp;mode=email"
     alt="Time remaining until the promotion ends"
     style="display:block;max-width:100%;height:auto;border:0;">
```

Do not paste the shortcode or JavaScript into email. Attaching a downloaded GIF, or importing it into an editor's image library, may replace dynamic fetching with a fixed copy. See [Embedding](docs/wiki/Embedding.md).

## Behavior and limitations

The English interface and validation messages are translation-ready. New timer labels remain stable data: `Days`, `Hours`, `Minutes`, `Seconds`. Changing the administrator's locale does not rewrite labels in campaigns. Existing labels, including custom and empty values, are preserved; edit the **Labels** fields to change them.

A GIF contains **60 frames, one second each**. It is a finite sequence, not a live server connection. A shortcode refreshes its image when the browser tab becomes visible again; there is no continuous polling. Images are shared within 15-second cache buckets, so the first frame need not match the exact request time.

**An email client may prefetch, cache or block the image.** Server headers cannot guarantee an up-to-date countdown on every opening. Always include the absolute deadline in plain text. [Email-client limitations and sources](docs/wiki/Embedding.md#email-client-limitations).

Width is a **minimum image width**, not scaling to an exact dimension; `0` means automatic. Without a custom font, GD uses fixed-size bitmap fonts. Digit and label size fields do not resize those bitmap fonts. [Configuration](docs/wiki/Configuration.md).

## Data retention

**Easy Countdown > Data Settings > Delete all plugin data when uninstalling** is off by default. Saving this setting or deactivating the plugin never deletes timers. When enabled, uninstall through WordPress removes that site's plugin options and owned database image cache. Multisite consent is per site. Shared cron, foreign options and other plugins' caches are preserved; this plugin schedules no cron events.

Unknown orphaned entries existing only in an external cache expire at their original 60-second TTL rather than triggering a global cache flush. Uninstall cannot erase backups, hosting logs or email-client copies. [Data removal](docs/wiki/Data-Removal.md).

## Verification

```sh
php tests/run.php
php tests/uninstall.php
python3 tests/test-pcp-gate.py
node --check assets/countdown.js
node --check assets/admin.js
```

The isolated tests use WordPress API doubles and cover validation, permissions, saved-label preservation, translated output, caching and pixel comparisons against the frozen v12.1 renderer. Separately, disposable real WordPress/MySQL integration jobs exercise single-site and multisite installations with database transients and Redis, HTTP images, retention and uninstall. Missing GD is an explicit local skip and a CI failure.

CI also runs **Plugin Check 2.1.0 with runtime checks** against the distribution files. `required-checks` requires unit tests, WordPress integration and Plugin Check to pass. The PCP gate reads reported findings, not just the command's exit status, and fails on errors or warnings. Narrow, documented code-local annotations remain for context-sensitive cases such as binary image output; there are no global check exclusions. [Preflight review](docs/PLUGIN-CHECK-REVIEW.md).

The current CI also exercises the actual admin in a Chromium browser, with and without JavaScript, and records a synthetic renderer/cache benchmark. These measurements exclude complete HTTP/WordPress startup and concurrent requests.

See [current verification and performance evidence](docs/VERIFICATION-12.2.0.md), [previous preflight evidence](docs/VERIFICATION-12.1.3.md) and the [historical 12.1.1 audit](docs/SECURITY-PERFORMANCE-AUDIT.md). Tests do not certify security, every hosting configuration, browser accessibility, production throughput or WordPress.org acceptance. No measured percentage speedup or full WPCS compliance is claimed.

## Documentation and contribution

[Wiki index](docs/wiki/Home.md) · [Admin interface](docs/wiki/Admin-Interface.md) · [Installation](docs/wiki/Installation.md) · [Configuration](docs/wiki/Configuration.md) · [Embedding](docs/wiki/Embedding.md) · [Data removal](docs/wiki/Data-Removal.md) · [Performance and security](docs/wiki/Performance-and-Security.md) · [Troubleshooting](docs/wiki/Troubleshooting.md) · [Development](docs/wiki/Development.md)

`docs/wiki/` contains version-controlled documentation, not an automatically published native GitHub Wiki. `scripts/publish-wiki.sh` handles that separate operation with local Git authentication.

Use focused pull requests and check the latest CI. [Contributing](CONTRIBUTING.md) · [Security reporting](SECURITY.md) · [WordPress.org readiness](docs/WORDPRESS-ORG-READINESS.md) · [Changelog](CHANGELOG.md).

## License

GNU GPL v3.0 (`GPL-3.0-only`); see [LICENSE](LICENSE). Check the separate license of any custom fonts. Source publication does not imply a tagged release or acceptance into the WordPress.org directory.
