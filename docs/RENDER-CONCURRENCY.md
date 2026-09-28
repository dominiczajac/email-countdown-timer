# Image generation concurrency

Source 12.3.0 uses session-owned MySQL/MariaDB advisory locks for cache misses. This is a shared-server coordination mechanism, not a global rate limiter or a guarantee of unlimited campaign capacity.

Warm hits are returned before any lock query. Unknown IDs and HEAD do not acquire a render lock. On an image miss, a prepared `GET_LOCK(name, 2)` waits at most two seconds for a 64-byte, hashed database/site/timer/format-scoped name. A queued request then rereads shared cache, bypassing the earlier request-local cache state. It renders only when the image is still missing for its signature/current 15-second bucket.

The lock has no time lease that could expire while a slow live renderer still owns it. Ownership is checked against the original connection before publishing. `finally`, a shutdown callback and ultimately database-session termination release it. An old/reconnected request cannot deliberately release another session's lock. No lock options, files or cron events are created; uninstall needs no new sweep.

A timeout or unavailable locking service produces an uncached 503 PNG with `Retry-After: 15`, rather than uncontrolled duplicate rendering or a fabricated/stale countdown. An email client might not retry; this tradeoff is intentional and must be included in capacity planning. A warm cached response can still be served when no lock is needed.

## Supported topology and failure boundary

The mechanism requires MySQL/MariaDB `GET_LOCK`, `IS_USED_LOCK`, `CONNECTION_ID` and `RELEASE_LOCK` on one consistent database session/primary. Verify managed database proxies, connection multiplexers, custom wpdb replacements, read/write splitting and multi-primary clusters before deployment. Locks do not coordinate independent MySQL primaries. SQLite compatibility is not claimed. A disconnected database session loses its lock; an old native renderer may briefly continue computing but cannot intentionally publish after failed ownership verification. Absolute serialization through arbitrary database failover cannot be guaranteed.

The two-second timeout bounds lock waiting, not a hung database connection/network or native image encoder. Configure database connection/query timeouts, PHP-FPM request timeouts and host-level capacity limits. Many distinct valid timers/formats can still consume concurrent workers. Protect public endpoints at the hosting layer as appropriate without adding plugin visitor tracking.

The external object-cache adapter must honor forced backend reads (`wp_cache_get(..., true)`). Database transients are reread after clearing only per-key Options API lookup state and its negative lookup map, not flushing a shared object cache. Redis and database-backed cases are exercised separately in HTTP CI.

## Verification method

`tests/http/benchmark.py` drives actual loopback HTTP requests against a newly installed WordPress, using a multiworker PHP CLI development server, MySQL and optional Redis. It records sequential cold PNG/GIF requests, warm concurrent requests, simultaneous cold and expired-bucket bursts, held-lock timeout, owner-process termination, failure cleanup, response headers and a synthetic privacy canary. A test-only mu-plugin counts cache publications through a WordPress hook; it is not shipped and the plugin runtime files are not instrumented or rewritten.

This includes WordPress startup and local HTTP transfer, unlike the older in-process renderer microbenchmark. It does not measure production PHP-FPM/TLS/CDN/remote networks, test either proprietary optimizer, establish an SLO from seven cold samples or prove that a real hosting plan supports a particular mailing-list size. Evidence and current-head results must be inspected before merging.

Primary references: [MySQL advisory locks](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html) and [reconnection effects](https://dev.mysql.com/doc/c-api/8.4/en/c-api-auto-reconnect.html).
