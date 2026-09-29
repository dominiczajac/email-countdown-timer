# Image after the deadline

In **Easy Countdown > Create/Edit Timer > After Countdown**, choose a local Media Library image and save. Without JavaScript, enter the image's WordPress attachment ID. **Remove End Image** sets the ID to 0; save to apply. Previously sent emails keep the same timer URL.

Use a JPEG, PNG, GIF or WebP file stored locally in this site's uploads. Limits: **4 MiB**, **4096 pixels per side**, **4 million pixels total**. Remote-only/offloaded assets and SVG are not fetched or accepted. An animated input contributes only its first frame. The selected content becomes public through the timer; do not choose personal or confidential material.

The image is scaled proportionally to fit the timer canvas, with the configured background in unused space. A wide image with a similar aspect ratio works best. It replaces zeros on new requests at/after the deadline, and replaces the remaining frames when a newly generated 60-second GIF crosses the deadline. The existing output MIME type is preserved; no redirect or original-file metadata is sent.

A GIF that finished playing before the deadline cannot refresh itself. Email providers can also prefetch, cache or block images. Always include the absolute deadline in visible text. Choose alternative text suitable for both states: the HTML `alt` cannot change within a GIF or a previously sent email.

Deleting/trashing the selected attachment or removing its local file restores the zero countdown. Deleting a timer or uninstalling Easy Countdown does **not** delete Media Library attachments. The original file remains managed by WordPress, not the countdown cache.

The package slug and text domain are `easy-countdown`. Public shortcode/query names and the persistent `uploads/email-countdown-timer/fonts/` path are retained for compatibility. See [Installation](Installation.md) before changing from the earlier GitHub package directory.
