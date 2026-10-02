/* Native WordPress editor packages only. No frontend execution or polling. */
(function (wp) {
    'use strict';
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var emptySettings = { timers: [], truncated: false, imageUrl: '', manageUrl: '' };
    var positions = { left: 'flex-start', center: 'center', right: 'flex-end' };

    function Edit(props) {
        var attributes = props.attributes;
        var timerId = typeof attributes.timerId === 'string' ? attributes.timerId : '';
        var alignment = Object.prototype.hasOwnProperty.call(positions, attributes.alignment) ? attributes.alignment : 'left';
        var settings = wp.data.useSelect(function (select) {
            return select('core/block-editor').getSettings().easyCountdown || emptySettings;
        }, []);
        var timers = Array.isArray(settings.timers) ? settings.timers : [];
        var errorState = wp.element.useState(false);
        var failed = errorState[0];
        var setFailed = errorState[1];
        var draftState = wp.element.useState(timerId);
        var draft = draftState[0];
        var setDraft = draftState[1];
        wp.element.useEffect(function () { setFailed(false); setDraft(timerId); }, [timerId]);
        var previewUrl = wp.element.useMemo(function () {
            if (!timerId || !settings.imageUrl) return '';
            return settings.imageUrl + '&ecd=' + encodeURIComponent(timerId) + '&_t=' + Date.now();
        }, [timerId, settings.imageUrl]);
        var found = timers.some(function (timer) { return timer.value === timerId; });
        var missing = !!timerId && !found && !settings.truncated;
        var blockProps = wp.blockEditor.useBlockProps();
        var controls = el(wp.blockEditor.InspectorControls, {},
            el(wp.components.PanelBody, { title: __('Countdown', 'easy-countdown'), initialOpen: true },
                el(wp.components.SelectControl, {
                    label: __('Alignment', 'easy-countdown'), value: alignment,
                    options: [
                        { label: __('Left', 'easy-countdown'), value: 'left' },
                        { label: __('Center', 'easy-countdown'), value: 'center' },
                        { label: __('Right', 'easy-countdown'), value: 'right' }
                    ], onChange: function (value) { props.setAttributes({ alignment: value }); }
                }),
                settings.truncated ? el(wp.element.Fragment, {},
                    el('p', {}, __('The first 250 timer IDs are listed. Enter another existing ID below, or reopen the editor to refresh the list.', 'easy-countdown')),
                    el(wp.components.TextControl, { label: __('Timer ID', 'easy-countdown'), value: draft, maxLength: 200, onChange: setDraft }),
                    el(wp.components.Button, { variant: 'secondary', onClick: function () { props.setAttributes({ timerId: draft }); } }, __('Use timer ID', 'easy-countdown'))
                ) : null,
                settings.manageUrl ? el('a', { href: settings.manageUrl + (timerId ? '&edit=' + encodeURIComponent(timerId) : ''), target: '_blank', rel: 'noopener noreferrer' },
                    __('Open timer settings (new tab)', 'easy-countdown')) : null
            )
        );
        return el(wp.element.Fragment, {}, controls,
            el('div', blockProps,
                el(wp.components.ComboboxControl, {
                    label: __('Timer', 'easy-countdown'), value: timerId,
                    options: timers, onChange: function (value) { props.setAttributes({ timerId: value || '' }); },
                    help: __('Choose a saved timer. Reopen the editor after creating or renaming a campaign.', 'easy-countdown')
                }),
                !timers.length ? el('p', {}, settings.manageUrl ? __('Create a timer in Easy Countdown, then reopen this editor.', 'easy-countdown') : __('Ask a site administrator to create a timer.', 'easy-countdown')) : null,
                missing ? el(wp.components.Notice, { status: 'warning', isDismissible: false }, __('This timer is no longer in the saved list. Choose another timer or reopen the editor. A deleted timer displays nothing on the website.', 'easy-countdown')) : null,
                failed ? el(wp.components.Notice, { status: 'warning', isDismissible: false }, __('The static preview could not load. Check the timer and image cache settings.', 'easy-countdown')) : null,
                previewUrl && !missing ? el('div', { style: { display: 'flex', justifyContent: positions[alignment] } },
                    el('img', {
                        key: previewUrl, src: previewUrl, alt: __('Static preview of the selected countdown.', 'easy-countdown'),
                        referrerPolicy: 'no-referrer', draggable: false, loading: 'eager',
                        style: { display: failed ? 'none' : 'block', maxWidth: '100%', height: 'auto' },
                        onError: function () { setFailed(true); }, onLoad: function () { setFailed(false); }
                    })
                ) : null,
                timerId ? el('p', {}, __('Static editor preview. The website uses the saved timer, deadline, alternative text and end image. No campaign settings are copied into this block.', 'easy-countdown')) : null
            )
        );
    }
    // Server registration supplies metadata/attributes; JavaScript supplies the editor.
    wp.blocks.registerBlockType('easy-countdown/timer', {
        apiVersion: 3, edit: Edit, save: function () { return null; }
    });
})(window.wp);
