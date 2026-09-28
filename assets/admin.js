/* Progressive enhancement only: explicit saves, local computation, no polling. */
(() => {
    'use strict';
    const root = document.querySelector('[data-ect-admin]');
    if (!root) return;
    root.querySelectorAll('[data-ect-disclosure]').forEach(section => { if (!section.querySelector('[aria-invalid="true"]')) section.open = false; });
    const form = root.querySelector('#ect-editor');
    const summary = root.querySelector('#ect-errors');
    let allowNavigation = false;
    const announce = message => {
        if (window.wp && wp.a11y) wp.a11y.speak(message, 'polite');
    };
    const reveal = field => {
        let parent = field.parentElement;
        while (parent && parent !== root) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
        field.focus();
    };
    if (summary) {
        summary.addEventListener('click', event => {
            const link = event.target.closest('a[href^="#ect-"]');
            if (!link) return;
            const field = document.getElementById(link.hash.slice(1));
            if (field) { event.preventDefault(); reveal(field); }
        });
        if (!summary.hidden) { summary.querySelectorAll('a').forEach(link => { const field = document.getElementById(link.hash.slice(1)); if (field) { const details = field.closest('details'); if (details) details.open = true; } }); summary.focus(); }
    }
    if (form) {
        const serialize = () => JSON.stringify(Array.from(new FormData(form).entries()));
        const initial = serialize();

        const dirty = () => serialize() !== initial;
        const state = root.querySelector('#ect-save-state');
        const initialState = state ? state.textContent : '';
        form.addEventListener('input', () => { if (state) state.textContent = dirty() ? state.dataset.dirty : initialState; });
        window.addEventListener('beforeunload', event => { if (!allowNavigation && dirty()) { event.preventDefault(); event.returnValue = ''; } });
        form.noValidate = true;
        form.addEventListener('submit', event => {
            const problems = [];
            form.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(field => {
                const error = document.getElementById(`ect-error-${field.name}`);
                field.removeAttribute('aria-invalid');
                if (error) error.textContent = '';
                if (!field.validity.valid) {
                    problems.push([field, field.validationMessage]);
                    field.setAttribute('aria-invalid', 'true');
                    if (error) error.textContent = field.validationMessage;
                }
            });
            if (problems.length) {
                event.preventDefault();
                const list = summary.querySelector('ul'); list.replaceChildren();
                problems.forEach(([field, message]) => { const item = document.createElement('li'); const link = document.createElement('a'); link.href = `#${field.id}`; link.textContent = `${field.labels && field.labels[0] ? field.labels[0].textContent : field.name}: ${message}`; item.append(link); list.append(item); const details = field.closest('details'); if (details) details.open = true; });
                summary.hidden = false; summary.focus();
            } else allowNavigation = true;
        });
        const expandHex = value => /^#[a-f0-9]{3}$/i.test(value) ? '#' + value.slice(1).split('').map(c => c + c).join('') : value;
        const luminance = value => {
            if (!/^#[a-f0-9]{6}$/i.test(value)) return null;
            const rgb = [1, 3, 5].map(i => parseInt(value.slice(i, i + 2), 16) / 255).map(v => v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
            return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
        };
        const contrast = root.querySelector('#ect-contrast');
        const updateContrast = () => {
            const [bg, dc, lc] = ['bg', 'dc', 'lc'].map(key => luminance(expandHex(form.elements[key].value)));
            if ([bg, dc, lc].includes(null)) { contrast.hidden = true; return; }
            const ratio = value => ((Math.max(bg, value) + 0.05) / (Math.min(bg, value) + 0.05)).toFixed(2);
            contrast.textContent = contrast.dataset.template.replace('{digits}', ratio(dc)).replace('{labels}', ratio(lc)); contrast.hidden = false;
        };
        root.querySelectorAll('[data-ect-picker]').forEach(label => {
            const picker = label.querySelector('input'); const text = document.getElementById(picker.dataset.colorFor);
            const sync = () => { const color = expandHex(text.value); if (/^#[a-f0-9]{6}$/i.test(color)) picker.value = color; updateContrast(); };
            label.hidden = false; sync(); text.addEventListener('input', sync);
            picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); text.dispatchEvent(new Event('input', { bubbles: true })); });
        });
    }
    const image = root.querySelector('#ect-preview');
    const animation = root.querySelector('#ect-animation');
    if (image) {
        const error = root.querySelector('#ect-image-error');
        const failed = () => { error.hidden = false; announce(error.textContent); };
        image.addEventListener('error', failed);
        image.addEventListener('load', () => { error.hidden = image.naturalWidth > 1; });
        if (image.complete && image.naturalWidth <= 1) error.hidden = false;
    }
    if (image && animation) {
        let playing = false;
        const stop = () => { if (!playing) return; playing = false; image.src = image.dataset.static; animation.textContent = animation.dataset.play; animation.setAttribute('aria-pressed', 'false'); };
        animation.hidden = false; animation.setAttribute('aria-pressed', 'false');
        animation.addEventListener('click', () => { if (playing) return stop(); playing = true; image.src = image.dataset.animated; animation.textContent = animation.dataset.stop; animation.setAttribute('aria-pressed', 'true'); });
        document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    }
    const code = root.querySelector('#ect-embed-code');
    if (code) {
        root.querySelector('[data-ect-manual]').open = false;
        const formats = { url: code.value, html: root.querySelector('#ect-manual-html').value, shortcode: root.querySelector('#ect-manual-shortcode').value };
        const select = root.querySelector('#ect-embed-format'); const button = root.querySelector('#ect-copy'); const result = root.querySelector('#ect-copy-result');
        root.querySelector('[data-ect-embed-tools]').hidden = false; button.hidden = false;
        select.addEventListener('change', () => { code.value = formats[select.value]; root.querySelector('#ect-embed-label').textContent = select.selectedOptions[0].textContent; result.textContent = ''; });
        button.addEventListener('click', async () => {
            try { if (!navigator.clipboard || !window.isSecureContext) throw new Error('manual'); await navigator.clipboard.writeText(code.value); result.textContent = button.dataset.success; }
            catch (_) { code.focus(); code.select(); result.textContent = button.dataset.fallback; }
            announce(result.textContent);
        });
    }
    const deletion = root.querySelector('#ect-delete'); const dialog = root.querySelector('#ect-delete-dialog');
    if (deletion && dialog && typeof dialog.showModal === 'function') {
        let trigger;
        deletion.addEventListener('submit', event => { event.preventDefault(); trigger = document.activeElement; dialog.showModal(); root.querySelector('#ect-delete-cancel').focus(); });
        root.querySelector('#ect-delete-cancel').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => { if (trigger) trigger.focus(); });
        root.querySelector('#ect-delete-confirm').addEventListener('click', () => { if (deletion.checkValidity()) { allowNavigation = true; dialog.close(); HTMLFormElement.prototype.submit.call(deletion); } });
    }
})();
