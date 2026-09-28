# FlyingPress, WP Rocket and other optimizers

Applies to source versions 12.3.0 and 12.3.1. Guidance was checked against the vendors' official documentation on 2026-09-28. This is an interoperability design and troubleshooting guide, **not a claim that every current release/configuration of either commercial plugin was integration-tested**. Our CI does not install their proprietary binaries.

## Exclude narrowly, only where needed

| Symptom / setting | Exact asset or identifying keyword | Action |
|---|---|---|
| A website timer does not refresh when returning to its tab | `email-countdown-timer/assets/countdown.js` | Exclude this file from Delay JavaScript / load-after-interaction. If the issue is caused by minification or combination, exclude it there separately. Ordinary `defer` is supported. The WordPress handle is `ecd-refresh`; the usual script element ID is `ecd-refresh-js`. |
| Image is blank or replaced by a lazy-load placeholder | `email-countdown-timer-image` | Add this keyword to the image lazy-load exclusion field (without a leading dot). It is a class on the `img` itself. For custom, manually written markup, add the class or match `ecd_action=render` in the image URL. |
| A timer GIF becomes static or shows an old countdown | Requests with query parameter `ecd_action=render` | Bypass HTML/page/edge caching and image conversion/proxy/CDN transformation for this dynamic image request. Do not add these parameters to a 'cache query strings' allowlist or an 'ignored parameters' list. |
| A nonstandard optimizer modifies authenticated wp-admin | `email-countdown-timer/assets/admin.css` and `email-countdown-timer/assets/admin.js` | Exclude only those admin assets from the offending feature. In ordinary operation these assets are not loaded on the front end and need no public-page CSS exclusion. |

Use the actual asset URL when WordPress content directories are customized. Do not exclude all of `/wp-content/`, all JavaScript, all images, or the entire website merely for this plugin. Keep CSS/JS minification enabled unless testing identifies a specific breakage. No front-end stylesheet is added by this plugin.

## WP Rocket

Under File Optimization, add `email-countdown-timer/assets/countdown.js` to Delay JavaScript execution's **Excluded JavaScript Files** only if necessary. A minification/defer exclusion is a separate setting. The image exists in server-rendered HTML and does not need JavaScript to appear or play its initial GIF; this script refreshes it only on tab visibility changes.

Under Media, use `email-countdown-timer-image` in **Excluded images or iframes** when diagnosing lazy-load problems. Generated markup includes `loading="eager"` and `data-no-lazy="1"` as interoperability hints. Other optimizers or themes may still rewrite that markup.

WP Rocket normally bypasses pages with query strings unless configured otherwise. Leave `ecd_action`, `ecd`, and `mode` out of query-string caching and cache-ignore rules. The render request must not resolve to cached homepage HTML. Never exclude just `/` as a workaround: that would unnecessarily disable homepage caching.

## FlyingPress

Use the same unique script keyword in **Delay All JavaScript** exclusions if load-after-interaction disrupts tab refresh. The vendor supports partial, case-insensitive matching and does not support wildcard expressions in that field.

Use `email-countdown-timer-image` in media lazy-load exclusions. FlyingPress checks attributes on the complete media tag. Its documented default bypasses unknown query parameters; do not override this for `ecd_action=render` through Separate Cache for Query Parameters or Ignored Query Parameters.

For confirmed minifier breakage only, the documented `flying_press_exclude_from_minify:js` filter can append our precise path to the existing exclusions; preserve other exclusions rather than replace the entire array:

```php
add_filter( 'flying_press_exclude_from_minify:js', static function ( $keywords ) {
    $keywords[] = 'email-countdown-timer/assets/countdown.js';
    return $keywords;
} );
```

A host/CDN rule should match the parsed query parameter `ecd_action` with value `render`, independently of parameter order. Do not blindly paste that condition into a product's path-only field. Consult that layer's query-matching syntax. Exclude the same dynamic request from image conversion/copying: an optimization that preserves only the first GIF frame destroys the countdown.

## What the plugin does, and cannot do

Image responses use an image MIME type, `nosniff`, `Cache-Control: no-cache, no-store, must-revalidate, no-transform` and a best-effort `DONOTCACHEPAGE` flag for the render request. This does not disable caching of ordinary pages containing a shortcode. A plugin cannot retroactively override an `advanced-cache.php` drop-in, web server or CDN that has already served a cached response before normal plugins execute.

The refresh script works when deferred/loaded after DOM readiness, tolerates malformed individual image URLs, and registers only one listener even if an optimizer executes it twice. No background polling or persistent browser storage is used. These behaviors have dedicated simulations; they are not substitutes for testing a particular optimizer version.

The plugin's own short-lived image transient is different from page caching: it is shared among requests for the same timer/format within a 15-second bucket. Do not globally disable WordPress transients or flush Redis to fix a markup problem.

## Verify on staging

Purge affected page/optimized-asset/CDN caches after changing settings. Test logged out, on both mobile and desktop, with each relevant optimizer feature enabled individually. In browser Network, the image must return 200 and `image/gif` for `mode=email`/`mode=anim`, not `text/html`. Inspect animation, not just MIME. After a later time bucket, request the same timer again; it must not receive an indefinitely stale CDN copy. Return to a hidden tab and verify one refresh, not duplicate requests. Test with JavaScript disabled: the initial image should still load.

After changing a timer's alt, purge the **HTML page** cache of pages containing its shortcode, since alt belongs to HTML, not image pixels. Previously copied email HTML must be copied again; an already-sent email's alt cannot be changed by updating its image URL. No page-cache or browser setting can force all email proxies to refetch immediately.

## Vendor references

- WP Rocket lazy-load exclusions: https://docs.wp-rocket.me/article/15-disabling-lazy-load-on-specific-images
- WP Rocket JavaScript delay: https://docs.wp-rocket.me/article/1349-delay-javascript-execution
- WP Rocket default cache bypass: https://docs.wp-rocket.me/article/99-pages-not-cached-or-optimizations-not-working
- FlyingPress JavaScript delay: https://docs.flyingpress.com/en/articles/11406701-delay-all-javascript
- FlyingPress media exclusions: https://docs.flyingpress.com/en/articles/11406804-lazy-load-images-videos-and-iframes
- FlyingPress query caching: https://docs.flyingpress.com/en/articles/11406502-separate-cache-for-query-parameters
- FlyingPress ignored parameters: https://docs.flyingpress.com/en/articles/11406543-ignored-query-parameters
- FlyingPress minifier exclusions: https://docs.flyingpress.com/en/articles/11405981-exclude-files-from-javascript-minification

## FlyingPress without a CDN

Version 12.3.1 sets the `flying_press_is_cacheable` filter to false only for `ecd_action=render`; the early `DONOTCACHEPAGE` flag and `no-store, no-transform` response remain. Pages containing the shortcode remain cacheable. Without a CDN there are no CDN settings to change, but a hosting/page-cache layer can still run before this plugin. Clear the affected page HTML cache after changing alt text. Apply the narrow script or LazyLoad exclusions above only if an actual display/refresh issue occurs.

No commercial FlyingPress binary or production site configuration was tested by this reconciliation; this is not a compatibility certificate for an unspecified vendor version.
