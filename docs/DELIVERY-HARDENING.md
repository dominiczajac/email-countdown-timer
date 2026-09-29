# Stable embeds and delivery hardening

Baseline: main `642edc2c15992243cd8b4aff939707a40a9e060c`, Easy Countdown 12.5.0. Candidate 12.5.1 is maintained in PR #14, independently of the PHP-FPM test-only extension in PR #13.

`Email_Countdown_Timer_Embed` calls the existing renderer measurement function, without image allocation, encoding or HTTP. A request-local memo holds at most 64 configurations. HTML is updated through `WP_HTML_Tag_Processor`; original escaped alt, source, optimizer and referrer attributes are retained. The website gets natural dimensions, fixed aspect ratio, object containment and asynchronous decoding. Email markup gets proportional display dimensions capped at 600 pixels and conservative inline styling. No public frontend files or dependencies are added. Invalid/unmeasurable records return unchanged markup, rather than crashing the host page.

The fixed CSS ratio deliberately does not use `auto`: cached HTML must not switch to a different natural ratio when days hide, a font changes or a small error image arrives. The tradeoff is padding/containment until the administrator invalidates page HTML after a deliberate geometry change. Mail clients may strip styles and independently cache remote images; the email path does not claim the website's fixed-ratio behavior in every client.

Tests include actual WordPress/native geometry; delayed real image responses at three widths with the former markup as a positive control; subsequent intrinsic-ratio changes; blocked-image/CSS-stripped/dark-canvas simulations. A pinned native Thunderbird local-message test is separate from browser simulations. A local .eml does not test transport, spam filtering, Gmail/Outlook/Apple Mail infrastructure or image proxies. Results must be read back from current-head CI before any passing-client claims.

PHP-FPM results, Cloudflare configuration, email limitations and dependency inventory are separate documents. No production mutation, new service, Google Fonts, source-image publishing, media deletion, telemetry or global cache flush is introduced.
