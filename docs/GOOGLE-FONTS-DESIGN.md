# Proposed Google Fonts Importer

Status: design proposal, **not implemented in 12.2.0**. The approved admin refresh includes the existing installed-font selector only. This document distinguishes that selector from a remote font downloader.

## Current architecture

`Email_Countdown_Timer_Config::fontPath()` resolves a local TTF/OTF file and rejects paths or symlinks escaping the plugin's `fonts/` directory. GD/FreeType draws the glyphs into PNG/GIF frames; email recipients and shortcode visitors receive images, not downloadable font resources. There is currently no Google Fonts request on that rendering path.

Therefore the value of an importer is convenient font installation, not removing an existing remote-font bottleneck. The more consequential performance risks are GIF generation, WordPress startup and concurrent cache misses.

## Recommended installation flow

Choose a family and a specific static style/weight, then explicitly choose **Install locally**. Explain the remote request before installation. Download only that variant server-side, verify it and its license, and publish the file atomically. Thereafter selection, rendering and saved previews use the local file, even when the upstream service is unavailable. Do not automatically update installed binaries or rewrite existing campaign font choices.

For the first version, use a small versioned catalog manifest with verified upstream locations, file digests and license metadata. That avoids requiring each administrator to provision a Google API key; it is not a claim to expose the complete live catalog. The official Google Fonts Developer API can supply the live catalog and variant URLs, but requires an API key. A full-catalog mode would cache server-side metadata and keep the key out of browser markup and logs.

Use static TTF/OTF variants verified against GD/FreeType. Do not save a CSS response or rename a WOFF2 file as TTF. Google's Developer API can return static instances for variable families by default; compatibility and character coverage still require tests. Avoid fetching all weights, scripts and preview fonts merely to draw a selection list. Render list names in the system font and preview only an installed selection.

## Persistent storage, not the plugin code directory

Resolve the site's upload base through WordPress APIs and use an owned directory such as `{uploads_basedir}/email-countdown-timer/fonts/`. A plugin upgrade replaces its code directory; a downloader must not put persistent user-selected assets exclusively in `/plugins/email-countdown-timer/fonts/`.

Retain read compatibility with the existing plugin-local directory. Give managed imports distinct IDs so an imported filename cannot shadow an existing campaign file. Resolve local paths without creating monthly upload directories on every image request. An offloading plugin must not remove the physical copy that GD needs; persistent local availability is an explicit hosting requirement.

Do not reuse a shared core font directory or delete unrelated media. On multisite, both the manifest and storage belong to a site. File ownership must be recorded independently of current timer references.

## Security and lifecycle requirements

Installation is an authenticated POST with a dedicated nonce and appropriate capability. Use the WordPress safe HTTP API plus an exact HTTPS origin/path policy; validate every redirect or disallow redirects. Do not accept arbitrary user-provided URLs. Apply bounded time, response size and total storage budgets. Validate the actual font signature/table structure, not only filename, MIME or extension. Reject executable/HTML responses, unsafe names and symlink escapes. Use temporary files, atomic publication and idempotent installation.

Record family, variant, upstream version, source, content digest and the applicable license. A Google Fonts listing is not a substitute for retaining the license attached to a particular file. Do not expose API keys, cookies or server credentials to upstream requests or logs.

A remove action must warn or refuse when a font is referenced by a timer. Full uninstall with explicit opt-in would remove only manifest-owned imported files and metadata, preserving manual files and other plugins' data. The existing 12.2.0 uninstall routine does not yet know about a future managed-font directory; lifecycle tests must be extended before adding it. No global cache flush or generic recursive deletion of uploads is acceptable.

## Required verification before implementation can be called complete

Test allowed/blocked sources, redirects, oversized/invalid binaries, timeouts, permission/nonce failures, unwritable storage, duplicate installs, concurrent installs, update survival, multisite isolation, missing local files and manifest-owned uninstall. Run actual GD rendering of the selected static variant and confirm no remote request in repeated public image calls. Run Plugin Check on the final distribution without including downloaded font binaries in repository test artifacts or release packages.

## Primary references

- [Google Fonts Developer API](https://developers.google.com/fonts/docs/developer_api)
- [Google Fonts and open-source use](https://developers.google.com/fonts)
- [WordPress plugin upgrade behavior](https://developer.wordpress.org/reference/classes/plugin_upgrader/upgrade/)
- [WordPress lightweight upload-directory lookup](https://developer.wordpress.org/reference/functions/wp_get_upload_dir/)
- [WordPress safe GET and redirect validation](https://developer.wordpress.org/reference/functions/wp_safe_remote_get/)
