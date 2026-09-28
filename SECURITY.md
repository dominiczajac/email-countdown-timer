# Reporting Security Issues

Do not publish working exploits, credentials, or user data in a public issue. If private vulnerability reporting is available in the repository's Security tab, use it. Otherwise, ask the owner for a private contact channel, stating publicly only that you would like to report a vulnerability. These instructions do not imply that private reporting has already been enabled.

A private report should include the version/commit, minimal reproduction steps using test data, required permissions, potential impact, and the PHP, WordPress, and extension versions. Remove secrets and personal data from logs. Do not test other people's websites without their permission.

Version 12.1.1 includes fixes for validation, escaping, font handling, and rendering limits. It does not guarantee the absence of vulnerabilities. The public image endpoint can still be overloaded by sufficient traffic; session locking in 12.3.0 serializes each image cache key, but is not a global rate limit or WAF. Managed database topologies must satisfy docs/RENDER-CONCURRENCY.md. See `docs/SECURITY-PERFORMANCE-AUDIT.md`.

No SLA or support period for older versions has been declared. Before deploying, check the repository for current fixes and test in your own environment.

See [privacy boundaries](docs/wiki/Privacy-and-Local-Fonts.md). The static sensitive-API guardrail prevents accidental introduction of tracking/network primitives but is not a complete security proof. Keep WordPress, PHP, GD, FreeType, Imagick and the database patched; native-library vulnerabilities are outside this source-only audit. Public advisory searches for this exact repository/slug on 2026-09-28 found no clearly matching advisory; similarly named countdown plugins are different products. Search coverage is not exhaustive and absence of an advisory is not evidence of absence of vulnerabilities.
