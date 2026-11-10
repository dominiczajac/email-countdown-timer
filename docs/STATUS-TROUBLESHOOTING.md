# On-demand Status & Troubleshooting

Add an administrator-only page under Easy Countdown. Opening it runs no
rendering probe, lock attempt or image request. **Run local checks** requires a
POST and nonce, then inspects GD, FreeType, Imagick, a zero-wait/released advisory
lock and the selected saved campaign's font resolution. No campaign or plugin
setting is changed. Missing optional animation/typography support is explained
with a severity and an action; this is not a complete host compatibility test.

An optional campaign ID is validated against saved records. Suggestions are
bounded to 250 IDs. Paths, names, attachment IDs, deadlines, site URLs, salts,
credentials and raw database/exception text never enter the clipboard report.
The report is an allowlist of plugin/WordPress/PHP versions and fixed diagnostic
codes, severity, explanation and recommended action. It is generated only on
request and retained only in the current page, with manual copying available.

A separate **Test public image** button makes one browser request to that
campaign's existing static image route, omitting credentials. No server-side
remote fetch, automatic probe, polling, visitor telemetry or external dependency.
It reports HTTP/decode failure without echoing a response body or URL into the
report. Success verifies this browser's anonymous access only; real email
providers, remote networks, caches and recipient clients still need acceptance.

Use the existing native admin controls, scoped assets and clipboard fallback.
Keep data retention and font-file maintenance separate. Site Health integration
is optional and deferred; no background Site Health network checks are added.
