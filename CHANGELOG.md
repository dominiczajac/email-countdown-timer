# Changelog

## 12.5.0

- Align the display name, text domain and package directory with the existing Easy Countdown / easy-countdown submission; preserve repository URLs, internal/storage identifiers and public timer interfaces.
- Add an optional local Media Library end image, including exact deadline/cache boundaries and transitions within 60-second GIFs. Re-encode bounded raster input without source metadata; never delete user media.
- Load the native media picker only on the editor, with attachment-ID entry available without JavaScript. Preserve old forms and unconfigured timers.
- Refuse duplicate initialization alongside the known active legacy package and document the explicit deactivation/install transition.

## 12.4.3

- Add an explicit, nonce/capability-protected local font access-rule installer. Do not overwrite existing files or symlinks; shared legacy rules on multisite require network-administrator permission. Apache must honor its rules; nginx requires host configuration. New required CI checks test both server types and ignored-rule negative cases.
- Deliver an already completed, still-fresh image after lock ownership loss without publishing it to shared cache. Clock rollback, a changed freshness interval or a crossed deadline selects a current static fallback instead.
- New timers inherit the WordPress site timezone, including offsets. Existing saved/legacy zones retain their interpretation. Obsolete form saves/deletes now return 409 with no data mutation; reload old editor tabs.
- Explain animation prerequisites, slow-render static responses, font HTTP protection and licensing, and shorten user-facing migration instructions. No Google Fonts importer, telemetry or alternative options-based lock is added.
- Keep the frozen renderer oracle and canonical ZIP build. Validate font-rule request routing/status values.

## 12.4.2

- One explicit, verified installation ZIP is now used by packaging, Plugin Check and real WordPress lifecycle/browser/HTTP tests. Negative build tests reject unsafe paths, source symlinks, missing or unlisted runtime files and inconsistent metadata.
- Suggested, translatable privacy wording is registered on admin_init for the WordPress Privacy Policy Guide. Published policies are never edited automatically; no tracking or remote service is added.
- Refresh user-facing readme and canonical instructions for persistent fonts, current static fallback, FlyingPress and safe upgrades. WordPress.org contributor identity remains to be confirmed.
- Renderer, saved campaign data, public URLs and generation locking are unchanged.


## 12.4.1

- Distinguish acquired, busy, unsupported and failed advisory-lock attempts. Preserve real ownership checks.
- Use a current single-frame image after a one-second busy/unavailable lock wait; never reuse stale animated output or populate the animation cache with fallback frames.
- Add administrator-only lock diagnostics and real HTTP tests for error, busy and expired-deadline fallback paths.

## 12.4.0

- Add persistent per-site local font lookup, explicit non-destructive copying and hash-scoped uninstall cleanup. Manual/replaced files remain. Legacy name precedence preserves existing rendering.
- Reuse the exact layout calculation for save-time geometry validation without allocating/encoding an image; reject unsupported bitmap labels only on save. Existing public image interpretation is retained.
- Surface unavailable GD and bitmap fallback in the admin panel; centralize versioned asset metadata.
- The first upgrade cannot rescue old plugin-local fonts already removed by WordPress. Copy/backup before replacement. No Google Fonts import.


## 12.3.1 — 2026-09-28

- Reconcile overlapping PRs #5 and #6 without removing session-owned render locks or HTTP tests.
- Keep PR #5's absent-versus-explicit-empty alternative text behavior and 1000-byte limit; do not duplicate fields or migrate saved data.
- Add the image-only FlyingPress cache filter, stricter source validation in the tab refresher, and independent binary validation of HTML-only alternative text.
- Retain both branches' non-conflicting tests; update duplicate alt assertions to the documented PR #5 semantics.
- Consolidate English optimizer/privacy/font instructions through stable documentation links. Google Fonts automatic import is deferred, not implemented.

## 12.3.0 — 2026-09-28

Added per-timer alternative text with safe HTML escaping, explicit empty values, legacy fallbacks and editor/email/shortcode coverage. Alt never changes rendered pixels. Added narrow lazy-load interoperability markers, no-referrer hints, an idempotent tab refresher, no-transform image headers and best-effort dynamic-route page-cache bypass.

Serialized cold image generation using a bounded MySQL/MariaDB session lock, followed by a shared-cache recheck and ownership verification. Contention/lock-service failure returns 503 with Retry-After rather than unbounded duplicate work. No persistent lock options or cron were added. Renderer geometry and 60-frame timing remain unchanged.

Added full HTTP concurrency tests, privacy-sensitive API guardrails and English optimizer/privacy/manual-font guidance. Commercial optimizer combinations, all hosting topologies and GDPR/security certification are not claimed. Automatic Google Fonts importing is not implemented.

