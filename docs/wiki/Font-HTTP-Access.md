# Font file HTTP access

The timer reads fonts locally; their files do not need to be publicly downloadable. **Data Settings > Font File Access > Install Font Access Rules (Apache)** creates fixed directory deny rules only in the current site’s uploads font folder and preserves existing files. Protect any plugin-local legacy fonts through host configuration.

The button does not prove that the host enforces those files. Apache must allow the directive; nginx requires host-managed configuration. An index file alone is not protection. Test direct GET/HEAD denial and confirm that a timer can still use the font. Rules stay on uninstall for any remaining manual fonts. Verify server-side licensing separately.

See the [complete Apache/nginx instructions and verification procedure](../FONT-HTTP-ACCESS.md) and [local font storage](../LOCAL-FONT-STORAGE.md). Automatic Google Fonts import is not included.
