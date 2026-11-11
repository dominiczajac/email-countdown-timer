# CI toolchain verification for the 12.6.0 candidate

This record describes the CI maintenance in [PR #36](https://github.com/dominiczajac/email-countdown-timer/pull/36).
It does not record maintainer live acceptance or publication of a release.

## Source and action identity

PR #36 was reviewed at `473ac612bd19c2a8e0ff8c97f0f8cc9a462c40a7` and
squash-merged as `8737ab4f6f388782de624f78d590e4e07b80d725`.
Both, and the PR's synthetic Actions checkout
`7aad8c0e2aa77d0c37bda259b1e92b8256d71e6d`, have tree
`f965e85ad3bc74d6ee99427e4dc4aae1ce5c6937`.

All eight setup-php references use 2.40.0 commit
`eb7c497e18156a6bbabfef1d3a82760b9eda3962`.
The upstream annotated tag object
`4424c9aab975b57bd706ee5a39c6b4334d6e4f60` resolves to that commit;
GitHub reports valid signatures for both the tag and the commit.
All nine upload-artifact references use 7.0.2 commit
`cf430e030ddbb5b0abf93d22962f4752f3646cd9`, resolved from its upstream
release reference and reported as a valid signed commit.
Every upload has explicit `archive: true`, including the single JSON file.
See [the native dependency and archive contract](CI-NATIVE-DEPENDENCIES.md).

## Observed PR validation

The following workflows all ran the final reviewed head or its identical-tree
PR checkout. No failed, skipped or cancelled cell is counted as passing.

| Layer | Successful jobs | Run |
| --- | ---: | --- |
| Required CI, including real WordPress, native Gutenberg and delivery | 26 | [43093192578](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43093192578) |
| Multisite baseline / WP Super Cache | 16 | [43093192280](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43093192280) |
| Isolated FPM plugin profiles | 12 | [43093192403](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43093192403) |
| Native OpenLiteSpeed / LSAPI profiles | 4 | [43093192442](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43093192442) |
| CodeQL: Actions, JavaScript/TypeScript and Python | 3 | [43093185310](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43093185310) |

CodeQL's language coverage is separate from the PHP regression, coding-standard
and Plugin Check layers. These results do not establish the absence of defects.

All **46** actual evidence ZIPs were downloaded: 14 core CI, 16 multisite,
12 FPM and four LSAPI. Their downloaded SHA256 digests and byte sizes match the
GitHub API metadata. Internal paths, reports and source manifests were checked;
native/report acceptance was replayed against the retained files. The single
font HTTP report remains `font-http.json` inside a named ZIP artifact.
All 58 test-job logs show 46 explicit ZIP inputs and 46 successful uploads,
with matching artifact names, IDs and sizes and no unexpected-input warnings.
The v7 success message names the artifact and its final size/ID; readers must
not depend on the previous v4 success sentence.

Multisite evidence contains 96 complete states, 288 site probes, 96 real form
saves, 1,632 browser checks, 432 measured API cache hits and 144 browser hits.
Single-site FPM/LSAPI evidence contains 96 states and 2,634 recorded checks.
Cache coverage remains limited to the named versions, settings and controlled
purges; it is not certification of arbitrary combinations or automatic edit
invalidation. See [the profile scope](ISOLATED-CACHE-PROFILES.md) and
[the multisite scope](MULTISITE-SUPER-CACHE.md).

[Codex](https://github.com/dominiczajac/email-countdown-timer/pull/36#issuecomment-6806077045)
and [Claude](https://github.com/dominiczajac/email-countdown-timer/pull/36#issuecomment-6806129101)
completed static reviews at that exact head with no P0-P2 findings. The
automatic Codex pass after marking ready also completed cleanly. These are
automated reviews, not independent human or maintainer acceptance.

## Integrated-main recovery

The first main attempts did not provide passing evidence:

- [CI 43095212929](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43095212929)
  reported `queued` with zero jobs for more than 50 minutes. A later cancellation
  request returned HTTP 409, saying the run had not been queued yet. Preserve
  this inconsistent infrastructure state separately from executed tests.
- [CodeQL 43095213204](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43095213204)
  ended with `startup_failure`, zero jobs and GitHub's unexpected-error
  annotation (support request `90e5e3c6-de0d-11f1-8b0c-78515c97827d`).
  Its retry endpoint returned "This workflow run cannot be retried"; a supported
  check-suite rerequest returned "Resource not accessible by integration".
- Authenticated API reads briefly returned HTTP 401. Fresh reads subsequently
  worked. No credential value or security setting was changed during recovery.

GitHub later acknowledged an [incident affecting Actions and other products](https://stspg.io/jdjlq5zjyl48).
Its recovery update says the Actions backlog was being processed. That supports
waiting for service recovery; it does not by itself establish the exact cause
of every earlier authentication/startup error.

The explicit [recovery CI 43100931028](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43100931028)
ran the unchanged integrated commit `8737ab4f6f388782de624f78d590e4e07b80d725`
and passed all **26/26 jobs**, including `required-checks`. All 14 core artifacts
were downloaded and parsed again, their API digests/sizes matched, and the eight
Gutenberg native/log gates passed. Plugin Check artifact **13892216448** contains
the same installation ZIP and zero findings. These results and the 46 PR
artifacts are indexed in the [60-row checksum receipt](CI-ARTIFACTS-12.6.0.csv).
The receipt records author verification and API identity, not independent
reproduction. Raw artifacts have the workflow's 14-day retention; retaining a
hash does not preserve the expired archive or turn upload success into test
acceptance. The original main CodeQL startup still did not execute an analysis.

A later successful run is evidence for its own commit and execution. It does
not relabel the original startup failures or an unexecuted queued run as passing.
Tests, timeouts, native/JIT bounds and report gates were unchanged during recovery.

## Installation and owner acceptance

All 32 shipped files, original font/license bytes, catalog PNGs and the frozen
renderer oracle match the prepared PR #35 source
`92dda90a4cf8c7269d674988d1b768a498b164e0`.
The named installation ZIP remains **425,814 bytes**, SHA256
`4fa70056f95965ab880ad18329c9c1326dd18d9ad928a9c0670e8744b5e1cd75`.
Actual Plugin Check findings are **zero errors and zero warnings**, with empty
stderr and exit zero. The source/install manifests and catalog assets match.
Development verification documents are outside the installation manifest.

The [Gate A handoff](REVIEW-HANDOFF-12.6.0.md) and
[owner-pending worksheet](ACCEPTANCE-12.6.0.csv) remain applicable.
Sendy-to-Gmail/Outlook/Apple Mail, real host/settings, accessibility and
legitimately supplied paid-plugin checks remain separate owner/live work.
No real message, release, tag, deployment, WordPress.org submission, native Wiki
or environment publication is recorded here.
