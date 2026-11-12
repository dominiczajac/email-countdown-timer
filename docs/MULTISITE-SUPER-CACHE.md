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

## Concrete profile and provenance

The separate vendor profile uses official GPL-2.0-or-later WP Super Cache 3.1.5,
archive SHA256 `5a1af59e8a8289083de64d28ded9fb1dfb56ee996d4a84d3ed1f76c5f2224eb6`,
and WordPress.org installed-file checksums. The prior single-site profile's
3.1.3 results retain their original version and scope. The vendor is a test
dependency only; all 32 distributed runtime files remain unchanged.

Use the vendor's normal initialization and `wp_cache_setting` APIs: caching and
supercache enabled, Simple/PHP mode, TTL 3600, slash checks on and known users
excluded. Compression/CDN/preload/dynamic execution/late initialization/debug
are off; cache rebuild is on. Its existing served-file response header is enabled
as fixed cache evidence. WP_CACHE and its genuine advanced-cache drop-in must be
present; no object-cache drop-in or second cache engine is allowed.

Each controlled state explicitly purges its owned network through the vendor
API. Two fixed warm requests precede two measurements on each independent page.
The first must execute the actual origin; both measurements need the normal
`X-WP-Super-Cache` supercache header, coherent origin markers and response-body
SHA256 equal to the exact site's cache file. The file's path binds the root/blue/
red site and state. Fresh native headers are required on origin responses;
early cache hits legitimately do not bootstrap the observer. Admin and both
image fetches still require actual PHP/blog/JIT context and no served-cache header.

Copied public cache files are retained under fixed evidence names before later
admin operations can invalidate them. Post-stop acceptance rechecks their hashes,
markers, public timer/live context and known paths, rejecting missing/changed or
linked snapshots. A browser cache hit gets its own bound snapshot; a browser
origin after a legitimate invalidation must instead prove its native context.
The original baseline still requires four fresh generations and all four native
contexts. None of these branches changes the baseline or native log gates.

The earlier runs test controlled steady states and private-name save/restore
isolation, without certifying automatic invalidation after visible edits.
Blog-only purge behavior, arbitrary ports/domain mapping and other vendor
settings remain outside scope. Local WordPress preflight observed separate cold/hit transitions and
repeated native GIF/PNG bypass for all three sites, with its real `cli-server`
SAPI reported. Hosted FPM evidence remains separately required. Sources executed
by pull-request CI are the expected synthetic merge, bound to the reviewed head
by Actions metadata and Git tree equality before merge.

## Schedule/style edit and explicit refresh experiment

The C3 edit check changes the existing blue site's timer through its real admin
form, changing its deadline, background and minimum image width, then restores
the exact original values. Compare anonymous HTML before the edit, after saving
without a purge, after the vendor's explicit purge, and after restoring with and
without that purge. Each observation retains its synthetic HTML, origin marker,
native context and, where applicable, the exact vendor cache-file hash.

Decode fresh PNG/GIF responses at the same immutable public URLs throughout the
cycle. Confirm the saved settings, new image dimensions/background, native cache
bypass, and unchanged sibling campaign hashes. The browser must show the
observed live deadline/background and updated image, including a cache hit that
still carries older HTML. A successful explicit purge must bring HTML back in
line with the saved timer. The matching baseline must stay current without a
vendor purge. No automatic invalidation result is assumed in advance.

The experiment extends each active comparison in the existing two-theme,
per-site/network activation and JIT matrix. Existing controls and post-stop
native gates remain required. Only tests and documentation change; the named
12.6.0 installation ZIP remains byte-identical. [PR #38](https://github.com/dominiczajac/email-countdown-timer/pull/38)
records the reviewed revision, hosted results and both static reviews. Integration
requires the full matrix and core CI to pass with no unresolved P0–P2 finding.

The initial edit experiment at head `371242b` failed before completing its first
visible edit. Its page-wide `timer_id` assertion also selected the hidden delete
form field, although the edited settings were saved successfully. The harness
now targets `#ect-editor` specifically. Failed run
[43188103744](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43188103744)
and its partial evidence remain negative; native bounds and acceptance checks
are unchanged.

Codex's first static review also caught a P1 evidence-file collision: browser
screenshots reused the native PNG response filename for the edited/purged stages.
Browser captures now use a distinct `-browser.png` suffix, covered by a filename
collision regression; native response hashes and post-stop replay stay required.

Claude's first review identified a P2 failure-isolation gap: an interrupted edit
left blue's changed settings in later comparisons. Failed states now invoke a
CLI-only recovery of the synthetic blue snapshot before the next comparison and
record its hash check separately. Recovery is not successful form acceptance;
the original failure still fails the whole run. A recovery exception or mismatch
also stays negative. Successful edit cycles must still restore through the real
form and match the original hashes without using that recovery path.

Run [43189539623](https://github.com/dominiczajac/email-countdown-timer/actions/runs/43189539623)
at `59e4619` exposed a second test locator error: passing an already form-scoped
locator into a `details.filter(has=...)` search made that relative descendant
search empty. Chromium reproduced zero matches there and one for the corrected
`#ect-editor details:has([name=fixed_width])` selector. All three interrupted
cycles recovered the original site hashes, with later off states still running;
the run remains failed. Fixed operation labels now locate such failures without
retaining arbitrary exception text, form bodies or nonces.

A local native renderer preflight found that GD/Imagick's GIF palette represents
the edited `#f8fafc` background as RGB `[247,249,251]`, one step below each source
channel; PNG is exactly `[248,250,252]`. The new color check allows at most one
step per GIF channel, keeps PNG exact, retains the actual decoded samples and
rejects larger changes/non-integer channels. This concerns only the new palette
comparison; frame timing, dimensions, native limits, original log gates and
retained-image hash verification are unchanged.
