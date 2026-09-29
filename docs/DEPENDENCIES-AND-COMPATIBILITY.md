# Dependencies and update strategy

The runtime is PHP plus small native browser JavaScript. There are no shipped Composer/npm libraries, remote fonts, SPA framework, CDN scripts or vendor telemetry. Development tools (Python, Playwright, image decoders, Thunderbird and CI actions) are isolated test/build dependencies and never enter the installation ZIP.

| Boundary | Current dependency | Maintainable approach |
|---|---|---|
| WordPress lifecycle | Public hooks, Shortcode/Options/Transient APIs, upload/media APIs, capability/nonce checks and privacy guide | Preserve stable APIs and test minimum/current WordPress; do not edit core or replace request bootstrap with fragile direct includes. |
| Markup | Public `WP_HTML_Tag_Processor`, available since WP 6.2; plugin minimum is WP 6.4 | Use core parsing rather than a bundled parser/regular-expression HTML mutation. |
| Admin media picker | Core `wp_enqueue_media` / `wp.media`, loaded on the editor only | Keep native picker and non-JavaScript attachment-ID entry; do not bundle another media library. |
| Admin accessibility | Native controls and core `wp-a11y` | Preserve labels, focus, error feedback and fallback form submission. Automated checks do not replace assistive-technology tests. |
| Drawing | PHP GD and optional FreeType | Required for text rasterization and static fallback. Inspect function support and limits, not merely PHP version. Host patches native libraries. |
| Animation | PHP Imagick / ImageMagick | Existing bounded GIF composition and static fallback when absent. WordPress's static image-editor abstraction is not a drop-in equivalent for 60 timed countdown frames. Avoid rebuilding working animation to hide this dependency. |
| Coordination | MySQL/MariaDB session advisory locks | Retain owner/connection checks and current static fallback. This is not universal SQLite/proxy support. A new backend needs separate concurrency tests. |
| Cache | WordPress transients, optionally an external object cache | Do not require Redis or flush shared site caches. CDN/page-cache bypass remains an operator responsibility. |

`wp_get_attachment_image()` is appropriate for library attachments, not for this dynamically generated countdown URL: substituting it does not automatically supply correct dimensions or cache semantics. Likewise, `wp_get_image_editor()` cannot be assumed to preserve the existing animation contract. WordPress-native public APIs are preferred where their contract matches the operation, not simply to replace every PHP/native function.

Upgrades should run the frozen pixel oracle, native regression suite, minimum/current WordPress lifecycle, same-ZIP Plugin Check, layout tests and pinned compatibility profiles. Changing one external plugin version should retain its exact settings, package checksums and before/after outcomes. Production-like FPM tests supplement PHP CLI tests. Avoid unbounded matrices or treating version syntax checks as full runtime compatibility.

Future WordPress/PHP/native-library changes can still require fixes. This inventory reduces the number of owned moving parts; it cannot guarantee that every future upgrade is a settings-only update. Runtime APIs, callback timing, image codecs, third-party global hooks and external cache behavior remain explicit boundaries. No major architecture rewrite is justified by the present dependency review.

Sources: [HTML Tag Processor](https://developer.wordpress.org/reference/classes/wp_html_tag_processor/), [media enqueue](https://developer.wordpress.org/reference/functions/wp_enqueue_media/), [image editor](https://developer.wordpress.org/reference/functions/wp_get_image_editor/), [attachment image](https://developer.wordpress.org/reference/functions/wp_get_attachment_image/), [transients](https://developer.wordpress.org/apis/transients/).
