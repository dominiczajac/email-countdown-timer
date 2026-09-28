# Persistent local fonts — 12.4.0

No Google importer, font CDN, browser upload endpoint or new network request is provided.

## Before the first upgrade

WordPress replaces the plugin code directory during an update. Version 12.3.1 cannot run the new copy tool before it is installed. **Before installing 12.4.0 over an older version, copy or back up the existing `wp-content/plugins/email-countdown-timer/fonts/` files through SFTP/your hosting file manager.** Preserve filenames and font license notices. Do not uninstall just to update.

Preferred destination: the WordPress uploads base, followed by `email-countdown-timer/fonts/`. For multisite add `site-ID/` within that directory, even if uploads are shared. Default single-site example: `wp-content/uploads/email-countdown-timer/fonts/Example-Regular.ttf`. Repeat preparation for each site before a network update. The uploads base may differ from this example; inspect your WordPress configuration.

After installation, restore files there before enabling live campaigns. The original bare filename in saved timers still works. If a legacy plugin-local file with the same name remains, it wins until removed/replaced during update: a different persistent file never silently replaces the typeface while the original exists. Resolve conflicting files manually before updating again.

## Copy tool in Data Settings

Once 12.4.0 is running and original files still exist, **Copy Legacy Fonts to Persistent Storage** copies them without deleting originals. Requires administrator capability, POST and nonce. A session-owned lock serializes updates to the per-site ownership manifest. It copies at most 50 new files per request, each up to 5 MiB; repeat for larger collections. Paths are contained, symlinks rejected, static sfnt signatures checked and copied bytes SHA-256 verified. Atomic same-filesystem hard-link publication refuses overwrites. On filesystems without that operation or without advisory locks, use manual SFTP copying instead; no unsafe fallback is attempted.

Checking the signature does not validate an entire native font binary. Install trusted static TTF/OTF fonts and keep FreeType/GD patched. Copy license notices manually; this tool cannot infer the license of an arbitrary font. Fonts are read by PHP to rasterize images, not fetched by visitors as web fonts.

Read/render paths never create directories. A per-site option `email_countdown_timer_copied_fonts` stores only copied filenames and content hashes, not visitor activity. Identical pre-existing manual copies are not adopted as plugin-owned files. Files skipped because of conflicting content, type/size or a per-call limit are counted; no successful-copy claim is made for them.

## Retention

Deactivation and ordinary settings saves retain all files. On opt-in uninstall, the copy tool's files are removed **only if** they remain ordinary contained files with the recorded hash. Manually uploaded, replaced, unrecognized and symlinked files are retained. Shared directories are never recursively deleted and empty directories may remain. Database option cleanup follows the existing per-site consent. Backups and infrastructure logs are outside this scope.

## Save-time renderability

The form checks the same layout calculation as the renderer, without allocating an image or encoding a preview. Limits remain 4000 pixels wide, 1000 high and 400000 total pixels. Combined text/font/size must fit. Bitmap fallback has fixed text sizes and new saves accept printable ASCII labels only; choose a real available TTF/OTF for other characters. Missing/unreadable selected fonts count as bitmap fallback. TTF existence does not guarantee glyph coverage for every language.

Stored campaigns and public URLs are not migrated. Existing public rendering keeps its legacy behavior. If GD is absent, settings remain editable and the panel explicitly warns that rendering and layout preflight are unavailable. This is not a successful geometry check.

Tests: `php tests/font-storage.php`, existing frozen pixel regressions, real WordPress lifecycle/browser/HTTP suites. Test results for a particular commit must be read from that commit's CI, not inferred from this list.

References: WordPress `wp_get_upload_dir()`, `Plugin_Upgrader::upgrade()`, and PHP `link()` documentation. No font binaries are distributed with the plugin.

## Direct HTTP access

Local storage alone does not make font binaries private. Configure denial for the dedicated font roots before storing files whose licenses restrict downloading. The explicit Font File Access action can create Apache rules, but does not verify enforcement and never overwrites existing files. nginx needs host-managed rules. Shared legacy roots on multisite require network-administrator permission; per-site uploads remain independently scoped. See [font HTTP protection](FONT-HTTP-ACCESS.md). Guard files remain on uninstall to protect manually managed fonts; they contain no campaign or visitor data.
