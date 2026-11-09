# Contributing

Work on a focused branch, open a real PR early and describe the problem, scope, compatibility impact and verification. Read AGENTS.md and SECURITY.md. Never fast-forward main directly, rewrite published history, bypass protection or fabricate retrospective reviews. Merge only after authorization and passing checks for the current head.

Preserve `easy_countdown_timers`, `ecd_timer`, image URLs, saved labels, timezone, geometry and frame count/timing unless a compatibility change is explicit. The v12.1 test renderer is frozen; do not change expected results to hide regressions. Document invalid-input and resource-limit changes in the changelog.

Use the distinctive `Email_Countdown_Timer_` and `EMAIL_COUNTDOWN_TIMER_` prefixes for new internals. English user-facing strings use the `easy-countdown` translation domain. Saved labels and default campaign data must not change when the administrator's locale changes. Test new, custom and empty saved labels.

Validate input, escape at the actual output boundary and preserve capability/nonce checks. Do not add secrets, production data, fonts, telemetry, dependencies or external services without authorization and licensing review. Pin CI actions to full SHAs and retain minimal token permissions. Explain necessary code-local static-analysis annotations; do not globally suppress findings to obtain a green build.

## Verification

```sh
php tests/run.php
php tests/uninstall.php
python3 tests/test-pcp-gate.py
node --check assets/countdown.js
```

The isolated suites use WordPress/database doubles, with native rendering when available. Hosted CI separately provisions real WordPress/MySQL/Redis and runs Plugin Check with runtime checks. `required-checks` passes only when all required layers pass; cancelled, skipped or failed results must not satisfy it. A zero PCP exit code is not sufficient: parse its findings. Never replace actual tests with a successful no-op.

UI changes also require real browser checks: labels, keyboard/focus, narrow layouts, saved values, validation, copying and destructive actions. Use synthetic screenshots. Do not silently turn a UI restyling PR into a preview endpoint or renderer rewrite.

Keep README, Wiki sources and verification records current. Historical audits stay historical. Distinguish a source ZIP, a release and WordPress.org acceptance. Native Wiki publication is separate from `docs/wiki/` commits.

CODEOWNERS routes review; it is not independent approval. Do not approve your own PR under the author's account or misrepresent automated checks as an independent human audit. Repository protection is enforced by actual settings, not documentation. [Maintainer guide](docs/REPOSITORY-MAINTENANCE.md) and [verified protection state](docs/VERIFICATION-12.1.3.md).

## Installation artifact

Use `bash scripts/build-zip.sh --output dist/easy-countdown.zip --report dist/distribution.json`. The explicit `scripts/distribution-files.txt` manifest is shared by all installation tests and release packaging. Run `python3 tests/test-build.py` and the relevant runtime checks. Only the reviewed Lato example and its original OFL notice may be bundled; do not add other font binaries or site data without authorization, licensing review and an explicit manifest/hash update. See [build instructions](docs/DISTRIBUTION-BUILD.md).
