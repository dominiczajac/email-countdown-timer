# Isolated plugin compatibility lab

Target: Easy Countdown 12.5.0, main `642edc2c15992243cd8b4aff939707a40a9e060c`.

This test-only change evaluates representative combinations on disposable GitHub runners and database containers. Production sites, runtime code, settings and release ZIPs are not changed. Do not interpret coding-standard compliance or plugin activation alone as proof of compatibility.

Planned profiles: baseline; publishing with WP Super Cache, Autoptimize, Yoast SEO and Contact Form 7; commerce with WooCommerce, W3 Total Cache and Storefront; builder with Elementor, Rank Math, Query Monitor and Limit Login Attempts Reloaded. Cache plugins are kept in separate profiles. Exact downloaded versions and hashes, configuration, functional checks and limitations must be recorded in evidence. A failed download/setup is not a passed compatibility test.

Checks must include real anonymous HTTP, decoded image bytes, different timer IDs, expiry images, page-cache hits where configured, JavaScript refresh, admin saves, and no-JavaScript behavior. Do not alter the frozen image oracle, suppress failures, or disable the original required checks. New tests are exploratory until verified.

No paid FlyingPress/WP Rocket binaries or licensed cloud/WAF services are available in this test scope. A local simulation is not certification of Cloudflare, LiteSpeed server caching, all themes, all plugins or future versions. Do not claim complete security/privacy compliance for third-party plugins. Synthetic fixtures only; no real accounts, emails, orders, fonts or site exports are committed.

Results: pending execution. This is not a compatibility claim.
