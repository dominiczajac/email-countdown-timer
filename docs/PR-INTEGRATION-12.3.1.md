# PR integration — 12.3.1

Date: 2026-09-28. The maintainer authorized pushing and merging the latest PRs, confirmed FlyingPress without a CDN, and deferred Google Fonts. This is repository integration, not a live deployment or optimizer configuration change.

## Baselines and resolution

PR #5 head `f7b1639b2c78d472402ad42f9eaae8ccbc0a1100` was merged through GitHub as `c41ee71b2bc8e7d4f54cf7607d3edbf119e364db` after all 16 current-head CI jobs, CodeQL and the absence of unresolved review threads were checked. Its downloaded source tree reproduced `0af274564f00efd6f2b6af053e727cdad50ca8cb` exactly.

PR #6 head `efc228800444ab4efcd0f72c4c2f183d01c00610` overlapped rather than forming a sequential update. Its original body/12.3.0 test package is a historical pre-integration snapshot, not the final combined release.

The reconciliation preserves the complete PR #5 baseline, including render-lock implementation, cold/warm cache logic, no-transform headers, HTTP harness and retained raw evidence. It adds PR #6's image-only FlyingPress filter, strict tab-refresh source checks, HTML-only alt exclusion before binary validation, further tests and stable documentation paths. Duplicate implementations are not layered on each other.

The canonical alt contract is PR #5's: absent legacy data uses contextual fallback; explicit empty text remains empty; maximum 1000 bytes. This differs from PR #6's earlier empty-means-automatic proposal. No stored-data migration is performed. Duplicate branch tests are aligned to this explicit decision, not discarded; the frozen legacy renderer and all PR #5 assertions remain unchanged. Native image validation also ignores corrupt alt metadata because alt is never a renderer input.

Current UI/shortcode/version metadata is 12.3.1, avoiding two different packages claiming to be the same 12.3.0 build. English documentation links resolve to canonical optimizer/privacy/font pages. Historical verification reports keep their original revisions/results. Automatic Google Fonts importing remains deferred and no network/API-key behavior is added.

## Verification boundary

The reconciled PR requires fresh hosted tests, including both HTTP cache backends, browser saves/alt checks, native image regressions and Plugin Check. Earlier green results are not evidence for a new head. Current-head run IDs and final outcomes are recorded in the PR conversation after execution. No independent human approval, exhaustive security/WCAG/GDPR compliance or paid-optimizer integration test is claimed.

Merges and branch cleanup must preserve history: no force-push, no protection bypass, no deleting an unmerged branch or moving a published tag. Native GitHub Wiki publishing remains separate from docs/wiki sources.
