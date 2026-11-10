'use strict';
// Deterministic DOM/clock doubles; real WordPress/browser checks run separately.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const code = fs.readFileSync('assets/live-countdown.js', 'utf8');
let checks = 0;
function check(value, name) { assert.ok(value, name); checks++; }
function harness({ remaining = 90061, reduced = false, loading = false, end = '', count = 1, rawDeadline } = {}) {
    let now = 2000000000000, next = 0;
    const events = {}, mediaEvents = {}, timers = new Map(), images = [];
    const roots = Array.from({ length: count }, () => {
        const values = Object.fromEntries(['d', 'h', 'm', 's'].map(unit => [unit, { textContent: '99', getAttribute: () => unit }]));
        const digits = { hidden: false, querySelectorAll: () => Object.values(values) };
        const button = { style: { visibility: 'hidden' }, disabled: false, textContent: '', events: {}, addEventListener(name, fn) { this.events[name] = fn; }, getAttribute(name) { return { 'data-ecd-pause': 'Pause', 'data-ecd-resume': 'Resume', 'data-ecd-ended': 'Ended' }[name]; } };
        const stage = { children: [], appendChild(image) { this.children.push(image); } };
        return { values, digits, button, stage,
            getAttribute(name) { return { 'data-ecd-deadline': rawDeadline === undefined ? String(now / 1000 + remaining) : rawDeadline, 'data-ecd-end-image': end, 'aria-label': 'Saved alt' }[name]; },
            querySelector(selector) { return { '.ecd-live__digits': digits, button, '.ecd-live__stage': stage }[selector]; }
        };
    });
    const document = { visibilityState: 'visible', readyState: loading ? 'loading' : 'complete', baseURI: 'https://example.test/', querySelectorAll: () => roots,
        addEventListener(name, fn) { (events[name] ||= []).push(fn); },
        createElement(type) { assert.equal(type, 'img'); const img = { setAttribute() {}, remove() { this.removed = true; } }; images.push(img); return img; }
    };
    const window = { matchMedia: () => ({ matches: reduced, addEventListener(name, fn) { mediaEvents[name] = fn; } }) };
    const context = vm.createContext({ window, document, URL, Date: { now: () => now }, setTimeout(fn, delay) { const id = ++next; timers.set(id, { fn, delay }); return id; }, clearTimeout(id) { timers.delete(id); } });
    const execute = () => vm.runInContext(code, context);
    execute();
    return { roots, timers, images, events, document, execute,
        fire(name) { (events[name] || []).forEach(fn => fn()); },
        advance(ms) { now += ms; const due = [...timers.values()]; timers.clear(); due.forEach(t => t.fn()); },
        visibility(state) { document.visibilityState = state; (events.visibilitychange || []).forEach(fn => fn()); },
        reduce() { mediaEvents.change({ matches: true }); }
    };
}
const h = harness();
const r = h.roots[0];
check(Object.values(r.values).map(v => v.textContent).join(':') === '01:01:01:01', 'same day/hour/minute/second split as images');
check(r.button.style.visibility === 'visible' && r.button.textContent === 'Pause' && h.timers.size === 1, 'keyboard control enabled with exactly one scheduled tick');
h.advance(10000);
check(r.values.s.textContent === '51', 'delayed ticks recalculate from the absolute deadline, not tick count');
r.button.events.click();
const snapshot = r.values.s.textContent;
h.advance(20000);
check(h.timers.size === 0 && r.values.s.textContent === snapshot && r.button.textContent === 'Resume', 'pause freezes digits and removes scheduled work');
r.button.events.click();
check(r.values.s.textContent === '31' && h.timers.size === 1, 'resume catches up to the absolute deadline');
h.visibility('hidden');
check(h.timers.size === 0, 'hidden page suspends its timer');
h.advance(30000); h.visibility('visible');
check(r.values.s.textContent === '01' && h.timers.size === 1, 'visible page catches up without a network refresh');
h.execute();
check(h.timers.size === 1 && h.events.visibilitychange.length === 1, 'optimizer reexecution does not duplicate timers or listeners');
h.reduce();
check(h.timers.size === 0 && r.button.textContent === 'Resume', 'a new reduced-motion preference pauses ongoing ticking');
const reduced = harness({ reduced: true });
check(reduced.timers.size === 0 && reduced.roots[0].values.s.textContent === '01' && reduced.roots[0].button.textContent === 'Resume', 'reduced motion starts paused with a current snapshot even from cached markup');
reduced.roots[0].button.events.click();
check(reduced.timers.size === 1, 'reduced-motion user can explicitly resume');
const expiry = harness({ remaining: 2, end: '/?ecd_action=render&ecd=example&mode=end' });
check(expiry.images.length === 0, 'no image request before expiry');
expiry.advance(3000);
check(expiry.images.length === 1 && expiry.timers.size === 0 && expiry.roots[0].button.disabled, 'expiry stops ticking and requests the static end image once');
check(Object.values(expiry.roots[0].values).every(v => v.textContent === '00'), 'expired values never become negative');
expiry.visibility('hidden'); expiry.visibility('visible'); expiry.execute();
check(expiry.images.length === 1, 'expiry and visibility cannot repeat the end-image request');
expiry.images[0].naturalWidth = 400; expiry.images[0].onload();
check(expiry.roots[0].digits.hidden && expiry.images[0].alt === 'Saved alt' && expiry.images[0].referrerPolicy === 'no-referrer', 'loaded end image replaces zero digits with saved accessible name');
const broken = harness({ remaining: -1, end: '/?ecd_action=render&ecd=example&mode=end' });
broken.images[0].onerror();
check(!broken.roots[0].digits.hidden && broken.images[0].removed, 'missing end image retains readable zero digits');
const pixel = harness({ remaining: -1, end: '/?ecd_action=render&ecd=example&mode=end' });
pixel.images[0].naturalWidth = 1; pixel.images[0].onload();
check(!pixel.roots[0].digits.hidden && pixel.images[0].removed, 'renderer error pixel does not hide fallback digits');
const unsafe = harness({ remaining: -1, end: 'javascript:alert(1)' });
check(unsafe.images.length === 0 && unsafe.timers.size === 0, 'invalid image URL never becomes a request');
const head = harness({ loading: true, count: 2 });
check(head.timers.size === 0, 'optimizer head execution waits for the DOM');
head.fire('DOMContentLoaded');
check(head.timers.size === 2 && head.roots.every(root => root.button.style.visibility === 'visible'), 'multiple block instances initialize independently');
for (const rawDeadline of ['', 'NaN', 'Infinity', '1e3', '9007199254740992']) {
    const invalid = harness({ rawDeadline });
    check(invalid.timers.size === 0 && invalid.roots[0].button.style.visibility === 'hidden', 'malformed deadline remains an unenhanced snapshot: ' + rawDeadline);
}
console.log(`LIVE COUNTDOWN DOUBLES PASS: ${checks} assertions`);
