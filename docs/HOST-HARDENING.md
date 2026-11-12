# Public image endpoint and native-library maintenance

These controls support the intentionally public image endpoint. They do not establish production capacity or replace host maintenance.

## Cache persistence

HTTP responses use `no-store` to reduce stale countdowns. Internally, images share a per-campaign/format transient with a 60-second TTL, validated against configuration, deadline state and the current 15-second freshness bucket. A valid hit precedes lock acquisition. Request cache-busters do not create new image slots.

After an internal cache hit or publication attempt, `X-Email-Countdown-Cache` reports:

| Value | Meaning |
| --- | --- |
| `hit` | A current internal image was reused. |
| `stored` | The backend accepted the entry. |
| `retained` | The write returned false, but a forced backend read found the exact current image. |
| `write-failed` | The write returned false and the exact current image could not be verified. |
| `too-large` | The entry exceeded an explicitly configured host budget and was not submitted. |

Fallback/error responses need not contain this header. A valid freshly rendered image is still returned after a false write or size rejection. Its MIME, pixels, frame count/timing and public URL are unchanged. No stale GIF or cached static substitute is introduced. Failure while verifying a false write also means `write-failed`; exceptions from the original cache-read/write or encoder paths retain sanitized error handling.

The `email_countdown_timer_image_cache_result` action receives three values: fixed publication state, serialized envelope bytes, and effective byte limit. It receives no campaign ID, configuration, path or visitor metadata. The plugin adds no log, persistent diagnostic record or external request. A host can attach its own local monitoring handler.

The `email_countdown_timer_cache_entry_max_bytes` filter defaults to integer `0` (no additional size limit). A positive integer refuses larger entries before calling the backend. An operator with a verified 1 MiB item limit could return `900000`, leaving adapter headroom. Measure the actual adapter: PHP's serialized envelope size includes base64 overhead but does not guarantee every backend's wire encoding. Invalid limits preserve the default. Renderer pixel limits remain independent and cannot be raised through this filter.

**A rejected or unavailable cache can cause every subsequent request to render again.** The limit and diagnostic hook do not provide a circuit breaker or rate limit. Repair the backend, adjust its limits, reduce campaign geometry, or provision suitable hosting. Do not lower a working installation's budget without measuring the effect.

## Capacity and edge controls

Measure cold and warm traffic using the actual PHP-FPM/native-library/cache stack. A GD-only benchmark cannot characterize Imagick's 60-frame animation or production memory use. Include simultaneous opens, email-provider prefetching, multiple campaign/format keys and backend failure. One-second advisory lock waiting does not bound encoder or database-network time.

Apply any hosting/WAF rate rule specifically to requests whose parsed query contains `ecd_action=render`; keep normal pages unaffected. Preserve dynamic queries and bypass page/edge caching and image conversion. Choose burst/sustained limits from measured sending volume and capacity. Gmail/Outlook/Apple Mail proxies can concentrate legitimate requests; a blanket per-IP limit or browser challenge can break email images. Verify actual campaigns before enforcement. The plugin does not configure a WAF or collect visitor identities.

## Native libraries

Keep deployed PHP, GD, FreeType, Imagick/ImageMagick and image codecs on patched, vendor-supported builds. Check the actual PHP SAPI and extension linkage, not just a separate command-line utility. Ask the hosting provider for package/build security status and verify distributor backports.

Historical examples: FreeType [CVE-2025-27363](https://www.facebook.com/security/advisories/cve-2025-27363), upstream fix 2.13.1; libwebp [CVE-2023-4863](https://github.com/webmproject/libwebp/releases/tag/v1.3.2), upstream fix 1.3.2. These thresholds address those defects, not all current vulnerabilities. A lower distributor version may contain a backported fix. Source review cannot determine the target host's patch status.

Install trusted, licensed static TTF/OTF files through the local-font workflow. There is no plugin font-upload endpoint. Containment and copy-time magic bytes screen location/format; a valid header does not establish that a native binary is safe or mitigate these CVEs. Existing font-selection/render behavior is preserved for saved campaigns.

## Public campaign content

IDs and image URLs are public identifiers, not authorization secrets. The expiry-only image is intentionally accessible before the deadline. Gutenberg exposes only bounded IDs after edit authorization, not private names or raw configuration. Keep sensitive information out of rendered content and identifiers. An unpublished-state feature requires an explicit product/compatibility decision.
