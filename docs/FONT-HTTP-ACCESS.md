# Protect local font files from HTTP downloads

The countdown renderer reads font files from disk. It does not need their public URLs. Storing files under `uploads/` or the plugin directory does **not** make them private: a web server can deliver a known filename directly even when directory listing is disabled.

## Explicit local rule installer (12.4.3)

Open **Easy Countdown > Data Settings > Font File Access > Install Font Access Rules (Apache)**. The action requires `manage_options`, POST and a valid nonce. It creates these fixed files in the current site's persistent font root, and in an existing legacy font root where permitted:

- `.htaccess` containing `Require all denied`, covering the dedicated directory and its descendants.
- A non-executable `index.html` as a directory-index fallback. This index alone does **not** deny direct downloads.

Existing files, including symlinks, are never overwritten, merged, adopted or removed. If a file already exists, inspect it with your host; the plugin does not certify its content. Failures/partial writes require inspection. The parent uploads directory, WordPress configuration, font bytes and other assets are not modified. No external HTTP probe or automatic activation/update write is performed. Access-rule files remain after uninstall because manually managed fonts may remain.

On multisite, ordinary site administrators can install rules only in their site's persistent font root. The shared plugin-local legacy directory additionally requires `manage_network_options`. Do not place browser-served web fonts or unrelated files in these dedicated server-side directories: the rule intentionally denies **all** HTTP access there.

**A successful local write is not verification of HTTP protection.** Apache must permit the directive; nginx ignores `.htaccess`; upstream static serving or hosting aliases can bypass a different server's rules. Protection is not automatic merely because this plugin version is installed.

## Apache 2.4

The installer writes this fixed rule:

```apache
# Email Countdown Timer: server-side font directory only.
Require all denied
```

Apache must load authorization support and permit `Require` in this directory, for example through `AllowOverride AuthConfig` or an appropriate `AllowOverrideList`. With `AllowOverride None`, `.htaccess` can be ignored; disallowed directives can cause a server error. Your host should configure the narrowest appropriate exception rather than enabling overrides across every upload directory.

When you control the virtual host, a server-level rule is preferable:

```apache
<Directory "/absolute/site/path/wp-content/uploads/email-countdown-timer/fonts">
    Require all denied
</Directory>
```

Substitute the actual filesystem path shown by the plugin, repeat for any shared legacy font root, and validate/reload the server configuration. Do not copy a placeholder path unchanged. Apache-compatible products and proxy combinations need their own verification; the repository tests Apache 2.4 and nginx, not every LiteSpeed deployment.

## nginx

Ask the host to add a narrow rule to the relevant `server` block. For standard single-site public paths:

```nginx
location ^~ /wp-content/uploads/email-countdown-timer/fonts/ {
    deny all;
}

# Only when the old plugin-local font directory is still in use:
location ^~ /wp-content/plugins/email-countdown-timer/fonts/ {
    deny all;
}
```

These are URL prefixes, not filesystem paths. Adjust them for subdirectory WordPress installations, custom uploads, aliases and multisite (for example `/wp-content/uploads/sites/2/email-countdown-timer/fonts/`). Repeat for the actual public prefixes that serve font files. The `^~` prefix avoids ordinary regex static-file locations taking precedence; exact or nested locations and external serving layers still require review. Run `nginx -t` and reload through the host's normal procedure. Do not put nginx directives into `.htaccess`.

The WordPress render route `/?ecd_action=render&ecd=...&mode=email` must remain accessible. Do **not** deny the entire uploads directory, plugin assets or WordPress page containing a shortcode. FlyingPress is not a replacement for web-server access control, including installations without a CDN.

## Verify GET, HEAD and server-side rendering

Use an existing harmless test filename in the protected font root. Do not upload restricted font binaries just to check whether they are exposed. Test without an administrator session; do not share the file contents in logs or reports.

```sh
# GET: discard the response body and report only the status.
curl --max-time 10 -sS -o /dev/null -w '%{http_code}\n' \
  'https://example.com/wp-content/uploads/email-countdown-timer/fonts/test.ttf'

# HEAD must also be denied.
curl --max-time 10 -sSI \
  'https://example.com/wp-content/uploads/email-countdown-timer/fonts/test.ttf'
```

For the examples above, expect **403**, not 200. A deliberate host-level 404 can also conceal a file, but confirm that a real file exists and that denial, not a typo, caused the response. A 500 is a configuration error, not successful verification. Check alternate public aliases and any cache that might already hold a copy. Separately request a timer using that local font: it must still return a valid image. File permissions must continue to allow the PHP process to read the font.

CI exercises synthetic GET/HEAD requests against isolated Apache/nginx instances, unrelated-asset preservation and the negative case with ignored/absent rules. Real WordPress integration separately verifies local TTF rendering after rule installation. This is not a test of your production server, and no font bytes are bundled in the plugin or verification artifacts.

## Licensing and scope

Only install trusted fonts whose license permits your intended server-side use. Denying HTTP access reduces exposure; it does not grant a license, retroactively retract copies already downloaded, protect backups, or make a desktop license suitable for every automated use. Preserve license notices. Automatic Google Fonts import remains deferred. A configurable private directory outside the web root is not part of this release.

References: [Apache .htaccess guide](https://httpd.apache.org/docs/2.4/howto/htaccess.html), [Apache authorization](https://httpd.apache.org/docs/2.4/mod/mod_authz_core.html#require), [nginx access module](https://nginx.org/en/docs/http/ngx_http_access_module.html), [nginx location precedence](https://nginx.org/en/docs/http/ngx_http_core_module.html#location).
