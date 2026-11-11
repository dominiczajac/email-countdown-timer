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
Google fonts in public widget output. Its stock admin application CSS separately
requests the Roboto stylesheet observed in the hosted pinned profile:
`family=Roboto:wght@400;500;700&display=swap&ver=1.0.0`. The resource query's
`ver=1.0.0` is distinct from Elementor's plugin version 4.3.2.
The browser aborts this exact resource and records it as
`blocked_vendor_fonts`, alongside the existing blocked core-avatar control.
Its exact family/weights, display/version query, host/path and stylesheet type must
match, only on the five named admin phases and only in the Elementor profile.
The owned HTTP lab permits HTTP/HTTPS requests, both aborted before fetching.
All three off/on pairs must show identical nonempty font-request counts for
each shared admin page, stable across all pairs; the active timer editor must
match the dashboard control. The font control must reconcile with the actual
external-request records and their exact bounded query metadata. Missing,
added or changed observations fail the final report gate.
Other hosts, fonts, resource types, public-page requests and JavaScript errors
remain failures. Stock Elementor CSS/scripts are preserved. This establishes
timer behavior with that optional third-party font blocked; Elementor's own
external font attempts remain visible. The inventory flag records the public
filter setting, and browser observations provide the network evidence.
An Elementor version change requires renewed observation of this resource
signature and paired controls.

This is shortcode/widget/product-description coverage. It does not certify
Elementor drag-and-drop editing or every experiment, WooCommerce cart/checkout,
all theme/multisite combinations, external services or email clients. The full
C3 item and Gate A maintainer/artifact acceptance remain open. No release, tag,
production deployment, ruleset or environment publication is proposed.

The report must bind each vendor result to its own seeded post ID/path, with
six distinct documents/products. The HTTP parser and browser both require the
image inside the native Elementor shortcode widget or WooCommerce long
description. Elementor's `data-elementor-id` or WooCommerce's actual
`body.postid-<N>` must match the seeded ID in HTTP and browser markup. The off
control retains the unprocessed shortcode there; the on
control replaces it with a decoded countdown. Empty containers or a timer
elsewhere on the page do not satisfy this proof. The selected no-tracking,
coming-soon and local-font settings are also checked in recorded metadata.

Initial run at `f04a491`:
[42965221589](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42965221589)
passed 10/12 jobs, including both WooCommerce cells with the initial broader
page-level checks. Both Elementor cells failed before recorded state outcomes.
After `productionresultssa19.blob.core.windows.net` was allowed, the original
artifact was inspected: valid fixture JSON is immediately followed by an
Elementor shutdown deprecation notice about the implicitly nullable `$key` in
`Atomic_Global_Styles::get_cache_root_key()`, producing JSON `Extra data`.
Preserve that negative result; stronger scoped checks and fixture binding
require fresh current-head evidence.

The next run, at `a7deacd`
([42966541646](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42966541646)),
identified `fixture-json / JSONDecodeError` before either Elementor experiment
started. The historical PR #13 records CLI shutdown diagnostics mixed into JSON;
Elementor 4.3.2's upstream `modules/wp-cli/cli-logger.php` sends non-warning
diagnostics through `WP_CLI::log`, consistent with the subsequently inspected
original artifact.

The fixture now writes its structured seed/inventory data directly to new,
fixed files in the owned evidence directory. CLI stdout/stderr, including
successful-command shutdown diagnostics, remain verbatim in separate logs.
Installed-package metadata comes from WordPress's actual `get_plugins()` file
headers; the raw `wp plugin list` output is also retained. JSON decoding stays
strict, each state deletes its old inventory before requesting a fresh one,
and CLI exit codes, HTTP bytes and native log gates are unchanged.
Controls exercise startup/shutdown output, stale files, symlinks, wrong paths
and encoding failure. Retained CLI notices/deprecations are diagnostic evidence,
not a new zero-warning gate; nonzero CLI exits and the existing FPM/PHP/nginx
fatal/native gates remain mandatory. No compatibility conclusion follows from
channel separation alone; only fresh hosted evidence can establish behavior.

At `8165482`, [42967321693](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42967321693)
again passed 10/12 jobs. Both Elementor cells now reached all six experiments
and failed the browser-network gate for `fonts.googleapis.com/css2` in admin
pages, including Easy Countdown off controls. A four-font-handle override at
`e01ea97` ([42967998477](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42967998477))
also passed 10/12 and did not suppress the observed admin resource. That override
was removed. An initial classifier at `df6f2d0`
([42969108362](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42969108362))
used the Roboto variant in upstream `app/assets/styles/_base.scss`; it correctly
rejected the different actual request and remained negative. Bounded diagnostics
at `bb0bc26` ([42969691244](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42969691244))
recorded the exact `Roboto:wght@400;500;700`, display and resource-version query
in both JIT cells and off/on states. The measured signature above replaces that
incorrect source assumption, with stricter cross-record/cross-pair checks.
These earlier failures stay negative and are not attributed to the countdown
runtime. Completed current-head results and review revisions are recorded in
[PR #31](https://github.com/dominiczajac/email-countdown-timer/pull/31).
