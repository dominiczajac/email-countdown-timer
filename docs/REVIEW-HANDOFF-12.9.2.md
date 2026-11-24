# Tickloom Countdown Timers 12.9.2

Use `tickloom-countdown-timers.zip` from `plugin-check-evidence` in the CI run for this version. Upload it to the existing WordPress.org submission and reply in the same review thread.

WordPress request values are type-checked, unslashed when needed, and sanitized or validated for the consuming field. Text uses WordPress sanitizers. Timer IDs use the WordPress slug rules so older encoded IDs keep working. Numeric values and consent flags require valid values before they are used. Authorized forms retain their existing nonce checks.

The timer menu uses `tickloom-timers` and new embed codes use `tickloom_timer`. The former `ecd-timers` URL redirects authorized administrators to the new screen. The former `ecd_timer` shortcode is a compatibility alias for published content and is registered only when the tag is available. Public image and iframe URLs, saved options, and the Gutenberg block identifier keep their existing contracts.

> Hello WordPress Plugin Review Team,
>
> Thank you for the manual review. In version 12.9.2, I revised the reported request-handling paths to sanitize or strictly validate values before use and checked the adjacent timer and admin inputs. New embed codes use `[tickloom_timer]` and the timer menu is registered as `tickloom-timers`.
>
> Existing `[ecd_timer]` embeds still work through a compatibility alias, which is registered only if the tag is available. The former admin menu URL redirects authorized administrators to the new screen.
>
> Best regards,
>
> Dominic Zajac
