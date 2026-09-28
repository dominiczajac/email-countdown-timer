# Current static fallback — 12.4.1

A warm image-cache hit is unchanged and takes no generation lock. On a miss, the plugin attempts a real session-owned MySQL/MariaDB advisory lock with a one-second acquisition wait. `acquired`, `busy`, `unsupported` and `error` are distinct outcomes; an error never produces a pretend lock. The legacy internal `acquire()` wrapper still returns only a real lock or null (and retains its two-second bound for explicit font copying).

If the image lock is busy or unavailable, the plugin renders **one frame at the current server time after waiting**, with the existing layout and pixel limits. The requested GIF remains a GIF. PNG/WebP retain their negotiated formats. The fallback is not written into the animation cache, and never reuses an old animated countdown. A countdown that has expired is clamped to zero. The response advertises `X-Email-Countdown-Mode: static-busy`, `static-unsupported` or `static-error`, with only a fixed classification and no database error text or identifiers. Normal successful responses retain existing no-store/no-transform/nosniff behavior.

This is graceful degradation, not a guarantee of available images under all loads. A single frame still costs CPU and memory. Invalid configuration, missing GD, native rendering errors or cache-publication exceptions can still return a sanitized 503; they are not falsely reported as successful images. The acquisition timeout does not bound a native encoder, network outage or full WordPress startup. There is no new automatic polling, cron, visitor ID, remote service or global rate limiter.

A successful owner still rechecks shared cache and verifies ownership before publication. If its session was replaced, it does not publish a full animation without the lock. Finally/shutdown/disconnect release remains. MySQL/MariaDB deployments with proxies or multiple primaries need explicit verification. An explicit `DB_ENGINE=sqlite` skips the unsupported advisory-lock query; other unidentified incompatibilities become an error outcome. The synthetic unsupported-mode test is not a full SQLite compatibility certification.

## Administrator diagnostics

Data Settings performs a non-persistent, zero-wait probe on a separate diagnostic lock name. It shows the result and the static-fallback policy. This does not lock a campaign or store a history of requests, and does not prove complete proxy behavior. No raw SQL error or visitor data is shown. The font-copy tool still requires a real lock; manual SFTP copying is its safe alternative.

## Verification

`tests/lock-outcomes.php` exercises all result states, error-display restoration, real-lock ownership, one-frame output and exact deadline boundaries. Existing renderer pixel comparisons remain frozen. `tests/http/benchmark.py` tests real loopback HTTP with eight PHP worker processes and both database and Redis image caches: normal cold bursts still require one publication and identical GIF bodies, held-lock requests must return a valid single frame, a past-deadline fallback must match the zero image, backend-error and unsupported-mode simulations must not populate animation cache, and normal rendering must recover. The SQL error injection is in a disposable test-only mu-plugin, not shipped code.

Reports distinguish complete loopback HTTP from production PHP-FPM/TLS/network performance and describe sample counts. Earlier versioned reports retain their old two-second/503 results rather than being rewritten. Google Fonts remains deferred. Commercial optimizer binaries and every email client's retry/cache behavior are outside this test scope.
