# Privacy and data handling

Scope: Email Countdown Timer 12.3.0, its shipped PHP/JavaScript, and the behavior described below. This is a technical disclosure, not a legal certification of a whole website or a promise that a server processes no personal data.

## No visitor or recipient tracking in this plugin

The plugin does not implement analytics, open tracking, profiling, telemetry, advertising identifiers, visitor cookies, browser local/session storage, fingerprinting, geolocation or remote error reporting. It does not read or store visitor IP addresses, user agents, referrers or recipient email addresses. It does not send data to the developer or request Google Fonts during normal use. No external runtime SDK, Google stylesheet or remote executable code is loaded. The current version has no automatic font importer.

All recipients use the same public timer URL for a campaign. `_t` is a time-based image-refresh parameter, not a persistent visitor/recipient ID; it is not used in the server cache key. The plugin does not record when a person opened a message or displayed a timer. Do not add customer IDs, email addresses, tracking tokens or secrets to timer IDs, URLs, labels or alternative text. Such content is entered by the administrator and can be publicly exposed; the plugin cannot determine that arbitrary text contains personal information.

## What is stored locally

| Data | Storage / retention |
|---|---|
| Administrator-authored timer settings: ID, deadline/time zone, colors, labels, optional `alt`, font filename and dimensions | `easy_countdown_timers` in each site's options table. Kept until deleted, or removed on uninstall with prior per-site opt-in. No author/visitor activity history is added. |
| Uninstall preference | `email_countdown_timer_delete_data_on_uninstall`, off by default. |
| Encoded image data and a configuration signature/time bucket | Owned `ecd_v1211_...` transients, 60-second TTL with 15-second freshness buckets. No request IP, cookie, user agent or recipient identity. WordPress/database cleanup may remove expired database rows later; known owned rows are explicitly removed on opt-in uninstall. |
| Optional manually installed fonts | Files in the plugin-local `/fonts/` directory, read by GD/FreeType. No browser font download is required for raster timer images. Back up before plugin updates. |

Deactivation does not delete campaign data. Opt-in uninstall removes owned options and known current/legacy transient rows, without deleting shared cron, unrelated caches or other sites' retained data. This plugin schedules no cron events and creates no custom user tables. See [Data Removal](wiki/Data-Removal.md) for external-cache and multisite boundaries. Removing plugin files may remove plugin-local fonts independently of the database-retention preference.

## Infrastructure still processes requests

A remote image must be fetched over HTTP. The origin server, hosting provider, CDN, WAF, optimizer services and email-image proxy may process/log an IP address, request URL, headers and time. WordPress itself manages administrator authentication, cookies, capabilities, updates and its own data; other plugins can process data before this plugin executes. None of that is disabled or audited by the statements above. `referrerpolicy="no-referrer"` is added to generated markup as a minimization measure where the client honors it; it cannot hide a network address, override all mail clients or erase server logs.

Configure hosting/CDN access-log fields and retention appropriately; avoid logging full query strings when unnecessary. Review processors and international transfers for the **whole deployment**. No production hosting configuration was changed as part of this code update. GDPR explicitly recognizes online identifiers in Recital 30; an absence of plugin telemetry is not an automatic exemption from data-protection duties.

## Fonts and future external connections

Manual installation from Google Fonts is available now: obtain a licensed TTF/OTF yourself and copy it to the plugin `/fonts/` directory. Automatic import is **planned, not implemented**. A future importer must require an explicit administrator action, disclose the remote source/connection, download only selected variants and licenses, and use a persistent per-site local directory. It must not contact Google on visitor requests or silently fetch an entire font catalog. See [Fonts](wiki/Fonts.md) and the [design proposal](GOOGLE-FONTS-DESIGN.md).

## Verification and security boundaries

Review covers source input/output, authorization/nonces, path containment, bounded rendering and data/network primitives. Regression tests cover alternative-text escaping, preserved old data, image-only cache exclusions, ignored synthetic visitor metadata, a no-tracking source tripwire, and anonymous image response headers in disposable WordPress. A static tripwire is not a general taint analyzer; passing tests does not prove the absence of every vulnerability or third-party interaction.

The plugin has no bundled third-party PHP/JavaScript runtime dependencies. Host WordPress, PHP, GD/FreeType, Imagick/ImageMagick and optimizer versions still require security maintenance. Public GIF rendering is resource-intensive; simultaneous uncached requests can duplicate work because an atomic generation lock is not implemented. This remains an availability risk requiring workload-specific protection and separate concurrency testing. No load or destructive test was run on the owner's live campaign.

## Suggested site-policy wording to adapt

> We use locally generated countdown images. The countdown plugin does not implement visitor analytics or email-open tracking and does not set tracking cookies or share data with its developer. Request delivery may still be processed by our hosting and image-delivery providers. Our provider, logging and retention information is described elsewhere in this policy.

This is a starting point, not an automatically published policy or legal advice. The site operator must supply accurate infrastructure-specific details.

References: [WordPress privacy guidance](https://developer.wordpress.org/plugins/privacy/), [WordPress plugin guideline 7](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [GDPR text, including Recital 30](https://eur-lex.europa.eu/eli/reg/2016/679/oj/eng).
