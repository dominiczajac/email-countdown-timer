## Summary

Describe the problem and the smallest useful change. Link related issues.

## Scope and compatibility

- What changes for users?
- What does not change? Consider saved labels, timer IDs, image URLs, time zones, GIF timing, and layout.
- Does this add dependencies, endpoints, migrations, or resource costs?

## Verification

Record commands, results, and the tested commit. State skipped or unavailable checks explicitly.
For UI changes, include before/after screenshots using synthetic data and describe keyboard, focus, narrow-screen, and error-state checks.

## Risks and rollback

Describe the remaining risks and how to revert safely. Do not claim a WordPress integration test or performance benchmark based only on stubs or code inspection.

## Merge checklist

- [ ] The diff is scoped and does not contain secrets, production data, or font files.
- [ ] Compatibility is preserved, or each intentional change is explained.
- [ ] Relevant tests and English documentation are updated; the frozen legacy renderer is unchanged.
- [ ] All CI jobs, including `required-checks`, passed for the latest PR revision.
- [ ] Review findings have been addressed; any unavailable independent review is stated.

<!-- A checked box is evidence supplied by the author, not an independent approval.
Do not include exploit details or sensitive logs in public PRs; follow SECURITY.md. -->
