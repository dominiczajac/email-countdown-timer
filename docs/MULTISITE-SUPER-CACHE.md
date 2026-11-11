# Isolated WP Super Cache multisite profile

Continues roadmap #20 from integrated main `5810988`. Extend the existing
three-site theme/activation baseline with WP Super Cache, one cache engine at a
time. Compare the unchanged baseline and the cache profile on Twenty Twenty-One
and Twenty Twenty-Four, per-site/network timer activation and both JIT modes.
The vendor stays network-active; only Easy Countdown changes state.

Require actual anonymous page-cache hits with matching per-site origin markers
and owned cache files, separate URLs for each off/on state, and no sibling data
or image reuse. Reuse native GIF/PNG decoding, missing-sibling campaign controls,
admin/media/live-browser probes and real form save/restore. Retain uncached image
response policy, strict native limits and post-stop log/exit/report gates.

Pin the vendor archive and verify its installed files, record observed normal
vendor configuration, and keep negative setup/test outcomes. No distributed
runtime, public endpoint, option schema, font/license or frozen renderer change
is planned. This is named Simple/PHP cache coverage, not Expert/nginx rewrite,
CDN, object cache, other cache engines, arbitrary hosting or mail certification.
Full C3 and Gate A remain open. No release, deployment, native Wiki or environment
publication is included. Current-head hosted results and Codex/Claude static
review outcomes are recorded before integration.
