# Installation and Upgrades

## New installation

Prepare WordPress, PHP 8.1 or later, and GD with PNG/GIF support. Animation also requires the PHP Imagick extension and a working GIF codec in ImageMagick. TTF/OTF fonts require FreeType support in GD. The plugin header declares WordPress 6.4 as the minimum; CI tests do not replace testing on a real installation.

Download the code, extract it into an `email-countdown-timer` directory, and place that directory in `wp-content/plugins/`. It must contain `email-countdown-timer.php`, `includes/`, and `assets/`. Keep `LICENSE` and `readme.txt` as well. Do not add another nested directory with a second copy of the plugin name.

Activate the plugin in WordPress. Open **Easy Countdown**, create a test timer, and check its link in a browser window without a logged-in session. An email image must be publicly accessible; Basic Auth on a staging environment or site-wide protection may prevent it from loading.

## Upgrading from v12.1 or a snippet

Back up the database and the old code, and make a separate backup of your custom fonts. Disable the previous plugin/snippet before activating this version. Both implementations use the `ECD_Plugin_Colons_Fix` class, so loading them at the same time causes a conflict.

The `easy_countdown_timers` option, `ecd_timer` shortcode, `ecd-timers` menu slug, and `ecd_action`, `ecd`, and `mode` parameters are preserved. Valid saved dates and configurations are read without migration. Corrupted data or configurations exceeding the new safety limits are rejected instead of triggering expensive rendering. Correct them in the admin panel; see [Configuration](Configuration.md).

Copy your own TTF/OTF files into `fonts/` next to the main PHP file. There is no automatic font download or font-license installer. Keep a backup: replacing the entire plugin directory may remove your fonts.

## Updates and uninstallation

The repository does not include an automatic GitHub updater. Deploy subsequent versions deliberately, after reviewing the changes and creating a backup. Deactivation or file removal does not delete the option containing your timers. To permanently delete a particular timer, use its form in the admin panel.

Before launching a campaign, test saving, editing, and deleting timers; every font you use; the 24-hour threshold; behavior after the deadline; and rendering in the target email applications. Rolling back a deployment should not mean returning to a known vulnerable version on a public server.
