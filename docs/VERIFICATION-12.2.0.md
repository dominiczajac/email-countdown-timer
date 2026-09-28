# Admin UI and Performance Verification — 12.2.0

Recorded on 2026-09-28. This is test evidence, not a full security, WCAG, WPCS or WordPress.org certification. No production deployment, live-site load test, Google Fonts import or native Wiki publication is implied.

## Identified source and evidence

Implementation head: `d05c08fea88119dc628eb4ac6f467c39e36edcc5`; tree `5fc2f098d973f8474137fc7e57500d5e7f7a9add`. Tested PR-merge revision: `c86ae6763af12cde1b0df94ead4b6ecfc7f946aa`.

[CI run 36415015653](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36415015653) supplies the measurements below. `admin-ui-evidence` artifact 10967128197 was downloaded and its SHA-256 verified as `1d3f06bec9e4b7efb323b7e162ba1affaa37a01a7d04902d30a86477dfc184d4`. It contains the browser-check report, real admin screenshots, asset budget and performance JSON. Evidence artifacts expire after 14 days; the essential results are recorded here.

Plugin Check artifact 10966968497 was also downloaded and verified (`e8430dd1cf460606cfd56098a0d67c24c4e0f5dae82706b7ede314e9468a502a`). PCP 2.1.0 reports `Success: Checks complete. No errors found.`, empty stderr and exit 0 against the 14 runtime distribution files. The existing narrow code-local annotations remain; there are no global category exclusions.

The run's first attempt was not wholly green: WordPress 6.4/single-site/Redis could not install WP-CLI and stopped with `wp: command not found` before testing. The retry completed successfully: all 16 jobs, including the full real WordPress matrix, browser/benchmark step, Plugin Check and `required-checks`, were read back as successful. This does not rewrite the failed first attempt. Any subsequent documentation commit still requires its own complete current-head gate before merge.

Raw recorded results: [performance](evidence/ui-12.2.0/performance.json), [browser checks](evidence/ui-12.2.0/browser-checks.json), [asset budget and test revision](evidence/ui-12.2.0/asset-budget.json).

## Admin implementation and browser checks

The server-rendered implementation has separate list/editor screens, ID search and 25-row pagination, visible labels, field errors, summary focus, retained input, duplicate-create protection, server-enforced read-only IDs and a distinct confirmed delete action. Preview starts static; animation is explicitly requested and can be stopped. Copy tools preserve manual alternatives and include a text deadline in email HTML. Font selection remains local-only.

The actual WordPress browser job completed **37 named checks**, in addition to Playwright locator assertions, using Chromium 153.0.8010.52. Covered cases include database save/empty-label roundtrips, client/server validation, all form labels and ARIA references, editor widths 320/375/768/1440px, focus in forced colors, Cancel-first deletion/Escape restoration, clipboard refusal, no-JS native saving/manual selection, actual confirmed deletion, unchanged uninstall opt-in and no Google Fonts requests. The no-JS layout keeps optional fields and manual formats expanded rather than relying on hidden script-only controls.

The initial browser attempt exposed an unreliable manual-format interaction without JavaScript. Progressive-enhancement defaults were corrected and the real no-JS save and selection path was rerun successfully. No force-click or assertion skip is used to report that path as passed.

Local PHP 8.4.23 passed **111 assertions**, with native image tests explicitly skipped because GD is absent in that local environment. The existing **573 lifecycle-double assertions**, four PCP-gate tests, PHP/JS/shell syntax checks and whitespace checks passed. Hosted matrix jobs retain actual GD/Imagick image regressions and the untouched frozen legacy renderer. Real lifecycle tests still cover WordPress 6.4/current stable, single/multisite and database/Redis caches.

## Admin asset cost

Custom CSS: **1,841 bytes gzip**. Custom JavaScript: **2,494 bytes gzip**. Total: **4,335 bytes (4.23 KiB)**, below the project's 15 KiB target. This excludes WordPress core assets, headers and the generated image. It is a compressed-size measurement, not a measured page-load speedup.

Browser tests confirmed the custom assets were absent on Dashboard and present only once on the plugin screen. There is no new front-end stylesheet, external UI font, React bundle, animated list thumbnail, autosave or background polling. Color/contrast work is local computation. The existing public renderer and front-end countdown script are unchanged; the plugin-version change invalidates older cache signatures once.

## Synthetic image benchmark

