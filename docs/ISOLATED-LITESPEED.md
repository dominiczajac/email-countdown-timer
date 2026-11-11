# Isolated LiteSpeed server-cache profile

This continues roadmap #20 from integrated main `625c52a`. The existing
PHP-FPM cache/builder/commerce profiles and their qualified evidence remain
intact. This tranche targets actual OpenLiteSpeed 1.9.4 with LSAPI PHP 8.4 and
LiteSpeed Cache 7.9.3, using the official server image pinned by digest.
The server and vendor plugin are development/test dependencies only.

Use owned disposable sites, synthetic data and one cache engine at a time.
Compare a server baseline and a LiteSpeed Cache profile, each with three
counterbalanced Easy Countdown off/on pairs and both JIT modes. Record exact
image/native/plugin versions, settings and source/package hashes. Preserve
current PHP execution/memory controls and report real LSAPI/server limits.
Existing PHP-FPM results must retain their own SAPI and native-log gates.

An installed/enabled vendor is insufficient. Require ordinary anonymous page
hits using independent URLs, the origin-generation marker and actual server
cache evidence. The renderer must remain uncached with correct image bytes,
frame timing and response policy. Reuse saved-timer, Media Library and live
pause/resume browser probes. Inspect server/PHP logs after owned services stop,
and fail on incomplete/negative behavior or native evidence.

The fixture does not use production exports, customers, credentials, real mail,
QUIC.cloud services, a CDN, paid packages or another cache engine. This is a
bounded configuration, not all LiteSpeed options, Enterprise LiteSpeed, hosting
capacity, full theme/multisite or real sender/client certification. Preserve
earlier negative outcomes. Full C3 and Gate A acceptance remain separately
tracked. No release, tag, deployment or environment publication is included.

Implementation, observed settings and current-head outcomes are recorded in the
associated pull request before integration.

## Concrete fixture and gates

The official OpenLiteSpeed image uses digest
`sha256:84b5a648789f0c4ba1da9ffe3cb3ba79ae30a9c5c95f1e6a7f9302f8b273f2f8`
with an explicit Linux/amd64 platform. Observed server 1.9.4 / cache module 1.66,
LSAPI PHP 8.4.27 (`lsphp84 8.4.27-1+resolute`) and Imagick
`3.8.1-1+resolute`. MySQL 8.4.13 also uses a pinned image digest. WordPress
7.1.2 retains its existing archive SHA256; LiteSpeed Cache 7.9.3 additionally
pins its archive SHA256
`8ab0373e41a3e9d4329d8fa6f99f3afd096cc21ac2befb0a84dc005d8afb36d7`
and verifies its installed files against WordPress.org checksums.

One server worker owns an LSAPI pool configured for four PHP children. The
server connection timeout is 40 seconds and LSAPI processing limit is 35;
PHP `max_execution_time=30` and `memory_limit=256M` are admin overrides. Browser
and image responses must expose the effective PHP values and prove that PHP
cannot raise either value with `ini_set`. JIT and OPcache are directly observed.
The worker/process settings come from the loaded server configuration, whose
hash must match the controller's input. LSAPI consumes its environment options
during startup; the test does not treat absent request environment variables as
runtime measurements. CGI launcher CPU/memory lifetime rlimits are unset;
the per-request controls apply to persistent workers. This is a separate LSAPI
configuration, not a claim that its mechanism is PHP-FPM.

All Docker resources carry an unpredictable ownership label. The controller
clears endpoint/context/TLS selectors and uses the managed local socket while
preserving proxy/registry configuration. It mounts only the owned lab directory
and the bootstrap script, publishes HTTP/MySQL on loopback, and removes only
resources whose names and labels match. It downloads no runtime dependency from
inside the server. Packages are staged by normal host clients; WordPress HTTP
API calls, mail and external browser requests are controlled after setup.

The baseline has the server cache module disabled and no third-party plugin.
Its four page requests must have independent origin generations and no cache
hit headers. The vendor profile enables only public page caching, with TTL
1800, query-string caching enabled at the server and Guest/Object/Crawler/CDN/
QUIC.cloud/minification/lazyload features off. WP_CACHE is true but no advanced
or object-cache drop-in is present (LiteSpeed removed its advanced drop-in in
3.0.4). Two fixed warm requests precede two measurements; measurements must
both report `X-LiteSpeed-Cache: hit` with matching origin generations. The first
warm response must miss, and every hit must preserve its preceding generation.

Each active state requests the same GIF and PNG URL twice. Both responses must
remain `no-store`, avoid a server cache hit and decode as 600px images; GIFs
retain 60 frames and the primary GIF probe checks all 60 one-second delays.
Normal WordPress admin/media, saved timers and live text pause/resume probes
are shared with the existing harness. Reuse does not change the FPM SAPI/gates.

The server stops before mandatory OLS/PHP/stderr/container logs and Docker's
exit/OOM state are assessed. Missing logs, bad startup/worker evidence, server
errors, native faults, processing timeouts or an unclean stop fail the cell.
The exit trap preserves an original failure and requires successful stop,
native/report acceptance and cleanup. Local controls cover wrong/missing
metadata, a cache-enabled baseline, inconsistent hit markers, an image cached
on its second request, unlocked PHP limits, stale owner-state files and refusal
to clean another owner's resources.

## Local preflight observations

Configuration setup initially failed for an unsupported worker directive,
missing global access-log/CGI-launcher configuration, an assumption that the
server binary had a Debian package entry, and a root-owned unreadable INI file.
Those are retained setup failures, not countdown defects. The configuration
uses `httpdWorkers`, the canonical CGI launcher, the server's actual version
output and a readable final `zz-ecd.ini` override. Direct probes subsequently
observed LSAPI, real MISS/HIT transitions, effective locked PHP limits and JIT
1235 on. Native GIF/PNG preflight and exact-head hosted evidence are recorded
in the pull request; these local observations do not replace the four CI cells.
