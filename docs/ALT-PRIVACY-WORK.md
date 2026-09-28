# Alternative text, optimization compatibility and privacy

Baseline: main `6cdbb8b6f3c3d4209c590412f3c919ef6f4a246f` (12.2.0). Requested scope: an administrator-editable image alt field, targeted FlyingPress/WP Rocket compatibility guidance, accurate local/Google Fonts documentation, and security/privacy review.

## Acceptance

- Optional plain-text `alt` stored with a timer; safe attribute escaping in the website shortcode, saved preview and copied Email HTML. Empty/missing values retain automatic descriptions. Do not rewrite sent emails or pretend an image URL contains an HTML alt attribute.
- Preserve old settings, image URLs, shortcode, renderer pixels and GIF timing. Alternative text must not partition the image cache. Retain POST/capability/nonce guards and old-form compatibility.
- Use narrow image-only cache/LazyLoad signals and documentation; do not disable optimization for ordinary pages or claim comprehensive testing of paid optimizer versions not installed in CI.
- Add regressions for malformed/oversized/attribute-breaking input, browser/no-JS roundtrips, delayed/duplicate script execution and image-response headers.
- No telemetry, visitor identifiers, cookies, browser storage, remote font downloading or executable dependencies. Disclose the distinction between plugin behavior and WordPress/hosting/CDN request processing.
- Document manual local TTF/OTF installation and mark automatic Google Fonts import as planned, not available.

This work follows the protected PR workflow. A passing static check is not a full security, privacy or WCAG certification. No live-site deployment or destructive/load testing is authorized by this change. Keep draft until current-head checks have been inspected; no independent approval is implied.
