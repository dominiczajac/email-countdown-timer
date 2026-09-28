/* Idempotent even when an optimizer re-executes the script. No polling or storage. */
(function () {
    'use strict';
    if (window.emailCountdownTimerRefreshInstalled) return;
    window.emailCountdownTimerRefreshInstalled = true;
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        document.querySelectorAll('img[data-ecd-src]').forEach(function (image) {
            try {
                var url = new URL(image.getAttribute('data-ecd-src'), document.baseURI);
                if (url.protocol !== 'https:' && url.protocol !== 'http:') return;
                url.searchParams.set('_t', String(Date.now()));
                image.src = url.href;
            } catch (error) {
                // A malformed third-party rewrite must not stop other timers.
            }
        });
    });
}());
