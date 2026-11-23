# ActNow – Countdown Timers 12.9.0

- Proposed directory slug and translation domain: `actnow-countdown-timers`.
- Installation archive: `actnow-countdown-timers.zip`, with one root directory of the same name and the existing `email-countdown-timer.php` entry point.
- WordPress.org contributor: `ddoomm`.

Use the inner installation ZIP in `plugin-check-evidence` from the successful CI run for the reviewed source. Keep the reports and the source archive separate. The prior 12.8.0 screenshots and verification records describe Easy Countdown.

## Upgrade check

Back up the database and custom fonts. Deactivate the old Easy Countdown copy before activating ActNow, and keep the old directory until its custom fonts have been copied. Both versions use the same timer settings and uninstall preference. Test a previously saved timer, its existing public image and iframe URLs, a published shortcode or block, and both new and legacy fonts. Update path-based cache exclusions to the installed slug. The original block identifier `easy-countdown/timer` is retained for existing posts.

Use **Data Settings > Copy Legacy Fonts to Persistent Storage** to copy original font files to `actnow-countdown-timers/fonts/` in WordPress uploads. Review font access after the move. The Apache rule installer writes only in this site's new uploads font directory.

## Directory review

Upload the revised ZIP to the existing Easy Countdown submission using the WordPress.org dashboard, then reply in the review thread. Suggested short reply after the upload:

> Hello Plugin Review Team,
>
> Thank you for the review. I've uploaded the revised ZIP for my existing Easy Countdown submission. Please reserve `actnow-countdown-timers` as the new slug. The display name is ActNow – Countdown Timers.
>
> Best regards,
> Dominic Zajac
