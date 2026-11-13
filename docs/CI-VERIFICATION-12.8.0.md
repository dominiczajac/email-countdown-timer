# 12.8.0 candidate: executed checks and boundaries

This record covers [PR #44](https://github.com/dominiczajac/email-countdown-timer/pull/44).
It records executed local results. Final hosted checks, automated Codex/Claude
reviews and integrated-main verification are identified on that PR and in the
delivery receipt; no future run is predeclared successful.

## Package and local execution

Runtime/capture source: `c0fcfde6b700af270718e88b276fca79b74e2e35`.
Installation ZIP: **12.8.0**, **656,272 bytes**, **41 files**;
SHA256 `71f80d26d84f328ae1848548b376715efc94e276bcfc13b763ce0c79c8542383`.
All installed hashes were read back on the native WordPress container and match
the build manifest and `.wordpress-org/capture.json`.

Executed checks include:

- **762 PHP assertions**, **792** with alt compatibility, and **76 block adapter
  assertions**, using native GD/FreeType/Imagick where available.
- **355 frame assertions**: five fonts, custom/empty/Polish labels, expiry,
  data/escaping boundaries, all 15 PNG sprites, the valid 3999×53 bitmap canvas
  and unsupported/busy-lock and oversize-entry active system-font fallbacks.
  Cached-miss regressions exercise fresh post-lock reads for both cache backends
  without regenerating or overwriting an already completed payload.
- JavaScript frame-clock checks in raster, system-font and initially hidden/paused
  modes, plus failed sprite loading: absolute deadline, 65-second progression, pause/resume, sleep, visibility,
  persisted pageshow and expiry. **39 Gutenberg component assertions**, including
  owning-window messages, spoof rejection and HTTP failure not cleared by load.
- **22 clipboard assertions**, timezone aliases, privacy audit/tripwire,
  syntax/whitespace checks and **16 packaging tests**.
- Native Apache WordPress **7.1.4**, PHP **8.4.27**, MySQL **8.4.13**:
  **123 admin**, **15 alt**, **4 privacy**, **6 maintenance**, **10 end-image**
  and **19 frame browser checks** passed against this installed package.

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

The later cache-freshness review finding is fixed with a forced backend/Options
API re-read under the lock, and both backends have cached-miss regression checks.
Deleted timers no longer start a false preview timeout; background frames signal
loaded assets before their first visible paint. The fallback PNG appears only
once in the HTML, and CSP enforces the sandbox even for direct navigation.

The bounded 250-timer catalog was measured on the owned native WordPress CLI
with all five fonts and varied sizes: 211 / 230 / 185 ms across three runs.
This is a metadata-only cost on explicit bootstrap/refresh, with no PNG generation
or polling; it is not a production-capacity guarantee.

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
