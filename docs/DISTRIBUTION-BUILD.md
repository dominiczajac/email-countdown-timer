# Distribution build and privacy-policy follow-up

Stage 3 of the maintainer-approved audit plan, based on main `e2d70b1944dc4260a8302906a1e7152dd3c93fa8` (12.4.1). This initial scope record is not evidence of a completed build or test run.

## Required outcome

- One explicit runtime-file manifest and a deterministic ZIP builder shared by release packaging, Plugin Check and real WordPress installation/browser/HTTP tests.
- Fail on missing/unlisted runtime files, symlinks, unsafe paths or inconsistent version metadata. Do not bundle fonts, private files, tests or development documents.
- User-focused English readme with practical upgrade, local-font, image-alt, optimizer and fallback instructions. Do not invent a WordPress.org contributor username or claim directory acceptance.
- Suggested privacy-policy text through the WordPress admin Privacy Policy Guide, not automatic edits to a published page. No tracking or external service.
- Preserve the existing renderer, current static fallback, session locks, saved campaigns and frozen image oracle.

Google Fonts remains deferred. No production deployment, live-site load test, tag, release or native Wiki publication. Verification must use the final PR head and exact package contents before any protected merge. Later commits will replace this scope note with the implemented build instructions and verification boundaries.
