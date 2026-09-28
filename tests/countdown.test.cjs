// Dependency-free behavioral contracts for delayed/duplicated script execution.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/countdown.js', 'utf8');
const listeners = [], attributes = new Map();
const image = (url) => ({ src: 'unchanged', getAttribute: () => url });
const images = [image('http://['), image('javascript:alert(1)'), image(null), image('https://example.test/unrelated'), image('https://example.test/?ecd_action=render&ecd=sale&mode=anim')];
const document = {
    visibilityState: 'visible', baseURI: 'https://example.test/',
    documentElement: { hasAttribute: name => attributes.has(name), setAttribute: (name, value) => attributes.set(name, value) },
    addEventListener: (name, fn) => { assert.equal(name, 'visibilitychange'); listeners.push(fn); },
    querySelectorAll: selector => { assert.equal(selector, 'img[data-ecd-src]'); return images; }
};
const context = vm.createContext({ window: {}, document, URL, Date });
vm.runInContext(source, context);
assert.equal(images[4].src, 'unchanged', 'loading/deferred execution performs no extra request');
vm.runInContext(source, context);
assert.equal(listeners.length, 1, 're-execution cannot attach duplicate refresh listeners');
document.visibilityState = 'hidden'; listeners[0]();
assert.equal(images[4].src, 'unchanged', 'hidden documents do not refresh');
document.visibilityState = 'visible'; listeners[0]();
for (let i = 0; i < 4; i++) assert.equal(images[i].src, 'unchanged', 'invalid/unrelated source ignored');
const url = new URL(images[4].src);
assert.equal(url.searchParams.get('ecd'), 'sale');
assert.equal(url.searchParams.get('mode'), 'anim');
assert.ok(/^\d+$/.test(url.searchParams.get('_t')));
assert.equal(url.searchParams.size, 4, 'only time cache-buster appended, no user token');
console.log('PASS countdown script: duplicate/delayed execution, malformed URLs, visibility, parameters');
