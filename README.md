# Email Countdown Timer

A WordPress plugin that generates images counting down to a fixed deadline: animated GIFs for email and web pages, plus static PNG/WebP images. Images are generated on your own WordPress server; the plugin does not send email and does not require an external SaaS service or API key.

**Version:** 12.1.1 · **License:** GPL-3.0-only · **Admin menu:** Easy Countdown

## Requirements

| Component | Requirement |
|---|---|
| WordPress | The header declares a minimum of 6.4; test on your own installation before deploying |
| PHP | Minimum 8.1; CI covers 8.1–8.5 |
| GD | Required, with PNG/GIF support; WebP depends on the GD build |
| Imagick | Required for animation. Without it, GIF responses are static |
| FreeType | Required for custom TTF/OTF fonts |
| Network access | A publicly accessible WordPress HTTPS URL for images embedded in email |

No Composer/npm dependencies are required at runtime. Font files are not bundled.

## Installation and your first timer

1. Place the files in `wp-content/plugins/email-countdown-timer/`, with `email-countdown-timer.php` directly inside that directory. Activate **Email Countdown Timer** in WordPress.
2. Open **Easy Countdown** and create a timer, such as `promotion`: set the deadline, time zone, colors, and labels. Save the form.
3. Copy the shortcode or email image link from the timer list.

**Upgrading from the earlier code:** first back up your database and files, then disable the previous plugin or snippet. Do not run both implementations at once: the `ECD_Plugin_Colons_Fix` class name has been preserved. Data remains in the `easy_countdown_timers` option; there is no migration or automatic timer deletion on deactivation. See [Installation and Upgrades](docs/wiki/Installation.md).

## Embedding

Use a Shortcode block on a WordPress page:

```text
[ecd_timer id="promotion"]
```

In an HTML email, use the URL copied from the admin panel. Example — replace the domain and ID:

```html
<img src="https://example.com/?ecd_action=render&amp;ecd=promotion&amp;mode=email"
     alt="Time remaining until the promotion ends"
     style="display:block;max-width:100%;height:auto;border:0;">
```

Do not paste the shortcode into an email or attach a downloaded GIF as a file when the timer needs to be calculated as the image is fetched from the server. See [Embedding](docs/wiki/Embedding.md).

## Preserved features and important limitations

Configuration includes the deadline and time zone, three colors, four labels, font, text sizes, width, and the option to hide days when fewer than 24 hours remain. The Polish admin interface and default values, shortcode, image URLs, and v12.1 renderer layout are preserved.

A GIF contains **60 frames, one second each**. It is a finite image sequence, not a live connection to the server. The shortcode fetches a new image when the browser tab becomes visible again; it does not poll every minute. The built-in cache shares images within 15-second buckets, so the first frame may not match the exact time of the request.

**Email does not guarantee a countdown starting at the moment of opening.** A client may prefetch, cache, or block the image. Apple Mail Privacy Protection may fetch content in the background; Gmail uses an image proxy. Server headers do not provide control over the entire process. Include the absolute deadline as text in the message as well. See [Sources and limitations](docs/wiki/Embedding.md#email-client-limitations).

The width field retains its original meaning: it sets the **minimum image width**, rather than scaling to an exact size; `0` means automatic. Without a custom font, fixed-size GD bitmap fonts are used. The digit and label size fields do not resize those bitmap fonts. See [Configuration and limits](docs/wiki/Configuration.md).

## Documentation

[Wiki index](docs/wiki/Home.md) · [Installation](docs/wiki/Installation.md) · [Configuration](docs/wiki/Configuration.md) · [Embedding](docs/wiki/Embedding.md) · [Performance and Security](docs/wiki/Performance-and-Security.md) · [Troubleshooting](docs/wiki/Troubleshooting.md) · [Development and Wiki publishing](docs/wiki/Development.md)

Wiki pages are version-controlled in `docs/wiki/`. This directory **is not automatically the native GitHub Wiki tab**. To publish to the separate Wiki repository, the owner runs `scripts/publish-wiki.sh` using local Git authentication.

## Verification and development

```sh
php tests/run.php
node --check assets/countdown.js
```

CI checks syntax and runs tests on PHP 8.1–8.5 with GD/Imagick, plus a separate PHP 8.4 configuration without Imagick. Tests cover validation, saves with capability and nonce checks, caching, 60-frame GIFs, and pixel comparisons against the original renderer. These tests use WordPress API stubs; they are **not full WordPress integration tests**. Missing GD is explicitly reported as skipped image tests locally and as an error in CI.

The [audit report](docs/SECURITY-PERFORMANCE-AUDIT.md) records results, scope, and remaining risks. We do not claim a measured percentage speedup or full WPCS compliance. Contribution guidelines: [CONTRIBUTING.md](CONTRIBUTING.md); security reports: [SECURITY.md](SECURITY.md).

## License

The code is distributed under GNU GPL v3.0 (`GPL-3.0-only`). The repository's existing [LICENSE](LICENSE) file has been preserved. Check the separate license of any custom fonts you add. See the [Changelog](CHANGELOG.md).
