/* Native WordPress editor packages only. No frontend execution or polling. */
(function (wp) {
    'use strict';
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var emptySettings = { timers: [], truncated: false, imageUrl: '', manageUrl: '', createUrl: '' };
    var positions = { left: 'flex-start', center: 'center', right: 'flex-end' };
    // One explicit request updates every mounted timer block, without polling or
    // changing post attributes. The server rechecks the concrete editor capability.
    var catalogState = { settings: window.emailCountdownTimerBlock || emptySettings, loading: false, error: false, updated: false };
    var listeners = new Set();

    function publishCatalog(change) {
        catalogState = Object.assign({}, catalogState, change);
        listeners.forEach(function (listener) { listener(catalogState); });
    }

    function useCatalog() {
        var state = wp.element.useState(catalogState);
        wp.element.useEffect(function () {
            listeners.add(state[1]);
            state[1](catalogState);
            return function () { listeners.delete(state[1]); };
        }, []);
        return state[0];
    }

    function validCatalog(data) {
        return data && Array.isArray(data.timers) && data.timers.length <= 250 &&
            typeof data.truncated === 'boolean' &&
            ['imageUrl', 'manageUrl', 'createUrl'].every(function (key) { return typeof data[key] === 'string'; }) &&
            data.timers.every(function (timer) {
                return timer && typeof timer.value === 'string' && timer.value.length > 0 && timer.value.length <= 200 && typeof timer.label === 'string' && timer.label.length > 0 && timer.label.length <= 403;
            });
    }

    function refreshCampaigns() {
        var config = catalogState.settings.refresh;
        if (catalogState.loading || !config || !config.url) return;
        publishCatalog({ loading: true, error: false, updated: false });
        return Promise.resolve().then(function () {
            return window.fetch(config.url, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: 'action=email_countdown_timer_campaigns&post_id=' + encodeURIComponent(config.postId) + '&nonce=' + encodeURIComponent(config.nonce)
            });
        }).then(function (response) {
            if (!response.ok) throw new Error('catalog');
            return response.json();
        }).then(function (response) {
            if (!response.success || !validCatalog(response.data)) throw new Error('catalog');
            publishCatalog({ settings: Object.assign({}, catalogState.settings, response.data), loading: false, updated: true });
        }).catch(function () {
            // Keep the last list and selected ID on expiry, permission loss or a
            // network error. Only an explicit retry makes another request.
            publishCatalog({ loading: false, error: true });
        });
    }

    function Edit(props) {
        var attributes = props.attributes;
        var displayMode = attributes.displayMode === 'live' ? 'live' : 'image';
        var timerId = typeof attributes.timerId === 'string' ? attributes.timerId : '';
        var alignment = Object.prototype.hasOwnProperty.call(positions, attributes.alignment) ? attributes.alignment : 'left';
        // Core filters custom store keys in some editors. PHP attaches this bounded,
        // capability-checked payload only to our editor script, before it executes.
        var catalog = useCatalog();
        var settings = catalog.settings;
        var timers = Array.isArray(settings.timers) ? settings.timers : [];
        var searchState = wp.element.useState('');
        var search = searchState[0];
        var setSearch = searchState[1];
        var visibleTimers = timers.filter(function (timer) { return timer.label.toLowerCase().indexOf(search.toLowerCase()) !== -1; });
        var refreshState = wp.element.useState(0);
        var refresh = refreshState[0];
        var setRefresh = refreshState[1];
        var errorState = wp.element.useState(false);
        var failed = errorState[0];
        var setFailed = errorState[1];
        var draftState = wp.element.useState(timerId);
        var draft = draftState[0];
        var setDraft = draftState[1];
        wp.element.useEffect(function () { setFailed(false); setDraft(timerId); }, [timerId]);
        var previewUrl = wp.element.useMemo(function () {
            if (!timerId || !settings.imageUrl) return '';
            return settings.imageUrl + '&ecd=' + encodeURIComponent(timerId) + '&_t=' + Date.now() + '-' + refresh;
        }, [timerId, settings.imageUrl, refresh]);
        var found = timers.some(function (timer) { return timer.value === timerId; });
        var missing = !!timerId && !found && !settings.truncated;
        var blockProps = wp.blockEditor.useBlockProps();
        var controls = el(wp.blockEditor.InspectorControls, {},
            el(wp.components.PanelBody, { title: __('Countdown', 'easy-countdown'), initialOpen: true },
                // Keep the searchable input outside the canvas WritingFlow,
                // whose native arrow navigation can steal focus before React.
                timers.length || timerId ? el(wp.components.ComboboxControl, {
                    label: __('Timer', 'easy-countdown'), value: timerId,
                    options: visibleTimers, onFilterValueChange: function (value) { setSearch(value || ''); }, onChange: function (value) { setSearch(''); props.setAttributes({ timerId: value || '' }); },
                    help: __('Choose a saved timer. After creating a campaign in another tab, select Refresh campaigns.', 'easy-countdown')
                }) : null,
                el(wp.components.SelectControl, {
                    label: __('Website display', 'easy-countdown'), value: displayMode,
                    options: [
                        { label: __('Image (default)', 'easy-countdown'), value: 'image' },
                        { label: __('Live text', 'easy-countdown'), value: 'live' }
                    ], onChange: function (value) { props.setAttributes({ displayMode: value === 'live' ? 'live' : 'image' }); },
                    help: __('Live text uses the saved deadline, colors and labels with theme typography. It updates locally without image polling and includes a Pause control. Reduced-motion visitors start paused. Image fonts and dimensions apply to Image mode. End-image URLs are public before expiry.', 'easy-countdown')
                }),
                el(wp.components.SelectControl, {
                    label: __('Alignment', 'easy-countdown'), value: alignment,
                    options: [
                        { label: __('Left', 'easy-countdown'), value: 'left' },
                        { label: __('Center', 'easy-countdown'), value: 'center' },
                        { label: __('Right', 'easy-countdown'), value: 'right' }
                    ], onChange: function (value) { props.setAttributes({ alignment: value }); }
                }),
                settings.truncated ? el(wp.element.Fragment, {},
                    el('p', {}, __('The first 250 timer IDs are listed. Enter another existing ID below.', 'easy-countdown')),
                    el(wp.components.TextControl, { label: __('Timer ID', 'easy-countdown'), value: draft, maxLength: 200, onChange: setDraft }),
                    el(wp.components.Button, { variant: 'secondary', onClick: function () { props.setAttributes({ timerId: draft }); } }, __('Use timer ID', 'easy-countdown'))
                ) : null,
                settings.manageUrl ? el('a', { href: settings.manageUrl + (timerId ? '&edit=' + encodeURIComponent(timerId) : ''), target: '_blank', rel: 'noopener noreferrer' },
                    __('Open timer settings (new tab)', 'easy-countdown')) : null
            )
        );
        return el(wp.element.Fragment, {}, controls,
            el(wp.blockEditor.BlockControls, {}, el(wp.blockEditor.AlignmentToolbar, {
                value: alignment, onChange: function (value) { props.setAttributes({ alignment: value || 'left' }); }
            })),
            el('div', blockProps,
                timers.length && !timerId ? el('p', {}, __('Choose a saved timer in the block settings sidebar.', 'easy-countdown')) : null,
                !timers.length && !timerId ? el(wp.components.Placeholder, {
                    label: __('No campaigns yet', 'easy-countdown'),
                    instructions: settings.createUrl ? __('Create your first campaign in Easy Countdown, then return here and refresh the campaign list.', 'easy-countdown') : __('Ask a site administrator to create a campaign, then refresh the campaign list.', 'easy-countdown')
                }, settings.createUrl ? el('a', { href: settings.createUrl, target: '_blank', rel: 'noopener noreferrer' }, __('Create a campaign (new tab)', 'easy-countdown')) : null) : null,
                settings.refresh && settings.refresh.url ? el(wp.components.Button, {
                    variant: 'secondary', disabled: catalog.loading, isBusy: catalog.loading, onClick: refreshCampaigns
                }, __('Refresh campaigns', 'easy-countdown')) : null,
                catalog.loading ? el('p', { role: 'status' }, __('Refreshing campaigns…', 'easy-countdown')) : null,
                catalog.updated ? el('p', { role: 'status' }, __('Campaign list updated.', 'easy-countdown')) : null,
                catalog.error ? el(wp.components.Notice, { status: 'warning', isDismissible: false }, __('The campaign list could not refresh. Check your connection and editor access, then try again. Your selection has been kept. If your session expired, save your work before reopening the editor.', 'easy-countdown')) : null,
                missing ? el(wp.components.Notice, { status: 'warning', isDismissible: false }, __('This timer is no longer in the saved list. Choose another timer or refresh campaigns. A deleted timer displays nothing on the website.', 'easy-countdown')) : null,
                failed ? el(wp.components.Notice, { status: 'warning', isDismissible: false }, __('The static preview could not load. Check the timer and image cache settings.', 'easy-countdown')) : null,
                previewUrl && !missing ? el('div', { style: { display: 'flex', justifyContent: positions[alignment] } },
                    el('img', {
                        key: previewUrl, src: previewUrl, alt: __('Static preview of the selected countdown.', 'easy-countdown'),
                        referrerPolicy: 'no-referrer', draggable: false, loading: 'eager',
                        style: { display: failed ? 'none' : 'block', maxWidth: '100%', height: 'auto' },
                        onError: function () { setFailed(true); }, onLoad: function () { setFailed(false); }
                    })
                ) : null,
                previewUrl && !missing ? el(wp.components.Button, {
                    variant: 'secondary', onClick: function () { setFailed(false); setRefresh(function (value) { return value + 1; }); }
                }, __('Refresh preview', 'easy-countdown')) : null,
                displayMode === 'live' ? el('p', {}, __('Live text is enabled on the website. This image previews the campaign only; open the page to check theme typography and pause/resume. Clear any page cache after editing a campaign.', 'easy-countdown')) : null,
                timerId ? el('p', {}, __('Static editor preview. The website uses the saved timer, deadline, alternative text and end image. No campaign settings are copied into this block.', 'easy-countdown')) : null
            )
        );
    }
    // Server registration supplies metadata/attributes; JavaScript supplies the editor.
    wp.blocks.registerBlockType('easy-countdown/timer', {
        apiVersion: 3, edit: Edit, save: function () { return null; }
    });
})(window.wp);
