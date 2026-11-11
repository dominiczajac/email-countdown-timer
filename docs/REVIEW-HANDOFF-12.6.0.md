# Easy Countdown 12.6.0: Gate A acceptance handoff

This is the current development candidate. The [12.5.2 handoff](REVIEW-HANDOFF-12.5.2.md)
is historical and must not supply the package, screenshots or font exclusions
for this candidate. Preparing these materials does not publish a release or
complete maintainer acceptance.

## Identity and included behavior

- Plugin and installation root: **Easy Countdown**, `easy-countdown`.
- Main file: `email-countdown-timer.php`; version and stable tag: **12.6.0**.
- Confirmed contributor: **ddoomm** (Simple Fast Secure).
- Continue the existing submission from 3 November 2025 and its review thread;
  the maintainer supplied the 8 April 2026 notice inviting a revised ZIP.
- Included: Quick Create/presets, dashboard access, new-campaign Lato/fallback,
  campaign names/stable IDs, time zones, duplicate/archive, stored label presets,
  native block selection/refresh, separate website/email embeds, opt-in website
  live text and on-demand diagnostics. Existing image URLs remain compatible.
- The allowlisted Lato Regular font and its original SIL OFL 1.1 notice are
  included. Plugin code remains GPL-3.0-only. Other fonts and development assets
  must not enter the installation ZIP.

## One identified installation artifact

Use the final reviewed revision and its latest successful CI. In the
`plugin-check-evidence` artifact, retain `easy-countdown.zip`, `distribution.json`,
`plugin-check.json`, stderr, exit code, tested source and versions/hashes.
Prefer that exact tested ZIP for maintainer acceptance and any later submission.

```sh
git rev-parse HEAD
bash scripts/build-zip.sh --output dist/easy-countdown.zip --report dist/distribution.json
python3 tests/test-build.py
```

Record the source commit/tree, artifact run, package version, ZIP SHA256/bytes,
all 32 shipped file hashes and font/license hashes in the delivery receipt.
Compare a second build with the same Python/zlib toolchain, and verify a clean
extraction with `scripts/verify-distribution.py`. The exact allowlist is
`scripts/distribution-files.txt`; repository source ZIPs and presentation bundles
are separate artifacts. See [packaging](DISTRIBUTION-BUILD.md).

Read Plugin Check's actual findings with `scripts/check-pcp-report.py`, not only
its exit status. All 26 current-head CI jobs must pass, including native image,
font HTTP, real WordPress 6.4/current, multisite/Redis, browser, delivery and
Gutenberg/JIT layers. Read the final Codex and Claude reviews and resolve valid
P0–P2 before an authorized merge. Record integrated-main CI afterward. A readme
change changes the install ZIP and requires a new artifact/hash; a screenshot
does not change the shipped runtime manifest.

## Genuine catalog screenshots

The current catalog assets belong in `.wordpress-org/`, outside runtime
`assets/` and outside the install ZIP. Their numbers match `readme.txt`.
Use an owned disposable WordPress installation with synthetic campaigns and
the exact candidate ZIP. Capture real browser views without editing UI pixels.
Retain screenshot SHA256/dimensions, actual WordPress/PHP/SAPI/browser versions,
capture source and the installed distribution manifest. If capture and final
source commits differ, compare every shipped file with the final manifest.

1. Quick Create on the WordPress dashboard.
2. Saved campaign deadline, time zone and included typography.
3. Separate website/email embed guidance and copy fields.
4. Existing campaign selection and opt-in live text in the native block.
5. On-demand local diagnostics and redacted report.
6. Suggested text in the WordPress Privacy Policy Guide.

PNG assets are intended for the existing directory submission's separate
catalog assets location after approval. Committing them is not SVN, Wiki,
WordPress.org or environment publication.

## Maintainer acceptance still to record

Use [the acceptance worksheet](ACCEPTANCE-12.6.0.csv). Record the tested ZIP
SHA256, date, actual environment/client versions and PASS/FAIL with notes.
An empty or pending row is not a pass. Use synthetic content and staging for
destructive checks.

- Back up data/fonts. Update without uninstalling; follow the legacy directory
  transition with only one active plugin copy. Preserve saved IDs, labels,
  time zones, fonts and already sent URLs.
- Create/edit a disposable timer; test presets, keyboard/focus, 320/375px
  layouts, duplicate/archive/restore and custom/empty labels. Confirm existing
  campaigns are unchanged. Perform manual assistive-technology checks.
- Check anonymous PNG/GIF requests before/after a short deadline, zero and
  selected end-image modes, frame timing and image blocking. Repeat the same
  URL; inspect bytes, MIME and no-store/no-transform.
- On disposable media, warm an end-image cache, add/remove an attachment or
  parent password and check restricted-image fallback/recovery.
- Test the actual host/theme/optimizer settings one cache system at a time.
  Purge affected HTML after campaign changes. Inspect a real Cloudflare zone
  only if configured; an HTML challenge is not a usable email image.
- Recopy Email HTML and test the actual sender and recipient clients, retaining
  a visible deadline. Record prefetch/proxy caching, animation and blocked-image
  behavior. Local Thunderbird fixtures do not replace real delivery.
- Inspect PHP/browser errors. Verify deactivate/reactivate retention and
  opt-in uninstall isolation on staging, preserving Media Library files.

Licensed FlyingPress/WP Rocket packages have not been supplied. Named free
cache/builder/commerce/theme/multisite profiles are documented in the
[roadmap](https://github.com/dominiczajac/email-countdown-timer/issues/20),
[FPM profiles](ISOLATED-CACHE-PROFILES.md), [builder/commerce](ISOLATED-BUILDER-COMMERCE.md),
[LSAPI profiles](ISOLATED-LITESPEED.md) and [multisite cache scope](MULTISITE-SUPER-CACHE.md).
Their exact sources/settings and negative outcomes remain qualified. Do not
extend those results to arbitrary combinations or automatic cache invalidation.

## Review-team reply and publication boundary

Prepare an English reply for the existing thread with the identified ZIP,
version/hash, changes, exact test evidence and remaining relevant limitations.
Attach the installation ZIP only. The presentation bundle, source snapshot,
screenshots and acceptance worksheet are not installable plugins.

Keep the reply unsent and the package unreleased until the maintainer has
recorded live acceptance and explicitly approved sending/publication. Do not
infer WordPress.org approval, account/2FA verification or universal optimizer,
email-client, accessibility, security or privacy certification from CI.
