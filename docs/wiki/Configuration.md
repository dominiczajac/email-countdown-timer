# Timer Configuration

The **Easy Countdown** admin panel is available to users with the `manage_options` capability. Saving and deleting also require a valid form nonce. The admin interface remains in Polish; the original field labels are included below to help you locate them.

| Field | Meaning and limits |
|---|---|
| Timer ID (`ID Licznika`) | Identifier used in the shortcode and URL; normalized by `sanitize_title`, with a maximum input length of 200 bytes. Lowercase ASCII letters, digits, and hyphens are the simplest choice. During editing, the field remains `readonly`, not `disabled` |
| Deadline (`Data Końca`) | A specific date and time, with optional seconds; stored as `YYYY-MM-DDTHH:MM[:SS]` or the equivalent with a space |
| Time zone (`Strefa Czasowa`) | An identifier supported by PHP, such as `Europe/Warsaw`; an empty field uses that value |
| Colors (`Kolory`) | Background `bg`, digits `dc`, and labels `lc`; hex values in `#RGB` or `#RRGGBB` format |
| Labels (`Etykiety`) | Defaults: `Dni`, `Godz`, `Min`, `Sek`; each may contain up to 256 UTF-8 bytes, not necessarily 256 characters |
| Font (`Czcionka`) | A local TTF/OTF file in `fonts/`; paths outside that directory are not allowed |
| Digit size (`Rozmiar Cyfr`) | 1–200; default 40; the value passed to FreeType |
| Label size (`Rozmiar Etykiet`) | 1–100; default 12; the value passed to FreeType |
| Width (`Szerokość`) | 0–4000 px; `0` means automatic, and a positive number sets the minimum width without scaling the content |
| Hide days (`Ukryj dni`) | Omits the days block when fewer than 24 hours remain |

The inherited admin panel labels the size fields as “px”, but GD/FreeType functions interpret the size argument in points. The same values are passed as in v12.1 to preserve the appearance. GD bitmap fonts have a fixed size and are not scaled by these fields.

In addition to field limits, the entire image has a budget: maximum dimensions of 4000 × 1000 and a maximum area of **400,000 pixels**. Not every combination of allowed sizes and long labels fits within that budget. For 60 frames, the cumulative limit is 24 million pixels; this is an input limit, not a guarantee of a specific RAM footprint.

## Dates and expiration

PHP interprets the date in the selected time zone. Relative deadlines such as `+1 hour` and nonexistent calendar dates are not accepted. PHP's daylight-saving-time rules are preserved; ambiguous times during daylight-saving transitions should be tested before a campaign.

After the deadline, the remaining time is clamped to zero. Whether the days block is visible still depends on the hide-days setting. The timer does not block purchases, forms, or coupons: the offer deadline must be enforced by the relevant sales system.

## Fonts and Polish characters

Font files are not bundled with the plugin. Choose a font you are entitled to use and check its supported characters. If the file or FreeType is unavailable, the plugin uses a GD bitmap font with limited Unicode support. The font list excludes symlinks pointing outside the fonts directory. There is no public font upload endpoint.

Source for size units and FreeType requirements: [PHP — imagettftext](https://www.php.net/manual/en/function.imagettftext.php).
