# Native Gutenberg block

## Usage

Insert **Easy Countdown** in the block editor and choose a saved timer. Set left, center or right alignment in the block sidebar. Administrators can open the selected campaign settings in a new tab; users who can edit a post may embed a campaign but do not gain `manage_options`. Create and configure campaigns in the existing Easy Countdown panel.

The preview is one static image, without background polling. Reopen the editor to refresh the saved list. At most 250 IDs are supplied to an authorized editor; when truncated, the sidebar also accepts another known ID with an explicit Apply action. Missing or deleted references show an editor warning and produce no public image. The block stores only `timerId` and `alignment`, not copied deadlines, font settings or image data. Changes to campaigns apply when the page is rendered again; purge page HTML caches after changing alt or geometry.

Shortcodes and classic-editor usage are unchanged. Dynamic blocks are empty when the plugin is inactive and resume when reactivated. Existing posts are not migrated.

## Architecture and boundaries

`block.json` (Block API 3) is registered with WordPress core. Only editor assets depend on the core block/element/component/data packages. The public render callback delegates to the existing shortcode and dimensions adapter: no second image renderer, frontend framework, polling, new public listing API or campaign mutation. The block has no custom REST route.

Native `block_editor_settings_all` supplies bounded IDs only after checking edit access to the concrete post, or site-editor capability for contexts without a post. It omits raw settings, attachment IDs, paths and deadlines. Administrator links do not replace authorization in the destination form. Native WordPress escaping and the existing image-publication policy remain in effect.

Preserve existing URLs, shortcode, saved data, renderer pixels and timing, font paths, password-protected media handling and uninstall behavior. User privacy and mail-cache limitations in README still apply. No automatic fonts, telemetry, network importer or sender is added.

## Verification

`php tests/gutenberg.php` covers adapter behavior using the existing WordPress doubles. `tests/integration/gutenberg.php` exercises actual block registration, dynamic HTML, permissions, settings preservation and multisite isolation in the existing WordPress matrix. The dedicated Gutenberg workflow installs the canonical ZIP on minimum/current WordPress with a classic and a block theme; `tests/gutenberg/browser.py` exercises core insertion, native selector/keyboard/alignment, save/reopen, static preview, anonymous output and deletion.

These tests complement existing Plugin Check, image/security, HTTP, lifecycle, layout and email checks. Failures and skipped checks must remain visible. Exact executed results are recorded in the PR, not assumed from this description. They are not full WCAG certification or proof of compatibility with every editor extension, theme, cache or hosting setup.

This 12.6.0 candidate originally started from main `507183c572f4633badd26dbcfe1ab141d722dae8` (12.5.2). On 9 November 2026, the PR branch was synchronized with main `12fa546`, including the already approved optional Lato font from PR #16. Both block metadata and the font/license allowlist remain required by packaging checks. Diagnostic PR #13 remains separate. See the [native JIT investigation](GUTENBERG-JIT-DISPOSITION.md) for Gate A evidence and the remaining acceptance boundary. The branch update does not merge PR #17 into main or publish a release or WordPress.org submission.

## Upstream references

- https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
- https://developer.wordpress.org/block-editor/how-to-guides/enqueueing-assets-in-the-editor/
- https://developer.wordpress.org/reference/functions/register_block_type/
