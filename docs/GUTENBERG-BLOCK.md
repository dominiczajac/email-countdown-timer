# Gutenberg timer block

Status: implementation and verification pending. Baseline: main 507183c572f4633badd26dbcfe1ab141d722dae8 (12.5.2). This change is independent of the unmerged example-font PR #16 and diagnostic PR #13.

## Authorized scope

Add a dynamic easy-countdown/timer block for choosing an existing campaign, a static editor preview, left/center/right alignment and a settings link for administrators. The block stores the campaign ID and alignment only. Campaign creation, deadline, font, colors, alternative text and end image remain in the existing administration screen.

Use block.json, core editor packages and the current PHP shortcode/embed path. No new frontend script, image renderer, third-party runtime dependency, public listing endpoint, automatic migration, polling or telemetry. Keep existing shortcode, URLs, saved campaigns, password-publication policy, pixel limits and frozen renderer oracle intact.

Pass only bounded campaign IDs to authorized editor contexts using native editor settings; never expose the complete options record or file paths. Do not let an author use the settings link to gain configuration privileges. A missing timer must not create a broken public image. An unavailable editor must not break shortcodes or the front end.

## Acceptance

Test block registration and metadata, empty/invalid/deleted IDs, dynamic output, saved alt and dimensions, multiple blocks, alignment, campaign-data preservation, editor permissions and multisite isolation. Exercise insertion, selection, keyboard controls, save/reopen, editor preview and anonymous front-end output in real WordPress on the minimum and current versions. Run the existing native rendering, password-policy, lifecycle, HTTP, Plugin Check and delivery gates against the exact new head. Preserve failures and skipped coverage explicitly.

Implementation is not authorization to merge, deploy, issue a release or submit to WordPress.org. Do not claim new test results before they exist.
