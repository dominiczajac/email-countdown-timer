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
