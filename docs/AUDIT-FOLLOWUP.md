# Audit follow-up

Accepted by the maintainer on 2026-09-28 against main `1cdb128a04cc7b23f6ff24c56fef12a420e2bb47`.

## Ordered implementation

1. Persistent per-site local fonts with contained lookup, explicit non-destructive legacy copying and ownership-scoped uninstall; measure renderer geometry before saving; explain bitmap character limitations. Preserve existing campaign font names, labels and image geometry.
2. Distinguish acquired/busy/unsupported/error lock outcomes; use a current, single-frame image rather than a stale animation when generation cannot safely be serialized. Never pretend a lock was acquired. Test deadline boundaries, concurrency and complete HTTP responses.
3. One reproducible distribution build for testing and packaging; user-focused readme and WordPress privacy-policy suggestions. Do not invent a WordPress.org contributor login or claim directory acceptance.
4. Separate maintenance and measured experiments: legacy form consolidation, defaults for new timers, asset versioning and WPCS. Do not move public rendering to an earlier hook without evidence.

Google Fonts importing is deferred. No external downloads, telemetry, visitor identifiers, production deployment or live load tests are authorized by this work. Existing shortcode and public image URLs remain stable. Frozen pixel comparisons must not be rewritten. Each completed change requires current-head CI/Plugin Check readback before a protected PR merge.

The first update from 12.3.1 cannot retroactively recover plugin-local fonts already removed by WordPress's directory replacement. Back up/copy those files before installing an update. Native GitHub Wiki publication is separate from editing docs/wiki.

This file records intended scope, not completed implementation or test evidence. Completed results belong in the PR and versioned verification notes.
