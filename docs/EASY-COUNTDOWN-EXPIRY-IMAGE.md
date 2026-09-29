# Easy Countdown submission identity and end images — 12.5.0

Baseline: 12.4.3 (`a2f93b60ad117aea703fd3893525f4099192b0db`). This work aligns the package with the maintainer's existing **Easy Countdown / easy-countdown** WordPress.org submission. It does not create or submit a second plugin, claim review approval, or verify the current queue status. The email supplied by the maintainer is not used as a `Contributors` username.

## Identity and compatibility

Display name: **Easy Countdown**. Text domain and installation ZIP root: **easy-countdown**. The GitHub repository remains `dominiczajac/email-countdown-timer`; no repository redirect or settings mutation is implied. The existing bootstrap filename `email-countdown-timer.php` is retained inside the new package directory; WordPress's translation domain follows the directory/submission slug, not this historical filename.

Storage (`easy_countdown_timers`, uninstall policy, image cache and font ownership), public query parameters (`ecd_action`, `ecd`, `mode`), `[ecd_timer]`, internal prefixes and persistent font paths are intentionally unchanged. Asset URLs use the real installed directory; the existing CSS/JS markers remain compatible with optimizer exclusions. The builder rejects display-name/text-domain drift.

A GitHub installation under `email-countdown-timer/` and the new `easy-countdown/` are separate WordPress plugin basenames. Back up data/fonts and deactivate the older copy without uninstalling it before activating the new one. The new bootstrap refuses to run alongside an active known legacy basename; it does not deactivate or delete anything. Never uninstall either copy with cleanup enabled while the other needs the shared data. Manually copied fonts and Media Library images remain user-owned.

## Optional end image

The editor adds **After Countdown**, a native Media Library button and a numeric attachment-ID fallback for no-JavaScript operation. The optional field is absent in legacy records and defaults to 0 in new forms. Older open forms cannot erase a saved selection. Saving still requires `manage_options`, POST and nonce; selecting a nonzero attachment also requires media permissions. Empty/zero restores the existing zero-countdown behavior.

Only regular, contained files in the current site's upload base are accepted, with a valid raster header and matching attachment MIME type: JPEG, PNG, GIF or WebP. Maximum source: 4 MiB, 4096 pixels per side, 4 million pixels total. Symlinks, wrappers, remote-only assets, SVG and nonpublic/trashed attachments are rejected. The path lookup explicitly bypasses attachment offload filters. A native decode is checked before accepting a new selection. The source's first frame is resampled into the existing countdown canvas, preserving aspect ratio and compositing onto its background. No source metadata is copied to the output.

The end image is publicly visible through the existing timer URL. At or after the absolute deadline, a new image request returns the selected image in the requested GIF/PNG/WebP format. A generated 60-frame GIF that reaches the deadline uses the end image in the remaining frames. Source image bytes are decoded/encoded once for that sequence, not once per end frame. No configured image leaves the original frame geometry and pixel oracle unchanged.

The current deadline phase and local attachment identity are part of the cache signature. A pre-deadline hit cannot mask an expired image even within one 15-second bucket. A valid expired image reuses the same bounded cache slot until the existing 60-second transient expires or the asset/configuration identity changes. Missing, deleted or invalid source files revert to the zero countdown without an outbound request. Busy/error/unsupported static fallbacks use the selected image when already expired; they do not publish into the animation cache. User attachments are not deleted on timer removal or uninstall.

The native media dependencies load only on the editor. There is no new public upload endpoint, remote image service, cron, polling or visitor identifier. The Media Library itself performs standard authenticated WordPress admin requests. Custom end-image UI code is budgeted separately from the core media dependency cost; it is not free of editor overhead.

## Delivery and accessibility limits

A GIF cannot fetch another image after it has stopped, and email proxies may retain an earlier response despite cache headers. This feature is deadline-aware server output, not remote control of previously delivered email bytes. Do not prematurely show an end image merely because a 60-second animation ends before the actual deadline. Include a visible deadline and choose the existing `alt` text to describe both states; the HTML attribute cannot change within GIF playback.

## Verification

Keep the PR draft until exact-head CI, the canonical installation ZIP, Plugin Check and new native/WordPress/browser/HTTP tests are inspected. Tests include local-path/MIME/type limits, no-JS editing, media selection, preserved old state, exact deadline/cache transitions, crossing GIF frames, file removal and the busy static path. Historical verification files keep their original versions and hashes.

No live-site deployment, active campaign load test, new WordPress.org submission, Contributors identity invention, native Wiki publication, release tag, Google Fonts importer or exhaustive security/GDPR/WCAG certification.
