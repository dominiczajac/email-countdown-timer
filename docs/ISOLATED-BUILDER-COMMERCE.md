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

This is shortcode/widget/product-description coverage. It does not certify
Elementor drag-and-drop editing or every experiment, WooCommerce cart/checkout,
all theme/multisite combinations, external services or email clients. The full
C3 item and Gate A maintainer/artifact acceptance remain open. No release, tag,
production deployment, ruleset or environment publication is proposed.
