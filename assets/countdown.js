/* One listener per document. No polling, cookies, visitor IDs or browser storage. */
(function () {
    'use strict';
    if (document.documentElement.hasAttribute('data-ecd-refresh-ready')) return;
    document.documentElement.setAttribute('data-ecd-refresh-ready', '1');
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        document.querySelectorAll('img[data-ecd-src]').forEach(function (image) {
            var source = image.getAttribute('data-ecd-src');
            if (!source) return;
            try {
                var url = new URL(source, document.baseURI);
                if (url.protocol !== 'https:' && url.protocol !== 'http:') return;
                if (url.searchParams.get('ecd_action') !== 'render' || !url.searchParams.get('ecd')) return;
                url.searchParams.set('_t', String(Date.now()));
                image.src = url.href;
            } catch (_) {
                // A malformed third-party rewrite must not stop other timers refreshing.
            }
        });
    });
}());
