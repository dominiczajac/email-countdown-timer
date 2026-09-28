/* Idempotent even when an optimizer re-executes the script. No polling or storage. */
(function () {
    'use strict';
    if (window.emailCountdownTimerRefreshInstalled) return;
    window.emailCountdownTimerRefreshInstalled = true;
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
            } catch (error) {
                // A malformed third-party rewrite must not stop other timers.
            }
        });
    });
}());
