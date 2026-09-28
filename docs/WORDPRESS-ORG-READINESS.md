# WordPress.org Submission Readiness

Reviewed 2026-09-28. This is a preparation checklist, not approval by WordPress or evidence that a submission has been made.

## WordPress.org versus WordPress.com

The public free-plugin submission process is the WordPress.org Plugin Directory. WordPress.com is a hosting service whose supported plans can install plugins from that directory or uploaded ZIPs. Directory approval is not proof of compatibility with every WordPress.com hosting configuration or eligibility for a separate commercial marketplace.

## Submission and release requirements

- Use a WordPress.org account with two-factor authentication and an actively monitored email address. Submit a complete installable ZIP, not a GitHub repository URL alone.
- Run the official **Plugin Check (PCP)** and resolve findings. It is part of the submission pre-check and does not replace manual review. Existing PHP tests are not PCP.
- Keep the plugin header and `readme.txt` complete and consistent: slug, text domain, required PHP/WordPress versions, plugin version, license, and stable version tag. Populate `Tested up to` only after testing that actual WordPress version; verify the real WordPress.org contributor login rather than copying a GitHub login by assumption.
- Keep all distributed material GPL-compatible, including fonts and third-party libraries. The repository currently declares `GPL-3.0-only`; WordPress strongly recommends GPLv2-or-later but permits GPL-compatible licenses. Do not change licensing silently.
- Preserve capability and nonce checks, validate inputs, escape outputs, guard executable PHP files, and prepare SQL. Use WordPress APIs and bundled libraries where applicable. English text alone is not internationalization: user-facing strings need WordPress translation functions and the matching text domain.
- Describe behavior and external services honestly. Do not add undisclosed tracking, remote executable code, unapproved front-end attribution, or artificial restrictions on built-in functionality. Resource-safety limits must not be presented as paid usage gates.
- After approval, publish releases through the assigned WordPress.org SVN repository. GitHub remains the development/review repository; it does not replace SVN distribution. Increment the plugin version for each release and keep the matching stable tag updated.

## Gaps in the current plugin

The current repository has isolated PHP/GD/Imagick tests, not full WordPress integration tests or a verified PCP pass. The supplied ZIP has no bundled fonts. Before submitting:

1. Run PCP against the actual distribution ZIP and review security/readme/internationalization findings. Run PHPCS with WordPress standards as a separate quality check; do not describe formatting alone as proof of security.
2. Review legacy three-letter `ECD_` global symbols: the review team's guidance discourages two-/three-letter prefixes. Prefer a distinctive namespace for internals while preserving existing public shortcode/URL and saved-data contracts deliberately.
3. Internationalize the legacy admin UI and validation messages; new data-settings UI uses translation helpers, but that does not translate or audit the rest of the plugin.
4. Replace `Stable tag: trunk` with the final released version, complete validated metadata, and test both minimum-supported and current WordPress versions. Do not fabricate compatibility claims.
5. Test GD/Imagick/FreeType availability and clear failure messaging on real hosting. Run an authenticated admin and anonymous image test, plus concurrent image requests, not only isolated render tests.
6. Review and integrate opt-in uninstall cleanup, then test retention/deletion on real single-site and multisite installations with and without persistent cache. The cleanup PR does not change the public endpoint or create cron jobs.
7. Package runtime files, license, and the useful readme only; keep tests, CI, private files, and build caches out. A clear native admin UI and screenshots are useful, but a decorative redesign is not a substitute for passing review.

## Sources

- [Developer information and submission workflow](https://wordpress.org/plugins/developers/)
- [Submit a plugin](https://wordpress.org/plugins/developers/add/)
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Common review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)
- [Plugin Check and 2FA requirements](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/)
- [WordPress.com plugin support](https://wordpress.com/support/plugins/)
