# Admin Interface

The 12.2.0 interface runs inside the existing **Easy Countdown** WordPress menu. All writes require `manage_options` and valid nonces. The option name, shortcode, image query parameters and existing campaign values remain compatible.

## Find or create a timer

The default screen is **Your Timers**, with name/ID search and 25 records per page. It shows an optional campaign name alongside the immutable ID, deadline/time zone, a text status, **Edit** and **Get Embed Code**. It does not generate image thumbnails. Pagination limits HTML, not the size of the existing single database option containing all timers.

Select **Create Timer**. Enter a required unique **Timer name**, deadline and time zone in **Schedule**, then choose the countdown preset/colors in **Appearance**. Internal IDs are generated on the server and hidden from ordinary management fields. Typography, custom labels and existing geometry controls remain available.

Renaming preserves the existing internal ID and every published image URL. Duplicate names are rejected, including names of archived timers. Legacy unnamed records remain valid until edited in the current form.

**Quick Create** on the timer list and dashboard offers Classic, Midnight, Ocean, Warm, Christmas, New Year, Easter, Valentine's Day, Halloween, Black Friday, Birthday and Wedding presets. The full editor offers the same choices in **Appearance**. They set background, digit/label colors and an available included font; choose your own deadline and time zone. Applying an editor preset changes the unsaved form, preserving custom/empty labels and schedule until you explicitly save. Quick Create also works without JavaScript. Classic uses Lato; Midnight/New Year use IBM Plex Mono; Ocean/Easter/Halloween/Birthday use Barlow Condensed Regular; Black Friday uses Barlow Condensed Black (900); Warm/Christmas/Valentine's Day/Wedding use Crimson Text. If a face or FreeType is unavailable, Quick Create uses bitmap fallback and the full editor preserves its selected font.

The required **Timer name** stays in administration only, never in generated IDs, image alt text, email HTML or public image caches. In Gutenberg only timer administrators receive names as choice labels; other content editors receive IDs. The dashboard widget shows friendly names with a clock icon and a **Countdown preset** selector. Its lightweight script initializes browser time zones; native WordPress styling is retained.

## Save and correct errors

The **Time Zone** selector initializes new forms from the browser zone when supported; without JavaScript it retains the configured WordPress site zone. Existing timers and duplicates preserve their saved zone, aliases and fixed offsets. Manual overrides remain available. Saving confirms the deadline and UTC equivalent.

**Create Timer** and **Save Changes** submit explicitly. There is no autosave. Invalid fields are marked individually and listed in an error summary. With JavaScript, focus moves to the summary and linked fields open their enclosing sections. Server validation preserves entered scalar values; malformed non-scalar requests are rejected. A failed database write must not show a success message.

The unsaved-change badge tracks changes locally; browsers may warn before leaving an edited form. No request is made for each keystroke or color change. The contrast helper compares digit/label colors against the image background without changing campaign colors or guaranteeing accessibility of a complete email.

## Preview and embedding

**Saved Preview** loads a static image from the existing image endpoint. It deliberately does not pretend to show unsaved values. Save first to update the image. If Imagick is present, **Preview Animation** loads the GIF only after a click. **Stop Preview** returns to the static image. A hidden browser tab also stops the preview; no refresh polling is installed.

**Embed Codes** offers Image URL, Email HTML and Website Shortcode. The email HTML includes image alternative text and the absolute deadline as text. Review that text before mailing, especially after changing a campaign deadline. No image can guarantee correct countdown timing in all email clients because proxies, prefetch and caching remain outside this plugin's control.

**Copy Code** announces success only after the clipboard write succeeds. If access is unavailable, it selects the text and explains manual copying. **All formats (manual copy)** exposes normal read-only textareas for every format.

## Delete a timer

Choose **Duplicate** to open an unsaved timer with the source schedule, zone, style and labels. Give the copy a different unique name and save; its internal ID is generated independently. The original is unchanged, and a copy starts unarchived.

**Archive** removes a campaign from the default list and dashboard shortcuts. It keeps the record, existing public image URLs and Gutenberg selections available, including already sent email links. Use **Archived** to search archived campaigns, edit, duplicate or restore them. Saving an archived timer keeps it archived. Archiving is not deletion and does not revoke a published URL.

Expand **Delete Timer**, review the timer and deletion warning, and check the irreversible-deletion confirmation. The JavaScript-enhanced dialog initially focuses **Cancel**, supports Escape, and returns focus to its trigger. The server still requires nonce and confirmation if JavaScript is unavailable or bypassed. Deletion removes that timer and invalidates its cache; sent emails using its URL can no longer fetch a fresh countdown.

This is separate from **Data Settings**, which controls optional full cleanup during WordPress uninstall. That preference remains off by default. See [Data removal](Data-Removal.md).

## Accessibility and progressive enhancement

The implementation uses real labels, native inputs/selects/details, visible focus, field-error descriptions, a text status rather than color alone, 44px control-height targets and a narrow single-column layout. The timer table may scroll inside its own named region. Forced colors and reduced motion are accounted for. Native WordPress buttons retain the administrator's color scheme.

Without JavaScript, typography/label sections and all manual-copy formats are expanded, and normal forms still save. Script-dependent pickers, clipboard buttons and animation controls stay hidden. The panel uses system typography, not externally loaded web fonts.

The included Chromium tests verify selected interactions on real WordPress, not every assistive technology. Manual NVDA/VoiceOver, zoom, RTL, additional browser and color-scheme evaluation remain appropriate before claiming full WCAG conformance.

## Fonts

Quick Create and the dashboard can create English, Polish, German, Spanish or French countdown labels without JavaScript. In the full editor, expand **Custom Labels**, choose a language and select **Apply label preset** to replace only the four label fields. Review and save explicitly. Selecting a language alone changes no campaign, and the WordPress admin locale never changes saved labels. Without JavaScript, use the native Quick Create language selector or edit the four text fields manually.

Accented labels require a suitable local font. All five included static faces cover the supplied label presets and Polish alphabet with FreeType. Active bitmap fallback accepts printable ASCII only; an accented preset produces a recoverable save error rather than changing the text. Empty and handcrafted labels remain supported. No font is downloaded. A label-language preset changes only labels; an appearance preset explicitly changes colors and font. Loading an existing campaign never switches its saved face.

The selector offers the five registered bundled faces and installed local TTF/OTF files from the persistent per-site directory, with legacy plugin-directory lookup where applicable. It does not connect to Google Fonts. Use the existing persistent storage/copy flow for custom fonts; no font is downloaded during public image rendering.

## Saved animation and email copy

The saved preview starts automatically when JavaScript is enabled, the page is visible and reduced motion is not requested. It refreshes after 58 seconds to continue from the current server time. There is no Preview Animation button. Reduced-motion and no-JavaScript views keep the static preview. Copied Email HTML is image-only; add deadline text in your own email content. Outlook animation follows client settings and is not forced. No Custom CSS or per-timer Clean uninstall control is added.

Saved previews and Gutenberg now use locally ticking raster frames. The Gutenberg canvas has no static-preview copy or refresh buttons; actions remain in its sidebar. The editor has no save-status badge. Copy the new External website (iframe) HTML under Embed Codes for other websites; see the main README for cache/header requirements.
