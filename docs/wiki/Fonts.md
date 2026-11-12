# Local fonts

Lato Regular, IBM Plex Mono Regular, Barlow Condensed Regular and Crimson Text Regular are included under SIL Open Font License 1.1. In the timer editor, select an included face under **Appearance > Typography and Size > Font**, then save. New campaigns prefer Lato when available; occasion presets explicitly apply their own font and colors. The bitmap remains available and loading an existing campaign preserves its saved font. GD/FreeType is required.

The unmodified static faces are stored in plugin `/fonts/` with original per-family licenses, source notices and pinned hashes. They can be bundled with commercial software under OFL conditions; they are not relicensed under the plugin GPL. The files are used for server-side images; the website live-text mode inherits theme typography and does not download these fonts into the browser.

For **your own** licensed TTF/OTF files use the persistent directory shown in Data Settings, normally `wp-content/uploads/email-countdown-timer/fonts/`. Files manually added to the plugin directory may be lost during updates. Automatic Google Fonts import and site-wide discovery remain deferred; there is no font upload endpoint.

See the [canonical guide](Privacy-and-Local-Fonts.md) for current instructions and limitations.
