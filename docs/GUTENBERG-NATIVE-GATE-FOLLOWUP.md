# Gutenberg native acceptance gate follow-up

## Scope and recovered changes

The original follow-up patch was prepared against `23c0493`. Before publication,
PR #17 advanced to `960a4a4aa5a7f28cf5f1dedda7ec670f3c866954` (tree
`68df0c5b1894d0c74ed78657f642612299f36a67`). That head already replaces the
multi-file grep, creates the PHP error log, adds seven gate regressions, and
captures cold plugin-off/on diagnostics. This change extends that implementation;
it does not overwrite or claim those existing changes.

All installation files and the canonical runtime ZIP remain byte-identical.
No renderer, campaign, block interface, permission, library, version number,
production endpoint, font PR #16 or diagnostic PR #13 is changed.

## Why acceptance needs another check

Historical run `37012443431`, current-core Twenty Twenty-Four, reported a
successful browser job despite a SIGSEGV in fpm.log. The old `if grep` returned 2
because its second log was missing, so it skipped its failure branch. That
specific defect is already corrected on the recovered head. The remaining gap is
that a scan before worker shutdown cannot capture a later native failure, and
cleanup must never replace the original failed exit status.

The updated gate retains the existing log-only `inspect()` and CLI interfaces,
including the requirement for both fpm.log and the pre-created php-errors.log.
It additionally bounds reads, rejects invalid/nonregular inputs, and can require
a complete, nonempty browser report with matching count and no page errors.
The FPM log must not be empty. Optional nginx-error.log is read independently.
Reports contain classifications and line numbers, never original log messages,
cookies, environment variables, response bodies or raw process dumps.

The EXIT handler checks combined browser/log acceptance before the existing
failure-only controls and again after processes stop. Nonzero original exit
codes are preserved; a gate may only promote success to failure. The current
cold-control arguments and raw-core cleanup are retained. The existing eight
JIT/core/theme cells, OPcache settings, timeouts, PHP/native matrices and required
aggregate remain unchanged. No failed profile is made optional or retried until
green.

## Verification

- `python3 tests/gutenberg/test-log-gate.py`: seven existing regressions, unchanged.
- `python3 tests/test-native-log-gate.py`: 22 additional local regressions,
  including the historical grep failure, malformed evidence, missing/empty logs,
  bounded reads, original failure propagation and late shutdown failures.
- The new regressions run in the existing CI unit matrix before runtime tests.
- Native CI results must still be read for the exact published head. Unit tests
  use synthetic log files and stubbed process/kernel operations; they do not
  reproduce an upstream engine vulnerability or constitute WordPress acceptance.

## Existing diagnostic evidence (not a new fix)

Run `37016385320` at the recovered head has an isolated classic-theme control
artifact `11229753715`, SHA256
`46700e07872fcb09c72117c94eb05dfb86b927953df0666df5bedf6bc2eb9fd3`.
Its archive integrity, failure-control.json and bounded native stack were read.
A fresh FPM pool with no active ordinary plugins records HTTP 502 and SIGSEGV;
synthetic headers confirm PHP 8.4.26, OPcache on, JIT 1235 active and Easy
Countdown not loaded. The plugin-on control also fails (timeout), not an
identical crash. The plugin-off stack points into opcache.so without function
symbols. This shows Easy Countdown is not necessary for that observed native
crash, not a complete root-cause attribution or a compatibility guarantee.

Earlier JIT-disabled cells passed with OPcache enabled. Keep those observations
separate from the failing JIT-enabled cells. Do not change users' PHP settings,
claim an engine fix, or clear release blockers from a clean Plugin Check report
alone. Keep this PR unmerged until its acceptance status is explicitly resolved.

## Pending-handoff reconciliation — 5 October 2026

The maintainer asked to publish the newest prepared changes. Remote PR #17 was
already at `63f97ca1af7932f1d1ec036bb89bd042fce4ccd7`, with the stronger
pre-control/post-shutdown scanner and 7 existing + 22 additional tests. The
separate three-file handoff prepared against `960a4a4` must not replace that
implementation. Its source patch SHA256 is
`2c717038fab62e5e8602c64b7cd72b3c2c0e0cc0afd051c2ab83cccc403969d1`.

Only eight still-missing EXIT-handler regression scenarios are carried forward:
late PHP timeout; a required log disappearing during shutdown; exact cold-control
arguments; failed diagnostics followed by cleanup; preservation of an original
failure when the checker fails; checker failure on an otherwise-successful run;
final-report write failure; and preservation of an original failure together
with a late native crash. Existing tests and the newer scanner remain intact.
The harmless process doubles are extended; the real Python scanner and actual
shell EXIT-handler text are still exercised. This does not reproduce a native
crash or send requests to any WordPress site.

The updated suite has 30 tests, plus the unchanged seven in
`tests/gutenberg/test-log-gate.py`. Both already run in CI; workflow files,
`run.sh`, native controls, runtime files and release metadata are unchanged.
Local validation also passed all 12 packaging tests, 25 PHP block/147 controller
assertions and 16 JS component assertions. PHP/native-image tests are explicitly
skipped locally because GD/Imagick are unavailable. The canonical 24-file ZIP is
byte-identical to the recovered PCP-built archive, SHA256
`b7ed9ca687247cb9f78bc283105ae46935abd5b10e62711b2661237aca700580`.

The new head must receive its own hosted result. Previous run `37019656984`
belongs to `63f97ca`, not this reconciliation; its failed JIT profile is not fixed
by adding tests. Keep the PR draft and unmerged while required checks fail.
