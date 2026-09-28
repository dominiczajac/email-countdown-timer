# Data Retention and Uninstall

Optional cleanup is included in the 12.1.3 source update. The feature was integrated through PR #2 after real WordPress/MySQL/Redis lifecycle tests; see `docs/VERIFICATION-12.1.3.md` in the main repository. It is not enabled automatically.

## Choose before removing the plugin

Open **Easy Countdown > Data Settings**. **Delete all plugin data when uninstalling** is off by default. Enable it and choose **Save Data Settings** only when you intend WordPress to remove this plugin's data during subsequent uninstall.

Saving the preference and deactivation do not erase timers. With the checkbox off or missing, uninstall retains saved data. With it on, uninstall removes the data below. To revoke consent, uncheck the option and save before uninstalling.

Back up the database and custom fonts first. Deactivate the plugin, then use **Delete** in WordPress's Plugins screen. Removing files through FTP/SFTP does not invoke `uninstall.php`. Standard WP-CLI uninstall can invoke cleanup, including with `--skip-delete` to test without deleting files; never use that command on production merely to test the feature.

Regardless of retention, removing or deactivating the plugin stops its image endpoint. Already-sent emails and published pages will no longer receive fresh countdown images. Retained settings do not keep the endpoint running.

## Owned data removed when enabled

| Data | Exact scope |
|---|---|
| Timer configurations | Site option `easy_countdown_timers` |
| Preference | Site option `email_countdown_timer_delete_data_on_uninstall` |
| Current database cache | `ecd_v1211_` plus exactly 64 lowercase hexadecimal characters, and its timeout |
| Legacy database cache | `ecd_img_` plus exactly 32 lowercase hexadecimal characters, and its timeout |
| Known external cache | Current PNG/GIF/WebP keys derived from saved IDs, plus matching keys found in the database |

Database enumeration uses keyset batches and includes expired, orphaned and timeout-only entries. Names are checked exactly before deletion; no broad `ecd_%` deletion is used. Options API calls keep the options cache coherent when persistent object caching is enabled.

The plugin creates **no cron events, custom tables, user metadata or post metadata**. It does not guess hook names, delete the shared `cron` option, remove posts/users, touch network options or flush another component's cache. Future scheduled work must add explicit owned-hook cleanup and tests alongside the scheduler.

## External cache and concurrency limits

WordPress's portable cache API cannot enumerate unknown keys. Legacy orphaned entries that exist only in Redis/Memcached and whose IDs are unknown retain their original 60-second TTL. The plugin deliberately does not flush the entire site/network cache. Cleanup does not erase backups, access logs or email-client copies.

Stop incoming traffic and in-flight rendering before uninstall when strict cleanup timing matters. Very large networks need a controlled maintenance window and hosting-specific resource tests.

## Multisite

Consent is per site. Network uninstall visits sites in batches and cleans only sites that explicitly opted in. One site's checkbox is not permission to erase another site's timers. The original blog context is restored, including when cleanup raises an error. No plugin-owned network-wide options exist.

## Verification boundary

`php tests/uninstall.php` runs 573 lifecycle assertions using WordPress/database doubles, including 205-site isolation, idempotence and SQL-failure context restoration. The separate hosted integration matrix uses real WordPress 6.4/current stable, MySQL 8.4, three-site multisite and Redis Object Cache 3.0.0. It tests actual uninstall, opt-out retention, opt-in cleanup, pagination, foreign options/cache/cron and reads from fresh PHP processes.

These are distinct layers, not proof of every hosting provider, multisite size or third-party integration. Test the intended deployment and retain backups.

Sources: [WordPress uninstall](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/), [WP-CLI uninstall](https://developer.wordpress.org/cli/commands/plugin/uninstall/), [delete_transient](https://developer.wordpress.org/reference/functions/delete_transient/).
