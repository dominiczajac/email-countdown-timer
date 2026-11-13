'use strict';
// DOM/clipboard doubles for the three independent embed actions.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
function editor({ clipboard = true, secure = true, reject = false } = {}) {
    const fields = new Map(), buttons = [], writes = [], announcements = [];
    for (const [id, value] of [['ect-manual-shortcode', '[ecd_timer id="summer"]'], ['ect-manual-html', '<img alt="Offer" src="https://example.invalid/?mode=email">'], ['ect-embed-code', 'https://example.invalid/?mode=email']]) {
        const code = { value, focused: false, selected: false, focus() { this.focused = true; }, select() { this.selected = true; } };
        fields.set(id, code); fields.set(id + '-result', { textContent: '' });
        buttons.push({ hidden: true, dataset: { ectCopy: id, success: 'Copied.', fallback: 'Select and copy manually.' }, addEventListener(name, action) { this[name] = action; } });
    }
    const root = { querySelector: () => null, querySelectorAll: selector => selector === '[data-ect-copy]' ? buttons : [] };
    const document = { querySelectorAll: () => [], querySelector: () => root, getElementById: id => fields.get(id) };
    const wp = { a11y: { speak: message => announcements.push(message) } };
    const navigator = clipboard ? { clipboard: { writeText: async value => { if (reject) throw new Error('denied'); writes.push(value); } } } : {};
    vm.runInNewContext(fs.readFileSync('assets/admin.js', 'utf8'), { document, window: { wp, isSecureContext: secure }, wp, navigator });
    return { fields, buttons, writes, announcements };
}
async function main() {
    const e = editor();
    for (const button of e.buttons) {
        check(!button.hidden, 'usable copy action is revealed');
        await button.click();
        check(e.writes.at(-1) === e.fields.get(button.dataset.ectCopy).value, 'each action copies its own exact embed, including escaped HTML/newlines');
        check(e.fields.get(button.dataset.ectCopy + '-result').textContent === 'Copied.' && e.announcements.at(-1) === 'Copied.', 'successful copy has local feedback and an announcement');
    }
    check(e.writes[0].startsWith('[ecd_timer') && e.writes[1].startsWith('<img') && e.writes[2].startsWith('https:'), 'website, email HTML and image URL are never interchanged');
    for (const options of [{ clipboard: false }, { reject: true }, { secure: false }]) {
        const fallback = editor(options), button = fallback.buttons[1];
        await button.click();
        const code = fallback.fields.get('ect-manual-html');
        check(code.focused && code.selected && fallback.writes.length === 0, 'missing, denied or insecure clipboard falls back to selected code');
        check(fallback.fields.get('ect-manual-html-result').textContent === 'Select and copy manually.', 'clipboard failure gives manual-copy instructions');
        check(!fallback.fields.get('ect-manual-shortcode').selected && fallback.fields.get('ect-manual-shortcode-result').textContent === '', 'fallback does not affect another channel');
    }
    console.log(`ADMIN COPY DOUBLES PASS: ${checks} assertions (not native browser)`);
}
main().catch(error => { console.error(error); process.exitCode = 1; });

// Actual initializer with Intl canonicalization: a system tzdb can omit backward aliases.
for (const [zone, expected] of [['Asia/Calcutta','Asia/Kolkata'], ['Unsupported/Zone','UTC']]) {
    const select = { value: 'UTC', options: [{ value:'UTC' },{ value:'Asia/Kolkata' }] };
    const document = { querySelectorAll: () => [{ dataset:{ ectAutoTimezone:'zone' } }], getElementById: () => select, querySelector: () => null };
    const Intl = { DateTimeFormat: (_locale, options) => ({ resolvedOptions: () => ({ timeZone:options ? (options.timeZone==='Asia/Kolkata'?'Asia/Calcutta':options.timeZone) : zone }) }) };
    vm.runInNewContext(fs.readFileSync('assets/admin.js','utf8'), { document, Intl });
    assert.equal(select.value,expected,'alias selects equivalent supported zone, unknown zone keeps site fallback');
}
// Saved-preview lifecycle with deterministic browser event/timer doubles.
{
    let next = 0;
    const timers = new Map(), events = {}, media = { matches: false, addEventListener(name, fn) { events['media-' + name] = fn; } };
    const image = { src: '', dataset: { animated: 'https://example.invalid/?mode=email', static: 'https://example.invalid/?mode=static' }, naturalWidth:600, complete:true, addEventListener() {} };
    const error = { hidden:true };
    const root = { querySelector: s => s === '#ect-preview' ? image : s === '#ect-image-error' ? error : null, querySelectorAll: () => [] };
    const document = { hidden:false, baseURI:'https://example.invalid/', querySelectorAll:()=>[], querySelector:()=>root, addEventListener(name,fn){ events[name]=fn; } };
    const window = { matchMedia:()=>media, addEventListener(name,fn){ events[name]=fn; } };
    vm.runInNewContext(fs.readFileSync('assets/admin.js','utf8'), { document, window, URL, setTimeout: (fn,delay) => { timers.set(++next,{fn,delay}); return next; }, clearTimeout:id=>timers.delete(id) });
    assert.match(image.src,/mode=email/);assert.equal([...timers.values()][0].delay,58000);
    document.hidden=true;events.visibilitychange();assert.equal(timers.size,0,'hidden preview stops renewal');
    document.hidden=false;events.visibilitychange();assert.equal(timers.size,1,'visible preview resumes');
    events.pagehide();assert.equal(timers.size,0,'leaving page clears renewal');
    events.pageshow({persisted:true});assert.equal(timers.size,1,'bfcache restoration resumes renewal');
    media.matches=true;events['media-change']();assert.equal(timers.size,0);assert.match(image.src,/mode=static/);
    console.log('ADMIN PREVIEW LIFECYCLE DOUBLES PASS: visible, hidden, pagehide, bfcache and reduced motion');
}
