/* Opt-in local ticking. No polling, storage, font loading or repeating announcements. */
(function () {
    'use strict';
    if (window.emailCountdownTimerLiveInstalled) return;
    window.emailCountdownTimerLiveInstalled = true;

    function install() {
        document.querySelectorAll('.ecd-live[data-ecd-deadline]').forEach(function (root) {
            var rawDeadline = root.getAttribute('data-ecd-deadline');
            if (!/^-?\d+$/.test(rawDeadline || '')) return;
            var deadline = Number(rawDeadline);
            var digits = root.querySelector('.ecd-live__digits');
            var button = root.querySelector('button');
            if (!Number.isSafeInteger(deadline) || !digits || !button) return;
            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
            var paused = !!(reduced && reduced.matches);
            var timeout = null;
            var ended = false;
            var imageAttempted = false;
            var firstUpdate = true;

            function endImage() {
                var source = root.getAttribute('data-ecd-end-image');
                if (!source || imageAttempted) return;
                imageAttempted = true;
                try {
                    var url = new URL(source, document.baseURI);
                    if ((url.protocol !== 'https:' && url.protocol !== 'http:') || url.searchParams.get('ecd_action') !== 'render' || url.searchParams.get('mode') !== 'end' || !url.searchParams.get('ecd')) return;
                    var image = document.createElement('img');
                    image.className = 'ecd-live__end-image';
                    image.alt = root.getAttribute('aria-label') || '';
                    image.referrerPolicy = 'no-referrer';
                    image.setAttribute('data-no-lazy', '1');
                    image.onload = function () {
                        if (image.naturalWidth > 1) digits.hidden = true;
                        else image.remove();
                    };
                    image.onerror = function () { image.remove(); };
                    image.src = url.href;
                    root.querySelector('.ecd-live__stage').appendChild(image);
                } catch (error) {
                    // Zero digits and the exact deadline remain available.
                }
            }

            function update() {
                clearTimeout(timeout);
                timeout = null;
                if (document.visibilityState === 'hidden') return;
                if (!paused || firstUpdate) {
                    firstUpdate = false;
                    var remaining = Math.max(0, deadline - Math.floor(Date.now() / 1000));
                    var values = { d: Math.floor(remaining / 86400), h: Math.floor(remaining % 86400 / 3600), m: Math.floor(remaining % 3600 / 60), s: remaining % 60 };
                    digits.querySelectorAll('[data-ecd-unit]').forEach(function (node) {
                        node.textContent = String(values[node.getAttribute('data-ecd-unit')]).padStart(2, '0');
                    });
                    var hideDays = root.getAttribute('data-ecd-hide-days') === '1' && values.d === 0;
                    var day = digits.querySelector('[data-ecd-unit="d"]');
                    if (day) day.parentElement.hidden = hideDays;
                    digits.classList.toggle('ecd-live__digits--three', hideDays);
                    ended = remaining === 0;
                    if (ended) endImage();
                }
                button.textContent = button.getAttribute(ended ? 'data-ecd-ended' : (paused ? 'data-ecd-resume' : 'data-ecd-pause'));
                button.disabled = ended;
                if (!paused && !ended) timeout = setTimeout(update, 1000 - Date.now() % 1000);
            }

            button.style.visibility = 'visible';
            button.addEventListener('click', function () { paused = !paused; update(); });
            document.addEventListener('visibilitychange', update);
            if (reduced && reduced.addEventListener) reduced.addEventListener('change', function (event) {
                if (event.matches) { paused = true; update(); }
            });
            update();
        });
    }
    // Also work when an optimizer moves the footer script to the document head.
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install, { once: true });
    else install();
}());
