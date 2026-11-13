/* Explicit saves and local controls; the visible saved preview refreshes its minute-long GIF. */
(() => {
    'use strict';
    document.querySelectorAll('[data-ect-auto-timezone]').forEach(marker => {
        const select = document.getElementById(marker.dataset.ectAutoTimezone);
        try {
            const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!select) return;
            const options = Array.from(select.options);
            const match = options.find(option => option.value === zone) || options.find(option => {
                try { return Intl.DateTimeFormat(undefined, { timeZone: option.value }).resolvedOptions().timeZone === zone; }
                catch (_) { return false; }
            });
            if (match) select.value = match.value;
        } catch (_) { /* Keep the configured WordPress timezone as fallback. */ }
    });
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
        const presetBox = root.querySelector('[data-ect-presets]');
        if (presetBox) {
            presetBox.hidden = false;
            const result = presetBox.querySelector('[data-ect-preset-result]');
            presetBox.querySelectorAll('[data-ect-preset]').forEach(button => {
                button.addEventListener('click', () => {
                    ['bg', 'dc', 'lc'].forEach(key => {
                        const field = form.elements[key];
                        if (!field) return;
                        field.value = button.dataset[key];
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    const font = form.elements.font;
                    if (font && button.dataset.font) {
                        font.value = button.dataset.font;
                        font.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    result.textContent = result.dataset.applied;
                });
            });
        }
        const labelBox = root.querySelector('[data-ect-label-presets]');
        if (labelBox) {
            const language = labelBox.querySelector('#ect-label-language');
            const apply = labelBox.querySelector('#ect-apply-labels');
            const result = labelBox.querySelector('[data-ect-label-result]');
            labelBox.hidden = false;
            language.addEventListener('change', () => { apply.disabled = !language.value; result.textContent = ''; });
            apply.addEventListener('click', () => {
                const option = language.selectedOptions[0];
                if (!option || !option.dataset.labels) return;
                const labels = JSON.parse(option.dataset.labels);
                ['label_d', 'label_h', 'label_m', 'label_s'].forEach(key => {
                    const field = form.elements[key];
                    field.value = labels[key];
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                });
                result.textContent = result.dataset.applied;
                announce(result.textContent);
            });
        }
    }
    const preview = root.querySelector('#ect-preview'), previewError = root.querySelector('#ect-image-error');
    if (preview && previewError) {
        let timeout = setTimeout(() => { previewError.hidden = false; },10000);
        window.addEventListener('message', event => {
            if (event.source === preview.contentWindow && event.data && event.data.type === 'ecd-frame-status' && typeof event.data.ready === 'boolean') {
                clearTimeout(timeout); previewError.hidden = event.data.ready;
            }
        });
    }
    root.querySelectorAll('[data-ect-copy]').forEach(button => {
        const code = document.getElementById(button.dataset.ectCopy);
        const result = document.getElementById(`${button.dataset.ectCopy}-result`);
        if (!code || !result) return;
        button.hidden = false;
        button.addEventListener('click', async () => {
            try { if (!navigator.clipboard || !window.isSecureContext) throw new Error('manual'); await navigator.clipboard.writeText(code.value); result.textContent = button.dataset.success; }
            catch (_) { code.focus(); code.select(); result.textContent = button.dataset.fallback; }
            announce(result.textContent);
        });
    });
    const deletion = root.querySelector('#ect-delete'); const dialog = root.querySelector('#ect-delete-dialog');
    if (deletion && dialog && typeof dialog.showModal === 'function') {
        let trigger;
        deletion.addEventListener('submit', event => { event.preventDefault(); trigger = document.activeElement; dialog.showModal(); root.querySelector('#ect-delete-cancel').focus(); });
        root.querySelector('#ect-delete-cancel').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => { if (trigger) trigger.focus(); });
        root.querySelector('#ect-delete-confirm').addEventListener('click', () => { if (deletion.checkValidity()) { allowNavigation = true; dialog.close(); HTMLFormElement.prototype.submit.call(deletion); } });
    }
})();
