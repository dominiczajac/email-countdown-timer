# Contributor instructions for coding agents

Read CONTRIBUTING.md, SECURITY.md, and the relevant source files before editing. Keep repository documentation, user-facing plugin text, and new label defaults in English.

## Change workflow

- Start from the current remote main commit. Use a focused working branch and open a pull request early; use draft while the change is incomplete.
- Do not push or fast-forward main directly, fabricate retrospective PRs, rewrite published history, or bypass checks. Merge only through a real PR when the user has authorized merging.
- Keep independent changes in separate commits. UI work must not silently change the renderer, public endpoints, option schema, timing, or saved timer labels.
- Preserve the frozen tests/legacy-frame.php oracle. Add regressions rather than rewriting expected results to hide differences.
- Run relevant checks, then read the hosted CI result for the latest PR revision. Report skipped tests and remaining risks, not just a green badge.
- Do not mark your own work as independently reviewed or post an approval under the author's identity.

## Safety and publication

Keep nonce/capability checks, input validation, output escaping, font containment, and image limits intact. Do not add secrets, production data, font files, telemetry, dependencies, or new public endpoints without explicit justification and authorization.

Do not claim that repository settings were changed by committing a configuration guide. Read back any actual settings mutation. Never confuse docs/wiki with publication to the separate native Wiki.

A downloadable ZIP must come from an identified commit; inspect its contents and state whether it has been tested on real WordPress. Do not create a release or mutate tags just to make an incomplete change appear finished.
