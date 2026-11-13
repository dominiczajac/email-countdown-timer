# WordPress.org review readiness

## Existing submission

The maintainer supplied the **Easy Countdown** acknowledgement from 3 November 2025 and the 8 April 2026 notice inviting an updated ZIP in the original thread. Continue the existing **easy-countdown** submission; do not create a duplicate. This repository does not claim directory approval.

The current unreleased candidate is **12.7.0**: **Easy Countdown** as its display name, `easy-countdown` as its translation domain and ZIP root, and the existing main PHP filename. GitHub retains its repository name. Review [identity and compatibility](EASY-COUNTDOWN-EXPIRY-IMAGE.md) before moving an installation from the old GitHub package directory. The maintainer will submit the plugin personally; preparing these files does not send anything.

The confirmed WordPress.org contributor is **ddoomm** (Simple Fast Secure), as recorded in the historical [12.5.2 handoff](REVIEW-HANDOFF-12.5.2.md). Keep that confirmed username; do not replace it with an email or infer another profile from the GitHub account. The maintainer still needs to verify current account access, monitored email and 2FA.

## Technical preparation

The project includes a protected PR workflow; validation, escaping and permission checks; opt-in ownership-scoped uninstall with multisite isolation; local fonts; current static degradation when rendering locks are unavailable; an English translation-ready panel; and optional bounded local end images. It adds no developer telemetry or recipient tracking. Infrastructure and WordPress core have separate privacy responsibilities.

A single explicit manifest and ZIP builder are used by Plugin Check and disposable WordPress installation tests. CI includes PHP/GD/Imagick, WordPress single/multisite with database and Redis cache, browser interactions, full loopback HTTP, and Apache/nginx font-access tests. Results belong to the exact source revision and are recorded in its PR. Prior green reports do not establish verification of a later candidate.

The [current Gate A handoff](REVIEW-HANDOFF-12.7.0.md) describes the exact package, genuine catalog screenshots, receipt and [pending maintainer worksheet](ACCEPTANCE-12.7.0.csv). The installed package includes the native block, optional website live text, five local font faces, occasion presets, Quick Create/dashboard access, campaign management and on-demand diagnostics. Animated GIF remains the default image mode; static previews respect PNG/WebP Accept preferences. Named compatibility profiles retain their exact tested sources/settings and negative results; paid optimizers, real sender/client delivery and arbitrary combinations are not certified. Earlier 12.6.0/12.6.1 receipts remain historical. Owner feedback on 12.6.1 does not establish acceptance of the new candidate.

## Before uploading revised files

- Review the original team feedback against the current candidate and confirm the monitored account email and 2FA.
- Retain the confirmed `ddoomm` contributor and assigned `easy-countdown` slug in the existing submission.
- Select the final tested commit and its canonical ZIP. Verify update/activation and font preservation on staging, including the directory transition and existing campaign URLs.
- Use genuine current-candidate screenshots from `.wordpress-org/` and accurate readme captions; keep them outside the install ZIP. Complete manual accessibility checks; automated browser checks are not a screen-reader or full WCAG audit.
- Review full WPCS quality, production-representative resource limits, licenses and the deployment's privacy notice. A clean PCP result does not guarantee security, every optimizer configuration or campaign capacity. Only the five allowlisted static faces across four families (Lato, IBM Plex Mono, Barlow Condensed and Crimson Text) and their original OFL notices are bundled; user fonts are excluded.
- After recorded live acceptance and explicit maintainer approval, upload the replacement ZIP to the **existing submission** using the review team's process. Do not open another plugin submission. After directory approval, use the assigned SVN repository and matching release metadata.

The GPL-3.0-only license is retained. Automatic Google Fonts import remains deferred. No new directory submission, production deployment, native Wiki publication or review approval is performed by committing this checklist.

## Official references

- [Plugin submission page](https://wordpress.org/plugins/developers/add/)
- [Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Readme metadata](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Internationalization](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)
