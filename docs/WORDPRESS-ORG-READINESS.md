# WordPress.org review readiness

## Existing submission

The maintainer supplied the **Easy Countdown** acknowledgement from 3 November 2025 and the 8 April 2026 notice inviting an updated ZIP in the original thread. Continue the existing **easy-countdown** submission; do not create a duplicate. This repository does not claim directory approval.

The current candidate is **12.9.1**, **Tickloom Countdown Timers**, with proposed slug, translation domain and ZIP root `tickloom-countdown-timers`. Update the existing `easy-countdown` submission and request the new slug in that review thread. See the [current handoff](REVIEW-HANDOFF-12.9.1.md).

The confirmed WordPress.org contributor is **ddoomm** (Simple Fast Secure), as recorded in the historical [12.5.2 handoff](REVIEW-HANDOFF-12.5.2.md). Keep that confirmed username; do not replace it with an email or infer another profile from the GitHub account. The maintainer still needs to verify current account access, monitored email and 2FA.

## Technical preparation

The project includes a protected PR workflow; validation, escaping and permission checks; opt-in ownership-scoped uninstall with multisite isolation; local fonts; current static degradation when rendering locks are unavailable; an English translation-ready panel; and optional bounded local end images. It adds no developer telemetry or recipient tracking. Infrastructure and WordPress core have separate privacy responsibilities.

A single explicit manifest and ZIP builder are used by Plugin Check and disposable WordPress installation tests. CI includes PHP/GD/Imagick, WordPress single/multisite with database and Redis cache, browser interactions, full loopback HTTP, and Apache/nginx font-access tests. Results belong to the exact source revision and are recorded in its PR. Prior green reports do not establish verification of a later candidate.

Previous launch materials and acceptance records are historical. Check the new package on staging using the [12.9.1 handoff](REVIEW-HANDOFF-12.9.1.md).

## Before uploading revised files

- Review the original team feedback against the current candidate and confirm the monitored account email and 2FA.
- Retain the confirmed `ddoomm` contributor and request `tickloom-countdown-timers` as the updated slug in the existing review thread.
- Select the final tested commit and its canonical ZIP. Verify update/activation and font preservation on staging, including the directory transition and existing campaign URLs.
- Use current screenshots if supplying catalog assets. The 12.8.0 captures in `.wordpress-org/` show the previous branding. Complete manual accessibility checks; automated browser checks are not a screen-reader or full WCAG audit.
- Review full WPCS quality, production-representative resource limits, licenses and the deployment's privacy notice. A clean PCP result does not guarantee security, every optimizer configuration or campaign capacity. Only the five allowlisted static faces across four families (Lato, IBM Plex Mono, Barlow Condensed and Crimson Text) and their original OFL notices are bundled; user fonts are excluded.
- After recorded live acceptance and explicit maintainer approval, upload the replacement ZIP to the **existing submission** using the review team's process. Do not open another plugin submission. After directory approval, use the assigned SVN repository and matching release metadata.

The GPL-3.0-only license is retained. Automatic Google Fonts import remains deferred. No new directory submission, production deployment, native Wiki publication or review approval is performed by committing this checklist.

## Official references

- [Plugin submission page](https://wordpress.org/plugins/developers/add/)
- [Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Readme metadata](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Internationalization](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)
