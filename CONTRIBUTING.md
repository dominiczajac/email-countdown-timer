# Contributing

Work on a separate branch and describe the problem, scope, and compatibility impact. Add a regression test for each fix, run `php tests/run.php` and `node --check assets/countdown.js`, and then check all CI jobs for the current commit.

Do not change the `easy_countdown_timers` option, `ecd_timer` shortcode, image URLs, saved labels, time-zone default, image layout, or frame count/timing without agreement. Keep the v12.1 test renderer as the reference; do not adapt it to the new implementation. Document changes to invalid-input behavior and safety limits in the changelog.

The interface and new-timer label defaults are English as of 12.1.2. Keep them English and preserve existing saved labels, including custom and empty values. Test both new and existing timers when changing UI text.

Validate input before use, escape output, and preserve capability and nonce checks. Do not add secrets, production data, font files, or new external services without reviewing permissions and licenses. Pin CI actions to full commit SHAs and keep token permissions minimal.

The README is the administrator's starting point, `docs/wiki/` contains the guides, and the audit report records test evidence and limitations. Update the documentation alongside code changes. Isolated tests do not replace staging with a real WordPress installation. See `SECURITY.md` for vulnerability reporting.
