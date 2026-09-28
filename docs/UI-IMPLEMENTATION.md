# Accessible admin refresh

Approved design baseline: the English Email Countdown Timer admin prototype reviewed by the maintainer on 2026-09-28. Implementation starts at main `5be84f858f5f538d0d148663b311a42b50370dec` (12.1.3).

## Scope

Server-rendered Timers list, Create/Edit Timer, saved-image preview and embed tools; retain the separate opt-in Data Settings page. One scoped stylesheet and one progressively enhanced script. Native WordPress navigation, system typography, profile-aware primary buttons, explicit labels, keyboard controls, field errors and a separate confirmed delete action.

Preserve timer options, public image URLs, shortcode, timezone, saved labels, renderer geometry, 60-frame timing and image cache semantics. No autosave, public preview endpoint, automatic animated thumbnails, third-party UI runtime, external fonts or background polling. Saved preview is not a preview of unsaved inputs.

Google Fonts import is evaluated separately: a local installed-font selector is not an implemented remote downloader. A remote font importer needs explicit admin consent, owned persistent storage, download validation, licensing and lifecycle tests before introduction.

## Verification gates

Keep all existing rendering, lifecycle, real WordPress and Plugin Check gates. Add admin-specific authorization, nonce, duplicate-ID, invalid-input retention, safe-deletion, translation and markup regressions. Verify the actual PHP-generated screen in a browser at narrow/wide widths, with keyboard operation and JavaScript disabled. Record the exact scope of testing; do not equate automated tests with WCAG certification or a screen-reader audit.

Measure custom CSS/JS gzip size against the approved 15 KiB budget, confirm plugin-only asset loading and run a synthetic renderer/cache benchmark separately from production. No destructive operations or load tests against the maintainer's public site.

This document is an implementation plan until the PR's verification record confirms completion. No release or deployment is implied by opening the PR.
