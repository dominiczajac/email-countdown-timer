# 12.6.1 candidate: executed preflight and evidence boundaries

This record identifies the runtime/capture preflight for
[PR #42](https://github.com/dominiczajac/email-countdown-timer/pull/42).
Final-head hosted CI, Codex/Claude reviews and integrated-main checks are recorded
on that PR and in the downloadable delivery receipt. Read those current results;
this document does not predeclare a future run successful or transfer owner
acceptance from an older candidate.

## Identified installation

- Runtime and test source: `7a40d9b628733040894df927b550a35860b6e5ab`.
- Candidate: **Easy Countdown 12.6.1**, not a published release.
- Installation ZIP: **596,489 bytes / 38 files**.
- SHA256: `ef2c15f17d00a4257035d7391d88acccc00868ca391988729fd19331d83000e8`.
- Four pinned static font files and their original OFL notices; user fonts and
  catalog/development files remain outside the install manifest.

The current catalog receipt contains every installed file hash. Documentation
and catalog-only commits may follow the runtime source; verify every shipped
file against the final CI `distribution.json`, not just the version number.
The previous 12.6.1 ZIP from PR #41 has a different hash and is superseded for
future host acceptance. Its verification remains valid for its own bytes.

## Executed local checks

- **740 isolated PHP assertions** on PHP 8.4.27 with native GD/Imagick, including
  **28 Accept preference regressions** and the unchanged frozen rendering oracle.
- **16 packaging tests**, including complete allowlist/hash integrity, rejected
  font/license tampering, excluded site fonts and repeatable ZIP output.
- PHP/Python syntax and full diff whitespace checks.
- Native anonymous Apache HTTP against disposable WordPress **7.1.4**, PHP
  **8.4.27** and MySQL **8.4.13**: **58 requests**, comprising GET/HEAD for
  27 static Accept cases and two explicit animation-mode controls.

The HTTP preflight decoded actual response bytes and checked selected MIME,
dimensions, single static frames, empty HEAD bodies, `Vary: Accept`, `no-store`
and `no-transform`. Cases include WebP refusal, relative PNG preference,
wildcard specificity, case/whitespace, invalid weights, false media-type matches
and conservative duplicate handling. Both `mode=email` and `mode=anim` retained
**60 GIF frames at 1000 ms each** despite explicit WebP acceptance.

The hosted HTTP suite independently adds decoded static GET/HEAD checks to both
database and Redis lifecycle cells. It preserves cold/warm/concurrent generation,
lock-failure, privacy and expiry controls. Full hosting/codec combinations are
not inferred from a local server's capabilities.

## Genuine catalog capture

Six screenshots were recaptured from the exact ZIP on the same disposable
native Apache WordPress installation. The editor shows an included Barlow
Condensed face, all twelve occasion buttons and unchanged saved configuration;
the selector exposed all four included fonts. The native block, dashboard,
embedding panel, diagnostics and core Privacy Policy Guide were exercised.
The diagnostic image loaded anonymously and its redacted report copied correctly.
The capture recorded **no browser JavaScript errors**.

`.wordpress-org/capture.json` retains source/tree, ZIP/hash/bytes, every installed
file hash, actual WordPress/PHP/server/browser versions, PNG hashes/dimensions
and readme captions. Browser requests to core's emoji host `s.w.org` were blocked
by the isolated fixture and retained in that receipt. No UI pixels were edited.
The fixture uses synthetic campaigns, not production data, and does not send mail.

Catalog captures establish presentation provenance and selected local behavior;
they do not replace the hosted FPM/JIT, optimizer, browser or lifecycle gates.
The earlier [12.6.0 CI receipt](CI-VERIFICATION-12.6.0.md) remains historical.

## Pending owner work and publication

All fourteen rows in [the 12.6.1 worksheet](ACCEPTANCE-12.6.1.csv) remain pending.
The maintainer plans hosting feedback on 13 November 2026 (Europe/Warsaw).
Real Sendy → Gmail/Outlook/Apple Mail, paid optimizer packages, actual host and
manual assistive-technology acceptance are not supplied by automated tests.
The maintainer handles the existing WordPress.org submission personally.
No release/tag, production deployment, real email, native Wiki, SVN, directory
submission or environment publication is performed by preparing these materials.
