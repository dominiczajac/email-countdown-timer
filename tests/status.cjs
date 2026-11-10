'use strict';
// Explicit request/privacy/timeout contracts, independent of native browser tests.
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const code = fs.readFileSync('assets/status.js', 'utf8');
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
const settle = async () => { for (let i = 0; i < 3; i++) await new Promise(setImmediate); };
function harness({ ok = true, status = 200, mime = 'image/png', width = 300, network = false, hang = false, decodeHang = false, decodeError = false, blobHang = false, target = 'https://example.test/?ecd_action=render&ecd=private-id&mode=static' } = {}) {
    const requests = [], timeouts = new Map(), revoked = [];
    let blobs = 0;
    const button = { hidden: true, disabled: false, dataset: { ectImageUrl: target, loading: 'Loading', success: 'Success', failure: 'Fixed failure' }, addEventListener(name, fn) { this.click = fn; } };
    const report = { value: JSON.stringify({ format: 1, checks: [{ status: 'pass' }], image_test: { status: 'not_run', code: 'not_run', http_status: null } }) };
    const result = { textContent: '' };
    const image = { hidden: true, naturalWidth: width, removeAttribute(name) { if (name === 'src') delete this.source; }, set src(value) { this.source = value; if (!decodeHang) queueMicrotask(() => decodeError ? this.onerror(new Error('private decode details')) : this.onload()); } };
    const root = { dataset: {}, querySelector: selector => ({ button, img: image, '[data-ect-image-result]': result })[selector] };
    class TestURL extends URL { static createObjectURL() { return 'blob:synthetic-result'; } static revokeObjectURL(value) { revoked.push(value); } }
    const context = vm.createContext({ URL: TestURL, AbortController, Promise, Date, document: { baseURI: 'https://example.test/', querySelector: () => root, getElementById: () => report },
        setTimeout(fn, delay) { check(delay === 15000, 'test has a bounded 15-second deadline'); const id = timeouts.size + 1; timeouts.set(id, fn); return id; }, clearTimeout(id) { timeouts.delete(id); },
        window: { fetch: async (url, options) => {
            requests.push({ url, options });
            if (hang) return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('private timeout details'))));
            if (network) throw new Error('private network error with host/path');
            return { ok, status, headers: { get: () => mime }, blob: async () => { blobs++; if (blobHang) return new Promise((resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('private body details')))); return {}; } };
        } }
    });
    const execute = () => vm.runInContext(code, context); execute();
    return { requests, button, report, image, result, timeouts, revoked, execute, blobs: () => blobs, click: () => button.click(), expire: () => [...timeouts.values()].forEach(fn => fn()) };
}
async function main() {
    const h = harness();
    check(h.requests.length === 0 && !h.button.hidden, 'opening the page makes no automatic image request');
    h.execute(); h.click(); h.click(); await settle();
    check(h.requests.length === 1, 'optimizer reexecution and double-click cannot duplicate an in-flight request');
    const r = h.requests[0];
    check(r.options.credentials === 'omit' && r.options.cache === 'no-store' && r.options.referrerPolicy === 'no-referrer' && r.options.signal, 'image request omits cookies, cache and referrer with an abort signal');
    check(new URL(r.url).searchParams.get('mode') === 'static' && new URL(r.url).searchParams.has('_t'), 'request uses the existing static route and explicit cache buster');
    check(!h.image.hidden && h.result.textContent === 'Success' && !h.button.disabled, 'decoded non-pixel image reports success and permits explicit retry');
    check(JSON.parse(h.report.value).image_test.http_status === 200 && !h.report.value.includes('private-id') && !h.report.value.includes('example.test'), 'copied result contains status only, not URL/campaign identity');
    check(h.revoked.length === 1 && h.timeouts.size === 0, 'success releases blob URL and timeout');
    for (const config of [{ ok: false, status: 403 }, { mime: 'text/html' }, { width: 1 }, { decodeError: true }, { network: true }]) {
        const f = harness(config); f.click(); await settle();
        check(JSON.parse(f.report.value).image_test.status === 'failed' && f.result.textContent.startsWith('Fixed failure') && f.image.hidden && !f.button.disabled, 'HTTP, MIME, pixel and network failures remain redacted and retryable');
        check(!f.report.value.includes('private') && f.timeouts.size === 0, 'failure never copies backend/network text and always clears its timeout');
        const expected = config.ok === false ? 'http_error' : config.mime ? 'not_image' : config.width === 1 ? 'error_pixel' : config.decodeError ? 'decode_error' : 'network_or_cors';
        check(JSON.parse(f.report.value).image_test.code === expected, 'failure report classifies the cause without exposing raw text');
        if (config.mime || config.ok === false) check(f.blobs() === 0, 'HTTP/MIME failure does not decode a response body');
    }
    for (const config of [{ hang: true }, { blobHang: true }, { decodeHang: true }]) {
        const t = harness(config); t.click(); await settle(); t.expire(); await settle();
        check(JSON.parse(t.report.value).image_test.status === 'failed' && JSON.parse(t.report.value).image_test.code === 'timeout' && !t.button.disabled && t.timeouts.size === 0, 'hung network/decode request can time out without permanent disabled controls');
    }
    const bad = harness({ target: 'javascript:alert(1)' }); bad.click(); await settle();
    check(bad.requests.length === 0 && JSON.parse(bad.report.value).image_test.code === 'invalid_target', 'malformed target never becomes a request');
    console.log(`STATUS JAVASCRIPT DOUBLES PASS: ${checks} assertions`);
}
main().catch(error => { console.error(error); process.exitCode = 1; });
