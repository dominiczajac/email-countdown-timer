# Easy Countdown 12.7.0: host acceptance and maintainer handoff

This is an unreleased installation candidate. The maintainer tested 12.6.1 on a website and in email and supplied the feedback
implemented here. The new 12.7.0 bytes still need owner acceptance. The maintainer
handles WordPress.org submission personally; no submission, approval or release is implied.
The [12.6.1 handoff](REVIEW-HANDOFF-12.6.1.md) and its receipts are historical;
use the new [owner-pending worksheet](ACCEPTANCE-12.7.0.csv) for this candidate.

## Identity and package

- Display name/root/text domain: **Easy Countdown**, `easy-countdown`.
- Main PHP file: `email-countdown-timer.php`; candidate version: **12.7.0**.
- Confirmed WordPress.org contributor: **ddoomm** (Simple Fast Secure).
- Installation ZIP: **649,119 bytes / 39 files**.
- ZIP SHA256: `dd476394303a4f92ec4ded0778a112afa0dd533b0e1de41b8ec49a250307e3fd`.
- Runtime/capture source: `015d1bf25d11671fbcbe2c7e78a9a867adb84b86`.
- Integration and final verification: [PR #43](https://github.com/dominiczajac/email-countdown-timer/pull/43).

Use the exact installation ZIP from the successful final CI's
`plugin-check-evidence` artifact, with its `distribution.json`, PCP findings,
exit code, stderr and tested source. Verify every shipped file, the ZIP bytes
and SHA256 against this identity. Final source may also contain documentation
and catalog-only changes; compare all 39 installed file hashes with
`.wordpress-org/capture.json`. A changed install file requires a refreshed
package identity and relevant evidence, even if the unreleased version is the same.

Source snapshots, the presentation/materials bundle and individual screenshots
are not installable plugins. See [distribution build](DISTRIBUTION-BUILD.md)
and the [12.7.0 verification record](CI-VERIFICATION-12.7.0.md).

## Included behavior

Quick Create on the dashboard/list, stored label languages, time zones, campaign
names and immutable IDs, duplicate/archive/restore, native block selection and
refresh, separate Website/Email embeds, optional website live text and local
diagnostics remain included. Existing saved data and image URLs are preserved.

Five unmodified static faces are bundled: **Lato Regular, IBM Plex Mono Regular,
Barlow Condensed Regular, Barlow Condensed Black (900) and Crimson Text Regular**. Original per-family OFL 1.1
notices and pinned source/hash information are in `fonts/`. Plugin code remains
GPL-3.0-only. Custom site fonts and development files are excluded from the ZIP.
The faces are used on the server, without remote or browser font downloads.

Appearance presets explicitly apply their available font and colors to an
unsaved editor form. They preserve labels and schedule; saving remains explicit.
If a preset face/FreeType is unavailable, the editor preserves its selected
font and Quick Create keeps its bitmap fallback. Loading an existing campaign
does not migrate its typography. Website live text inherits theme typography.

**Animated GIF remains the default image output** for email and website embeds.
Static images honor explicit PNG/WebP Accept preferences, including WebP refusal
with `q=0`; GET and HEAD agree. PNG fallback remains available. AVIF and automatic
format conversion are not enabled. Native HTTP tests decode the selected format
and retain 60-frame GIF controls; cache/MIME/Vary behavior remains format-specific.

Hide days now keeps the full canvas below 24 hours, with unchanged digit size and
the remaining units centered. Recopy Email HTML that was generated previously
below 24 hours with a narrower image. Already downloaded/provider-cached images
cannot be replaced. Pixel limits still apply to the full reserved canvas.

## Host-feedback changes in this candidate

Timer name is required and unique (active and archived timers), compared without
case differences and with WordPress accent normalization. New IDs are opaque and
server-generated; existing IDs/URLs remain unchanged. Modern forms hide IDs;
legacy integration payloads that omit name metadata keep their compatibility path.
New timers prefer the browser timezone, accepting supported aliases/equivalents,
with the WordPress timezone as no-JS/unsupported-zone fallback. Existing timers
and duplicates keep their saved zone. Failed rename deletion confirmations use
the saved timer name. The dashboard adds a clock and Countdown preset wording.

The saved GIF preview starts automatically and renews every 58 seconds while
visible and motion is allowed. It stops renewing when hidden, without Imagick,
or with reduced motion; no-JS keeps the initial static preview. This is the
existing bounded rendering/cache path, not a new endpoint. Black Friday uses a
genuine weight-900 font. Polish preset labels are Dni / Godzin / Minut / Sekund.
Colliding legacy names are disambiguated in the administrator picker without changing ID values. Gutenberg name labels are restricted to timer administrators; other content
editors still receive IDs. Public embeds and image caches contain no names.

Custom CSS, per-timer clean uninstall and forced Outlook autoplay were withdrawn.
Global uninstall opt-in remains available. Classic Outlook in Office LTSC
Professional Plus 2024 controls GIF playback; the plugin does not override it.

## Genuine catalog materials

The six current catalog PNGs and their provenance are in `.wordpress-org/`,
outside the install ZIP. They are real browser captures of synthetic data on
an owned disposable WordPress installation; UI pixels are not edited. Numbers
match `readme.txt`: dashboard Quick Create; saved campaign/typography; separate
Website/Email embeds; native block/live option; diagnostics; Privacy Policy Guide.

The capture receipt records actual WordPress/PHP/server/browser versions,
installed-file hashes, screenshot SHA256/dimensions and the named source.
Catalog screenshots do not establish production, FPM/JIT or owner acceptance.
Committing them does not publish SVN, native Wiki, WordPress.org or an environment.

## Host testing still to record

All fourteen rows in [ACCEPTANCE-12.7.0.csv](ACCEPTANCE-12.7.0.csv) start pending.
Record the actual ZIP hash, date, host/theme/optimizer/client versions and
PASS/FAIL with notes. Automated or old-version results must not fill these rows.

- Back up site data/fonts; update without uninstalling. If moving from the old
  plugin directory, keep one active copy and preserve existing timer URLs.
- Create/edit disposable campaigns; exercise occasion/font/label presets,
  custom and empty labels, keyboard/focus, narrow layouts and manual assistive
  technology. Verify existing campaign typography and deadlines remain intact.
- Test before/after 24h with Hide days, and before/after expiry. Inspect anonymous
  actual PNG/GIF/WebP bytes, animation, HEAD and response cache/MIME headers.
- On staging, verify private/password-protected end-image fallback/recovery,
  deactivate/reactivate retention and opt-in uninstall isolation. Keep media
  and manually installed/replaced fonts. Use a disposable clone for deletion.
- Test actual hosting/theme/optimizer settings one cache system at a time.
  Purge page HTML after campaign edits. Check Cloudflare only if configured.
- New copied Email HTML is image-only; existing copied HTML is unchanged. Add
  explanatory deadline text separately if desired and record real Sendy →
  Gmail/Outlook/Apple Mail behavior when those tests are available. Local
  Thunderbird and browser fixtures do not replace sender/provider acceptance.

Licensed FlyingPress/WP Rocket packages remain unavailable. Named free-plugin,
theme, multisite, cache and native delivery tests keep their exact sources,
settings and negative results; see the [roadmap](https://github.com/dominiczajac/email-countdown-timer/issues/20).

## WordPress.org submission belongs to the maintainer

Continue the existing **easy-countdown** submission and original review thread;
do not create a duplicate submission. The maintainer verifies account/email/2FA,
reviews the acceptance results and sends the exact accepted installation ZIP.
An [unsent English reply](WORDPRESS-ORG-REPLY-12.7.0.md) is supplied for adaptation.
Screenshots are separate catalog assets. No message or archive is sent by
preparing this handoff. No release/tag or production deployment is performed.
