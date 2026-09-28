# Plugin Check review

Date: 2026-09-28. This is a preflight workstream, not a WordPress.org acceptance or a security certification.

## Baseline evidence

[Plugin Check run 36405176179](https://github.com/dominiczajac/email-countdown-timer/actions/runs/36405176179) checked PR #3's initial revision `cf693b4` using Plugin Check 2.1.0, WordPress 7.1.2, PHP 8.4.26 and WP-CLI 2.12.0. Its artifact contains the exact tested source and tool versions. The report contained **13 errors and 42 warnings**, despite a zero CLI exit status. Not all findings are vulnerabilities: several concern metadata, naming, or context-sensitive static-analysis limitations.

## Changes under review

- Replace three short internal class names and two constants with the `Email_Countdown_Timer_` / `EMAIL_COUNTDOWN_TIMER_` prefix. Public option names, shortcode, image parameters, admin slug, cache keys and saved timer labels remain unchanged.
- Make the English interface and validation messages translation-ready, while keeping campaign label defaults as stable data rather than locale-dependent values. Test translated heading, error and attribute escaping.
- Escape numeric HTML attributes at their output boundary. Prefix template variables.
- Declare candidate version 12.1.3 consistently and use a numeric stable tag. Tested up to 7.1.2 is based on real single-site CI, not an inferred current version. No tag, release or submission is created by this metadata change.
- Split the uninstall file-access guard and inline the prepared SQL call so static analysis can recognize them.

## Narrow static-analysis exceptions

These are explicit code-local annotations, not global exclusions or a claim of a completely unsuppressed PHPCS scan:

| Location | Reason |
|---|---|
| Binary image output | Escaping GD/Imagick bytes as HTML corrupts the image. Sources are encoder output, its private cache, or a hardcoded PNG; Content-Type and nosniff are set. |
| Anonymous image GET parameters | Rendering is a public read-only endpoint. Requiring a user nonce would break email embedding; input normalization remains in place. |
| Read-only admin selection/notice | Capability checks protect the screen. A selected ID is normalized and escaped; the notice only checks a constant value. |
| Initial form action discriminator | It only selects the handler; capability and nonce checks precede every write. |
| Validation exception factory | Exceptions contain plain strings. The admin controller escapes once at the HTML sink; image errors reveal no exception message. |
| Uninstall database enumeration | There is no portable API to enumerate owned transient option names. A prepared keyset query runs only during consented uninstall; caching a changing deletion set is incorrect. Deletion still uses WordPress APIs. |
| Data-retention checkbox | Exact string `0`/`1` validation is deliberate. Malformed or escaped inputs are rejected rather than normalized into destructive consent. |

## Required verification

The revised workflow parses `strict-json` output and fails on any reported error or warning, malformed output, diagnostics on stderr or CLI failure. Synthetic negative tests prove that exit zero does not mask findings. The job is included in `required-checks`, alongside unit and real WordPress integration matrices. Check the latest revision's actual run; this document does not predeclare success.

Only the test harness supplies legacy class aliases to the frozen rendering oracle. No runtime aliases or changes to that oracle are distributed. The UI layout is deliberately outside this change. Updating custom code that directly called undocumented internal classes may be necessary.

References: [Plugin Check](https://wordpress.org/plugins/plugin-check/), [Plugin Check CLI](https://github.com/WordPress/plugin-check/blob/trunk/docs/CLI.md), [WordPress internationalization](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/).
