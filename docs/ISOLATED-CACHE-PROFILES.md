# Isolated cache profiles for the integrated candidate

This continues roadmap #20 on the current integrated source. Historical PR #13
remains a separate, draft record of Easy Countdown 12.5.0, combined plugin
profiles and negative PHP-FPM/control results. Its failures are not relabelled or
replaced by this experiment.

The first tranche has four independent profiles: no external plugin, WP Super
Cache 3.1.3, W3 Total Cache 2.10.6, and Autoptimize 3.1.16. Exactly one third-party
plugin is installed/active in a vendor profile. Hold WordPress 7.1.2, PHP 8.4,
MySQL 8.4 and Twenty Twenty-Five constant. Run OPcache with JIT 1235 and a
separately labelled JIT-disabled diagnostic cell; neither overrides a failure.
The existing required 26-job CI and its native JIT matrix remain unchanged.

Each cell owns a new loopback-only WordPress/MySQL fixture, verifies package
checksums, builds the installation ZIP from the tested commit and records actual
versions, runtime SAPI/JIT, plugin state and effective configuration. Runtime
WordPress HTTP API calls and mail delivery are blocked after setup; no real
customers, credentials, payment or sender data is used.

Use three counterbalanced Easy Countdown off/on pairs. Cache profiles must prove
ordinary page hits using an origin-generation marker, with independent URLs and
explicit cache purging between states. Optimizer profiles must prove a generated
asset is served. Image requests must still reach the renderer and decode, with
correct MIME/no-store policy; active states exercise saved timers, the native
block/live text and pause/resume in a real browser. Keep timeouts and native-log
gates strict. Retain all negative outcomes and report the exact failing phase;
no retry is used to replace a failed result.

The initial harness run at `245b79b` failed all eight cells before cache/browser
measurement. The runner incorrectly used Playwright's APIRequestContext as a
context manager, and the inventory did not normalize WordPress's sparse active
plugin array after deactivation. These are test-harness failures, not evidence
for or against plugin compatibility. Their original CI/artifacts remain at
[run 42896894744](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42896894744).

The next harness run at `b84cf36` also failed. Its 400px assertion misunderstood
the renderer's minimum-width contract: the valid four-digit-day image was 410px.
The fixture now requests 600px, without changing the renderer. The Media Library
assertion selected multiple headings after core mounted its grid; it now checks
the actual grid. WP Super Cache did not produce page hits even with Easy
Countdown off. The lab now records its drop-in rather than treating enabled
flags as proof. Preserve
[run 42897700295](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42897700295).

Run `c413364` failed too: the test-only network classifier accepted only legacy
MD5 Gravatar image paths, while WordPress 7.1 uses SHA-256 paths. Both recognized
core image forms remain blocked and recorded; other attempts still fail. The
attempted WP Super Cache CLI enable command is not provided by that upstream
version. Initialization now calls its existing activation checks from
[`inc/lifecycle.php` at 3.1.3](https://github.com/Automattic/wp-super-cache/blob/a4f8616422b54ccce9db07c0b639b27ed04759bb/inc/lifecycle.php).
Preserve [run 42898803641](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42898803641).

The Autoptimize assertion proves the live JavaScript is bundled and executes;
it does not certify CSS optimization. The synthetic control-image attributes
are observational lazy-load evidence. Cache-file inspection is limited to this
small fixture and 2 MiB per file; an oversized or unreadable required artifact
fails closed. Frontend image/script cancellations fail; only recorded admin
XHR/fetch cancellations during navigation can be non-failing.

These bounded profiles do not certify every option or environment. FlyingPress
and WP Rocket need legitimately supplied packages; LiteSpeed server-cache needs
a separately verified LiteSpeed server. Elementor, WooCommerce, additional
engines/themes and multisite remain subsequent independent profiles. No release,
production test, ruleset change or native Wiki publication is included.
