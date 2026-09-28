# Performance and Security

## What is cached

The completed image is stored through the WordPress Transients API as Base64, together with a configuration signature and a time-bucket number. The physical key is stable for each timer/format pair. Moving to the next 15-second bucket overwrites the same slot instead of creating more option entries. The entry expires after 60 seconds, but only an image from the current bucket is served.

The signature includes image-affecting configuration, including the label color, deadline, format, font path and modification time, Imagick availability, and implementation version. Alternative text is excluded because it is HTML metadata, not image content. Saving or deleting a timer invalidates its three PNG/GIF/WebP slots. The `_t` parameter and other arbitrary URL parameters do not partition the server-side cache.

The renderer measures the same font/text combination once per rendering operation, and the first frame is not drawn twice. Multiple shortcodes on a page share one listener. No representative requests-per-second benchmark or measurement of percentage speedup on a production server has been performed.

## Endpoint and admin protection

The admin panel requires `manage_options`, and save operations require a nonce. Form input passes through `wp_unslash()` and validation of types, dates, colors, and ranges. HTML output is escaped, URLs are built with WordPress APIs, and redirects are local and safe. Public rendering validates saved data again.

Fonts must remain within the local directory. Image dimensions are checked before GD allocation. Unknown IDs return 404 without creating an animation. The plugin does not raise PHP's execution time limit to 120 seconds. Buffers are closed only when they are removable.

Responses include `X-Content-Type-Options: nosniff`, `Vary: Accept`, and `Cache-Control: no-cache, no-store, must-revalidate, no-transform`. This does not guarantee that email intermediaries will not keep copies; see [Embedding](Embedding.md).

## Remaining risks and hosting

The endpoint is public by design and can still be abused. Since 12.3.0, **cold generation is serialized per site/timer/format using a MySQL/MariaDB session advisory lock**. Followers recheck shared cache after waiting. A two-second lock timeout produces 503 with Retry-After rather than duplicate work. This requires a consistent supported database session/primary and is not a global capacity or per-IP limit. The frame count remains unchanged. See [lock behavior and topology limits](../RENDER-CONCURRENCY.md).

Before a large campaign, test concurrent downloads and monitor PHP-FPM memory and CPU usage, as well as database connections. A persistent object cache may reduce database load, but does not solve stampedes on its own. The lock does not bound database network stalls or native encoder execution. Configure excessive-traffic protection at the hosting/WAF layer, accounting for shared email-client proxy addresses. Overly restrictive per-IP limits may block legitimate image requests.

Do not enable full CDN caching for this endpoint without considering the consequences: extending image freshness changes countdown accuracy. ImageMagick/PHP limits and codec policies depend on the server. The pixel budget limits input size but does not replace resource configuration.

## Data and privacy

The plugin stores timer configurations and cached images. It does not add recipient analytics, tracking identifiers, or external API requests. Standard hosting/CDN HTTP logs may still record image requests. Do not treat this implementation description as a legal audit of the entire email campaign.

Basis for the validation principles: [WordPress Security APIs](https://developer.wordpress.org/apis/security/). Detailed findings are recorded in `docs/SECURITY-PERFORMANCE-AUDIT.md` in the main repository.

## Current verification and integration guides

[12.3.0 HTTP measurements](../VERIFICATION-12.3.0.md) include complete loopback requests and simultaneous clients, not production-host or PHP-FPM capacity. [FlyingPress/WP Rocket guidance](Optimization-Compatibility.md) identifies narrow exclusions; [privacy and fonts](Privacy-and-Local-Fonts.md) distinguishes plugin behavior from infrastructure and manual fonts from a proposed importer.
