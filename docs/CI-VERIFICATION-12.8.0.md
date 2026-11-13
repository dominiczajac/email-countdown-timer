# 12.8.0 candidate: executed checks and boundaries

This record covers [PR #44](https://github.com/dominiczajac/email-countdown-timer/pull/44).
It records executed local results. Final hosted checks, automated Codex/Claude
reviews and integrated-main verification are identified on that PR and in the
delivery receipt; no future run is predeclared successful.

## Package and local execution

Runtime/capture source: `ba33c952bad037d75086006991204558ddc26b28`.
Installation ZIP: **12.8.0**, **656,033 bytes**, **41 files**;
SHA256 `7f12a882741798173b13322b723ffed5e0b65955d99da0685017f1c023c1173e`.
All installed hashes were read back on the native WordPress container and match
the build manifest and `.wordpress-org/capture.json`.

Executed checks include:

- **762 PHP assertions**, **792** with alt compatibility, and **76 block adapter
  assertions**, using native GD/FreeType/Imagick where available.
- **346 frame assertions**: five fonts, custom/empty/Polish labels, expiry,
  data/escaping boundaries, all 15 PNG sprites, the valid 3999×53 bitmap canvas
  and an unsupported-lock active system-font fallback.
- JavaScript frame-clock checks in raster, system-font and initially hidden/paused
  modes: absolute deadline, 65-second progression, pause/resume, sleep, visibility,
  persisted pageshow and expiry. **38 Gutenberg component assertions**, including
  owning-window messages, spoof rejection and HTTP failure not cleared by load.
- **22 clipboard assertions**, timezone aliases, privacy audit/tripwire,
  syntax/whitespace checks and **16 packaging tests**.
- Native Apache WordPress **7.1.4**, PHP **8.4.27**, MySQL **8.4.13**:
  **123 admin**, **15 alt**, **4 privacy**, **6 maintenance**, **10 end-image**
  and **18 frame browser checks** passed against this installed package.

Native frame checks exercise a genuinely different-origin parent, opaque sandbox,
PNG decoding, reduced-motion ticking, 65 seconds without image polling, pause,
expiry/end-image, cold/warm cache headers, GET/HEAD/405/404/503, no private names
or paths and nonce/CSP/no-store/referrer headers. Six screenshots were captured
from synthetic data in the real panel, without editing UI pixels. The receipt
contains source/tree, installed files, image dimensions/hashes, browser/server
versions, actual anonymous PNG decoding and a copied diagnostics report.

## Failures retained and corrected

Earlier runs failed because old profile tests still queried `complete` and
`naturalWidth` on the new iframe. These tests now inspect the actual decoded
canvas, retaining their width requirement. Native clipboard testing also exposed
a Chromium out-of-process iframe hit-test race immediately after parent scrolling;
the real pointer test now allows the compositor to settle before clicking.
No failed run is relabeled or waived.

Review found four further defects: HTTP iframe load falsely cleared errors,
sprite padding exceeded a valid renderer canvas, nested Gutenberg status was
sent to the wrong window, and a background-loaded paused frame hid its fallback
before drawing. Explicit status/ping, fit-to-original bounds, owning-window
listeners and first-visible-draw behavior have regression coverage. The original
renderer and frozen oracle are unchanged.

Hosted CI independently installs the canonical ZIP on minimum/current WordPress,
FPM/JIT, database/Redis, multisite/theme and named isolated/LiteSpeed profiles.
Plugin Check must be parsed for both warnings and errors. Automated review is
static review, not human approval or owner acceptance.

## Remaining acceptance

[All owner worksheet rows](ACCEPTANCE-12.8.0.csv) remain pending. Previous package
feedback does not establish acceptance of these bytes. Actual host framing/cache
rules, manual accessibility, licensed optimizers and real Sendy/client delivery
remain separate. No release, submission, actual mail or production change is
implied by this candidate.
