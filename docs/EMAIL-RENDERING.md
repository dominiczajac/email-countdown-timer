# Email rendering: supported construction and test boundaries

## Generated HTML

Use **Copy Email HTML** from the saved editor, not a screenshot or attachment of a previously generated GIF. The remote image URL is intentionally stable per campaign. New HTML contains escaped alt, numeric proportional width/height (maximum default display width 600 px), inline display/block/border/responsive sizing and an absolute deadline as text. The whole snippet belongs inside the surrounding campaign's content area. No scripting or animation CSS is embedded.

A blocked image must not hide the actual offer deadline. Write alt that describes both the countdown and optional end-image states, and retain important terms as normal text. Empty alt is appropriate only with equivalent nearby content. Source images, their title and personal information should not be used as recipient identifiers.

## Client-controlled behavior

- Thunderbird blocks remote content by default. Users can permit it for a message or sender. Do not force a bypass.
- Current Outlook can play animated GIFs, but client/OS animation preferences can result in a still frame. Test the actual Outlook edition/build, including classic Windows where relevant, rather than treating every Outlook as identical.
- Apple Mail Privacy Protection can retrieve images in the background before a person reads a message. A proxy may retain the response. This plugin does not personalize URLs to defeat that protection.
- Provider caching, remote-image blocking, style rewriting, dark mode and message-width choices can affect the result. No origin header can guarantee replacing bytes already held by a recipient/provider.
- One GIF contains 60 one-second frames, not a permanently connected countdown. If the actual deadline is inside that animation it includes the configured end image; previously downloaded completed animations do not fetch a new state.

## Automated tests in this change

`tests/delivery/fixture.php` obtains actual snippet/geometry from WordPress and creates a synthetic public end image. `layout.py` exercises normal, blocked-image, CSS-stripped and dark-canvas **browser simulations**; these are not branded mail-client certifications. `thunderbird.py` launches the pinned native Linux Thunderbird executable against a local multipart .eml, both with its remote-content blocking and explicit test-profile permission. It validates the synthetic image by pixels and preserves screenshots for visual review. A separate freshly armed 32-second campaign verifies changing live countdown frames and the transition to the selected image inside the same downloaded GIF. It compares only the located timer area, not changing desktop chrome. No message is sent externally, and this does not measure SMTP deliverability. Current outcomes belong in the PR verification report, not an assumed pass from the presence of these scripts.

Still required for a real campaign: actual Gmail web/mobile, Outlook classic/new/web, Apple Mail macOS/iOS, dark/light appearance, image permission on/off, narrow/wide viewports, pre-deadline/expiry/post-deadline and forwarded messages. Test through the actual sender so link rewriting, MIME and any tracking configuration are represented. Use synthetic campaigns and consented test inboxes. A commercial rendering lab can broaden client/OS coverage; no such account or results are supplied in this repository.

## Sources

- [Thunderbird remote content](https://support.mozilla.org/en-US/kb/remote-content-in-messages).
- [Outlook animated graphics](https://support.microsoft.com/en-us/office/the-animated-graphic-in-my-e-mail-message-doesn-t-work-e6ba1581-12bc-4d31-ba26-87fd9b05b8ae).
- [Apple Mail privacy settings](https://support.apple.com/guide/mail/change-privacy-settings-mlhlae4ca39e/mac).

No claim of exhaustive email-client, accessibility, privacy or delivery certification is made.
