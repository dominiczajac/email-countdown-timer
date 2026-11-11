# Isolated Elementor and WooCommerce profiles

This continues roadmap #20 from integrated main `d066601`. PR #30's four
baseline/cache profiles and historical draft PR #13 retain their results.
The new profiles are Elementor 4.3.2 and WooCommerce 11.1.2, each activated
alone alongside the installed Easy Countdown candidate. No cache plugin is
active in either new profile. The installation ZIP/runtime remains unchanged.

Reuse the owned PHP-FPM/MySQL fixture and three counterbalanced Easy Countdown
off/on pairs, with WordPress 7.1.2, PHP 8.4, MySQL 8.4 and Twenty Twenty-Five.
Record actual patch versions and packages. OPcache stays enabled; JIT 1235 and
the separately labelled JIT-disabled diagnostic must both report their results.
Keep the current native limits, log and report gates; preserve negative results.

Each state gets its own synthetic Elementor document or WooCommerce simple,
virtual product. Prove native widget/product markup and the shortcode output
over HTTP and in a real anonymous browser, including the off control. Continue
saved timer, media, image decoding and live-block browser checks on the normal
WordPress page. Package checksums and exact active-plugin sets are mandatory.

Profiles represent an already configured local site. Activation redirect
transients are cleared; WooCommerce coming-soon mode and optional tracking are
disabled and recorded. WordPress HTTP API/mail and external browser attempts
remain controlled by the existing lab. Use only synthetic data: no real orders,
customers, subscriptions, payment, shipping, mail or remote service credentials.
Elementor's public `elementor/frontend/print_google_fonts` hook disables remote
Google fonts for this explicit offline/local-font profile; other unexpected
browser attempts remain recorded and fail acceptance.

This is shortcode/widget/product-description coverage. It does not certify
Elementor drag-and-drop editing or every experiment, WooCommerce cart/checkout,
all theme/multisite combinations, external services or email clients. The full
C3 item and Gate A maintainer/artifact acceptance remain open. No release, tag,
production deployment, ruleset or environment publication is proposed.

The report must bind each vendor result to its own seeded post ID/path, with
six distinct documents/products. The HTTP parser and browser both require the
image inside the native Elementor shortcode widget or WooCommerce long
description. The off control retains the unprocessed shortcode there; the on
control replaces it with a decoded countdown. Empty containers or a timer
elsewhere on the page do not satisfy this proof. The selected no-tracking,
coming-soon and local-font settings are also checked in recorded metadata.

Initial run at `f04a491`:
[42965221589](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42965221589)
passed 10/12 jobs, including both WooCommerce cells with the initial broader
page-level checks. Both Elementor cells failed before recorded state outcomes.
Their precise cause is unconfirmed while the cloud policy blocks the logs on
`productionresultssa19.blob.core.windows.net`. Preserve that negative result;
stronger scoped checks and fixture binding require fresh current-head evidence.
