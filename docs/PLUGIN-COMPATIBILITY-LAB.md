# Isolated plugin compatibility lab

Target runtime: **Easy Countdown 12.5.0**, main `642edc2c15992243cd8b4aff939707a40a9e060c`. This is a tests/documentation-only change; installed distribution files must remain unchanged.

## Reproducible profiles

All profiles use WordPress 7.1.2 and native GD/Imagick in separate Ubuntu GitHub runners with disposable database containers. Plugins are official WordPress.org packages, version-pinned after initial discovery, with checksum verification and recorded source digests.

| Profile | PHP / database series | Actual plugins | Theme |
|---|---|---|---|
| Baseline | 8.4 / MySQL 8.4 | Easy Countdown only | Twenty Twenty-Five 1.5 |
| Publishing | 8.3 / MariaDB 10.11 | WP Super Cache 3.1.3; Autoptimize 3.1.16; Yoast SEO 28.6; Contact Form 7 6.1.7 | Twenty Twenty-Five 1.5 |
| Commerce | 8.4 / MySQL 8.4 | WooCommerce 11.1.2; W3 Total Cache 2.10.6 | Storefront 4.6.2 |
| Builder | 8.4 / MariaDB 11.4 | Elementor 4.3.2; Rank Math 1.0.279; Query Monitor 4.0.7; Limit Login Attempts Reloaded 3.3.10 | Hello Elementor 3.5.1 |

Cache plugins are never enabled together. WP Super Cache uses PHP/simple delivery; Autoptimize HTML/CSS/JS aggregation is enabled. W3 Total Cache uses disk-basic page cache, browser-cache settings and lazyload. Positive controls must demonstrate an unchanged origin-render marker on repeated anonymous requests and rewritten Autoptimize references. Installed or enabled status alone is not a passed cache test. Yoast's structured-data markup is checked instead of a comment that HTML optimization can legitimately remove.

These are preconfigured-site profiles, not first-install onboarding tests. Seeding removes only the one-shot `_wc_activation_redirect` and `elementor_activation_redirect` transients; it does not disable caching, security plugins, REST API or the timer. WooCommerce documents this setup for extension demos: https://developer.woocommerce.com/2025/01/24/demo-your-woo-extension-with-wordpress-playground/ . First-install tours and their remote services are not certified. One initial commerce run timed out inside the WooCommerce setup route (WordPress REST code); that failure is retained and is not attributed to Easy Countdown without a controlled reproduction.

## Coverage

- Anonymous actual HTTP GET/HEAD/POST, valid GIF bytes and 60 one-second frames, PNG/WebP negotiation, exact expired-image bytes, controlled errors, distinct timer IDs and required cache/security headers.
- A deadline change at the same public URL without purging page cache; eight concurrent requests; normal and reversed active-plugin order. Existing CI separately verifies lock ownership and one publication per cold burst.
- Chromium public image loading and refresh JavaScript, native Media Library selection/save, alt editing, expired preview, no-JavaScript form, control labels and narrow layout. Real WooCommerce product controls and an Elementor shortcode widget are also inspected; payment and full builder editing are not exercised.
- Record PHP logs and JavaScript exceptions, plugin/theme versions, configuration and digests. Bootstrap other active plugins with Easy Countdown skipped as a diagnostic-attribution control. CLI trailing diagnostics are preserved separately; HTTP bytes are never sanitized by the test harness.

A test-only MU shortcode supplies the page-generation marker and disables outgoing test mail. These are synthetic sites with no production exports, visitor identities or real orders. After package installation, remote WordPress HTTP API calls and external browser requests are blocked. Remote plugin services, license checks, payments and cloud security therefore remain untested. This is not a system-wide network isolation claim.

## Execution and limitations

`.github/workflows/compatibility.yml` runs on relevant pull-request changes and supports manual dispatch. It is an additional exploratory workflow, not a replacement for the existing 17-job required gate. No merge or ruleset change is made by this test task. Read the exact-head Actions results and associated PR before making a compatibility claim; earlier failed attempts remain visible.

The server is an eight-worker **PHP CLI loopback server**, not PHP-FPM, Apache/nginx request handling, LiteSpeed or real Cloudflare. MySQL/MariaDB containers test different databases but not a managed proxy/replica topology. Existing font-access CI separately exercises Apache/nginx rules. No paid FlyingPress/WP Rocket package, Cloudflare account/WAF/Workers/APO, real mail client, ARM/Windows host, production capacity or exhaustive security/accessibility/privacy certification is included. A passing profile establishes the specified behavior only for these versions and settings, not all features or combinations.

First attempts exposed harness assumptions (a Python filename shadowed `http.client`, empty PHP arrays encode as lists, WooCommerce login redirects, Elementor onboarding, directory-index routing in the temporary CLI server, optimized-away Yoast comments and resizing during a browser transition). Corrections retain binary checks and functional assertions; they do not change the plugin under test. Third-party PHP deprecations and deliberate external-update failures stay in evidence rather than being silently hidden.

Final results are recorded in the associated PR after current-head execution and artifact inspection. Production sites, runtime code, release tags, WordPress.org submission and native Wiki are unchanged.
