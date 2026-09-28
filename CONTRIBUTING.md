# Contributing

Work on a separate branch and describe the problem, scope, and compatibility impact. Add a regression test for each fix, run `php tests/run.php` and `node --check assets/countdown.js`, and then check all CI jobs for the current commit.

Do not change the `easy_countdown_timers` option, `ecd_timer` shortcode, image URLs, saved labels, time-zone default, image layout, or frame count/timing without agreement. Keep the v12.1 test renderer as the reference; do not adapt it to the new implementation. Document changes to invalid-input behavior and safety limits in the changelog.

The interface and new-timer label defaults are English as of 12.1.2. Keep them English and preserve existing saved labels, including custom and empty values. Test both new and existing timers when changing UI text.

Validate input before use, escape output, and preserve capability and nonce checks. Do not add secrets, production data, font files, or new external services without reviewing permissions and licenses. Pin CI actions to full commit SHAs and keep token permissions minimal.

The README is the administrator's starting point, `docs/wiki/` contains the guides, and the audit report records test evidence and limitations. Update the documentation alongside code changes. Isolated tests do not replace staging with a real WordPress installation. See `SECURITY.md` for vulnerability reporting.

## Pull requests and review

Open a real pull request against `main` before integrating changes. Start as a draft while work is incomplete. Do not fast-forward or push changes directly to `main`, and do not rewrite history to simulate a review of changes already on `main`.

Keep each PR focused. Separate repository housekeeping, UI changes, renderer changes, and new preview endpoints unless there is a documented dependency. Use the PR template to record risks, verification, and skipped tests. Keep all public project documentation in English.

CI runs on pull requests, pushes to `main`, and manual dispatch. The `required-checks` job succeeds only when the complete existing PHP test matrix succeeds. A workflow change must not replace real tests with a successful no-op. Check the latest PR revision, not an earlier successful run.

For UI changes, test actual WordPress in addition to the isolated suite: labels, keyboard navigation, focus, narrow layouts, saved values, validation errors, copying, and destructive actions. Include screenshots with synthetic data. Do not silently turn a restyling change into a new preview service or a renderer rewrite.

CODEOWNERS routes reviews to the maintainer; it does not provide independent approval or enable branch protection. A PR authored under the maintainer's own account cannot be approved by that account. Document an unavailable independent review honestly. Maintainer instructions and settings still requiring explicit configuration are in [Repository maintenance](docs/REPOSITORY-MAINTENANCE.md).
