# Optimization plugin compatibility

Applies to 12.3.0. Documentation reviewed on 2026-09-28. These are scoped compatibility contracts and troubleshooting instructions, not a claim that every FlyingPress/WP Rocket version, theme, CDN or setting combination has been tested. The paid plugins are not bundled or installed in our CI.

## Keep the page cache; do not freeze the image

A page containing `[ecd_timer id="promotion"]` can normally be cached and optimized. The separate image request (`ecd_action=render`) must reach the dynamic endpoint rather than receive cached HTML or a permanently cached GIF. Its response keeps `Cache-Control: no-cache, no-store, must-revalidate` and `X-Content-Type-Options: nosniff`. At plugin bootstrap, only render requests set `DONOTCACHEPAGE` (unless already defined) and add FlyingPress's `flying_press_is_cacheable` filter returning false. Ordinary pages are untouched.

These PHP signals cannot intercept a response already served by an earlier WordPress cache drop-in, reverse proxy, CDN or email proxy. Purge stale caches after changing configuration. Where an upstream rule caches all URLs, create a **bypass when query parameter `ecd_action` equals `render`**. Match the parameter, regardless of its position; do not exclude the entire home page just because the image URL begins with `/`.

FlyingPress and WP Rocket document that query-string URLs are normally bypassed, with exceptions. Do not add `ecd_action`, `ecd`, `mode` or `_t` to settings that ignore these parameters, cache them as ordinary pages, or strip them from timer image URLs. Do not force page caching over `DONOTCACHEPAGE` for render requests. The plugin's internal 15-second image cache is intentional and remains enabled; a global object-cache flush is not needed.

## Minimal exclusions if an image is missing, frozen or delayed

| Feature | Specific value to exclude | Where / when |
|---|---|---|
| Image LazyLoad | `email-countdown-timer-image` | WP Rocket: Media / LazyLoad / Excluded images or iframes. FlyingPress: media LazyLoad exclusions. Enter the keyword without a leading dot. Both match image-tag text. |
| Delayed JavaScript | `/email-countdown-timer/assets/countdown.js` | WP Rocket: File Optimization / Delay JavaScript execution / Excluded JavaScript Files. FlyingPress: script-delay exclusion in the installed version, if this script is actually delayed. |
| JavaScript defer/minify/combine | `/email-countdown-timer/assets/countdown.js` | Only when isolating a reproducible script failure; standard defer/minification should not need an exclusion. |
| Lazy rendering of a containing section | The exact affected wrapper, or `email-countdown-timer-image` where supported | Only if lazy rendering demonstrably prevents the timer from becoming visible. Avoid broad selectors. |
| CDN/image transformation, GIF conversion or URL rewrite | Image requests with `ecd_action=render` | Bypass static-image optimization and stale caching for this endpoint; preserve all timer URL parameters and GIF animation. Do not exclude all site images. |
| Admin CSS/JS | `/email-countdown-timer/assets/admin.css` and `/email-countdown-timer/assets/admin.js` | Admin-only assets, not front-end exclusions. A third-party tool should not optimize authenticated `/wp-admin/` pages. If it does, exempt the admin area; do not publish/cache admin HTML. |

The website shortcode marks only its own image with `loading="eager"`, `data-no-lazy="1"`, `class="email-countdown-timer-image"` and `referrerpolicy="no-referrer"`. WP Rocket documents automatic LazyLoad exclusion for `loading="eager"` and `data-no-lazy`. FlyingPress's documented manual keyword exclusion is the fallback; do not assume undocumented attributes work in every version. Immediate loading is deliberate for time-sensitive images, not a recommendation to disable LazyLoad site-wide.

FlyingPress's current third-party-delay documentation targets external-domain scripts. This plugin's script is local, so an exclusion may be unnecessary unless a CDN or different configuration makes it a delay target. Use the installed version's controls; never disable all optimization pre-emptively. Do not run two full-page optimization stacks together just to test the plugin.

## What the script does

`countdown.js` registers one `visibilitychange` listener. Returning to a visible tab reloads timer images with a time-only `_t` cache-buster. It has no polling, analytics, visitor identifier, persistent browser storage, external dependency or initial-load fetch. Duplicate execution does not add listeners; malformed or unrelated image URLs cannot abort refreshing other valid timers. The GIF itself animates without JavaScript. Delaying the script may miss a tab-return event before the script loads; it does not prevent a normal GIF's first display.

Do not remove `data-ecd-src` or confuse it with optimizer-specific `data-src`/`data-lazy-src`. Test after clearing the generated optimization cache, not just the browser cache. No custom front-end CSS file exists.

## Verification on your staging site

Test FlyingPress and WP Rocket **separately**, with the versions/settings you use. Start logged out, without optimizer features, then enable page cache, LazyLoad, defer/delay and CDN/image transforms one at a time. Confirm a GIF response (not HTML), preserved query parameters, normal animation, a fresh request when returning to a tab and no console errors. Test a second timer, a below-fold image, blocked images and JavaScript disabled. After saving new alternative text, purge the cached **page HTML** containing the shortcode; generated image bytes cannot update an HTML attribute in that page. Re-copy previously pasted Email HTML as needed. Sent emails cannot be retroactively rewritten.

Email clients can prefetch, proxy and cache images regardless of server headers. Keep the absolute deadline as visible text and never infer an email open from a timer request. A finite 60-frame animation is not an indefinitely refreshed clock.

## Vendor references

- [WP Rocket: image exclusions](https://docs.wp-rocket.me/article/15-disable-lazy-load-on-specific-images)
- [WP Rocket: automatically excluded image attributes](https://docs.wp-rocket.me/article/1421-some-images-are-not-lazyloaded)
- [WP Rocket: Delay JavaScript execution](https://docs.wp-rocket.me/article/1349-delay-javascript-execution)
- [WP Rocket: DONOTCACHEPAGE and forced caching](https://docs.wp-rocket.me/article/141-force-page-caching)
- [WP Rocket: query strings](https://docs.wp-rocket.me/article/971-caching-query-strings)
- [FlyingPress: media LazyLoad](https://docs.flyingpress.com/en/articles/11406804-lazy-load-images-videos-and-iframes)
- [FlyingPress: third-party script delay](https://docs.flyingpress.com/en/articles/11406748-load-third-party-scripts-on-interaction)
- [FlyingPress: query parameters](https://docs.flyingpress.com/en/articles/11406502-separate-cache-for-query-parameters)
- [FlyingPress: conditional caching](https://docs.flyingpress.com/en/articles/11406011-conditionally-control-page-caching)
