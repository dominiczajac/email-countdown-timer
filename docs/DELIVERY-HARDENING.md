# Stable embeds and delivery hardening

Baseline: main `642edc2c15992243cd8b4aff939707a40a9e060c`, Easy Countdown 12.5.0. Candidate 12.5.1 is maintained in PR #14, independently of the PHP-FPM test-only extension in PR #13.

`Email_Countdown_Timer_Embed` calls the existing renderer measurement function, without image allocation, encoding or HTTP. A request-local memo holds at most 64 configurations. HTML is updated through `WP_HTML_Tag_Processor`; original escaped alt, source, optimizer and referrer attributes are retained. The website gets natural dimensions, fixed aspect ratio, object containment and asynchronous decoding. Email markup gets proportional display dimensions capped at 600 pixels and conservative inline styling. No public frontend files or dependencies are added. Invalid/unmeasurable records return unchanged markup, rather than crashing the host page.

The fixed CSS ratio deliberately does not use `auto`: cached HTML must not switch to a different natural ratio when days hide, a font changes or a small error image arrives. The tradeoff is padding/containment until the administrator invalidates page HTML after a deliberate geometry change. Mail clients may strip styles and independently cache remote images; the email path does not claim the website's fixed-ratio behavior in every client.

Tests include actual WordPress/native geometry; delayed real image responses at three widths with the former markup as a positive control; subsequent intrinsic-ratio changes; blocked-image/CSS-stripped/dark-canvas simulations. A pinned native Thunderbird local-message test is separate from browser simulations. A local .eml does not test transport, spam filtering, Gmail/Outlook/Apple Mail infrastructure or image proxies. Results must be read back from current-head CI before any passing-client claims.

PHP-FPM results, Cloudflare configuration, email limitations and dependency inventory are separate documents. No production mutation, new service, Google Fonts, source-image publishing, media deletion, telemetry or global cache flush is introduced.

## Recovery checkpoint and evidence boundaries

The interrupted implementation was recovered, not rebuilt. PR #14 already contained the runtime helper, Cloudflare/readme guidance and dependency inventory at `b946828cb14401d5fcf06c873aef7c03fb40c474`. Run `36546603964` had a clean same-ZIP Plugin Check and successful native geometry, HTTP and delivery jobs. The delivery artifact contained 54 layout/degradation checks: at 320, 768 and 1280 CSS pixels the reserved markup had 0 observed layout shift and no movement of the following text. The stripped-dimension positive control moved that text by 53 pixels. The existing native Thunderbird test verified an expired image with remote content blocked/allowed. These are precise laboratory scenarios, not field CWV, a Gmail screenshot or proof of every image/font combination.

The initial attempt was not fully green: the PHP CLI server crashed with a segmentation fault during the admin login sequence, before the UI assertions. The preserved failed run is not relabelled successful. Subsequent current-head results and any reruns must be separately identified in the PR description. No native-crash fix is claimed by this presentation-only change.

PR #13 contains the separate FPM investigation. The original four-profile run `36546953563` recorded 384 requests. The Woo-only profile reproduced the REST-schema timeout on a synthetic media-control page with Easy Countdown deactivated. The Woo+W3 profile also had two FPM worker SIGSEGV events. This disproves the necessity of Easy Countdown for that particular REST timeout, but does not establish the cause of native crashes or clear every WooCommerce configuration. Retain negative evidence and do not hide it in a generic "no conflicts" badge.

The current test extension adds native live-animation/deadline-transition checks. Only completed current-head results may be described as passing. No production mutation or automatic merge is part of recovery.
