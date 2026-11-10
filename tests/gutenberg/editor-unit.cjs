'use strict';
// Component/request doubles only. Native editor acceptance remains separate.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
const initial = () => ({
    timers: [{ value: 'alpha', label: 'alpha' }, { value: 'beta', label: 'beta' }], truncated: false,
    imageUrl: 'http://example.invalid/?ecd_action=render&mode=static',
    manageUrl: 'http://example.invalid/wp-admin/admin.php?page=ecd-timers',
    createUrl: 'http://example.invalid/wp-admin/admin.php?page=ecd-timers#ect-quick-create',
    refresh: { url: 'http://example.invalid/wp-admin/admin-ajax.php', postId: 12, nonce: 'synthetic-editor-nonce' }
});
function harness(settings = initial(), fetch = () => { throw new Error('Unexpected request'); }) {
    let definition, active, cursor;
    const changes = [], instances = new Map();
    const wp = {
        i18n: { __: text => text },
        element: {
            Fragment: 'Fragment', createElement: (type, props, ...children) => ({ type, props: props || {}, children }),
            useState: initialValue => {
                const instance = active, index = cursor++;
                if (!(index in instance.slots)) {
                    const slot = { value: initialValue };
                    slot.set = next => { slot.value = typeof next === 'function' ? next(slot.value) : next; };
                    instance.slots[index] = slot;
                }
                const slot = instance.slots[index];
                return [slot.value, slot.set];
            },
            useEffect: (callback, deps) => {
                const index = cursor++, previous = active.slots[index];
                if (!previous || deps.some((v, i) => v !== previous.deps[i])) {
                    if (previous && previous.cleanup) previous.cleanup();
                    const slot = { deps }; active.slots[index] = slot;
                    active.effects.push(() => { slot.cleanup = callback(); });
                }
            },
            useMemo: fn => fn()
        },
        components: Object.fromEntries(['ComboboxControl', 'SelectControl', 'TextControl', 'PanelBody', 'Notice', 'Button', 'Placeholder'].map(name => [name, name])),
        blockEditor: { InspectorControls: 'InspectorControls', BlockControls: 'BlockControls', AlignmentToolbar: 'AlignmentToolbar', useBlockProps: () => ({ className: 'wp-block-easy-countdown-timer' }) },
        blocks: { registerBlockType: (name, value) => { assert.equal(name, 'easy-countdown/timer'); definition = value; } }
    };
    vm.runInNewContext(fs.readFileSync('assets/block-editor.js', 'utf8'), { window: { wp, fetch, emailCountdownTimerBlock: settings }, Date: { now: () => 1000 } });
    return {
        definition, changes,
        render(attrs, id = 'first') {
            if (!instances.has(id)) instances.set(id, { slots: [], effects: [] });
            active = instances.get(id); cursor = 0;
            const tree = definition.edit({ attributes: attrs, setAttributes: value => changes.push(value) });
            active.effects.splice(0).forEach(effect => effect());
            return tree;
        },
        unmount(id = 'first') {
            instances.get(id).slots.forEach(slot => { if (slot.cleanup) slot.cleanup(); });
            instances.delete(id);
        }
    };
}
function all(node, type) { if (!node || typeof node !== 'object') return []; return (node.type === type ? [node] : []).concat((node.children || []).flatMap(n => all(n, type))); }
const button = (tree, label) => all(tree, 'Button').find(node => node.children[0] === label);
async function main() {
    const requests = [];
    let respond;
    const h = harness(initial(), (url, options) => {
        requests.push({ url, options });
        return new Promise(resolve => { respond = resolve; });
    });
    check(h.definition.apiVersion === 3 && h.definition.save() === null, 'native API3 dynamic registration');
    let tree = h.render({ timerId: 'alpha', alignment: 'left' });
    check(all(tree, 'img').length === 1, 'valid timer has preview without any wp.data store');
    const modeHarness = harness();
    const display = all(modeHarness.render({ timerId: 'alpha' }), 'SelectControl').find(n => n.props.label === 'Website display');
    check(display.props.value === 'image', 'old blocks default to Image mode');
    display.props.onChange('live');
    check(modeHarness.changes.at(-1).displayMode === 'live' && Object.keys(modeHarness.changes.at(-1)).length === 1, 'explicit live choice stores only the block mode');
    const liveTree = modeHarness.render({ timerId: 'alpha', displayMode: 'live' });
    check(all(liveTree, 'p').some(n => String(n.children[0]).includes('theme typography')), 'live mode explains static preview and frontend differences');

    check(all(tree, 'img')[0].props.src.includes('mode=static'), 'static endpoint');
    check(all(tree, 'img')[0].props.referrerPolicy === 'no-referrer', 'no referrer');
    const before = all(tree, 'img')[0].props.src;
    all(tree, 'ComboboxControl')[0].props.onFilterValueChange('bet');
    tree = h.render({ timerId: 'alpha' });
    check(all(tree, 'ComboboxControl')[0].props.options.length === 1 && all(tree, 'ComboboxControl')[0].props.options[0].value === 'beta', 'search filters choices');
    check(h.changes.length === 0 && all(tree, 'img')[0].props.src === before, 'typing does not apply a timer or refresh the image');
    button(tree, 'Refresh preview').props.onClick();
    tree = h.render({ timerId: 'alpha' });
    check(all(tree, 'img')[0].props.src !== before && h.changes.length === 0, 'explicit preview refresh changes only image request');
    all(tree, 'AlignmentToolbar')[0].props.onChange('center');
    check(h.changes.at(-1).alignment === 'center' && Object.keys(h.changes.at(-1)).length === 1, 'native toolbar changes alignment only');
    all(tree, 'ComboboxControl')[0].props.onChange('beta');
    check(h.changes.at(-1).timerId === 'beta' && Object.keys(h.changes.at(-1)).length === 1, 'selection stores ID only');
    check(all(tree, 'a')[0].props.rel === 'noopener noreferrer', 'safe administrator link');
    all(tree, 'img')[0].props.onError();
    tree = h.render({ timerId: 'alpha' });
    check(all(tree, 'Notice').some(n => n.children[0].includes('could not load')), 'preview failure shown');
    button(tree, 'Refresh preview').props.onClick();
    tree = h.render({ timerId: 'alpha' });
    check(!all(tree, 'Notice').some(n => n.children[0].includes('could not load')), 'explicit retry clears preview error');
    check(requests.length === 0, 'render, selection, search and preview retry do not request the catalog');
    h.render({ timerId: 'beta' }, 'second');
    const changeCount = h.changes.length;
    const pending = button(tree, 'Refresh campaigns').props.onClick();
    const busy = h.render({ timerId: 'beta' }, 'second');
    check(button(busy, 'Refresh campaigns').props.disabled && button(busy, 'Refresh campaigns').props.isBusy, 'all blocks show a disabled busy action during a shared refresh');
    button(tree, 'Refresh campaigns').props.onClick();
    await Promise.resolve();
    check(requests.length === 1, 'repeated clicks share one explicit request');
    const request = requests[0];
    const body = new URLSearchParams(request.options.body);
    check(request.url === initial().refresh.url && request.options.method === 'POST' && request.options.credentials === 'same-origin' && request.options.cache === 'no-store', 'refresh uses only the authenticated WordPress admin URL');
    check([...body.keys()].sort().join(',') === 'action,nonce,post_id' && body.get('post_id') === '12' && body.get('nonce') === 'synthetic-editor-nonce' && body.get('action') === 'email_countdown_timer_campaigns', 'request carries only action, editing context and nonce');
    const next = { ...initial(), timers: [{ value: 'alpha', label: 'alpha' }, { value: 'gamma', label: 'gamma' }] };
    delete next.refresh;
    respond({ ok: true, json: async () => ({ success: true, data: next }) });
    await pending;
    tree = h.render({ timerId: 'alpha' });
    check(all(tree, 'ComboboxControl')[0].props.options.some(t => t.value === 'gamma') && all(tree, 'img').length === 1, 'new campaign appears without remounting or changing the selected timer');
    const second = h.render({ timerId: 'beta' }, 'second');
    check(all(second, 'img').length === 0 && all(second, 'Notice').some(n => n.children[0].includes('no longer')), 'deleted campaign is detected in another mounted block');
    check(h.changes.length === changeCount, 'catalog refresh never rewrites selected IDs or alignment');
    check(all(tree, 'p').some(n => n.props.role === 'status' && n.children[0] === 'Campaign list updated.'), 'refresh completion has an accessible status');
    const denied = button(tree, 'Refresh campaigns').props.onClick();
    await Promise.resolve();
    respond({ ok: false }); await denied;
    tree = h.render({ timerId: 'alpha' });
    check(all(tree, 'Notice').some(n => n.children[0].includes('could not refresh')) && all(tree, 'ComboboxControl')[0].props.value === 'alpha', 'permission/network failure retains last list and selected ID with a retry message');
    const malformed = button(tree, 'Refresh campaigns').props.onClick();
    await Promise.resolve();
    respond({ ok: true, json: async () => ({ success: true, data: { ...next, timers: [{ value: 'bad', label: '<raw>' }] } }) });
    await malformed;
    tree = h.render({ timerId: 'alpha' });
    check(all(tree, 'ComboboxControl')[0].props.options.some(t => t.value === 'gamma'), 'malformed success response cannot replace the safe list');
    h.unmount('second');
    const empty = harness({ ...initial(), timers: [] }, async () => ({ ok: true, json: async () => ({ success: true, data: next }) }));
    let emptyTree = empty.render({});
    check(all(emptyTree, 'Placeholder').length === 1 && all(emptyTree, 'ComboboxControl').length === 0, 'empty state explains the first step instead of an empty selector');
    const create = all(emptyTree, 'a').find(n => n.children[0] === 'Create a campaign (new tab)');
    check(create.props.href.endsWith('#ect-quick-create') && create.props.rel === 'noopener noreferrer', 'administrator can open quick creation in a safe new tab');
    await button(emptyTree, 'Refresh campaigns').props.onClick();
    emptyTree = empty.render({});
    check(all(emptyTree, 'Placeholder').length === 0 && all(emptyTree, 'ComboboxControl')[0].props.options.length === 2 && empty.changes.length === 0, 'first campaign refresh leaves empty state without automatically choosing a timer');
    const author = harness({ ...initial(), timers: [], manageUrl: '', createUrl: '' });
    const authorTree = author.render({});
    check(all(authorTree, 'a').length === 0 && all(authorTree, 'Placeholder')[0].props.instructions.includes('administrator'), 'content editor gets guidance without management links');
    const truncated = harness({ ...initial(), truncated: true });
    const unlisted = truncated.render({ timerId: 'beyond-list' });
    check(all(unlisted, 'TextControl').length === 1 && all(unlisted, 'img').length === 1, 'truncated catalog retains explicit entry for a known unlisted ID');
    const absent = harness(null);
    const absentTree = absent.render({});
    check(all(absentTree, 'img').length === 0 && all(absentTree, 'a').length === 0 && !button(absentTree, 'Refresh campaigns'), 'absent bootstrap fails without data or request access');
    console.log(`GUTENBERG EDITOR DOUBLES PASS: ${checks} assertions (not native browser)`);
}
main().catch(error => { console.error(error); process.exitCode = 1; });
