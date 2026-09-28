/* Simulated optimizer timing, not a test of proprietary optimizer binaries. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/countdown.js', 'utf8');
const listeners = [];
const images = ['https://example.test/?ecd_action=render&ecd=t1&mode=anim', 'http://[bad', 'javascript:alert(1)', 'https://example.test/?ecd_action=render&ecd=t2&mode=anim'].map(value => ({src: '', getAttribute: () => value}));
const document = {baseURI: 'https://example.test/page', visibilityState: 'hidden', addEventListener: (name, fn) => listeners.push([name, fn]), querySelectorAll: () => images};
const context = {window: {}, document, URL, Date};
vm.runInNewContext(source, context); vm.runInNewContext(source, context);
assert.equal(listeners.length, 1, 'duplicate execution adds only one listener');
listeners[0][1]();assert.equal(images[0].src, '', 'hidden document does not refresh');
document.visibilityState='visible';listeners[0][1]();
assert.match(images[0].src, /_t=/);assert.match(images[3].src, /ecd=t2/, 'malformed preceding image does not stop the next');
assert.equal(images[1].src, '');assert.equal(images[2].src, '');
assert.equal(new URL(images[0].src).searchParams.get('mode'),'anim');
assert.equal(new URL(images[0].src).searchParams.get('ecd'),'t1');
console.log('PASS frontend optimizer-timing simulation: idempotent, late-execution safe, per-image URL isolation, no polling');
