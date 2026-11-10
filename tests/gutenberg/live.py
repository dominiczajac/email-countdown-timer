"""Native live-mode assertions shared by hosted and explicitly owned local fixtures."""
from datetime import datetime, timedelta, timezone
from pathlib import Path
import json
from playwright.sync_api import expect


def verify_live(page, browser, wp, base, evidence, check, open_editor, insert):
    fixture = json.loads(wp('eval-file', str(Path(__file__).with_name('live-fixture.php'))))
    post = fixture['post']
    open_editor(page, post)
    first = insert(page, {'timerId': 'gutenberg-live'})
    mode = page.get_by_label('Website display', exact=True)
    expect(mode).to_have_value('image')
    mode.select_option('live')
    page.wait_for_function("(id)=>wp.data.select('core/block-editor').getBlock(id).attributes.displayMode==='live'", arg=first)
    check(True, 'native sidebar explicitly opts in to live text')
    insert(page, {'timerId': 'gutenberg-live-end', 'displayMode': 'live'})
    insert(page, {'timerId': 'gutenberg-ended', 'displayMode': 'live'})
    page.evaluate("async()=>{await wp.data.dispatch('core/editor').savePost();}")
    page.wait_for_function("!wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/editor').isEditedPostDirty()")
    saved = wp('post', 'get', str(post), '--field=post_content')
    check(saved.count('"displayMode":"live"') == 3 and '2035' not in saved and 'PRIVATE-LIVE-NAME' not in saved, 'saved live blocks retain only references and display choices')
    open_editor(page, post)
    page.wait_for_function("wp.data.select('core/block-editor').getBlocks().filter(b=>b.name==='easy-countdown/timer').length===3")
    check(page.evaluate("wp.data.select('core/block-editor').getBlocks().every(b=>b.isValid && b.attributes.displayMode==='live')"), 'live mode survives native editor reopen without invalid blocks')

    context = browser.new_context(viewport={'width': 1440, 'height': 1000}, reduced_motion='no-preference')
    front = context.new_page()
    errors, requests = [], []
    front.on('pageerror', lambda error: errors.append(str(error)))
    front.on('request', lambda request: requests.append(request.url))
    # Much newer than PHP's rendered snapshot, exercising stale page HTML without
    # clock synchronization or server polling. This future campaign has no image.
    instant = datetime(2035, 12, 31, 23, 57, 1, tzinfo=timezone.utc)
    # Patch only Date.now. Playwright's Clock also replaces Performance and
    # removes navigation entries consumed by WordPress Interactivity API.
    front.add_init_script('window.emailCountdownTestNow = ' + str(int(instant.timestamp() * 1000)) + '; Date.now = () => window.emailCountdownTestNow;')
    front.goto(base + '/?p=' + str(post))
    roots = front.locator('.ecd-live')
    expect(roots).to_have_count(3)
    first_root = roots.first
    pause = first_root.get_by_role('button', name='Pause countdown', exact=True)
    expect(pause).to_be_visible()
    expect(first_root.locator('[data-ecd-unit="d"]')).to_have_text('00')
    check(first_root.locator('time').get_attribute('datetime') == '2035-12-31T23:59:59+00:00', 'cached snapshot catches up using the PHP-parsed absolute deadline')
    check(first_root.locator('.ecd-live__digits').get_attribute('aria-hidden') == 'true' and first_root.locator('[aria-live]').count() == 0, 'screen readers get the static deadline without repeating announcements')
    check(first_root.locator('.ecd-live__label').first.get_attribute('title') == 'Very long custom day label' and first_root.locator('.ecd-live__label').last.inner_text() == '', 'live text preserves long and explicitly empty labels')
    expect(roots.nth(2).locator('img')).to_have_js_property('complete', True)
    check(roots.nth(2).locator('img').evaluate('(img)=>img.naturalWidth>1') and not roots.nth(2).locator('.ecd-live__digits').is_visible(), 'already expired campaign renders its real local end image')
    image_requests = [url for url in requests if 'ecd_action=render' in url]
    check(len(image_requests) == 1 and 'mode=end' in image_requests[0], 'three live blocks make only one configured end-image request')
    before = first_root.locator('[data-ecd-unit="s"]').inner_text()
    pause.focus(); pause.press('Enter')
    expect(first_root.get_by_role('button', name='Resume countdown', exact=True)).to_be_focused()
    instant += timedelta(seconds=5)
    front.evaluate('(value) => { window.emailCountdownTestNow = value; }', int(instant.timestamp() * 1000))
    front.wait_for_timeout(1200)
    check(first_root.locator('[data-ecd-unit="s"]').inner_text() == before, 'keyboard pause freezes the displayed snapshot')
    first_root.get_by_role('button', name='Resume countdown', exact=True).press('Enter')
    check(first_root.locator('[data-ecd-unit="s"]').inner_text() != before, 'keyboard resume catches up without resetting the deadline')
    instant += timedelta(seconds=65)
    front.evaluate('(value) => { window.emailCountdownTestNow = value; }', int(instant.timestamp() * 1000))
    expect(first_root.locator('[data-ecd-unit="m"]')).to_have_text('01')
    check(len([url for url in requests if 'ecd_action=render' in url]) == 1 and first_root.locator('[data-ecd-unit="m"]').inner_text() == '01', 'live text keeps updating beyond 60 seconds without polling images')
    for width in [320, 375, 768, 1440]:
        front.set_viewport_size({'width': width, 'height': 1000})
        check(first_root.evaluate('(el)=>el.scrollWidth<=el.clientWidth+1 && el.getBoundingClientRect().width<=el.parentElement.getBoundingClientRect().width+1'), 'live component fits viewport ' + str(width))
    height = first_root.locator('.ecd-live__stage').bounding_box()['height']
    instant += timedelta(seconds=120)
    front.evaluate('(value) => { window.emailCountdownTestNow = value; }', int(instant.timestamp() * 1000))
    expect(first_root.locator('[data-ecd-unit="s"]')).to_have_text('00')
    expect(first_root.get_by_role('button', name='Countdown ended', exact=True)).to_be_disabled()
    check(abs(first_root.locator('.ecd-live__stage').bounding_box()['height'] - height) < 1, 'live expiry keeps the reserved stage height')
    skew_image = roots.nth(1).locator('img')
    expect(skew_image).to_have_js_property('complete', True)
    color = skew_image.evaluate('''(img) => {
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
        const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0);
        return Array.from(ctx.getImageData(Math.floor(canvas.width/2), Math.floor(canvas.height/2), 1, 1).data).slice(0,3);
    }''')
    check(color == [20, 90, 120], 'browser ahead of server receives real end-image pixels instead of a pre-deadline timer')
    (evidence / 'live-events.json').write_text(json.dumps({'errors': errors, 'image_requests': [url for url in requests if 'ecd_action=render' in url]}, indent=2))
    check(len([url for url in requests if 'ecd_action=render' in url]) == 2 and not errors, 'expiry introduces no polling or uncaught JavaScript errors')
    check('PRIVATE-LIVE-NAME' not in front.content() and not any('block-editor.js' in url for url in requests), 'live frontend excludes private names and editor scripts')
    front.set_viewport_size({'width': 320, 'height': 1000})
    front.screenshot(path=str(evidence / 'live-narrow.png'), full_page=True)
    context.close()

    reduced = browser.new_context(reduced_motion='reduce')
    rp = reduced.new_page(); rp.goto(base + '/?p=' + str(post))
    resume = rp.locator('.ecd-live').first.get_by_role('button', name='Resume countdown', exact=True)
    expect(resume).to_be_visible()
    before = rp.locator('.ecd-live').first.locator('[data-ecd-unit="s"]').inner_text()
    rp.wait_for_timeout(1200)
    check(rp.locator('.ecd-live').first.locator('[data-ecd-unit="s"]').inner_text() == before, 'native reduced-motion preference starts paused')
    reduced.close()
    nojs = browser.new_context(java_script_enabled=False, viewport={'width': 320, 'height': 900})
    np = nojs.new_page(); np.goto(base + '/?p=' + str(post))
    expect(np.locator('.ecd-live').first.get_by_text('Without JavaScript, these digits are a snapshot.', exact=False)).to_be_visible()
    check(np.locator('.ecd-live').first.locator('time').is_visible() and not np.locator('.ecd-live').first.locator('button').is_visible(), 'no-JS snapshot has a readable deadline and no inert pause control')
    nojs.close()
