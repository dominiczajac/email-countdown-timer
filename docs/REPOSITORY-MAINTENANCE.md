# Repository maintenance

This is an operating guide, not an applied GitHub configuration. Merging this file does not enable branch protection, private vulnerability reporting, or native Wiki publishing.

## Baseline observed on 2026-09-28

The default branch was `main` at `af22fe705cd4718dbd3683e664776b60b060ccfa` (plugin 12.1.2). GitHub reported it as unprotected. No pull requests existed, and automatic deletion of merged branches was disabled. Earlier work had been integrated directly. Those facts are a historical snapshot, not a live security-status dashboard.

Preserve that history. Open new PRs for new changes rather than manufacturing retrospective approvals. This hygiene change does not change plugin runtime code or the version, and is not a new product release.

## Recommended main ruleset

In repository Settings, use Rules / Rulesets to configure a branch ruleset targeting `main`:

| Control | Recommended setting |
|---|---|
| Enforcement | Active, after the required check has appeared in a real PR |
| Pull request before merging | Required |
| Required status check | `required-checks`, from GitHub Actions |
| Branch up to date | Required |
| Conversation resolution | Required |
| Force pushes | Blocked |
| Branch deletion | Blocked |
| Bypass list | No routine bypass |
| Required approvals | 0 while there is only one eligible maintainer; raise to 1 when a second independent reviewer with write access is available |
| Required code-owner approval | Off for a sole maintainer who also authors the PRs |

A PR record and a human approval are different things. GitHub does not allow authors to approve their own PRs. CODEOWNERS is useful for ownership and external contributions but should not create an impossible self-approval requirement. A machine-authored comment is not automatically an independent human review.

The workflow's final gate uses `always()` and explicitly requires the aggregate matrix result to equal `success`. A failed, cancelled, or skipped matrix does not pass the gate. No path filter skips the PR workflow. Select the exact check name after it has run; do not require the workflow display name `CI` as a substitute.

The guide recommends these settings; it does not assert they have been applied. Inspect the effective rules after saving them and verify them against the PR merge box. Do not use an already-green badge as proof that rules are enforced.

## Merge and cleanup

Prefer squash merging for small, focused PRs. Include the meaningful change summary and verification evidence in the PR description. Enable **Automatically delete head branches** for future merged PRs. Do not enable unattended auto-merge by default.

The earlier `docs/english-documentation`, `fix/security-performance-review`, and `fix/english-plugin-ui` branches can be considered for cleanup only after freshly verifying that each head is contained in current `main`, that nobody has added new work, and that no open PR relies on it. Do not delete a branch merely because its name looks old. No branch deletion is performed by this guide.

The first housekeeping PR can be the bootstrap for the PR process. Once it is reviewed, use GitHub's normal PR merge action; do not move `main` with the Git refs API as a substitute.

## Security and dependencies

Keep Actions permissions at `contents: read`, pin actions to full commit SHAs, and keep checkout credential persistence disabled. Pull requests from forks must not receive deployment secrets. Do not switch this test workflow to `pull_request_target` to gain permissions.

Dependabot is already configured for GitHub Actions; review its proposals rather than merging them solely because they are automated. Inspect the Security settings for secret scanning and push protection. Enable private vulnerability reporting where available, then verify the actual reporting entry point. Until verified, SECURITY.md remains conditional about that channel.

## Releases and documentation

Build an installable ZIP from an explicitly selected, reviewed commit with `git archive`, retain the license, and inspect archive contents. Do not include `.git`, CI configuration, tests, agent instructions, secrets, or font files. Run a real WordPress smoke test before claiming a release is production-ready. A green stub-based test suite does not verify a complete WordPress installation.

Record the tested source SHA in release notes, attach a checksum when publishing an actual release, and do not overwrite version tags to replace artifacts. Keep source archives distinct from validated installation packages. This housekeeping PR creates neither a new tag nor a GitHub Release.

README and `docs/wiki` are version-controlled source documentation. The native Wiki is separate; use the existing publishing procedure and verify it independently.

## Reference documentation

- [GitHub rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/available-rules-for-rulesets)
- [Protected branches and required checks](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)
- [Approving pull requests](https://docs.github.com/en/pull-requests/how-tos/review-pull-requests/approving-a-pull-request-with-required-reviews)
