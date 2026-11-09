# Gate A: native editor / JIT investigation — 9 November 2026

## Decision boundary

The reproduced PHP 8.4.26 editor failure is an upstream JIT problem, not a
reason to change the countdown renderer or remove the JIT-enabled acceptance
profiles. PHP 8.4.27 includes the relevant upstream fix. Retain the existing
eight native profiles, OPcache, JIT modes, time limits, log checks and required
aggregate. Run them on an upstream-patched PHP build before considering PR #17
ready to integrate.

This document does not approve a merge, qualify every historical crash, change
the plugin's PHP minimum, or establish WordPress.org release readiness.

## Source and hosted checkpoint

- Main: `12fa54667539c6fc96c9947702f4f83b47e03712`, including the optional Lato font.
- Investigated PR #17 head: `490867a2e28d5298e7a6194133509c84c8435903`, still draft.
- Its [CI run 37278482937](https://github.com/dominiczajac/email-countdown-timer/actions/runs/37278482937)
  failed both current-core/JIT-1235 editor profiles and `required-checks`.
  The public check list was read for this exact head. This is a different result
  from the earlier one-profile failure at `63f97ca`.
- GitHub API access was denied by the cloud network policy. Public check/status
  pages were readable, but authenticated job logs and original artifacts could
  not be inspected. Historical native stacks described in
  [the earlier follow-up](GUTENBERG-NATIVE-GATE-FOLLOWUP.md) were not independently
  re-downloaded in this session. The run is older than the workflow's 14-day
  artifact retention; artifact availability must be checked rather than assumed.

The candidate ZIP was rebuilt from the investigated tree: 24 installation
files, SHA256 `b7ed9ca687247cb9f78bc283105ae46935abd5b10e62711b2661237aca700580`.
It matches the previously recorded candidate. This is the block candidate,
not a combined Lato/block release.

## Upstream evidence

[php-src issue #23991](https://github.com/php/php-src/issues/23991) reports hangs
and segmentation faults with `opcache.jit=1235`, a 256 MiB JIT buffer and
WordPress, including PHP 8.4.26. The upstream maintainer reproduced it and
provided [PR #24001](https://github.com/php/php-src/pull/24001).
The [PHP 8.4.27 NEWS](https://github.com/php/php-src/blob/php-8.4.27/NEWS)
lists that fix under OPcache; the release is dated 22 October 2026, after the
investigated CI run.

The fix removes an invalid recursive-call-to-loop IR transformation. For the
controlled comparison, only that eight-line removal was applied to OPcache
from the same PHP 8.4.26 source and rebuilt with the same compiler settings.
The patch was derived from upstream parent
`c4efd2093177d45012412eecc8a97d6759ccbd29` and head
`bdb9a8b8cb1bb21d5ca82e3db3c4ae71d0db9550`, applied with zero fuzz. No PHP
fork or engine patch is distributed with the plugin.

Other fixes in PHP 8.4.27, including #23637, are not independently established
as causes of this plugin's recorded failures. A matching issue title alone
would not identify a historical crash.

## Controlled local experiment

The synthetic site used official WordPress 7.1.2 tag commit
`160387b7312c9407c7fe4b1d3dd2055206749a34`, Twenty Twenty-One 2.9,
Twenty Twenty-Four 1.6, MariaDB 11.4.13 and four FPM workers behind nginx.
Each plugin-off/on state received a fresh FPM master and empty OPcache/JIT
state. Login cookies stayed in memory. Each state attempted at most six
authenticated requests for an otherwise empty native post editor; a failed
request stopped that state. Plugin-off had an empty `active_plugins` option
and an HTTP observation confirming that Easy Countdown was not loaded.

OPcache stayed enabled. Both configured JIT mode and actual JIT activation
were checked over HTTP before editor requests. Limits stayed at 30 seconds
for PHP execution, 35 seconds for FPM termination and 40 seconds for nginx.
The initial pool-only JIT configuration was rejected because its HTTP
observations showed JIT disabled; it is excluded from the comparison.

The minimal PHP image deliberately omitted GD/Imagick. This isolates editor
bootstrap from image generation; it does not validate rendering. The separate
full browser check below supplies those extensions.

| PHP / OPcache | Theme | Plugin off | Plugin on |
|---|---|---|---|
| 8.4.26, JIT 1235 | Twenty Twenty-One | First request 502, FPM timeout | First request 502, FPM timeout |
| 8.4.26, JIT disabled | Twenty Twenty-One | 6/6 HTTP 200, editor bootstrap | 6/6 HTTP 200, editor bootstrap |
| 8.4.26, JIT 1235 | Twenty Twenty-Four | First request 502, FPM timeout | One 200, then 502 and SIGSEGV |
| 8.4.26, JIT disabled | Twenty Twenty-Four | 6/6 HTTP 200, editor bootstrap | 6/6 HTTP 200, editor bootstrap |

These results establish that Easy Countdown is not necessary for the reproduced
JIT-enabled editor failure. The plugin-off FPM termination and plugin-on SIGSEGV
are distinct observations; they must not be described as identical crashes.

With only the upstream #23991 OPcache fix applied to that same 8.4.26 build,
**all eight states passed all six editor requests** (48 HTTP 200 responses
with native editor bootstrap). Both themes, both JIT modes and both plugin
states had clean final native/PHP logs. The machine-readable
[evidence](evidence/gutenberg-jit-2026-11-09.json) records observations, timings,
source/image identities and the separate full browser outcomes.

The upstream regression `ext/opcache/tests/jit/gh23991.phpt` was also exercised
without WordPress, plugins, a database or network access. With the test's own
JIT settings, unpatched 8.4.26 reached the **in-container** 10-second limit
(exit 124); the otherwise identical patched build completed with exit 0 and
the expected warning/array output. The timeout supervises the PHP process,
not merely a Docker client. These are intentionally different outcomes, not
two passing tests.

Together with the fixed-build HTTP comparison, this supports high confidence
that #23991 explains the editor hang reproduced here. It does not establish
the exact stack or cause of every previous CI SIGSEGV. The eight-line patch
is an experiment to isolate causality; the deployment recommendation is an
official patched PHP release, not this local image.

## Full native editor validation on the released engine

The same 24-file installer was installed in a separate disposable WordPress
7.1.4 site on the official WordPress PHP 8.4.27 FPM image with GD, FreeType and
Imagick. The existing integration fixture and native browser assertions were
used. The browser's fixed loopback port was adapted from 8093 to 8094 to avoid
the independent control experiment; assertions and acceptance criteria were
unchanged. Local fixture environment variables were supplied explicitly.
This was local Docker execution, not a GitHub-hosted workflow run.

| Theme | JIT | Browser | Final native/PHP log gate |
|---|---|---|---|
| Twenty Twenty-One | 1235, actually active | 36/36 | PASS |
| Twenty Twenty-One | disabled, actually inactive | 36/36 | PASS |
| Twenty Twenty-Four | 1235, actually active | 36/36 | PASS |
| Twenty Twenty-Four | disabled, actually inactive | 36/36 | PASS |

Every profile completed with no uncaught browser errors and clean required
logs **after** nginx/FPM shutdown. These are 144 repeated checks of 36
scenarios, not 144 distinct features. Checks cover keyboard selection,
preview refresh, alignment, save/reopen, reference-only storage, multiple
blocks, campaign retention, actual anonymous images/alt/dimensions, narrow
layout, delayed image geometry, removed timers and absence of editor scripts
on the public page. The real WordPress integration assertions also passed
before each browser profile.

Local checks of the original candidate also passed: 7 log-gate tests, 30
EXIT-handler/native-evidence regressions, 12 packaging tests, 16 JavaScript
component assertions, and 25 PHP block-adapter assertions alongside 440
controller/rendering assertions on PHP 8.3.36 with native extensions.

## Acceptance proposal

1. Keep the historical failed head and this evidence. Do not rerun the old
   engine until a passing attempt is obtained or treat the patch experiment
   as the old CI result becoming green.
2. Request a new exact-head run of the unchanged required matrix on the
   maintained PHP patch releases. For the current-core/PHP-8.4 cells, verify
   that CLI, FPM and OPcache actually use the patched release (8.4.27 or a
   later release carrying #23991). Record the observed versions, not just
   the requested `8.4` label.
3. Require all eight native profiles, complete browser reports, clean final
   logs, and the existing unit, lifecycle/cache, Plugin Check, HTTP and
   delivery jobs. A different incomplete profile remains a blocker to
   diagnose. Do not remove a cell, disable JIT/OPcache, increase a timeout,
   or change the renderer as a workaround.
4. Keep PR #17 draft until exact-head acceptance has been reviewed. The
   maintainer still authorizes integration. A combined candidate must then
   include main's already merged Lato changes, reconcile metadata/manifest,
   and receive its own exact-package checks and live acceptance before the
   existing WordPress.org submission continues.

No application, test assertion, workflow, JIT setting, timeout or required
gate is changed by this investigation's documentation commit. The existing
release boundary remains in force.
