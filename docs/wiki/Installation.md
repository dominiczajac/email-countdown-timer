# Installation and Upgrades

## New installation

Use WordPress 6.4 or later, PHP 8.1 or later, and GD with PNG/GIF support. Animation also requires PHP Imagick and an available GIF codec in ImageMagick. Custom TTF/OTF fonts require FreeType support in GD. CI exercises WordPress 6.4 and current stable; test your own hosting configuration before deployment.

Extract the plugin into `wp-content/plugins/email-countdown-timer/`. The directory must contain `email-countdown-timer.php`, `uninstall.php`, `includes/` and `assets/`, with `LICENSE` and `readme.txt`. Do not create an extra nested plugin directory.

Activate the plugin, open **Easy Countdown**, create a test timer and open its image URL without a logged-in session. Email images must be publicly accessible. Basic Auth or site-wide access restrictions can block image retrieval.

## Upgrading

Back up the database, old code and custom fonts. Disable the earlier plugin or snippet before activating this version; duplicate implementations can register competing hooks and output.

Version 12.1.3 changes internal class names to `Email_Countdown_Timer_Plugin`, `Email_Countdown_Timer_Config` and `Email_Countdown_Timer_Renderer`, and constants to `EMAIL_COUNTDOWN_TIMER_FILE` / `EMAIL_COUNTDOWN_TIMER_DIR`. These internal symbols are not a stable integration API. Custom code using undocumented old class names needs review. Legacy aliases exist only in CLI tests, not in the distributed plugin.

The saved option `easy_countdown_timers`, shortcode `ecd_timer`, admin slug `ecd-timers`, image parameters `ecd_action`, `ecd`, `mode`, time-zone default and saved labels are unchanged. No data migration is required. Invalid or excessively large configurations are rejected; see [Configuration](Configuration.md).

Copy legally usable custom TTF/OTF files into `fonts/` next to the main PHP file. Fonts are not downloaded or bundled. Keep a separate copy because replacing the plugin directory can remove them.

## Updates and uninstallation

There is no automatic GitHub updater. Deploy an identified, tested version after reviewing changes and making a backup. Deactivation preserves timer settings. To remove data when uninstalling through WordPress, explicitly enable **Data Settings > Delete all plugin data when uninstalling** first. Default behavior retains data; merely deleting files does not run cleanup. [Data removal and multisite policy](Data-Removal.md).

Removing or deactivating the plugin stops its image endpoint regardless of data-retention settings. Already-sent campaign links then stop receiving countdown images.

Before a campaign, test create/edit/delete, your fonts, the 24-hour threshold, expired deadlines and intended email clients. A source archive is not evidence of a WordPress.org release or acceptance. Avoid rolling back to a known vulnerable public version.
