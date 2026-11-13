# Easy Countdown 12.8.0: active previews and external embedding

This unreleased candidate addresses the maintainer's feedback on 12.7.0.
The maintainer handles the existing WordPress.org submission personally.
Preparing this package does not publish a release or submit it for review.

## Exact installation package

- Root, display name and text domain: `easy-countdown`, **Easy Countdown**.
- Version: **12.8.0**; main file: `email-countdown-timer.php`.
- Installation ZIP: **656,272 bytes / 41 files**.
- SHA256: `71f80d26d84f328ae1848548b376715efc94e276bcfc13b763ce0c79c8542383`.
- Runtime and screenshot source: `c0fcfde6b700af270718e88b276fca79b74e2e35`.
- Integration, latest-head CI and automated reviews: [PR #44](https://github.com/dominiczajac/email-countdown-timer/pull/44).

Open the successful final CI run linked on the PR, expand **Artifacts**, download
**plugin-check-evidence**, extract it and install the inner **easy-countdown.zip**.
The outer evidence ZIP and source/materials ZIPs are not installation packages.
Check `distribution.json`, both version fields and all 41 manifest hashes.
The installation hashes must match `.wordpress-org/capture.json`; subsequent
documentation/catalog-only commits can retain this package identity.
The [12.7.0 handoff](REVIEW-HANDOFF-12.7.0.md) is historical.

## Changes

Saved admin and Gutenberg previews count down locally, including after 60 seconds
and after returning from a background tab. They use PNG glyphs rasterized from
the saved server font, with no font download or request per second. Imagick is
not required. Pause/Resume freezes and catches up. Reduced-motion settings do not
prevent plain number changes; no-JS keeps a PNG snapshot. The active clock uses
the saved absolute deadline and requires a correctly set device clock.

The Gutenberg canvas omits the static-preview explanation and refresh buttons.
Refresh campaigns, Refresh preview and Pause/Resume preview remain in its sidebar.
The dashboard clock/title stay together on the left. The inert Saved configuration
badge is removed, while the unsaved-form navigation guard remains.

**Embed Codes > External website (iframe)** provides copyable HTML for another
website. Its read-only `ecd_action=frame` route accepts existing canonical IDs
and GET/HEAD only. It uses an opaque `allow-scripts` sandbox, nonce CSP and
no-referrer policy. It exposes neither private names nor font paths/files.
Errors are visible and send only a boolean status to the owning editor window.

Exclude `ecd_action=frame` from page/edge caches. Host-injected X-Frame-Options
or CSP may block external framing and require a narrowly scoped host rule.
The route uses one short-lived raster cache slot per timer, the existing session
lock topology and a 256 KiB ceiling (or smaller configured limit). Busy or
unsupported locks use an active system-font fallback. The cached no-JS snapshot
can be up to 60 seconds old. This is not a global rate limit or complete SQLite
compatibility; apply the deployment's infrastructure capacity policy.

Image/GIF/email output, saved settings, existing URLs, five pinned OFL faces and
the original renderer/oracle remain unchanged. The iframe is for websites;
copied email HTML remains image-only. Custom CSS, per-timer clean uninstall and
forced Outlook autoplay remain excluded. Global opt-in cleanup is unchanged.

## Evidence and owner acceptance

[Executed local checks](CI-VERIFICATION-12.8.0.md) and six genuine native WordPress
screenshots are recorded separately from hosted CI and automated static review.
The PR/delivery receipt identifies final hosted results and parsed Plugin Check
findings. A green badge alone does not replace those reports or owner acceptance.

All rows in [ACCEPTANCE-12.8.0.csv](ACCEPTANCE-12.8.0.csv) remain pending. Retest
the exact bytes on staging, including nested Gutenberg, no-JS, narrow layouts,
manual assistive technology, below 24h/expiry, cross-origin embedding and actual
host CSP/cache settings. Record real host/theme/PHP/client versions and results.
Licensed FlyingPress/WP Rocket packages and real Sendy → Gmail/Outlook/Apple Mail
acceptance remain separate dependencies. Classic Office LTSC Professional Plus
2024 controls GIF playback; the plugin does not force it.

Use the existing **easy-countdown** review thread and confirmed **ddoomm** account;
do not create a duplicate submission. Review the [unsent reply](WORDPRESS-ORG-REPLY-12.8.0.md)
after completing acceptance. No real email, production deployment, release/tag,
native Wiki, environment or WordPress.org publication is performed here.