Environment: PHP 8.4.26, WordPress 7.1.2, Imagick 3.8.1, a disposable MySQL-backed instance on GitHub Actions. The script temporarily uses a local runner TTF and removes it afterwards; no font is bundled. The configuration uses 600px minimum width and a fixed future deadline. Results vary with font, text, image dimensions, native libraries and hardware.

| Scenario | Median ms | Observed p95 ms | Samples |
|---|---:|---:|---:|
| Bitmap PNG, renderer only | 1.095 | 1.520 | 9 |
| Local TTF PNG, renderer only | 4.658 | 6.155 | 9 |
| Bitmap GIF, renderer only, 60 frames | 259.904 | 270.141 | 9 |
| Local TTF GIF, renderer only, 60 frames | 506.363 | 513.535 | 9 |
| Bitmap GIF, image-cache miss, 1 saved timer | 264.289 | 279.166 | 9 |
| Local TTF GIF, image-cache miss, 1 saved timer | 514.251 | 523.530 | 9 |
| Bitmap GIF, image-cache hit, 1 saved timer | 0.030 | 0.051 | 31 |
| Local TTF GIF, image-cache hit, 1 saved timer | 0.043 | 0.068 | 31 |
| Bitmap GIF, image-cache hit, 250 saved timers | 0.210 | 0.243 | 31 |
| Local TTF GIF, image-cache hit, 250 saved timers | 0.227 | 0.274 | 31 |

Cold means the image transient was deleted before each measurement. Warm means a matching signature and the same 15-second bucket. **These are sequential PHP calls within an already bootstrapped WordPress process, with a warm Options API cache and fixed time.** They exclude complete WordPress startup, HTTP, network transfer, cold database/object caches and concurrent requests. The observed p95 with nine samples is effectively the slowest sample, not a statistically established service-level latency. Do not derive production requests/second from these values.

The bitmap GIF contained 4,774 bytes, represented by 6,368 base64 bytes in the cache; the TTF example contained 50,681 bytes and 67,576 base64 bytes. This demonstrates the expected approximately one-third storage overhead, not a reason to write arbitrary binary data to a text option. Whole-process peak PHP allocation was 55,902,208 bytes and Linux peak RSS was 149,744 KiB across the entire sequential suite. Neither is an isolated per-request renderer memory cost; native image allocations are not fully represented by PHP's counter.

## Performance assessment and priorities

1. **Avoid unnecessary GIF rendering.** The measured example makes the new static-first preview materially cheaper than a 60-frame render. No rendering occurs for each keystroke. Installed local fonts already avoid an external download in public requests; a downloader primarily adds convenience.
2. **Handle concurrent cache misses before large bursts.** Stable cache slots and font-metrics memoization exist, but there is no atomic per-image generation lock. Simultaneous misses can duplicate the expensive operation. A separate change should specify lock ownership, bounded lifetime, crash recovery and a tested response while another request renders; a non-atomic get/set transient is not a reliable lock. This PR does not silently change image freshness, HTTP cache semantics or frame count.
3. **Measure complete HTTP behavior next.** WordPress startup and real database/cache latency remain outside this microbenchmark. Use a disposable PHP-FPM/HTTP test with realistic bursts, both warm and newly expired cache, and explicit p50/p95/CPU/RSS/timeout reporting. Do not run a load test against the owner's active campaign URL without separate permission.
4. **Scale storage only when justified.** Pagination reduces DOM and admin work, but `easy_countdown_timers` remains one serialized option read by the public endpoint. The 250-timer warm-call result shows additional work; it is not a full cold-DB scaling benchmark. Splitting storage would require a versioned migration and is not part of a UI restyling.
5. **Profile smaller costs before modifying the renderer.** Repeated font-path resolution and duplicate deadline parsing are possible micro-optimization candidates; system realpath caching and existing glyph-box memoization already reduce some work. Compare actual profiles rather than attributing all TTF cost to disk I/O. Keep pixel/timing regression tests.

## Remaining boundaries

Manual NVDA/VoiceOver, additional browsers, real zoom/text-spacing/RTL combinations, all administrator color schemes, a full WPCS pass and production load behavior remain unverified. The current browser checks do not prove conformance with every WCAG criterion. The former plugin-local fonts directory remains vulnerable to replacement during plugin updates; back up custom fonts. A persistent Google Fonts importer requires its own implementation and lifecycle review described in [Google Fonts design](GOOGLE-FONTS-DESIGN.md).
