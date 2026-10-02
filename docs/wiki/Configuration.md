# Timer Configuration

The **Easy Countdown** admin panel is available to users with the `manage_options` capability. Saving and deleting also require a valid form nonce. The admin interface, help text, and validation messages are in English as of 12.1.2.

| Field | Meaning and limits |
|---|---|
| Timer ID | Identifier used in the shortcode and URL; normalized by `sanitize_title`, with a maximum input length of 200 bytes. Lowercase ASCII letters, digits, and hyphens are the simplest choice. During editing, the field remains `readonly`, not `disabled` |
| Deadline | A specific date and time, with optional seconds; stored as `YYYY-MM-DDTHH:MM[:SS]` or the equivalent with a space |
| Time zone | A PHP-supported zone or UTC offset. New timers inherit the WordPress site zone; existing and legacy missing-zone behavior is preserved as described below |
| Colors | Background `bg`, digits `dc`, and labels `lc`; hex values in `#RGB` or `#RRGGBB` format |
| Labels | New-timer defaults: `Days`, `Hours`, `Minutes`, `Seconds`; each may contain up to 256 UTF-8 bytes, not necessarily 256 characters |
| Font | A contained local TTF/OTF in the dedicated persistent uploads root or the legacy plugin `fonts/` root; saved filename precedence is preserved |
| Digit size | 1–200; default 40; the value passed to FreeType |
| Label size | 1–100; default 12; the value passed to FreeType |
| Width | 0–4000 px; `0` means automatic, and a positive number sets the minimum width without scaling the content |
| Hide days | Omits the days block when fewer than 24 hours remain |

Saved labels are user content: upgrading does not translate or overwrite them, including custom or empty values. To translate an existing timer, click **Edit**, change its four **Labels** fields, and click **Save Changes**. Missing label values use the English defaults.

The admin panel labels the size fields as “px”, but GD/FreeType functions interpret the size argument in points. The same values are passed as in v12.1 to preserve the appearance. GD bitmap fonts have a fixed size and are not scaled by these fields.

In addition to field limits, the entire image has a budget: maximum dimensions of 4000 × 1000 and a maximum area of **400,000 pixels**. Not every combination of allowed sizes and long labels fits within that budget. For 60 frames, the cumulative limit is 24 million pixels; this is an input limit, not a guarantee of a specific RAM footprint.

## Admin workflow in 12.2.0

The list searches IDs and displays 25 timers per page. Open **Create Timer** or **Edit**, then use **Typography and Size** and **Custom Labels** for optional settings. Preview and embed codes reflect saved values only. Deletion is a separate confirmed action, not a checkbox on Save Changes. See [Admin interface](Admin-Interface.md).

## Dates and expiration

PHP interprets the date in the selected time zone. Relative deadlines such as `+1 hour` and nonexistent calendar dates are not accepted. PHP's daylight-saving-time rules are preserved; ambiguous times during daylight-saving transitions should be tested before a campaign.

After the deadline, the remaining time is clamped to zero. Whether the days block is visible still depends on the hide-days setting. The timer does not block purchases, forms, or coupons: the offer deadline must be enforced by the relevant sales system.

## Fonts and Unicode characters

One Lato Regular font is bundled under SIL OFL 1.1. Select Lato Regular (included) in Typography and Size; bitmap remains the default. Choose a font you are entitled to use and check its supported characters. If the file or FreeType is unavailable, the plugin uses a fixed-size GD bitmap font. New saves then require printable ASCII labels; arbitrary UTF-8 is not supported by that fallback. The font list excludes symlinks. Protect direct HTTP downloads separately; see [font file access](Font-HTTP-Access.md). There is no public font upload endpoint.

Source for size units and FreeType requirements: [PHP — imagettftext](https://www.php.net/manual/en/function.imagettftext.php).

## Alternative text (12.3.0)

Use **Image alternative text (alt)** for a short description such as “Registration closes Friday at 18:00”. Save explicitly. It changes shortcode HTML and new Email HTML, not pixels or URL parameters. Empty alt is valid only with equivalent adjacent content. Legacy records use their former fallback until edited; no bulk rewrite occurs. Purge affected page HTML caches and recopy email code after an edit. Already-sent email markup cannot be updated remotely.

[Optimizer exclusions](Optimization-Compatibility.md) · [Privacy and manual local fonts](Privacy-and-Local-Fonts.md).

## New timer time zone (12.4.3)

New timers start with the site time zone returned by WordPress, including a configured UTC offset. Editing never inherits a later site-zone change: a stored zone is preserved, while legacy records without a zone continue to mean `Europe/Warsaw`. No saved deadline is migrated. Reload old editor tabs after upgrading; obsolete `save_timer` submissions return 409 and cannot change data.
