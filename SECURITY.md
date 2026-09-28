# Reporting Security Issues

Do not publish working exploits, credentials, or user data in a public issue. If private vulnerability reporting is available in the repository's Security tab, use it. Otherwise, ask the owner for a private contact channel, stating publicly only that you would like to report a vulnerability. These instructions do not imply that private reporting has already been enabled.

A private report should include the version/commit, minimal reproduction steps using test data, required permissions, potential impact, and the PHP, WordPress, and extension versions. Remove secrets and personal data from logs. Do not test other people's websites without their permission.

Version 12.1.1 includes fixes for validation, escaping, font handling, and rendering limits. It does not guarantee the absence of vulnerabilities. The public image endpoint can still be overloaded by sufficient traffic; the plugin has no built-in stampede lock or WAF protection. See `docs/SECURITY-PERFORMANCE-AUDIT.md`.

No SLA or support period for older versions has been declared. Before deploying, check the repository for current fixes and test in your own environment.
