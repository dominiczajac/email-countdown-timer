# Verification scope — 12.3.0

Baseline: `6cdbb8b6f3c3d4209c590412f3c919ef6f4a246f`, main version 12.2.0. Work is tracked in PR #6. This record describes scope and early evidence; the latest head must have its own complete CI readback before readiness or merge.

## Implementation and source review

Alternative text is optional administrator-authored plain text, bounded to 1024 bytes, escaped independently in shortcode/preview attributes and generated email HTML. Older open forms preserve an omitted field. Blank/missing text selects the old context-specific automatic description. Metadata is removed before image validation/signature construction, not added to the public URL. Saving still clears the timer's image transients as before.

The image renderer and frozen legacy oracle are unchanged. The only public-script change makes visibility refresh idempotent and resilient to malformed/unrelated sources; no polling, storage or tracking is added. Page-cache opt-outs apply only to render requests. Upstream cache rules and paid optimizer behavior are separate deployment concerns.

Source review covered nine shipped PHP files and both JavaScript files: authorization/nonces, escaping, SQL/path ownership, image limits, persistence and network/data primitives. No visitor analytics, recipient tracking, telemetry or remote font code is implemented. Synthetic visitor metadata must not affect image bytes or cached data. This review does not cover native hosting libraries, every WordPress extension, deployment logs or a complete security/WPCS/WCAG audit.

Public advisory searches on 2026-09-28 for the exact plugin name/slug with CVE, Wordfence and Patchstack terms did not surface an advisory attributable to this repository. Unrelated countdown plugins and identically named projects are not evidence about this code. Search absence is not proof of no vulnerability.

## Checks

Local PHP 8.4.23: 137 assertions in the historical suite plus alt extensions passed, with native image tests explicitly skipped because GD is unavailable locally. The unchanged uninstall suite passed 573 assertions using WordPress/database doubles. Dependency-free script tests, three isolated cache-bootstrap cases, privacy source tripwires, syntax and whitespace checks passed.

Initial hosted run `36422259699`, code head `d32bdad4836d1cc0af0e8301ac93658e9d799344`: all six PHP configurations and eight real WordPress single/multisite database/Redis configurations passed, including the real admin/browser step. Plugin Check reported one naming warning for the intentionally shared `DONOTCACHEPAGE` constant while returning exit 0; the report gate correctly failed. The warning is addressed with one documented code-local naming annotation, not by disabling Plugin Check or renaming a compatibility API. No initial all-green claim is made. Read the latest PR status and artifacts for verification of the corrected revision.

Browser checks add actual saved alt roundtrips, copied HTML, anonymous shortcode output, no-store/nosniff headers, absence of Set-Cookie on the isolated image response, invalid-input retention and a no-JS fallback. Paid FlyingPress/WP Rocket binaries are not installed; this is not their complete integration matrix. Existing 37 UI checks, renderer comparisons and lifecycle tests remain enabled.

## Performance and privacy boundaries

No admin CSS/JavaScript is added by the field. The shortcode now reads stored timer metadata; WordPress's Options API can reuse it within a request, but large serialized-option costs still exist. No production speedup or throughput is claimed. No GIF is rendered per keystroke; alt does not fragment the image cache. Concurrent misses can still duplicate expensive GIF generation: an atomic generation lock is not part of this change.

No live deployment, production load test, WordPress.org submission, tag, remote font importer or native Wiki publication is performed. No independent human review or complete absence of vulnerabilities/data processing is asserted. See [privacy](PRIVACY.md), [optimizer guidance](OPTIMIZATION-COMPATIBILITY.md) and [fonts](wiki/Fonts.md).
