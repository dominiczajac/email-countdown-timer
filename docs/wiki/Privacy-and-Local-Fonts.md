# Privacy and local fonts

Scope: plugin source 12.3.0, not the entire WordPress installation. This describes technical behavior; it is not a GDPR/ePrivacy certification or an assertion that no personal data can ever be processed by the site.

## What this plugin stores

The local `easy_countdown_timers` option contains administrator-entered timer IDs, deadlines, time zones, colors, label text, alternative text, image dimensions and font filenames. A separate existing option records per-site consent to data removal during uninstall. Short-lived transients store shared rendered image bytes and configuration/cache signatures. They are not visitor profiles and are not keyed by recipients or IP addresses.

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

## Manual fonts available now

Obtain a licensed **static TTF or OTF** variant from a trusted publisher (Google Fonts is one possible source). Keep the font's license notice and comply with its terms. Upload it by SFTP or the hosting file manager into:

```text
wp-content/plugins/email-countdown-timer/fonts/
```

Create that directory when missing, refresh the timer editor, open Typography and Size, choose the filename and save. Preserve the original filename for existing timers. Do not upload PHP, archives, CSS or WOFF/WOFF2 and expect the server renderer to use them. There is no browser upload endpoint in this version. The renderer enforces path containment; install only trusted font binaries and keep PHP/GD/FreeType patched.

No font files are distributed with the plugin. Custom files inside the plugin directory can disappear during a WordPress plugin update; back them up and restore them under the same names. Update rather than uninstall when the data-removal option is enabled.

## Automatic Google Fonts import: proposed, NOT implemented

Do not look for a working Google picker or API-key field in 12.3.0. The proposed importer is described in [Google Fonts design](../GOOGLE-FONTS-DESIGN.md). Its intended flow is an explicit administrator action, one server-side download of a selected licensed variant, then exclusively local rendering. It should use a persistent per-site directory such as `wp-content/uploads/email-countdown-timer/fonts/`, not replace the current legacy directory silently.

The full Google Fonts Developer API requires an API key; a curated, versioned catalog is an alternative. A future download necessarily discloses the server IP and selected font to its source. That connection needs advance disclosure/appropriate authorization even though public image rendering would remain local. No consent, key or network request for that proposed feature is silently enabled here.

References: [WordPress plugin privacy](https://developer.wordpress.org/plugins/privacy/), [WordPress update behavior](https://developer.wordpress.org/reference/classes/plugin_upgrader/upgrade/), [Google Fonts API](https://developers.google.com/fonts/docs/developer_api).
