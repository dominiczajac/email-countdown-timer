# Optimization and Caching

The canonical [FlyingPress/WP Rocket compatibility guide](https://github.com/dominiczajac/email-countdown-timer/blob/main/docs/OPTIMIZATION-COMPATIBILITY.md) lists exact exclusions, code safeguards, limitations and vendor references for 12.3.0.

Keep ordinary pages cached. Bypass dynamic image requests where `ecd_action=render` at any cache layer that would otherwise store them. For LazyLoad issues, the image-tag keyword is `email-countdown-timer-image`. For delayed-script issues, the narrow path is `/email-countdown-timer/assets/countdown.js`. Admin CSS/JS are not front-end dependencies.

After changing alternative text, purge the cached page HTML containing the shortcode. Do not expect a new GIF to change an existing HTML attribute. Paid optimizer versions/settings are not all tested; use the staging procedure in the guide.
