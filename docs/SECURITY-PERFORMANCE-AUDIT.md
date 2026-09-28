# Security and Performance Audit — 12.1.1

Date: 2026-09-28. Scope: the supplied Easy Countdown v12.1 code and preparation for publication in `dominiczajac/email-countdown-timer`. This report distinguishes code observations, completed tests, and verification limitations.

## Baseline and scope of changes

Original user file: `Wklejony tekst(2).txt`, SHA-256 `7b5203cc43181482a5f33d93109aaf169c25b8e559fe8a34b1121f185815b6e2`.

Repository baseline commit: `b258d4abcd5f7299d4191fd0ea507863548597ea` (README, .gitignore, and GPLv3; no plugin code). Implementation and test commit: [`2b88b16767ee1e4f37974c45b238824bd61b3b30`](https://github.com/dominiczajac/email-countdown-timer/commit/2b88b16767ee1e4f37974c45b238824bd61b3b30).

The earlier working ZIP was not used as the implementation baseline: it changed the admin panel's language, and its `disabled` ID field was not submitted when editing. The original `readonly` behavior was restored. The existing LICENSE file was preserved instead of replacing GPLv3 with the previously proposed GPLv2.

## Findings and fixes

| Original observation | Impact | Change |
|---|---|---|
| `edit` was inserted into the heading without escaping | Reflected XSS risk in the admin panel context | ID normalization and `esc_html()` on output |
| Raw POST values and missing type checks | Invalid data, warnings/exceptions, and incorrect configuration | `wp_unslash()`, array rejection, and date, color, and range validation |
| The font filename was appended to a path | Escape from the intended directory under invalid configuration | Basename, extension allowlist, realpath, and destination-directory checks |
| No image dimension or area limits | Risk of excessive allocation and public-endpoint load | Budget checks before `imagecreatetruecolor()` and field limits |
| A nonexistent timer generated a `+1 hour` countdown | Unnecessary work for arbitrary IDs and a false result | Inexpensive 404 with a transparent PNG |
| An invalid date received a fallback deadline | Misleading countdown | Rejection of invalid configuration |
| Missing `lc` in the cache signature | The label color could remain outdated | Full-configuration signature and invalidation on save |
| The timestamp bucket was part of the transient name | More keys and database entries over time | One timer/format slot with the bucket number stored in its value |
| Repeated calculation of the same metrics | Unnecessary FreeType calls in every frame | Per-render memoization |
| Frame zero was also drawn as a master frame | Additional rendering work | Reuse of the completed first frame |
| A separate inline listener for every shortcode | Redundant handlers and JavaScript interpolation | One external script, with data in escaped attributes |
| Missing Imagick returned an empty string | Broken image | Static GIF without Imagick |
| Unconditional buffer closing and a 120-second time limit | Risk of looping and extending expensive requests | Buffer-removability checks; no increase to the hosting limit |

The original save handler already checked `manage_options` and the nonce; those checks were retained and should not be described as a newly discovered authorization gap. This review found no basis for describing the font-path issue as unauthenticated remote code execution.

## Preserved behavior and deliberate compatibility boundaries

The option name, admin slug, shortcode, image URLs, Polish default labels, Europe/Warsaw time zone, geometry, colors, minimum width, hide-days behavior, and 60 one-second frames remain unchanged. No once-per-minute polling or recipient limit was added.

Changes affect invalid or excessive inputs: there are no fabricated timers; arrays and invalid dates are rejected; and the 400,000-pixel limit and ranges described in [Configuration](wiki/Configuration.md) apply. Without Imagick, the image is static. The package declares PHP 8.1 and WordPress 6.4 as minimum versions; we do not claim to have tested a full installation of every WordPress version.

## Verification evidence

[CI run 36394611197](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36394611197) for commit `2b88b16`: all six jobs completed successfully — PHP 8.1, 8.2, 8.3, 8.4, and 8.5 with GD/Imagick, plus PHP 8.4 with GD but without Imagick. Job statuses were verified and the logs for both PHP 8.4 variants were read.

| Run | Result confirmed in the log |
|---|---|
| PHP 8.4.26 + GD/Imagick | 258 assertions PASS |
| PHP 8.4.26 + GD, without Imagick | 137 assertions PASS |
| Local PHP 8.4.23, without GD | 28 assertions PASS; image tests explicitly skipped |

The suite includes 40 bitmap-image pixel comparisons against the original geometry, 60 comparisons using a system TTF font across the one-day threshold, GIF frame-count and delay checks for 60 frames, comparisons of decoded frames, cache handling, validation, and controller tests. The metrics test checks fewer than 100 unique measurements rather than repeatedly recalculating them across 60 frames. The test reference does not serve HTTP and is blocked outside the CLI.

Local hashes for all nine implementation/test/CI files were compared with the blobs in published tree `08ba7858b51cfef2c8d76dccd9eed52d6073dcf5`; all matched. The later documentation commit also updates the checkout pin after a CI warning about the older Node runtime. Check the latest revision's status separately in Actions.

## What was not verified

No full integration tests with a database and real WordPress installation, exhaustive email-client tests, load benchmark, or full PHPCS/WPCS run were performed. Controller assertions use WordPress API stubs. Tests are not a security certification or a guarantee of zero regressions.

There is no stampede lock or protection against mass traffic to valid IDs. Concurrent cache misses can still render multiple GIFs. We do not state a percentage speedup or throughput without measurement; the verified improvements are fewer redundant operations and preserved output in the tested cases.

Custom fonts, unusual labels, PHP/ImageMagick resources, and email-client behavior require a staging test before a campaign. The GIF and caching model does not guarantee a perfectly up-to-date countdown every time an email is opened.

## External basis for recommendations

[WordPress Security APIs](https://developer.wordpress.org/apis/security/) — validation and escaping; [GitHub secure use](https://docs.github.com/en/actions/reference/security/secure-use) — minimal permissions and action SHAs; [PHP imagettftext](https://www.php.net/manual/en/function.imagettftext.php) — FreeType and sizes in points. These sources describe platform principles, not the audit results for this plugin.
