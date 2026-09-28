# Admin Interface

The 12.2.0 interface runs inside the existing **Easy Countdown** WordPress menu. All writes require `manage_options` and valid nonces. The option name, shortcode, image query parameters and existing campaign values remain compatible.

## Find or create a timer

The default screen is **Your Timers**, with ID search and 25 records per page. It shows deadline/time zone, a text status, **Edit** and **Get Embed Code**. It does not generate image thumbnails. Pagination limits HTML, not the size of the existing single database option containing all timers.

Select **Create Timer**. Enter a unique ID, deadline and time zone in **Schedule**, then choose colors in **Appearance**. **Typography and Size** contains local fonts and the existing numeric fields; **Custom Labels** contains the four campaign labels. Empty labels are allowed. Missing local fonts keep their saved names and fall back to the bitmap renderer rather than silently changing stored data.

A timer's ID is read-only during editing and enforced on the server. Reusing an ID in a new form returns an error instead of overwriting the existing timer.

## Save and correct errors

**Create Timer** and **Save Changes** submit explicitly. There is no autosave. Invalid fields are marked individually and listed in an error summary. With JavaScript, focus moves to the summary and linked fields open their enclosing sections. Server validation preserves entered scalar values; malformed non-scalar requests are rejected. A failed database write must not show a success message.

The unsaved-change badge tracks changes locally; browsers may warn before leaving an edited form. No request is made for each keystroke or color change. The contrast helper compares digit/label colors against the image background without changing campaign colors or guaranteeing accessibility of a complete email.

## Preview and embedding

**Saved Preview** loads a static image from the existing image endpoint. It deliberately does not pretend to show unsaved values. Save first to update the image. If Imagick is present, **Preview Animation** loads the GIF only after a click. **Stop Preview** returns to the static image. A hidden browser tab also stops the preview; no refresh polling is installed.

**Embed Codes** offers Image URL, Email HTML and Website Shortcode. The email HTML includes image alternative text and the absolute deadline as text. Review that text before mailing, especially after changing a campaign deadline. No image can guarantee correct countdown timing in all email clients because proxies, prefetch and caching remain outside this plugin's control.

**Copy Code** announces success only after the clipboard write succeeds. If access is unavailable, it selects the text and explains manual copying. **All formats (manual copy)** exposes normal read-only textareas for every format.

## Delete a timer

Expand **Delete Timer**, review the ID and campaign warning, and check the irreversible-deletion confirmation. The JavaScript-enhanced dialog initially focuses **Cancel**, supports Escape, and returns focus to its trigger. The server still requires nonce and confirmation if JavaScript is unavailable or bypassed. Deletion removes that timer and invalidates its cache; sent emails using its URL can no longer fetch a fresh countdown.

This is separate from **Data Settings**, which controls optional full cleanup during WordPress uninstall. That preference remains off by default. See [Data removal](Data-Removal.md).

## Accessibility and progressive enhancement

The implementation uses real labels, native inputs/selects/details, visible focus, field-error descriptions, a text status rather than color alone, 44px control-height targets and a narrow single-column layout. The timer table may scroll inside its own named region. Forced colors and reduced motion are accounted for. Native WordPress buttons retain the administrator's color scheme.

Without JavaScript, typography/label sections and all manual-copy formats are expanded, and normal forms still save. Script-dependent pickers, clipboard buttons and animation controls stay hidden. The panel uses system typography, not externally loaded web fonts.

The included Chromium tests verify selected interactions on real WordPress, not every assistive technology. Manual NVDA/VoiceOver, zoom, RTL, additional browser and color-scheme evaluation remain appropriate before claiming full WCAG conformance.

## Fonts

The selector currently reads installed local TTF/OTF files from the plugin's existing `fonts/` directory. It does not connect to Google Fonts. A future downloader must use persistent per-site storage, explicit installation and scoped cleanup; it must not download fonts as part of public image rendering.
