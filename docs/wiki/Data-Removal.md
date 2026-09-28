# Data Retention and Uninstall

This page describes the proposed uninstall feature, not a released change to 12.1.2. Review and merge its PR, run integration tests, and increment the plugin version before distributing a new release.

## Choose before removing the plugin

Open **Easy Countdown > Data Settings**. The **Delete all plugin data when uninstalling** checkbox is off by default. Enable it and choose **Save Data Settings** only if you want WordPress to remove the plugin's data when you subsequently uninstall it.

Saving this setting does not erase timers. Deactivation does not erase timers. With the checkbox off (or the option missing), uninstall retains the plugin's saved data. With the checkbox on, uninstall removes the data listed below. To revoke consent before uninstalling, uncheck it and save again.

Back up the database and custom fonts first. Deactivate the plugin, then use **Delete** in WordPress's Plugins screen. Deleting files via FTP/SFTP does not invoke `uninstall.php`. The standard WP-CLI uninstall command can invoke it, including with `--skip-delete` when testing the uninstall routine without deleting files. Do not use that command on production just to test this feature.

Regardless of data retention, removing or deactivating the plugin stops its image endpoint. Already-sent emails and published pages that reference it will no longer receive fresh countdown images. Retained settings do not keep the endpoint running.

## What is removed when enabled

| Data | Scope |
|---|---|
| Timer configurations | Exact site option `easy_countdown_timers` |
| Uninstall preference | Exact site option `email_countdown_timer_delete_data_on_uninstall` |
| Current database image cache | `ecd_v1211_` followed by exactly 64 lowercase hexadecimal characters, and its transient timeout |
| Legacy database image cache | `ecd_img_` followed by exactly 32 lowercase hexadecimal characters, and its transient timeout |
| Known external cache entries | Current PNG/GIF/WebP transient keys derived from the site's saved timer IDs, plus matching keys found in the database |

Database cache enumeration is batched with a keyset cursor. This includes expired, orphaned, and timeout-only rows. Each name is validated before deletion; a broad `ecd_%` delete is not used. Options API calls invalidate WordPress option caches, including when a persistent object cache is active.

The current plugin creates **no scheduled cron events, custom tables, user metadata, or post metadata**. The cleanup therefore does not guess cron hook names or delete the shared `cron` option. It does not touch other plugins, posts, users, or network options. Future scheduled events must have an explicit ownership registry and dedicated unscheduling tests added alongside the scheduler.

## Persistent object cache limitation

WordPress's portable cache API does not provide key enumeration. An orphaned legacy key existing only in Redis/Memcached cannot be enumerated here when its ID is no longer known. Such image entries retain their original 60-second TTL and expire normally. The plugin deliberately does not flush the site's or network's entire cache. Database deletion does not erase hosting backups, access logs, or copies of images held by email clients.

Run uninstall after stopping traffic/in-flight renders when strict cleanup timing matters. On very large networks, use a controlled maintenance window and test resource limits first.

## Multisite

Consent belongs to each site. Network uninstall visits sites in batches and removes data only for sites that explicitly opted in. One administrator's checkbox is not blanket permission to erase another site's timers. The original blog context is restored, including when cleanup raises an error. There are no plugin-owned network-wide options to remove.

## Verification boundary

`php tests/uninstall.php` covers default retention, strict consent, capability/nonce/method checks, standalone-file guards, old and orphaned cache rows, more than one batch, external-cache behavior, preservation of unrelated data and cron, 205-site isolation, idempotency, and context restoration on SQL failure. It uses WordPress/database doubles, **not a real WordPress/MySQL/Redis integration test**. Test normal uninstall on single-site and multisite staging before release.

Sources: [WordPress uninstall methods](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/), [WP-CLI uninstall](https://developer.wordpress.org/cli/commands/plugin/uninstall/), [delete_transient](https://developer.wordpress.org/reference/functions/delete_transient/).
