# Email Countdown Timer Documentation

This documentation covers version **12.1.1**, based on the supplied Easy Countdown v12.1 renderer. The plugin creates countdown images using a deadline saved by a WordPress administrator. It is not an email delivery system or a mechanism for enforcing the end of a promotion.

| Task | Page |
|---|---|
| Getting started and keeping existing timers | [Installation](Installation.md) |
| Admin fields, fonts, dates, and limits | [Configuration](Configuration.md) |
| Shortcodes, email HTML, and image URLs | [Embedding](Embedding.md) |
| Caching, load, permissions, and privacy | [Performance and Security](Performance-and-Security.md) |
| Blank images, missing animation, or outdated countdowns | [Troubleshooting](Troubleshooting.md) |
| Tests, packaging, and Wiki updates | [Development](Development.md) |

The main limitation: a GIF contains 60 frames, and an email client may fetch it before the message is opened. Always include the deadline as plain text as well.

The documentation source is stored in `docs/wiki/` in the main repository. Publishing to the native GitHub Wiki is a separate Git operation; the presence of these files alone does not confirm publication.
