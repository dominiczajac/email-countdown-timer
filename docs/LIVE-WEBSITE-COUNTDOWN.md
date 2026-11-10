# Optional live website block

The website block currently embeds the same 60-frame GIF used by email. A page
left open can outlive that animation. Offer an explicit **Live text** block mode
which updates digits locally, while retaining **Image** as the default for every
existing block and keeping email URLs, image rendering and shortcodes unchanged.

The saved campaign remains authoritative. Resolve its deadline with the existing
PHP parser, send only the public deadline/labels/colors to the page, and calculate
remaining seconds from the absolute deadline on each tick. There is no campaign
API, polling, visitor storage, external font or per-second network request.
Changing a campaign still requires purging any cached page which embeds it.

Use theme typography, the saved colors, labels and hide-days choice. The image
font, image sizes and fixed image width remain image settings. Reserve the digit
stage and control space; an optional saved end image uses the existing static
image route once at expiry, within the same stage. An unavailable image leaves
zero digits visible. Long labels may truncate visually with their full text in
the title attribute. Campaign names and archive metadata remain private.

No digit animation or repeating screen-reader announcements. Show the exact
saved deadline as accessible text, provide a keyboard-operable Pause/Resume
control, start paused for reduced-motion users and suspend timers while the page
is hidden. JavaScript-disabled visitors receive a clearly identified snapshot
and the deadline. Browser clock accuracy is required; no network clock sync is
introduced. A paused snapshot may be stale until resumed.

Verification must cover default/invalid modes, hostile or malformed configuration,
DST and hidden-day parity, no-GD rendering, pause/resume, reduced motion, expiry,
visibility, duplicate execution, cached HTML, multiple timers, no-JS and narrow
layouts in real WordPress, plus the existing required CI matrix. This is source
work, not a release or maintainer live acceptance.
