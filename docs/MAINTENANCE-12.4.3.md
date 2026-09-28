# Audit maintenance — 12.4.3

Approved follow-up from 12.4.2 (`e76ed199a167d6721178a207ee4a0cb02fd246cf`). Current-head hosted verification is recorded in the associated PR, not inferred from previous green runs.

## Implemented scope

- An explicit admin-only POST/nonce action creates fixed Apache deny rules and a non-executable index in font roots, without overwriting files or symlinks. nginx requires host configuration. Existing/manual fonts, settings and global uploads are untouched; no visitor-path writes or network probes. Guard files remain after uninstall. See [HTTP access](FONT-HTTP-ACCESS.md).
- A completed image may be returned after lock ownership loss only within the original freshness interval, without clock rollback or a crossed deadline. No shared cache publication is allowed. Otherwise use the current static frame. This avoids an unnecessary second render, not the CPU cost already spent on the first one.
- New timers inherit the site's WordPress time zone, including UTC offsets. Saved/legacy missing-zone deadlines retain their old interpretation. Edit POSTs without a timezone preserve the stored zone.
- The obsolete `save_timer` path keeps its authorization/nonce gate but returns 409 without writing or deleting data. Current editor save/delete, image URLs and options are retained. Tests of historical content/escaping now submit the supported form; additive tests explicitly prove that obsolete writes cannot mutate data. The frozen image oracle is unchanged.
- Update user-facing documentation and upgrade guidance, explain the one-second follower wait and permanent static behavior without usable locking, and add a focused font-access Wiki source page. Do not invent a WordPress.org contributor identity.

## Verification design and boundaries

The canonical ZIP is installed by WordPress, browser, HTTP and Plugin Check jobs. Native PHP regression suites additionally test the source tree. An additional required CI job checks isolated Apache/nginx denial of direct GET/HEAD to synthetic fonts, preservation of unrelated assets, and negative cases where rules are ignored/absent. Real WordPress separately renders from local TTF files after installing rules; opt-in removal retains guards for manual files. Font bytes are never included in artifacts or the repository.

Ownership-loss HTTP injection is a test-only query filter; no failure switch is shipped. Tests cover a fresh uncached 60-frame response and an artificial delay across the campaign deadline returning the zero frame, without cache writes. Actual database-proxy reconnection behavior still requires host-specific testing.

The privacy API guardrail has one reviewed exception for the exact fixed-content `fwrite` statement in the local admin-only rule installer. Other logging/network/tracking primitives remain prohibited. This static tripwire is not a complete data-flow or vulnerability proof.

Google Fonts, alternative expiring lease locks, private-directory configuration, unconditional Content-Length, blanket Imagick memory limits, full WPCS reformatting and early WordPress bootstrap are outside this change. Legacy font-copy functionality/precedence is kept, not expanded or removed. No production deployment, font-license grant, universal server/optimizer certification, native Wiki publication or WordPress.org submission is claimed.
