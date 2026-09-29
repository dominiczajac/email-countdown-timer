# Easy Countdown identity and end image

Work in progress from 12.4.3 (`a2f93b60ad117aea703fd3893525f4099192b0db`). The maintainer has an existing WordPress.org submission for **Easy Countdown**, initially assigned the **easy-countdown** slug. Update that submission, not a duplicate plugin. This is not directory approval or a verified current submission status.

Planned scope:

- Match display name, translation domain and installation directory to Easy Countdown / easy-countdown. Keep the GitHub repository URL, internal class names, stored options, persistent font locations and public image/shortcode interfaces compatible. Do not infer a WordPress.org username from an email address.
- Let an administrator choose an optional local raster attachment from the WordPress Media Library. Reuse the current public timer URL, with no remote download, redirect, recipient tracking or new public upload endpoint.
- After the deadline, show that image; when a 60-second GIF reaches the deadline, replace its remaining frames. Preserve the old renderer output when no end image is selected. Fit the image to the timer canvas without changing its aspect ratio.
- Validate local paths, actual image format, file size and decoded-pixel budget. If a previously selected attachment is removed or becomes unreadable, retain the existing zero-countdown fallback. Never delete user media during uninstall.
- Test deadline/cache boundaries, static degradation, invalid attachments, escaping, capability/nonce gates, media-selector scope and the canonical installation ZIP on real WordPress.

This initial commit records scope, not completed implementation or verification. Keep the PR draft until current-head CI is reviewed. No production deployment, new directory submission, Google Fonts importer, native Wiki publication, release tag or independent security certification.
