/* Native WordPress Media Library, editor only. No public network or storage API. */
(function () {
    'use strict';
    var tools = document.querySelector('[data-ect-end-tools]');
    var input = document.getElementById('ect-expiry_image_id');
    var choose = document.getElementById('ect-end-choose');
    var remove = document.getElementById('ect-end-remove');
    var status = document.getElementById('ect-end-status');
    if (!tools || !input || !choose || !remove || !status || !window.wp || !wp.media) return;
    var frame;
    tools.hidden = false;
    function selected(id, message) {
        input.value = String(id);
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        status.textContent = status.getAttribute(message);
        if (wp.a11y && wp.a11y.speak) wp.a11y.speak(status.textContent);
        choose.focus();
    }
    choose.addEventListener('click', function () {
        if (!frame) {
            frame = wp.media({title: choose.getAttribute('data-title'), button: {text: choose.getAttribute('data-select')}, library: {type: 'image'}, multiple: false});
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();
                var id = attachment ? Number(attachment.get('id')) : 0;
                if (Number.isSafeInteger(id) && id > 0) selected(id, 'data-selected');
            });
            frame.on('close', function () { choose.focus(); });
        }
        frame.open();
    });
    remove.addEventListener('click', function () { selected(0, 'data-removed'); });
}());
