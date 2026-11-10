/* Administrator-only, explicit anonymous image test. No automatic requests or storage. */
(function () {
    'use strict';
    var root = document.querySelector('[data-ect-image-test]');
    if (!root || root.dataset.installed) return;
    root.dataset.installed = '1';
    var button = root.querySelector('button');
    var image = root.querySelector('img');
    var result = root.querySelector('[data-ect-image-result]');
    var report = document.getElementById('ect-status-report');
    if (!button || !image || !result || !report) return;
    button.hidden = false;
    button.addEventListener('click', function () {
        if (button.disabled) return;
        button.disabled = true;
        image.hidden = true;
        result.textContent = button.dataset.loading;
        var controller = new AbortController();
        var reason = 'network_or_cors';
        var timeout = setTimeout(function () { reason = 'timeout'; controller.abort(); }, 15000);
        var status = null;
        var objectUrl = null;
        function record(state, code) {
            var data = JSON.parse(report.value);
            data.image_test = { status: state, code: code, http_status: status };
            report.value = JSON.stringify(data, null, 2);
        }
        record('running', 'pending');
        Promise.resolve().then(function () {
            reason = 'invalid_target';
            var url = new URL(button.dataset.ectImageUrl, document.baseURI);
            if (!/^https?:$/.test(url.protocol) || url.searchParams.get('ecd_action') !== 'render' || !url.searchParams.get('ecd') || url.searchParams.get('mode') !== 'static') throw new Error('target');
            url.searchParams.set('_t', String(Date.now()));
            reason = 'network_or_cors';
            return window.fetch(url.href, { credentials: 'omit', cache: 'no-store', referrerPolicy: 'no-referrer', signal: controller.signal });
        }).then(function (response) {
            status = response.status;
            if (!response.ok) { reason = 'http_error'; throw new Error('http'); }
            if (!/^image\/(png|gif|webp)(;|$)/i.test(response.headers.get('Content-Type') || '')) { reason = 'not_image'; throw new Error('mime'); }
            reason = 'decode_error';
            return response.blob();
        }).then(function (blob) {
            return new Promise(function (resolve, reject) {
                controller.signal.addEventListener('abort', function () { reject(new Error('timeout')); }, { once: true });
                if (controller.signal.aborted) { reject(new Error('timeout')); return; }
                objectUrl = URL.createObjectURL(blob);
                image.onload = function () { if (image.naturalWidth > 1) resolve(); else { reason = 'error_pixel'; reject(new Error('pixel')); } };
                image.onerror = reject;
                image.src = objectUrl;
            });
        }).then(function () {
            image.hidden = false;
            result.textContent = button.dataset.success;
            record('pass', 'loaded');
        }).catch(function () {
            image.removeAttribute('src');
            var tips = { timeout: button.dataset.timeout, network_or_cors: button.dataset.network, http_error: button.dataset.http, not_image: button.dataset.mime, decode_error: button.dataset.decode, error_pixel: button.dataset.pixel, invalid_target: button.dataset.target };
            result.textContent = button.dataset.failure + ' ' + (tips[reason] || '');
            record('failed', reason);
        }).finally(function () {
            clearTimeout(timeout);
            image.onload = image.onerror = null;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            button.disabled = false;
        });
    });
}());
