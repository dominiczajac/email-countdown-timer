# Easy Countdown 12.6.0 catalog assets

These six PNGs are genuine browser screenshots of an owned, synthetic WordPress
7.1.4 installation using the 12.6.0 candidate. They are presentation assets for
the existing WordPress.org submission. They are outside the explicit install
manifest and the plugin's runtime `assets/` directory.

| File | Readme caption / view |
|---|---|
| `screenshot-1.png` | Dashboard Quick Create widget |
| `screenshot-2.png` | Saved deadline, time zone, appearance and included Lato |
| `screenshot-3.png` | Separate Website/Email embeds and copy guidance |
| `screenshot-4.png` | Native campaign selection and opt-in live text |
| `screenshot-5.png` | On-demand diagnostics, anonymous image test and redacted report |
| `screenshot-6.png` | Suggested WordPress Privacy Policy Guide text |

`capture.json` records the capture source, ZIP SHA256, all installed distribution
file hashes, exact screenshot bytes/dimensions/hashes and actual environment.
Capture used the real local `cli-server` SAPI; hosted FPM/JIT tests are separate.
The anonymous PNG decoded at 600×79, local checks and the clipboard report were
verified, and the capture had no JavaScript page errors. The demo uses synthetic
campaigns/administrator data, standard dashboard preferences and disabled
avatars. It blocks mail and external WordPress HTTP requests. No UI pixels were
edited; the widget and embed views use element screenshots as recorded.

The captured package has the same shipped file hashes as the final candidate
when only development docs/assets are added afterward. Verify every manifest
entry against the final `distribution.json`; a runtime/readme change requires
fresh package verification and, when it changes the view, fresh screenshots.

After maintainer acceptance and explicit publication approval, use only the PNG
files for the separate directory catalog-assets location. Do not place this
folder in the installation ZIP, publish a native Wiki, upload a materials ZIP as
a plugin, or treat these screenshots as maintainer live acceptance, real email
delivery or universal compatibility certification. See the
[current handoff](../docs/REVIEW-HANDOFF-12.6.0.md).
