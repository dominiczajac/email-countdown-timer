/* One listener per document, regardless of the number of shortcodes. */
(function () {
    'use strict';
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        document.querySelectorAll('img[data-ecd-src]').forEach(function (image) {
            var source = image.getAttribute('data-ecd-src');
            var url = new URL(source, document.baseURI);
            if (url.protocol !== 'https:' && url.protocol !== 'http:') return;
            url.searchParams.set('_t', String(Date.now()));
            image.src = url.href;
        });
    });
}());
