# Multisite theme and activation compatibility

Continues roadmap #20 from integrated main `a4cb9a6`. This tranche adds real
subdirectory-network HTTP/browser coverage alongside the existing lifecycle,
Gutenberg and isolated vendor suites. The installed plugin and frozen renderer
oracle remain unchanged.

Use three owned synthetic sites with the same campaign ID and distinct saved
settings. Compare per-site and network activation on classic Twenty Twenty-One
and block Twenty Twenty-Four, with JIT 1235 and disabled (eight cells). Each cell
uses three counterbalanced plugin-off/on pairs. Observe actual blog/theme/plugin
context, anonymous PNG/GIF bytes and live countdown behavior, admin/media access
and unchanged sibling campaign data. Network activation must apply to all three
sites; per-site activation must leave siblings inactive. Inspect native logs
again after services stop and fail closed on incomplete evidence.

This certifies only the named baseline configurations. It does not extend the
previous single-site cache-engine results to multisite, arbitrary themes,
domain mapping or subdomain networks. Paid packages and real sender/client
acceptance remain separate. No release, deployment, native Wiki or environment
publication is included. Exact dependencies, negative outcomes and hosted
current-head results are recorded before integration.

The workflow also has a separately labelled [WP Super Cache profile](MULTISITE-SUPER-CACHE.md).
The original eight baseline cells retain their uncached-origin requirements;
early vendor-cache evidence applies only to that explicit profile.

## Concrete fixture

WordPress 7.1.2 retains the official archive SHA256 from the earlier profiles.
MySQL 8.4.13 uses the official image digest from the LSAPI fixture. The official
GPL-2.0-or-later themes are development dependencies: Twenty Twenty-One 2.9
(classic) and Twenty Twenty-Four 1.6 (block). Their archive SHA256 values are
`51d4ac1dcaef18b0e35a1a313bce8115d3aa4ff315bb1143f95c223c38817d8b`
and `9128150eff2682bf07ca7fdada2d2ed86a8f0d9210814d5369241e51e7a50baf`.
Every installed theme file must match its pinned ZIP, with no missing/extra or
linked files. WP-CLI 2.12 has no `theme verify-checksums` command; the initial
local attempt failed closed, then the byte-for-byte verifier replaced it.

The network consists of `/`, `/blue/` and `/red/`. Each has `shared-timer`, a
private site-only campaign, a unique future deadline and image width 480/540/600.
Inventory and every HTTP probe must expose the actual blog/home/theme, activation,
FPM SAPI, OPcache/JIT, GD and configured PHP 30s/256MB controls. FPM request and
nginx response limits remain 35s/40s. Four anonymous page requests per state/site
must have distinct origin markers. On states decode two GIF and two PNG responses;
GIFs require all 60 one-second frames. Requests for each sibling's private
campaign must return the real 404 pixel, never that sibling's timer.

Real browsers visit every dashboard and Media Library. Active sites show the
correct preview/image dimensions and live deadline, tick/pause/resume without
per-second image requests. A real form save changes the blue campaign's private
name; CLI snapshots must prove both siblings unchanged and then all data restored
after another real form save. Off controls retain the literal shortcode and no
active endpoint, preview or live widget. This is not a claim about lower-role
editing permissions or a complete Media Library compatibility matrix.

An owned loopback-only FPM/nginx pair is stopped before evidence is accepted.
Both actual service exit codes, all three native logs, startup evidence and the
complete three-site report are mandatory. The existing FPM log gate is retained;
the new gate additionally rejects fatal signals/error/timeout markers across all
logs. Original failures cannot become positive through the exit trap. Browser
requests outside the fixture fail except for blocked stock Gravatar images.
Only the two known subsite prefixes can be normalized for the existing bounded
core-navigation cancellation policy; candidate AJAX/render requests remain fatal.

## Setup and review corrections

Initial native cells failed while replacing a theme already bundled with core
or collecting inactive-root inventory. The disposable installer now replaces
only its owned selected theme before byte verification. Inventory explicitly
loads the pure configuration helper, so an inactive root or a plugin active only
on blue does not require the runtime bootstrap. The existing renderer's `_t`
cache-buster is validated alongside `data-ecd-src`; no renderer URL changed.
Both Codex P1 findings were fixture defects and require fresh hosted results.

A doc-only Gutenberg run was blocked by core's late welcome-guide overlay. An
initial global network-idle wait also failed with core background traffic; that
attempt remains negative. A scoped Playwright locator handler now dismisses only
the known native guide through its real close control whenever it blocks an
action, and waits for dismissal. Other dialogs and native block assertions retain
normal actionability checks. Local CLI inventory in off/site-only states, all three actual
image references, distinct decoded widths and blue-only form save/restore were
observed. The local browser preflight uses its true `cli-server` SAPI and does
not replace the eight native FPM cells or the required editor matrix.
