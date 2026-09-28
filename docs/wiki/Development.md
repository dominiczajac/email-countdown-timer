# Development, Testing, and Publishing

## Structure

`email-countdown-timer.php` loads the configuration, renderer, and controller. `includes/admin-view.php` contains the English admin panel. `assets/countdown.js` refreshes images when the tab becomes visible again. `tests/legacy-frame.php` is an isolated, CLI-only geometry reference from v12.1, not a second active plugin.

Do not change option names, shortcodes, URL parameters, or the way blocks are calculated and laid out without an explicit compatibility decision. Changing the test reference to hide a difference in the new renderer is not a regression fix.

## Tests

`tests/english-ui.php`, loaded by `tests/run.php`, checks English interface and validation text, new-timer defaults, preservation of saved labels, and English GIF frames. The historical pixel comparisons continue to use explicitly supplied legacy labels.

```sh
php tests/run.php
node --check assets/countdown.js
```

A full run requires GD, Imagick, and one of the system fonts supported by the test. The test temporarily copies a font and removes it after the comparison; no font should be added to the repository without an appropriate license. Tests use WordPress API stubs rather than a full WordPress installation. They do not verify compatibility with every WordPress version, ImageMagick policy, or email client.

CI requires GD, checks the expected presence or absence of Imagick, and runs a PHP 8.1–8.5 matrix. Changes go through a working branch, diff review, and passing CI. A separate integration test on staging is recommended; full PHPCS/WPCS and a production benchmark are not currently part of this suite.

## Installation package

In a local clone, after selecting a verified commit, run:

```sh
git archive --format=zip --prefix=email-countdown-timer/ \
  -o ../email-countdown-timer.zip HEAD
```

`.gitattributes` excludes tests, scripts, and CI configuration from `git archive`. Runtime files, documentation, `readme.txt`, and the license remain in the package. Do not include `.git/`, environment files, or private fonts in the distribution. An archive of a working branch is not automatically a stable release.

A minimal WordPress installation ZIP can include only `email-countdown-timer.php`, `includes/`, `assets/countdown.js`, `readme.txt`, and `LICENSE`. The readme links to the complete online documentation.

## Native GitHub Wiki

GitHub stores the Wiki in the separate `email-countdown-timer.wiki.git` repository. The `docs/wiki/` directory does not publish itself to the Wiki tab. If the Wiki has not been initialized, the owner must first create its initial page through the GitHub interface.

The script requires local Git, Python 3, write access to that Wiki, and a configured author identity. It uses normal Git authentication; do not store a token in the repository or send one in a conversation.

```sh
# Preview changes without committing or pushing:
bash scripts/publish-wiki.sh

# Publish to the separate Wiki repository:
bash scripts/publish-wiki.sh --publish
```

The script clones the Wiki into a temporary directory, copies only the pages supplied in `docs/wiki/`, adjusts local Markdown links, and publishes without force-pushing. It does not delete other pages. Existing pages with the same names will be replaced by the repository versions: review the displayed diff before publishing. If the remote changes concurrently, a normal push may be rejected; repeat the preview using a fresh clone.

Source: [GitHub — adding or editing wiki pages](https://docs.github.com/en/communities/documenting-your-project-with-wikis/adding-or-editing-wiki-pages).
