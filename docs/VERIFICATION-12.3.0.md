# Verification — 12.3.0: alt, privacy, optimizer boundaries and HTTP

Recorded 2026-09-28. Runtime/test commit: `fba810d29ac848ad5171c24cc85a556f50810096`; tested merge revision: `b5fec8f3f4777d65bc6a2e256e7b2d7309dec7df`. [CI run 36422093131](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36422093131). Later documentation commits require their own current-head checks before merge.

## Artifacts read back

- MySQL/database image cache: artifact `10969633330`, SHA256 `1e772e850a802d4e5680e67d3809628c415ea8fbf7434401801fc9d412ea14fd`.
- Redis image cache: artifact `10970236524`, SHA256 `12d0dbf7821541a0dea13fe2265128a5e3c33200bd6a0a6c23e038688769538f`.
- Plugin Check: artifact `10970201579`, SHA256 `686a58ef6f500beccc966b2ddd753ca57eb284908cbb1ad57c483d725ba04162`. Explicit clean report, empty stderr, exit 0; 15 distribution files.
- Admin browser: artifact `10969298879`, SHA256 `8c210327a5ee21d2a56a12b684ffd0aa30bd777dc0f3bd9ae1905f2d523d05d8`. All 40 named browser checks passed, including actual database alt roundtrip, HTML escaping and explicit empty alt. Chromium 153.0.8010.52.

Raw HTTP reports are retained in [database.json](evidence/http-12.3.0/database.json) and [redis.json](evidence/http-12.3.0/redis.json). Hosted artifacts expire after 14 days. All artifact hashes above were recomputed after download. No font or test credentials are distributed.

## Full HTTP measurements

Loopback HTTP; fresh WordPress startup for each request; multiworker PHP CLI development server (8 workers plus parent), PHP 8.4.26, WordPress 7.1.2, MySQL 8.4.11, GD 2.3.3 and Imagick 3.8.1. Redis configuration uses Redis Object Cache 3.0.0 with the existing Redis 7.4 service. Local runner TTF, 600px minimum width and future deadline. Each configuration is a different CI job, not a controlled Redis-versus-MySQL performance comparison.

| Scenario | n per backend | Database median / observed p95 (ms) | Redis median / observed p95 (ms) |
|---|---:|---:|---:|
| Image-cache-cold static PNG | 7 | 29.352 / 62.070 | 28.716 / 34.855 |
| Image-cache-cold 60-frame GIF | 7 | 497.190 / 545.669 | 517.040 / 530.899 |
| Warm GIF, concurrency 8 | 40 | 37.256 / 54.816 | 47.370 / 69.529 |
| 8 simultaneous cold GIF requests | 8 | 501.105 / 535.120 | 535.956 / 551.485 |
| 8 simultaneous expired-bucket requests | 8 | 488.893 / 508.347 | 529.163 / 547.241 |

All normal measured requests returned HTTP 200. Each cold and expired-bucket burst produced **one cache publication and eight identical GIF bodies**, on both backends. The 40-request warm phases produced zero image publications. These observations support the inspected lock/cache path; the observer counts successful encoder-output publication, not arbitrary native instructions.

An independent session intentionally held the lock. The public endpoint returned 503 plus `Retry-After: 15` after 2015.672 ms (database) and 2015.417 ms (Redis), with no render publication. HEAD and unknown-ID requests still worked without waiting for that lock. Terminating the owner process freed the lock; a subsequent request succeeded. A test-only exception before cache publication produced sanitized 503, followed by successful recovery. **24 HTTP assertions passed for each backend.**

The test uses real time and aligns bursts away from bucket boundaries. The expired test changes the stored bucket to the previous interval; it does not wait a full TTL. Cold means the image transient is cleared, not a cold OS/opcache/database. TLS, CDN, remote network, commercial optimizers and production PHP-FPM are not included. Small-sample p95 is descriptive and close to the maximum; no SLO, sustained throughput or mailing-list capacity is established.

Aggregate PHP server CPU at start/end: 0.63/8.20 seconds for database and 0.58/8.72 for Redis. Sum of process high-water RSS at suite end: 780688/864240 KiB across nine processes. These are not per-request renderer memory costs or simultaneous unique resident-memory measurements. No speedup is inferred from older in-process benchmarks or differences between CI runners.

## Security and privacy scope

Review covered capability/nonce checks, bounded typed alt input, output escaping, font-path containment, image dimension limits, prepared lock SQL, ownership and exceptional cleanup, scoped uninstall, optimizer-sensitive output and runtime outbound/storage primitives. The frozen renderer is unchanged. Existing isolated tests plus the new alt/lock tests and the full WordPress/GD/Imagick matrix remain mandatory. Local PHP without GD passed 147 assertions (native images explicitly skipped), 573 uninstall-double assertions and four gate tests.

The runtime PHP/JS guardrail found no tracking-cookie/session/storage/telemetry primitives. Synthetic public HTTP requests added no Set-Cookie and made no outbound WordPress HTTP API calls. A synthetic recipient/user-agent canary was not found in the options database. This is bounded test evidence, not proof about every possible data sink or infrastructure. User-entered campaign configuration is stored by design; WordPress, other plugins, hosting/CDN/email proxy logs and backups are outside the plugin's control.

No exhaustive absence-of-vulnerabilities or GDPR/ePrivacy certification is claimed. Exact repository/slug public-advisory searches did not return a clearly matching advisory; search results were insufficient to certify absence. Native libraries and the deployed WordPress/hosting versions require their own patch/advisory review. MySQL/MariaDB session topology and the 503 tradeoff are documented in [render concurrency](RENDER-CONCURRENCY.md).

## Optimizers and fonts

Only optimizer timing/duplicate-execution simulations and generated markup/header checks are automated here. **FlyingPress and WP Rocket proprietary binaries were not installed/tested.** The English [compatibility guide](wiki/Optimization-Compatibility.md) uses current official vendor guidance and identifies precise assets and image-query exclusions; it does not recommend globally disabling optimization.

The shipped plugin makes no Google Fonts requests. [Manual installation and privacy boundaries](wiki/Privacy-and-Local-Fonts.md) distinguish working local TTF/OTF selection from the proposed automatic importer. Fonts in the legacy plugin directory still need backup before updates.

No live-site load test, destructive operation on the owner's site, deployment, tagged release, directory submission, native Wiki publication, independent approval or full accessibility/WPCS certification was performed as part of these tests.