## 12.2.0 — 2026-09-28

Implemented the approved lightweight admin design: a searchable list with 25 timers per page, a separate Create/Edit screen, native local-font controls, responsive sections and scoped CSS/JavaScript. Rendering code and public data contracts are unchanged; changing the version invalidates older image-cache signatures once.

Added explicit labels, field errors and error-summary focus; invalid values and intentionally empty labels survive validation. Duplicate IDs cannot overwrite a timer through the new creation form, and edited IDs are checked server-side. Saving and deletion retain capability, POST and nonce checks. Deletion has a distinct confirmation form, with an optional native dialog and Cancel-first focus.

The editor shows the saved static image, with animation only on demand. Copy tools supply image URLs, unchanged shortcodes and email HTML including an absolute deadline. Clipboard failure has a manual-selection fallback. Without JavaScript, field sections and manual formats remain expanded; saving still uses native server forms. No remote font downloader, background polling or automatic animated table previews were added.

Added real WordPress/Chromium browser checks, no-JS saves, constrained-width checks and a synthetic renderer/cache benchmark. These supplement, rather than replace, the existing PHP, image, uninstall, MySQL/Redis and Plugin Check gates. See the 12.2.0 verification record for measured results and limits; no full WCAG/WPCS certification or production-throughput claim is made.

## 12.1.3 — 2026-09-28

Added explicit, default-off data removal during WordPress uninstall. Saving the policy or deactivation never removes timers. Cleanup is ownership-scoped, handles legacy/orphaned database cache and per-site multisite consent, and preserves unrelated options, cron and caches. No cron hooks are created.

Added real WordPress 6.4/current-stable lifecycle tests with MySQL and Redis, single-site/multisite coverage and anonymous HTTP rendering checks. Plugin Check 2.1.0 with runtime checks is now part of the fail-closed `required-checks` gate. Its report is parsed because exit zero alone can conceal findings. Verification evidence and narrow code-local static-analysis annotations are documented.

Prepared English interface and validation strings for WordPress translation, escaped numeric output attributes and replaced short internal class/constant prefixes with `Email_Countdown_Timer_` / `EMAIL_COUNTDOWN_TIMER_`. Saved labels, option names, shortcode, URL parameters, timezone, geometry and GIF timing are unchanged. Old internal aliases exist only in the CLI test harness. Custom code calling undocumented internal classes needs review.

Updated numeric stable-tag metadata and `Tested up to: 7.1`, based on actual WordPress 7.1.2 runs. No GitHub Release, tag or WordPress.org submission is implied by this source version. UI redesign and a full WPCS/load-testing pass remain separate work.

## 12.1.2 — 2026-09-28

Translated the admin interface, buttons, help text, and validation messages into English. New timers and missing label values default to `Days`, `Hours`, `Minutes`, and `Seconds`. Existing saved labels, including custom or empty values, are not translated or migrated automatically.

The renderer, time-zone default, option name, shortcode, image URLs, safety limits, and 60-frame timing are unchanged. Added regression tests for English UI text, validation errors, new-timer creation, saved-label preservation, and English GIF frames; the frozen legacy renderer and historical image fixtures remain intact. Updated the current documentation without rewriting the historical 12.1.1 audit.

## 12.1.1 — 2026-09-28

The first structured repository version based on the supplied Easy Countdown v12.1 code.

### Security

Fixed the unescaped edit heading. Added validation of input types, dates, time zones, colors, and ranges; `wp_unslash()` before saving; safe URLs and redirects; font-path restrictions; and a pixel budget. Existing capability and nonce checks are preserved. The public endpoint rejects unknown IDs and invalid configurations, accepts only GET/HEAD, and does not raise PHP's execution time limit.

### Performance and reliability

Stable cache slots replace separate keys for each time bucket. The signature now includes the label color. Added font-metrics memoization, reused the already-rendered first frame, and replaced multiple listeners with one script. Without Imagick, the plugin returns a static GIF instead of an empty response. Resource cleanup and buffer handling have been improved.

### Compatibility and documentation

Preserved the Polish admin panel, data, shortcode, URLs, 60-frame sequence, and renderer geometry. New safe ranges and the rejection of invalid data are documented in the Wiki. Added regression tests, PHP 8.1–8.5 CI, a README, Wiki documentation, and an audit report. The package declares a minimum PHP version of 8.1 and WordPress 6.4 in its header. No automatic updater or confirmation of WordPress.org publication has been added.

The repository's existing GNU GPL v3 license file is preserved; the code declares `GPL-3.0-only`.
