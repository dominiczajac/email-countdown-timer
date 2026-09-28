# Alt text, privacy and render concurrency

Baseline: `6cdbb8b6f3c3d4209c590412f3c919ef6f4a246f` (12.2.0).

This change is authorized by the maintainer and begins as a draft PR.

1. Add optional per-timer alternative text, preserve legacy fallback and explicitly empty alt, and escape HTML attributes at output.
2. Document narrow FlyingPress/WP Rocket exclusions without disabling optimization site-wide or claiming untested product-version compatibility.
3. Audit plugin-originated storage, outbound traffic and security-sensitive paths. Distinguish plugin behavior from WordPress, hosting/CDN logs and user-entered campaign data. Never promise an absence of all vulnerabilities or GDPR certification.
4. Document current manual local fonts and the not-yet-implemented Google importer accurately. No automatic outbound font requests are added here.
5. Serialize cold image generation using an ownership-scoped database lock, recheck shared cache after waiting, and fail safely rather than launch duplicate work on lock failure.
6. Measure full HTTP requests with simultaneous clients in a disposable WordPress/database environment. Never load-test the maintainer's live site.

Preserve renderer geometry, frame timing, public URL parameters, option keys and uninstall consent. Keep all existing gates and the frozen rendering oracle. No production deployment, release/tag, independent approval or native Wiki publication is implied.
