# WordPress.org Submission Readiness

Updated 2026-09-28. This is a preparation record, not WordPress.org approval or evidence of a submitted plugin.

## Completed technical work

- Protected PR workflow with active owner-installed main ruleset and a fail-closed `required-checks` job.
- Opt-in uninstall cleanup, including ownership-scoped cache deletion, per-site multisite consent and foreign cron/cache preservation.
- Real WordPress 6.4/current-stable integration matrix using MySQL and Redis, plus the existing PHP/GD/Imagick regressions.
- English UI and validation strings prepared for translation with the matching text domain. Campaign labels remain stable data.
- Distinct internal class/constant prefixes without changing public options, shortcode or URLs.
- Numeric stable tag and matching source metadata; 12.4.2 adds one canonical ZIP builder used by PCP and real installation tests. Historical core-version evidence remains identified in the relevant PRs.
- Official Plugin Check 2.1.0 with runtime checks. The reviewed report for head `89db910` has no reported errors or warnings. Later revisions must pass independently; see [verification evidence](VERIFICATION-12.1.3.md) and [annotated static-analysis cases](PLUGIN-CHECK-REVIEW.md).

## Remaining before submission

1. Confirm the owner's **WordPress.org** username, two-factor authentication and monitored contact email. Do not assume a GitHub login is the same account. Confirm the requested slug before submitting.
2. Select the final reviewed commit, build the minimal installable ZIP, rerun PCP on those exact distribution bytes and verify installation/update on the intended hosting. The automation tests defined scenarios, not every host or all browser interactions.
3. Select genuine final-revision screenshots for the directory and complete manual accessibility checks. The modernized panel and browser tests exist; they are not a full screen-reader audit.
4. Run a separate complete PHPCS/WPCS quality pass and production-representative load tests. A clean PCP report is not proof of full WPCS compliance or load capacity. Session locks and current static fallback are tested, not a global rate limiter.
5. Review any later-added fonts/libraries and their licenses. None are bundled now. Keep GPL-compatible licensing, disclosure of services and no unapproved tracking or remote executable code.
6. Confirm `Contributors` using the actual WordPress.org account (not an invented GitHub mapping). The current readme deliberately does not fabricate this identity.
7. Submit the complete ZIP through the WordPress.org Plugin Directory. Wait for the human review and address findings; a local pass does not guarantee acceptance. After approval, publish through the assigned SVN repository with matching version/tag metadata.

The existing GPL-3.0-only license is retained. GitHub remains the development repository. WordPress.com hosting supports plugin installation under its own plan and platform rules; WordPress.org approval does not establish compatibility with every WordPress.com configuration or eligibility for a separate commercial marketplace.

## Sources

- [Developer submission workflow](https://wordpress.org/plugins/developers/)
- [Submit a plugin](https://wordpress.org/plugins/developers/add/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Common review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)
- [Plugin Check and 2FA](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/)
- [Readme metadata](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [WordPress.com plugin support](https://wordpress.com/support/plugins/)

## Follow-up implementation status

PR #8 introduced persistent fonts and geometry preflight; PR #9 introduced current static fallback and lock diagnostics. Stage 3 adds user-focused documentation, a reproducible runtime-only ZIP and privacy-guide suggestions. Final source-specific test results belong in its PR. No new live deployment or directory submission is implied. Google Fonts remains deferred.
