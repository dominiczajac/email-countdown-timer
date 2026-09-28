# Email Countdown Timer Documentation

This documentation covers source version **12.2.0**, retaining the Easy Countdown v12.1 image geometry. The plugin creates countdown images from deadlines saved by WordPress administrators. It is not an email delivery system or a mechanism for enforcing promotion deadlines.

The English interface is translation-ready. New timer labels use English defaults; changing the admin locale or updating the plugin does not overwrite existing campaign labels.

| Task | Page |
|---|---|
| Installation and keeping existing timers | [Installation](Installation.md) |
| Fields, fonts, dates and limits | [Configuration](Configuration.md) |
| Shortcodes, email HTML and image URLs | [Embedding](Embedding.md) |
| Retention and optional uninstall cleanup | [Data removal](Data-Removal.md) |
| Caching, load, permissions and privacy | [Performance and Security](Performance-and-Security.md) |
| Blank images, missing animation or outdated countdowns | [Troubleshooting](Troubleshooting.md) |
| Tests, packaging and Wiki updates | [Development](Development.md) |

A GIF contains 60 frames, and an email client may fetch it before the message is opened. Always include the deadline as plain text as well.

These files are the version-controlled `docs/wiki/` sources. Publishing them to the native GitHub Wiki is a separate operation; their presence does not confirm that publication.

## Admin refresh in 12.2.0

See [Admin interface](Admin-Interface.md) for the separate list/editor, saved-image preview, copy tools, validation and deletion. Google Fonts download is a proposal, not an installed feature.
