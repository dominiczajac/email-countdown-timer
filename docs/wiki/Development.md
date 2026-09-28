# Development, Testing, and Publishing

## Structure and compatibility

`email-countdown-timer.php` loads the configuration, renderer, controller and data-settings class. `includes/admin-view.php` contains the English, translation-ready panel. `assets/countdown.js` refreshes images when the tab becomes visible. `uninstall.php` loads its separate cleanup implementation only during WordPress uninstall.

The implementation uses `Email_Countdown_Timer_` class names and `EMAIL_COUNTDOWN_TIMER_` constants. Public option, shortcode and image URL names remain unchanged. Do not change saved labels, time-zone defaults, geometry or timing without a compatibility decision.

`tests/legacy-frame.php` is a frozen CLI-only v12.1 layout oracle, not a second active plugin. The test harness supplies old-name aliases; runtime code does not. Do not change expected pixels to conceal a regression.

## Test layers

```sh
php tests/run.php
php tests/uninstall.php
python3 tests/test-pcp-gate.py
node --check assets/countdown.js
```

The first two suites use WordPress/database doubles. Native rendering requires GD/Imagick and a supported system font; temporary font copies are removed and never distributed. Translation tests verify that translated output is escaped and does not mutate stored campaign data. Missing GD is an explicit local skip but fails hosted CI.

`.github/workflows/wordpress.yml` provisions disposable WordPress 6.4/current-stable installations, single-site/multisite and database/Redis variants. It tests HTTP images, retention, actual uninstall, pagination, foreign-data/cron isolation and cross-process cache coherence. The destructive harness refuses non-local installations. Do not run it against production.

`.github/workflows/plugin-check.yml` runs Plugin Check 2.1.0 with runtime checks on allowlisted distribution files. The report gate fails on errors, warnings, malformed output, stderr or execution failure. The artifact preserves the raw result, versions, hashes and tested source. Code-local annotations are documented in `docs/PLUGIN-CHECK-REVIEW.md`; there are no global check exclusions.

`required-checks` requires all three layers: PHP regression matrix, real WordPress integration and PCP. Read the latest head's completed results; earlier green runs do not approve later changes. Recorded evidence and remaining gaps are in `docs/VERIFICATION-12.1.3.md`.

## Packaging

For a runtime distribution, include only `email-countdown-timer.php`, `uninstall.php`, `readme.txt`, `LICENSE`, `includes/` and `assets/` under one `email-countdown-timer/` root. This matches the PCP workflow's distribution inputs. Exclude tests, scripts, CI files, credentials, private fonts and development caches. Verify bytes against the identified commit before sharing a ZIP.

`git archive` remains useful for source archives; `.gitattributes` excludes development tooling, but the archive also contains documentation. It is not automatically identical to the minimal installation package or a stable release.

English source strings use the `email-countdown-timer` text domain. Campaign label defaults and saved labels are data: do not translate them dynamically with the administrator's locale. Optional translation catalogs may be generated from source; no catalogs or font assets are bundled by default.

## Native GitHub Wiki

The native Wiki is a separate `email-countdown-timer.wiki.git` repository. The owner must initialize its first page in GitHub before initial cloning. `docs/wiki/` is not automatically published there.

With local Git/Python 3, normal Git authentication, write permission and a configured author identity:

```sh
# Preview only:
bash scripts/publish-wiki.sh

# Publish after reviewing the diff:
bash scripts/publish-wiki.sh --publish
```

The script copies supplied pages, adapts relative links and uses a normal push. It does not remove other pages or force-push. Same-name pages are replaced by these sources; inspect the diff. A concurrent remote edit should cause a normal push rejection, not a forced overwrite. Never commit or paste access tokens.

Source: [GitHub Wiki documentation](https://docs.github.com/en/communities/documenting-your-project-with-wikis/adding-or-editing-wiki-pages).
