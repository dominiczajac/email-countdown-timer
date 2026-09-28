# Verification record for the 12.1.3 source update

Date: 2026-09-28. Keep this record distinct from the historical 12.1.1 audit. Test results below identify specific revisions, not all future commits.

## Repository protection

The owner installed `main-pr-ci-v1`, ruleset ID **24108510**. A live API readback confirmed active enforcement for `refs/heads/main`, no bypass actors, mandatory PRs, resolved conversations, strict `required-checks` from GitHub Actions (integration 15368), and blocked force-push/deletion. PR #1 was already merged by the owner. A committed JSON template alone was not treated as protection evidence.

## Uninstall lifecycle

[Run 36408297518](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36408297518) verified PR #2 head `75288fc758367f7f852e7c7a03388f804776486c`: **15 jobs passed**, comprising six isolated PHP/GD/Imagick configurations, eight real WordPress configurations and the aggregate gate.

| Dimension | Exercised |
|---|---|
| WordPress | 6.4 and current stable, observed as 7.1.2 |
| Topology | Single-site and three-site multisite |
| Storage | MySQL 8.4; database transients and Redis 7.4 with Redis Object Cache 3.0.0 |
| Lifecycle | Activation, default retention, deactivation, core uninstall, explicit per-site deletion, repeated uninstall and fresh-process cache reads |
| Isolation | Foreign options/transients/cache, near-prefix names, whole cron around uninstall and an unrelated scheduled event |
| Owned cache | Known format keys, 240 orphan/legacy keys per site, timeout-only rows and pagination |
| HTTP | Anonymous synthetic 60-frame GIF and unknown-ID 404 |

The 573 isolated lifecycle assertions remain separate from these integration checks. The broader PHP suite preserves the frozen pixel oracle and tests PHP 8.1–8.5, with an additional GD-only configuration.

Initial multisite failures occurred before uninstall: fixture creation with `wpmu_create_blog()` left `wp_installing(true)`, altering normal cache behavior. The harness now restores the original mode in finally and asserts normal mode. No cleanup/isolation assertion was removed; temporary tracing was removed. References: [wpmu_create_blog](https://developer.wordpress.org/reference/functions/wpmu_create_blog/), [set_transient](https://developer.wordpress.org/reference/functions/set_transient/).

PR #2 was merged through GitHub's normal PR endpoint after checking the expected head, with merge commit `fe3cb3600a4b509e1819638c40a95703a61d3288`. No protection bypass or direct main update was used.

## Plugin Check

The baseline [run 36405176179](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36405176179) on `cf693b4` reported 13 errors and 42 warnings even though its CLI exit code was zero. This prompted explicit report parsing rather than trusting the command status.

The PCP artifact from [run 36408514972](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36408514972), PR #3 head `89db9107d4f81c068b6d12953a7a6eb328a97543`, was downloaded and inspected: `Success: Checks complete. No errors found.`, empty stderr and exit code 0. Tools: Plugin Check 2.1.0, WordPress 7.1.2, PHP 8.4.26 and WP-CLI 2.12.0. The artifact records the tested merge revision and distribution hashes. This paragraph verifies the PCP report; check the full latest CI separately before merging subsequent documentation revisions.

There are no global check exclusions. Code-local annotations for binary responses, read-only requests, strict consent and controlled exception/SQL paths are explained in [Plugin Check review](PLUGIN-CHECK-REVIEW.md). A clean result with these annotations is not an independent security review or a claim that all code meets the complete WPCS ruleset.

Local PHP 8.4.23: 87 controller/translation assertions passed with native image tests explicitly skipped because GD was absent; 573 lifecycle-double assertions and four Python gate tests passed. Native image and database evidence comes from hosted CI, not the local editor.

## Remaining boundaries

The owner separately reported successful WordPress functional testing. No destructive operation or deployment was performed on the owner's live site. Automated integration runs use new local installations and synthetic data.

No claim is made for all hosting configurations, a production load benchmark, every mail client, real browser/screen-reader accessibility testing, arbitrary network sizes or independent human approval. The public endpoint still has no cache-stampede lock. Unknown external-only orphan cache entries retain their original expiry; unrelated cache is not flushed.

WordPress.org account identity, final submission, human directory review and native Wiki publication are separate tasks. No WordPress.org submission, release tag or GitHub Release is created by this record. UI modernization remains a separate PR.
