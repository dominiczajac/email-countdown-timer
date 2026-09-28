# Troubleshooting

| Symptom | What to check |
|---|---|
| The plugin is missing from the plugin list | Check that the main PHP file is directly inside the plugin directory and that PHP meets the 8.1 requirement |
| Class redeclaration error | Disable the old plugin/snippet before enabling the new version |
| The GIF is static | Check Imagick availability in the PHP environment serving web requests, not just the CLI |
| Transparent dot / blank image | Check the HTTP status: 404 for an unknown ID, 422 for invalid saved data, 503 for missing GD or a renderer/resource-budget error |
| 405 | The endpoint accepts only GET and HEAD |
| A font is missing from the list | It needs a TTF/OTF extension and must be a readable file in `fonts/`, without a symlink pointing outside that directory |
| Font size has no effect | Without TTF/OTF + FreeType, the plugin uses a fixed-size bitmap font |
| Incorrect Polish characters | Use a local font containing the required glyphs; the bitmap fallback does not provide full Unicode support |
| The image is wider than the value in the admin panel | The setting preserves minimum width, not scaling; long labels may widen the image |
| The timer shows an earlier state | Check the 15-second cache bucket, hosting/CDN caches, and email-client behavior |
| Form submission is rejected | Refresh the nonce and check permissions, the date and time zone, and field ranges |
| The time does not match expectations | Check the timer's time zone, server clock, campaign deadline, and daylight-saving transitions |

Public errors deliberately do not expose exceptions or server paths. `503` has several possible causes; the status alone does not identify one. Check extension configuration, limits, and safe server logs in a staging environment. The plugin does not create its own detailed exception log.

When reporting a bug, include the plugin version, WordPress/PHP versions, GD/Imagick availability, HTTP status, reproduction steps, and a nonsensitive test configuration. Do not paste passwords, keys, administrator cookies, a database, or recipient lists. For suspected vulnerabilities, follow `SECURITY.md` in the main repository.
