# Isolated cache profiles for the integrated candidate

This continues roadmap #20 on the current integrated source. Historical PR #13
remains a separate, draft record of Easy Countdown 12.5.0, combined plugin
profiles and negative PHP-FPM/control results. Its failures are not relabelled or
replaced by this experiment.

The first tranche has four independent profiles: no external plugin, WP Super
Cache 3.1.3, W3 Total Cache 2.10.6, and Autoptimize 3.1.16. Exactly one third-party
plugin is active in a vendor profile; bundled inactive plugins are recorded. Hold WordPress 7.1.2, PHP 8.4,
MySQL 8.4 and Twenty Twenty-Five constant. Run OPcache with JIT 1235 and a
separately labelled JIT-disabled diagnostic cell; neither overrides a failure.
The existing required 26-job CI and its native JIT matrix remain unchanged.

## Observed integrated-candidate results — 2026-11-10

At source head `7ae3ce138c3c4977e2c8654ca6cc596628fee853`,
[run 42903096446](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42903096446)
passed **8/8 jobs**, with **48 complete states and 1,230 recorded checks**.
Both the behavior/report gate and the separate post-shutdown native-log gate
executed in every cell. API step results and all six state annotations per cell
were read back; the full artifact download for that run is separately subject
to the cloud network policy.

| Profile | JIT 1235 | JIT disabled diagnostic | Recorded checks per cell |
| --- | --- | --- | --- |
| Baseline | Pass | Pass | 147 |
| WP Super Cache 3.1.3 | Pass | Pass | 153 |
| W3 Total Cache 2.10.6 | Pass | Pass | 153 |
| Autoptimize 3.1.16 | Pass | Pass | 162 |

Observed WordPress 7.1.2, PHP 8.4.27, MySQL 8.4.13 and Twenty Twenty-Five 1.5.
WP Super Cache used Simple/PHP mode (`wp_cache_mod_rewrite=0`), enabled cache
and supercache, rebuild=1, slash-check=1 and TTL=1800s. W3 used `pgcache.engine=file`
with page cache, browser cache and lazyload enabled. Autoptimize's HTML/JS/CSS
and JS/CSS aggregation options were on; the enforced optimizer proof is the
served live-JavaScript bundle and actual browser behavior, not all CSS/lazyload
variants. Subsequent reports also expose the tested checkout SHA, ZIP hash and
actual nginx/FPM package versions in their bounded CI result annotation.
`source_commit` is the actual checkout SHA: on pull requests this is normally
GitHub's synthetic merge commit, distinct from the PR head named in this table.

No distributed runtime file changed from integrated main `a1ae837`; the
32-file development ZIP has SHA256
`7182f910731443e56a2c5d9ebab84838f7a2f966a5be865af0d8113e372d8c55`.
It is development evidence, not the Gate A canonical release artifact.
The historical PR #13 used different PHP/MySQL patch versions and workloads;
these results do not diagnose or erase its native failures.

## Method and retained negative evidence

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
fails closed. Frontend image/script cancellations fail; only recorded core/admin
requests aborted while the harness navigates or closes admin pages can be
non-failing. Countdown and optimized assets remain protected.
The classifier also inspects bounded URL-encoded POST data for core AJAX;
campaign-refresh actions cannot be excused simply because their action is in
the body rather than the query. Bodies and nonces are never retained. Unknown
body formats, oversized forms and saves fail closed instead of being excused.

At `0bc7777`, W3 Total Cache and Autoptimize passed both JIT cells; baseline
passed JIT-disabled (5/8 successful jobs). The JIT-enabled baseline recorded an
aborted core emoji script during dashboard navigation; the next harness
revision classifies only core/admin aborts during explicit navigation, with
negative tests for protected assets and real request failures. WP Super Cache
still failed the page-generation check
in all off/on controls, despite an identified installed drop-in and enabled
flags. This is not accepted as a compatibility pass. Preserve
[run 42900124590](https://github.com/dominiczajac/email-countdown-timer/actions/runs/42900124590).
The next revision requests HTML explicitly, records fixed early-cache/response
diagnostics and adds a separate final completeness check. No page-hit
assertion, native gate or timeout is relaxed.

The WP Super Cache investigation found a skipped setup step: upstream
[`inc/admin-ui.php:730`](https://github.com/Automattic/wp-super-cache/blob/a4f8616422b54ccce9db07c0b639b27ed04759bb/inc/admin-ui.php#L730)
writes `wp_cache_slash_check` from the site's permalink structure. The sample
configuration lacks it; cache serving rejects trailing-slash URLs while it is
unset. The fixture now saves the same setting using the vendor's configuration
function. This diagnosis is checked again with actual off/on page hits; no
vendor or candidate source is patched. Fixed eligibility fields and synthetic
anonymous HTML are retained on a miss, without vendor raw cookie/debug logging.

The final gate re-validates the harness's own report; it is not a second browser
observer or independent audit. It checks the six counterbalanced states, actual
plugin versions/state, recorded native PHP/JIT/SAPI and completed behavioral
checks. Native logs are inspected separately after the servers stop. Cache
artifacts are limited to the engine's page-cache directories, not object/DB
caches. Apt provides the disposable native server; observed patch versions are
recorded rather than assuming the runner has an immutable OS image.

These bounded profiles do not certify every option or environment. FlyingPress
and WP Rocket need legitimately supplied packages; LiteSpeed server-cache needs
a separately verified LiteSpeed server. Elementor, WooCommerce, additional
engines/themes and multisite remain subsequent independent profiles. No release,
production test, ruleset change or native Wiki publication is included.

The separate [builder/commerce tranche](ISOLATED-BUILDER-COMMERCE.md) extends
the same harness with Elementor and WooCommerce alone. Its evidence does not
replace the results or qualifications above.
