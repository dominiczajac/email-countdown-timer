# Native Gutenberg block

## Usage

Insert **Easy Countdown** in the block editor and choose a saved timer. Set left, center or right alignment in the block sidebar. Administrators can open the selected campaign settings in a new tab; users who can edit a post may embed a campaign but do not gain `manage_options`. Create and configure campaigns in the existing Easy Countdown panel.

The preview is one static image, without background polling. With no campaigns, the block shows an explicit empty state. Administrators can open Quick Create in a new tab; other editors are asked to contact a site administrator. On return, **Refresh campaigns** updates the shared list in all mounted timer blocks without reloading the editor or rewriting selected IDs/alignment. **Refresh preview** independently updates the selected static image. A failed list request retains the previous list and selection, with explicit retry guidance; it never retries automatically. Save work before reopening an editor with an expired session.

At most 250 IDs are supplied to an authorized editor; when truncated, the sidebar also accepts another known ID with an explicit Apply action. Missing or deleted references show an editor warning and produce no public image. The block stores `timerId`, `alignment` and an optional `displayMode`, not copied deadlines, font settings or image data. Changes to campaigns apply when the page is rendered again; purge page HTML caches after changing alt or geometry.

Shortcodes and classic-editor usage are unchanged. Dynamic blocks are empty when the plugin is inactive and resume when reactivated. Existing posts are not migrated.

## Live text on websites

The sidebar **Website display** defaults to **Image**. Select **Live text** to
update digits locally for as long as the page stays open, without image polling.
The editor continues to show a static campaign image, with explicit guidance that
the published text uses theme typography. Labels, colors, hidden days and the
absolute deadline come from the saved campaign. Image fonts/sizes/width do not
control live text. No saved campaign is migrated or copied into the post.

Visitors can pause/resume; reduced-motion visitors start paused with a current
snapshot. Hidden pages suspend ticking and catch up when visible. Digits are
silent to screen readers; the exact deadline remains accessible. Without
JavaScript the component labels itself a snapshot. A configured end image makes
one existing static image request at expiry and fits the reserved stage; failure
leaves zero digits visible. Browser clocks must be accurate. Purge page caches
after campaign changes; cached HTML does not poll for new settings. See the
[decision and limits](LIVE-WEBSITE-COUNTDOWN.md).

## Architecture and boundaries

`block.json` (Block API 3) is registered with WordPress core. Only editor assets depend on the core block/element/component/data packages. Image mode delegates to the existing shortcode and dimensions adapter: no second image renderer, frontend framework, polling, new public listing API or campaign mutation. The block has no custom REST route.

Native `block_editor_settings_all` supplies bounded IDs only after checking edit access to the concrete post, or site-editor capability for contexts without a post. It omits raw settings, attachment IDs, paths and deadlines. Administrator links do not replace authorization in the destination form. Native WordPress escaping and the existing image-publication policy remain in effect.

Explicit refresh uses the authenticated `wp_ajax_email_countdown_timer_campaigns` action, with no `nopriv` action. POST carries only the action, post ID (0 for the site editor) and a core nonce bound to that context. The handler validates the ID, verifies the nonce and rechecks `edit_post` for an existing concrete post, or `edit_theme_options` for the site editor, before reading the list. It returns bounded IDs and editor URLs, never raw campaign configurations or campaign writes. The nonce does not replace capability checks. The manual same-origin request is the sole narrow exception in the browser-network privacy guardrails; new calls remain prohibited pending review.

Preserve existing URLs, shortcode, saved data, renderer pixels and timing, font paths, password-protected media handling and uninstall behavior. User privacy and mail-cache limitations in README still apply. No font downloads, telemetry, network importer or sender is added.

## Verification

`php tests/gutenberg.php` covers adapter behavior using the existing WordPress doubles. `tests/integration/gutenberg.php` exercises actual block registration, dynamic HTML, permissions, settings preservation and multisite isolation in the existing WordPress matrix. The dedicated Gutenberg workflow installs the canonical ZIP on minimum/current WordPress with a classic and a block theme; `tests/gutenberg/browser.py` exercises core insertion, native selector/keyboard/alignment, save/reopen, static preview, anonymous output and deletion.

These tests complement existing Plugin Check, image/security, HTTP, lifecycle, layout and email checks. Failures and skipped checks must remain visible. Exact executed results are recorded in the PR, not assumed from this description. They are not full WCAG certification or proof of compatibility with every editor extension, theme, cache or hosting setup.

This 12.6.0 candidate originally started from main `507183c572f4633badd26dbcfe1ab141d722dae8` (12.5.2). On 9 November 2026, the PR branch was synchronized with main `12fa546`, including the already approved optional Lato font from PR #16. Both block metadata and the font/license allowlist remain required by packaging checks. Diagnostic PR #13 remains separate. See the [native JIT investigation](GUTENBERG-JIT-DISPOSITION.md) for Gate A evidence and the remaining acceptance boundary. The branch update does not merge PR #17 into main or publish a release or WordPress.org submission.

## Upstream references

- https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
- https://developer.wordpress.org/block-editor/how-to-guides/enqueueing-assets-in-the-editor/
- https://developer.wordpress.org/reference/functions/register_block_type/
