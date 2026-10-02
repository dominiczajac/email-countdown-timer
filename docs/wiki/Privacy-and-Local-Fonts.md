# Privacy and local fonts

Scope: plugin source 12.4.2, not the entire WordPress installation. This describes technical behavior; it is not a GDPR/ePrivacy certification or an assertion that no personal data can ever be processed by the site.

## What this plugin stores

The local `easy_countdown_timers` option contains administrator-entered timer IDs, deadlines, time zones, colors, label text, alternative text, image dimensions and font filenames. Separate options record per-site consent to data removal during uninstall and the filenames/content hashes of fonts created by the explicit copy tool. Short-lived transients store shared rendered image bytes and configuration/cache signatures. They are not visitor profiles and are not keyed by recipients or IP addresses.

The rendering lock stores no options, files or scheduled jobs. Its name derives from the database/site table and timer/format cache key; it exists only for the database session holding it.

## What the shipped code does not collect

No impression/open counters, recipient email addresses, visitor IDs, IP addresses, user-agent strings, referrers, analytics events or fingerprinting data are collected or intentionally logged by the plugin. It does not set cookies or use browser localStorage, sessionStorage or IndexedDB. It does not send telemetry, call an external font service, read the clipboard or send email. Copy buttons write selected embed code to the clipboard only after an explicit action. The refresher's temporary in-memory boolean only prevents duplicate listeners; `_t` is a freshness timestamp, not a visitor identifier.

The only browser resources added are local plugin assets and the timer image URL. The font used to draw the image is read by GD/FreeType on the server; visitors/email readers receive image bytes, not a font download. The admin shell uses system typography. Generated embed markup requests no-referrer where supported, but email clients may strip that attribute.

These statements are supported by code review, a static sensitive-API guardrail, and synthetic HTTP/browser tests. A guardrail is not a complete information-flow proof. Adding an external service later requires a separate review and updated disclosure.

## Boundaries important for EU deployments

HTTP delivery inherently reveals network metadata to the receiving server or email-image proxy. WordPress core, other plugins, hosting access/error logs, firewalls, CDN services, backups and mail providers may process or store that information independently. The plugin cannot make those systems anonymous or delete their records. An optimizer that sends pages to its own cloud service also has its own privacy scope.

Do not put personal data, recipient-specific IDs or email addresses into timer IDs, labels, alt text or embed query strings. Administrator-entered content is stored by design and may be displayed publicly. A shared campaign timer does not require per-recipient personalization or unique tracking tokens. The site operator remains responsible for its complete processing inventory, retention and privacy notice.

Suggested site-policy wording, only after checking the complete deployment:

> This website serves countdown images from its WordPress server. The countdown plugin does not use tracking cookies, count individual views, or transmit visitor data to an external countdown provider. The server and services used to deliver our website or email may separately process connection data as described elsewhere in this policy.

Uninstall with the existing opt-in removes plugin-owned data; it does not erase backups, cached email copies, other plugins' data or infrastructure logs. See [Data removal](Data-Removal.md).

## Manual fonts and persistent storage

Use trusted, licensed static TTF/OTF files in the persistent per-site uploads directory. Follow [Local Font Storage](../LOCAL-FONT-STORAGE.md) before the first upgrade from 12.3.1 or older. The copy tool requires an explicit administrator action and never deletes originals or overwrites conflicts. Font license notices must be copied manually. No browser upload endpoint is provided. One optional Lato Regular example is bundled under SIL OFL 1.1 and used locally, without remote font requests.

Opt-in uninstall removes only unchanged files recorded as owned by the copy tool. Manually uploaded/replaced files and empty directories may remain; a retained file is not visitor tracking.

## Privacy Policy Guide

On `admin_init`, the plugin registers suggested text through `wp_add_privacy_policy_content()`. It appears in WordPress's Privacy Policy Guide, with supplementary administrator instructions marked `privacy-policy-tutorial`. This does not edit, publish or replace a privacy-policy page. The suggestion explicitly separates plugin behavior from hosting/email/optimizer processing; operators must add their own accurate provider/logging/retention information.

## Automatic Google Fonts import: deferred, NOT implemented

There is no working Google picker or API-key field. The [design proposal](../GOOGLE-FONTS-DESIGN.md) remains deferred. A future import would require explicit authorization and updated disclosure; no external request is silently enabled.

References: [WordPress plugin privacy](https://developer.wordpress.org/plugins/privacy/), [WordPress update behavior](https://developer.wordpress.org/reference/classes/plugin_upgrader/upgrade/), [Google Fonts API](https://developers.google.com/fonts/docs/developer_api).
