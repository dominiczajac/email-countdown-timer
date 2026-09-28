# Audit maintenance — 12.4.3

Approved follow-up from 12.4.2 (`e76ed199a167d6721178a207ee4a0cb02fd246cf`). Implementation and current-head verification are pending in this draft.

Scope: explicit, narrowly scoped font-directory HTTP protection with host-specific limitations and no remote probes; safely reuse a newly rendered uncached image after lock ownership loss when still fresh; site time zone for new timers without changing legacy interpretation; retire the obsolete write path safely; simplify user documentation and explain animation requirements.

Preserve public URLs, saved campaigns, the frozen pixel oracle, real generation-lock ownership, privacy guarantees within their documented scope, canonical ZIP installation tests and repository protection. Do not add Google Fonts, a lease/option-lock backend, a public upload endpoint, telemetry or a contributor identity that has not been supplied. Keep font-copy compatibility; do not expand the migration mechanism.

Validation must include native renderer regressions, real WordPress/MySQL/Redis HTTP and browser tests, Plugin Check on the canonical ZIP, font GET/HEAD denial on isolated Apache and nginx configurations, and negative cases for unrelated files and server configurations that ignore protection files. No live-site configuration change or production load test is authorized by this implementation.
