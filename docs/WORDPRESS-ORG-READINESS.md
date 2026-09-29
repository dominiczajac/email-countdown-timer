# WordPress.org review readiness

## Existing submission

The maintainer supplied the acknowledgement for **Easy Countdown**, initially assigned the **easy-countdown** slug in November 2025. Continue that existing submission; do not create a duplicate. The acknowledgement is not the subsequent rejection/review report. This repository does not claim current review status or directory approval.

The 12.5.0 package uses **Easy Countdown** as its display name, `easy-countdown` as its translation domain and ZIP root, and the existing historical main PHP filename. GitHub retains its repository name. Review [identity and compatibility](EASY-COUNTDOWN-EXPIRY-IMAGE.md) before moving an installation from the old GitHub package directory.

`Contributors` requires the exact WordPress.org account username. The email supplied for login is not published as a contributor name or assumed to equal a profile slug. Confirm the account username before resubmitting; do not substitute the GitHub username without verification.

## Technical preparation

The project includes a protected PR workflow; validation, escaping and permission checks; opt-in ownership-scoped uninstall with multisite isolation; local fonts; current static degradation when rendering locks are unavailable; an English translation-ready panel; and optional bounded local end images. It adds no developer telemetry or recipient tracking. Infrastructure and WordPress core have separate privacy responsibilities.

A single explicit manifest and ZIP builder are used by Plugin Check and disposable WordPress installation tests. CI includes PHP/GD/Imagick, WordPress single/multisite with database and Redis cache, browser interactions, full loopback HTTP, and Apache/nginx font-access tests. Results belong to the exact source revision and are recorded in its PR. Prior green reports do not establish verification of a later candidate.

## Before uploading revised files

- Address the actual review-team findings and confirm the monitored account email and 2FA. The initial acknowledgement contains no code rejection reasons.
- Confirm the WordPress.org username for `Contributors` and the assigned `easy-countdown` slug in the existing submission.
- Select the final tested commit and its canonical ZIP. Verify update/activation and font preservation on staging, including the directory transition and existing campaign URLs.
- Prepare genuine final-version screenshots and accurate captions. Complete manual accessibility checks; automated browser checks are not a screen-reader or full WCAG audit.
- Review full WPCS quality, production-representative resource limits, licenses and the deployment's privacy notice. A clean PCP result does not guarantee security, every optimizer configuration or campaign capacity. No fonts are bundled.
- Upload the replacement ZIP to the **existing submission** using the review team's process. Do not open another plugin submission. After approval, use the assigned SVN repository and matching release metadata.

The GPL-3.0-only license is retained. Google Fonts remains deferred. No new directory submission, production deployment, native Wiki publication or review approval is performed by committing this checklist.

## Official references

- [Plugin submission page](https://wordpress.org/plugins/developers/add/)
- [Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Readme metadata](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Internationalization](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)
