# Easy Countdown installation and upgrades

## New installation

Use WordPress 6.4 or later and PHP 8.1 or later with GD. Deploy on a currently security-supported PHP release. Animation also requires Imagick with GIF support and usable MySQL/MariaDB advisory locks on a consistent connection. GD FreeType support is needed for local TTF/OTF fonts. When locking is unavailable, cache misses use a current static image rather than an unprotected animation.

Upload the identified installation ZIP through **Plugins > Add New > Upload Plugin**. It installs into `wp-content/plugins/easy-countdown/`. The historical main filename inside it is `email-countdown-timer.php`; the package must not have another nested plugin directory. The display name is **Easy Countdown** and the translation domain is `easy-countdown`.

Activate Easy Countdown, create a test timer, and open its image URL while logged out. Basic Auth or site-wide restrictions can prevent email clients retrieving public timer images. The plugin does not bypass those restrictions.

## Moving from the older GitHub directory

An installation under `email-countdown-timer/` has a different WordPress plugin basename from `easy-countdown/`, even though both use the same settings. This transition is not an ordinary replacement of the old directory.

1. Back up the database and custom fonts. Copy any plugin-local fonts into the persistent location **before** replacing or removing their code directory. Preserve filenames and license notices.
2. Deactivate the old copy without uninstalling it. Then install and activate the Easy Countdown package. Do not run duplicate snippets or both copies together.
3. Check existing timers, their image URLs and your selected fonts. Reload old editor tabs. Update path-specific optimizer exclusions to the installed directory and purge affected HTML/optimized-script caches.

The new package detects an active copy at the known old basename and refuses to run alongside it. It does not automatically deactivate another plugin. **Do not uninstall either copy with data removal enabled while the other needs the shared data.** Review retention settings before removing an obsolete installation.

The GitHub repository remains `dominiczajac/email-countdown-timer`. Stored options, campaign IDs, `[ecd_timer]`, image query parameters, saved labels and existing time-zone interpretation stay compatible. New timers default to the site's configured time zone. Internal class names and the persistent font location are intentionally not renamed.

## Fonts and end images

Use the existing persistent per-site directory, normally `wp-content/uploads/email-countdown-timer/fonts/`. Multisite appends a site-ID directory. See [Local font storage](../LOCAL-FONT-STORAGE.md) and [Font HTTP access](Font-HTTP-Access.md). No font binaries or Google importer are bundled.

The optional **After Countdown** image is selected from the site's Media Library. It stays user-owned and is not deleted with a timer or during uninstall. See [End image](End-Image.md) for formats, limits, deadline behavior and email-cache limitations.

## Later updates and removal

There is no automatic GitHub updater. Use the canonical installation ZIP for an identified tested revision, back up first, and replace the existing `easy-countdown/` copy. Deactivation retains settings. Uninstall removes owned data only after an explicit opt-in; see [Data removal](Data-Removal.md). Removing or deactivating the active implementation stops its image endpoint regardless of retention settings.

Before a campaign, test create/edit/delete, fonts, the 24-hour threshold, expired deadlines, the selected end image and actual email clients. Repository publication and a clean automated report do not mean the existing WordPress.org submission has been approved.
