# Local Fonts and Google Fonts

## Available now: manual TTF/OTF installation

Obtain a font from a source you trust, such as Google Fonts, and check its license. Extract the archive on your computer. Using SFTP or your hosting file manager, create this directory when absent:

```text
wp-content/plugins/email-countdown-timer/fonts/
```

Copy the actual `.ttf` or `.otf` file directly into that directory, not a ZIP, CSS file, WOFF/WOFF2 file or link to a remote URL. Retain the applicable license/attribution. Do not make the directory world-writable. Open **Easy Countdown → Edit → Typography and Size**, select the local filename and save. Use a static font variant supported by the server's FreeType; variable-font axis selection is not provided. Missing fonts use the bitmap fallback, with fixed text sizes. Confirm accented glyphs and your campaign labels in the saved preview.

GD/FreeType reads the local file and rasterizes text into the PNG/GIF. The recipient receives an image, not a Google stylesheet or font. No remote font request is made during rendering, and copying a font does not add a third-party browser connection.

**Back up these files before updating.** WordPress may replace the plugin directory during an update or remove it on uninstall, regardless of database-retention settings. The current version does not automatically migrate plugin-local fonts to uploads. Re-upload missing files with the same filenames to preserve existing selections. There is no admin font-upload endpoint in this release; use your authorized hosting tools.

## Planned: explicit automatic import

Automatic Google Fonts installation is **not implemented in 12.3.0**. There is no working "Install from Google" button or API-key setting. The [design proposal](../GOOGLE-FONTS-DESIGN.md) separates a curated no-key catalog from an optional full Developer API catalog. No choice has been silently enabled.

The proposed importer will download only a chosen, validated font variant and license after an explicit administrator action, then read it locally from a persistent per-site directory (normally under uploads). It must have bounded downloads, approved sources, path containment, integrity checks and ownership-scoped uninstall. No background visitor requests, automatic catalog downloads or silent telemetry are permitted. A remote download necessarily contacts its provider from the server; local use afterward does not erase that installation-time connection.

Do not place files in the proposed uploads path expecting the current release to discover them. Only the plugin-local path above is currently supported.

References: [Google Fonts FAQ and licensing](https://developers.google.com/fonts/faq), [Developer API](https://developers.google.com/fonts/docs/developer_api), [WordPress plugin updates](https://developer.wordpress.org/reference/classes/plugin_upgrader/upgrade/).
