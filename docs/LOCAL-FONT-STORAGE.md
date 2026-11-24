# Persistent local fonts

## Switching to Tickloom

Back up custom fonts and their license notices before replacing a plugin directory. Deactivate the old plugin and install Tickloom without uninstalling the old copy. Both versions share timer settings.

Use the WordPress uploads base followed by `tickloom-countdown-timers/fonts/` for new custom fonts. On multisite, append `site-ID/`, even with shared uploads. **Data Settings** shows the resolved directory. Existing filename selections can read fonts from the current plugin directory and earlier installed copies. Uploads are searched in order under `tickloom-countdown-timers/fonts/`, `actnow-countdown-timers/fonts/`, and `email-countdown-timer/fonts/` after the plugin-local directories. An explicit built-in selection always uses its included face.

## Copy tool in Data Settings

**Copy Legacy Fonts to Persistent Storage** copies recognized fonts from the previous directories to Tickloom uploads without moving or overwriting them. It requires administrator permission, POST and a nonce. A database session lock serializes changes, file size is limited to 5 MiB and a request copies at most 50 fonts. The tool verifies source and destination hashes, rejects symlinks and uses exclusive publication within uploads. If the host lacks filesystem or database lock support, copy the files manually without replacing existing fonts.

Checking the signature does not validate an entire native font binary. Install trusted static TTF/OTF fonts and keep FreeType/GD patched. Copy license notices manually; this tool cannot infer the license of an arbitrary font. Fonts are read by PHP to rasterize images, not fetched by visitors as web fonts.

Each uploads namespace has its own per-site ownership map of copied filenames and hashes. The Tickloom copy action records its copies in `email_countdown_timer_tickloom_copied_fonts`. Read and render operations leave the filesystem untouched. Files skipped because of conflicting content, type, size or the per-call limit are reported in the skipped count.

## Retention

Deactivation and ordinary settings saves retain all files. On opt-in uninstall, the copy tool's files are removed **only if** they remain ordinary contained files with the recorded hash. While a previous plugin copy is installed, its uploads fonts and ownership map are kept. Cleanup of the previous map applies only to matching copies in the old uploads root. Manually uploaded, replaced, unrecognized and symlinked files are retained. Shared directories are never recursively deleted and empty directories may remain. Database option cleanup follows the existing per-site consent. Backups and infrastructure logs are outside this scope.

## Save-time renderability

The form checks the same layout calculation as the renderer, without allocating an image or encoding a preview. Limits remain 4000 pixels wide, 1000 high and 400000 total pixels. Combined text/font/size must fit. Bitmap fallback has fixed text sizes and new saves accept printable ASCII labels only; choose a real available TTF/OTF for other characters. Missing/unreadable selected fonts count as bitmap fallback. TTF existence does not guarantee glyph coverage for every language.

Stored campaigns and public URLs are not migrated. Existing public rendering keeps its legacy behavior. If GD is absent, settings remain editable and the panel explicitly warns that rendering and layout preflight are unavailable. This is not a successful geometry check.

Tests: `php tests/font-storage.php`, existing frozen pixel regressions, real WordPress lifecycle/browser/HTTP suites. Test results for a particular commit must be read from that commit's CI, not inferred from this list.

References: WordPress `wp_get_upload_dir()`, `Plugin_Upgrader::upgrade()`, and PHP `link()` documentation. Five unmodified OFL static faces (Lato, IBM Plex Mono, Barlow Condensed and Crimson Text) are distributed in the plugin fonts/ directory. Updates restore them; the legacy-copy tool skips exact shipped copies from plugin directories. Additional user fonts still belong in persistent storage. Original per-family notices and hashes are in `fonts/README.txt`.

## Direct HTTP access

Local storage alone does not make font binaries private. Configure denial for the dedicated font roots before storing files whose licenses restrict downloading. The explicit Font File Access action can create Apache rules, but does not verify enforcement and never overwrites existing files. nginx needs host-managed rules. The action writes only to the current site’s persistent uploads font folder. Ask your host to protect any plugin-local originals that remain. See [font HTTP protection](FONT-HTTP-ACCESS.md). Guard files remain on uninstall to protect manually managed fonts; they contain no campaign or visitor data.

Every included face uses a reserved internal selection rather than its shipped filename. An existing persistent custom font with the same basename remains bound to its own file; a missing custom file is not silently replaced by a bundled face. Existing saved campaigns are not migrated.
