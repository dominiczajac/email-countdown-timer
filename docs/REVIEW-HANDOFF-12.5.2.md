# Easy Countdown 12.5.2: existing-review handoff

## Identity and release scope

- Plugin: Easy Countdown; WordPress.org slug and installation directory: `easy-countdown`.
- Confirmed WordPress.org contributor: `ddoomm` (display name: Simple Fast Secure).
- Continue the existing submission from 3 November 2025. The maintainer supplied the rejection notice dated 8 April 2026, inviting an updated ZIP in the original thread. Do not create another submission unless the Plugin Review Team asks.
- Integrate PR #14 presentation/delivery changes with the password-publication fix already merged by PR #15. Use version 12.5.2 to distinguish this combined package from older 12.5.1 test ZIPs that lack the fix.
- PR #13 remains a separate diagnostic investigation. Its historical third-party/FPM results are not certification of this package. Native crashes and intermittent admin timeouts remain unresolved, including examples with Easy Countdown inactive.

No production deployment, email, WordPress.org upload, tag or GitHub Release is performed by preparing this handoff. Approval and live-test completion must never be inferred from this document.

## Exact artifact gate

Build only with `bash scripts/build-zip.sh --output dist/easy-countdown.zip --report dist/distribution.json`. Confirm Plugin Name, Text Domain, Version, the version constant, Stable tag and Contributors. No template tokens, test files, site data or font binaries belong in the installation ZIP. The existing manifest is authoritative.

Run current-head CI, inspect the actual Plugin Check report (not only exit status), and compare every shipped file and ZIP checksum. Run the combined password/cache, embed-layout, native image and real WordPress suites; separate historical results are not a substitute. Retain all failures and explicitly explain retries. Never weaken required-checks or the frozen renderer oracle.

The final reviewer email must attach the same ZIP the maintainer tests, not the materials archive, GitHub source ZIP or an older candidate. A code/readme change after testing requires a rebuilt and rechecked ZIP.

## Maintainer live acceptance checklist

Record environment, plugin version, ZIP SHA256, date and PASS/FAIL for each item. Use synthetic campaigns and media. Prefer staging for destructive tests; do not send load tests to production.

1. Back up the database and fonts. Update without uninstalling. For a legacy `email-countdown-timer/` installation, follow the documented deactivate/install transition; never activate both copies or uninstall one while cleanup would remove shared data.
2. Confirm existing timer IDs, saved labels, time zones, fonts, alt text and public URLs are preserved. Check creation, editing and deletion of a disposable timer; real campaigns must remain untouched.
3. Test a logged-out page with FlyingPress enabled and its real production settings. Repeat the same public image GET before and after a short deadline; verify actual GIF/image bytes, no HTML challenge and no stale page/edge image cache. Inspect Cloudflare only if it is actually configured.
4. Test both zero-countdown and selected end-image behavior. Open a fresh animation within its last 60 seconds and observe the transition. A GIF already completed or retained by a mail proxy cannot be revoked or remotely refreshed.
5. On a disposable attachment/parent, warm the end-image cache, add a password, and check that new origin requests show zeros rather than the restricted graphic. Remove the password and verify recovery. Do not use confidential media or treat direct upload URLs as protected by the timer.
6. Recopy Email HTML and send through the actual mailing platform to the email clients used by recipients. Check permitted and blocked images, alt/deadline text, animation preference, narrow screens and the final image. Record exact client versions and any provider caching limitations. Do not bypass recipient privacy preferences.
7. Purge affected HTML caches, then test a narrow page and a throttled image load. Confirm reserved timer space and no unexpected horizontal scroll. Theme overrides, LCP/INP and whole-site metrics require site-level checks.
8. Inspect relevant PHP/browser errors. On staging only, confirm deactivate/reactivate preserves configuration and optional uninstall cleanup does not delete Media Library files or unrelated data.

## Sending boundary

If the exact package passes CI and the relevant live checks, with no unresolved reproducible blocker in the plugin, reply to the existing review thread with that installation ZIP and the prepared English message. Disclose relevant limitations; do not claim all plugin/client combinations, zero vulnerabilities or automatic GDPR compliance. The WordPress.org team may request further changes before approval.

Catalog screenshots are separate SVN root `assets/` files, not the plugin's runtime `assets/` directory. The four supplied screenshots and their captions describe the existing editor, end-image/alt controls, narrow layout and Privacy Policy Guide. Do not upload the materials ZIP as a plugin.
