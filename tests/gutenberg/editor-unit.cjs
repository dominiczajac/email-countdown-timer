'use strict';
// Explicit component doubles: native editor acceptance is a separate CI job.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let definition, states = [], cursor = 0, changes = [], checks = 0;
const component = name => name;
const wp = {
    i18n: { __: text => text },
    element: {
        Fragment: 'Fragment', createElement: (type, props, ...children) => ({ type, props: props || {}, children }),
        useState: initial => { const i = cursor++; if (!(i in states)) states[i] = initial; return [states[i], v => { states[i] = typeof v === 'function' ? v(states[i]) : v; }]; },
        useEffect: () => {}, useMemo: fn => fn()
    },
    components: Object.fromEntries(['ComboboxControl', 'SelectControl', 'TextControl', 'PanelBody', 'Notice', 'Button'].map(n => [n, component(n)])),
    blockEditor: { InspectorControls: 'InspectorControls', BlockControls: 'BlockControls', AlignmentToolbar: 'AlignmentToolbar', useBlockProps: () => ({ className: 'wp-block-easy-countdown-timer' }) },
    blocks: { registerBlockType: (name, value) => { assert.equal(name, 'easy-countdown/timer'); definition = value; } }
};
const window = { wp, emailCountdownTimerBlock: { timers: [{ value: 'alpha', label: 'alpha' }, { value: 'beta', label: 'beta' }], imageUrl: 'http://example.invalid/?ecd_action=render&mode=static', manageUrl: 'http://example.invalid/wp-admin/admin.php?page=ecd-timers' } };
vm.runInNewContext(fs.readFileSync('assets/block-editor.js', 'utf8'), { window, Date: { now: () => 1000 } });
const check = (v, message) => { assert.ok(v, message); checks++; };
function render(attrs) { cursor = 0; return definition.edit({ attributes: attrs, setAttributes: v => changes.push(v) }); }
function all(node, type) { if (!node || typeof node !== 'object') return []; return (node.type === type ? [node] : []).concat((node.children || []).flatMap(n => all(n, type))); }
check(definition.apiVersion === 3 && definition.save() === null, 'native API3 dynamic registration');
let tree = render({ timerId: 'alpha', alignment: 'left' });
check(all(tree, 'img').length === 1, 'valid timer has preview without any wp.data store');
check(all(tree, 'img')[0].props.src.includes('mode=static'), 'static endpoint');
check(all(tree, 'img')[0].props.referrerPolicy === 'no-referrer', 'no referrer');
const before = all(tree, 'img')[0].props.src;
all(tree, 'ComboboxControl')[0].props.onFilterValueChange('bet');
tree = render({ timerId: 'alpha', alignment: 'left' });
check(all(tree, 'ComboboxControl')[0].props.options.length === 1 && all(tree, 'ComboboxControl')[0].props.options[0].value === 'beta', 'search filters choices');
check(changes.length === 0 && all(tree, 'img')[0].props.src === before, 'typing does not apply a timer or refresh the image');
all(tree, 'Button').find(n => n.children[0] === 'Refresh preview').props.onClick();
tree = render({ timerId: 'alpha', alignment: 'left' });
check(all(tree, 'img')[0].props.src !== before && changes.length === 0, 'explicit refresh changes only image request');
all(tree, 'AlignmentToolbar')[0].props.onChange('center');
check(changes.at(-1).alignment === 'center' && Object.keys(changes.at(-1)).length === 1, 'native toolbar changes alignment only');
all(tree, 'ComboboxControl')[0].props.onChange('beta');
check(changes.at(-1).timerId === 'beta' && Object.keys(changes.at(-1)).length === 1, 'selection stores ID only');
check(all(tree, 'a')[0].props.rel === 'noopener noreferrer', 'safe administrator link');
all(tree, 'img')[0].props.onError();
tree = render({ timerId: 'alpha', alignment: 'left' });
check(all(tree, 'Notice').some(n => n.children[0].includes('could not load')), 'preview failure shown');
all(tree, 'Button').find(n => n.children[0] === 'Refresh preview').props.onClick();
tree = render({ timerId: 'alpha', alignment: 'left' });
check(!all(tree, 'Notice').some(n => n.children[0].includes('could not load')), 'explicit retry clears error');
window.emailCountdownTimerBlock.manageUrl = '';
tree = render({ timerId: 'alpha' });
check(all(tree, 'a').length === 0, 'content editor has no management link');
tree = render({ timerId: 'missing' });
check(all(tree, 'img').length === 0 && all(tree, 'Notice').length === 1, 'missing timer has warning without bad image');
window.emailCountdownTimerBlock.truncated = true;
tree = render({ timerId: 'beyond-list' });
check(all(tree, 'TextControl').length === 1 && all(tree, 'img').length === 1, 'truncated catalog accepts a known unlisted ID');
delete window.emailCountdownTimerBlock;
tree = render({});
check(all(tree, 'img').length === 0 && all(tree, 'a').length === 0, 'absent bootstrap fails without data exposure');
console.log(`GUTENBERG EDITOR DOUBLES PASS: ${checks} assertions (not native browser)`);
