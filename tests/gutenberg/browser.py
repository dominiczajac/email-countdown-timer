"""Actual WordPress editor/HTTP checks. Fixed loopback-only disposable target."""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
from playwright.sync_api import sync_playwright, expect

assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
base = 'http://localhost:8093'
evidence = Path(os.environ['ECD_BLOCK_EVIDENCE'])
wpdir = Path(os.environ['ECD_BLOCK_WP_PATH']).resolve()
assert wpdir.is_relative_to(Path(os.environ['RUNNER_TEMP']).resolve())
post_id = int(os.environ['ECD_BLOCK_POST_ID'])
checks, errors, requests = [], [], []
complete = False

def check(value, name):
    if not value:
        raise AssertionError(name)
    checks.append(name)

def wp(*args):
    return subprocess.check_output(['wp', '--path=' + str(wpdir), '--no-color', *args], text=True).strip()

def block_frame(page):
    for frame in page.frames:
        if frame.locator('.wp-block-easy-countdown-timer').count():
            return frame
    raise AssertionError('No rendered native block in the editor or its iframe')

def open_editor(page):
    page.goto(base + '/wp-admin/post.php?post=' + str(post_id) + '&action=edit')
    page.wait_for_function("window.wp && wp.blocks && wp.blocks.getBlockType('easy-countdown/timer') && wp.data.select('core/editor').getCurrentPostId()")
    page.evaluate("""() => {
        wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
        wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
    }""")
    # Wait for the real, permission-checked inline bootstrap. Never inject test settings.
    try:
        page.wait_for_function("window.emailCountdownTimerBlock")
    except Exception:
        page.screenshot(path=str(evidence/'editor-settings-failure.png'), full_page=True)
        (evidence/'editor-settings-failure.json').write_text(json.dumps({
            'bootstrap_contains_our_key': 'emailCountdownTimerBlock' in page.content(),
            'store_keys': page.evaluate("Object.keys(wp.data.select('core/block-editor').getSettings())"),
            'page_errors': errors
        }, indent=2))
        raise
    page.wait_for_function("""() => document.querySelector('.block-editor-block-list__layout') || Array.from(document.querySelectorAll('iframe')).some(f=>f.contentDocument && f.contentDocument.querySelector('.block-editor-block-list__layout'))""")
    # Dismiss a guide if already mounted; this never skips block assertions.
    for button in page.get_by_role('button', name=re.compile('Close.*(dialog|guide)', re.I)).all():
        if button.is_visible():
            button.click()

def insert(page, attributes):
    return page.evaluate("""(attrs) => {
        const block=wp.blocks.createBlock('easy-countdown/timer', attrs);
        wp.data.dispatch('core/block-editor').insertBlock(block);
        return block.clientId;
    }""", attributes)

original = wp('option', 'get', 'easy_countdown_timers', '--format=json')
try:
    with sync_playwright() as p:
        executable = shutil.which('google-chrome') or shutil.which('chromium')
        assert executable, 'A real installed Chromium binary is required'
        browser = p.chromium.launch(executable_path=executable, args=['--no-sandbox'])
        context = browser.new_context(viewport={'width':1440,'height':1000}, reduced_motion='reduce')
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.on('request', lambda request: requests.append(request.url))
        page.on('dialog', lambda dialog: dialog.accept() if dialog.type == 'beforeunload' else dialog.dismiss())
        page.goto(base + '/wp-login.php')
        page.locator('#user_login').fill('ci-admin')
        page.locator('#user_pass').fill(os.environ['ECD_BLOCK_PASSWORD'])
        page.locator('#wp-submit').click()
        page.wait_for_url('**/wp-admin/**')
        check(page.locator('script[src*="assets/block-editor.js"]').count()==0,'block editor code absent on dashboard')
        open_editor(page)
        data = page.evaluate("window.emailCountdownTimerBlock")
        check(sorted(x['value'] for x in data['timers']) == ['gutenberg-a','gutenberg-b'], 'native editor receives actual saved IDs')
        check(all(set(x)=={'value','label'} for x in data['timers']), 'no raw campaign fields passed into choices')
        check(page.evaluate("wp.blocks.getBlockType('easy-countdown/timer').apiVersion") == 3, 'client registered with API 3')
        first = insert(page, {})
        page.wait_for_function("""() => Array.from(document.querySelectorAll('iframe')).some(f=>f.contentDocument && f.contentDocument.querySelector('.wp-block-easy-countdown-timer')) || document.querySelector('.wp-block-easy-countdown-timer')""")
        frame = block_frame(page)
        combo = frame.get_by_role('combobox', name='Timer', exact=True).first
        expect(combo).to_be_visible()
        combo.fill('gutenberg-a')
        combo.press('ArrowDown')
        combo.press('Enter')
        page.wait_for_function("(id)=>wp.data.select('core/block-editor').getBlock(id).attributes.timerId==='gutenberg-a'", arg=first)
        check(True, 'select existing timer with keyboard in native ComboboxControl')
        preview = frame.locator('.wp-block-easy-countdown-timer img').first
        expect(preview).to_have_js_property('complete',True)
        check(preview.evaluate('(img)=>img.naturalWidth>1'),'static preview is an actual image')
        check('mode=static' in preview.get_attribute('src'),'editor requests static not animated image')
        old_preview = preview.get_attribute('src')
        frame.get_by_role('button', name='Refresh preview', exact=True).click()
        expect(preview).not_to_have_attribute('src', old_preview)
        expect(preview).to_have_js_property('complete', True)
        check(preview.evaluate('(img)=>img.naturalWidth>1'), 'explicit refresh fetches a current static image')
        expect(page.get_by_label('Alignment', exact=True)).to_be_visible()
        page.get_by_label('Alignment', exact=True).select_option('center')
        page.wait_for_function("(id)=>wp.data.select('core/block-editor').getBlock(id).attributes.alignment==='center'", arg=first)
        check(True,'sidebar alignment updates block attributes')
        link=page.get_by_role('link',name='Open timer settings (new tab)',exact=True)
        expect(link).to_be_visible()
        check('edit=gutenberg-a' in link.get_attribute('href') and link.get_attribute('rel')=='noopener noreferrer','administrator settings link scoped and safely opens another tab')
        before=len([x for x in requests if 'ecd_action=render' in x])
        page.wait_for_timeout(1200)
        check(len([x for x in requests if 'ecd_action=render' in x])==before,'no periodic preview polling in observation window')
        check(not any(('mode=anim' in x or 'mode=email' in x) for x in requests if 'ecd_action=render' in x),'no animated request from editor')
        page.screenshot(path=str(evidence/'editor-selected.png'), full_page=True)
        second=insert(page,{'timerId':'gutenberg-b','alignment':'right'})
        insert(page,{})
        page.evaluate("async()=>{await wp.data.dispatch('core/editor').savePost();}")
        page.wait_for_function("!wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/editor').isEditedPostDirty()")
        content=wp('post','get',str(post_id),'--field=post_content')
        check(content.count('wp:easy-countdown/timer')==3 and 'gutenberg-a' in content and 'gutenberg-b' in content,'core saves multiple dynamic blocks')
        check('<img' not in content and '2035' not in content and 'Gutenberg campaign A' not in content,'saved post has references, not copied image/configuration')
        open_editor(page)
        page.wait_for_function("wp.data.select('core/block-editor').getBlocks().filter(b=>b.name==='easy-countdown/timer').length===3")
        blocks=page.evaluate("wp.data.select('core/block-editor').getBlocks().filter(b=>b.name==='easy-countdown/timer').map(b=>({attrs:b.attributes,valid:b.isValid}))")
        check(all(b['valid'] for b in blocks),'reopen has no invalid block')
        check(blocks[0]['attrs']['timerId']=='gutenberg-a' and blocks[0]['attrs']['alignment']=='center','selection and alignment survive reopen')
        check(json.loads(wp('option','get','easy_countdown_timers','--format=json'))==json.loads(original),'block edits do not mutate saved campaigns')
        # New anonymous context: no administrator cookie, actual frontend image fetches.
        anon=browser.new_context(viewport={'width':1280,'height':900})
        front=anon.new_page()
        front_requests=[]
        front.on('request',lambda request:front_requests.append(request.url))
        response = front.goto(base+'/?p='+str(post_id))
        check(response.headers.get('x-ect-test-sapi') == 'fpm-fcgi' and response.headers.get('x-ect-test-gd') == 'yes', 'actual PHP-FPM and GD confirmed over HTTP')
        check(response.headers.get('x-ect-test-opcache') == 'on', 'OPcache remains enabled in the tested PHP-FPM process')
        (evidence/'http-environment.json').write_text(json.dumps({key:value for key,value in response.headers.items() if key.startswith('x-ect-test-')},indent=2))
        imgs=front.locator('.wp-block-easy-countdown-timer img')
        expect(imgs).to_have_count(2)
        for image in imgs.all():
            expect(image).to_have_js_property('complete',True)
            check(image.evaluate('(img)=>img.naturalWidth>1'),'anonymous block image renders')
            check(image.get_attribute('width') and image.get_attribute('height') and 'aspect-ratio:' in image.get_attribute('style'),'frontend reserves measured space')
        check(len(set(imgs.evaluate_all('(xs)=>xs.map(x=>x.id)')))==2,'multiple block images have unique IDs')
        check(imgs.first.get_attribute('alt')=='Gutenberg campaign A','frontend uses saved alternative text')
        check(front.locator('.wp-block-easy-countdown-timer').first.evaluate('(el)=>getComputedStyle(el).justifyContent')=='center','frontend centered alignment')
        check(front.locator('.wp-block-easy-countdown-timer').nth(1).evaluate('(el)=>getComputedStyle(el).justifyContent')=='flex-end','frontend right alignment')
        check(not any('block-editor.js' in x for x in front_requests),'new editor JS never loaded by frontend')
        for width in [320,768]:
            front.set_viewport_size({'width':width,'height':900})
            check(imgs.first.evaluate('(el)=>el.getBoundingClientRect().width<=el.parentElement.getBoundingClientRect().width+1'),'image fits narrow container '+str(width))
        front.screenshot(path=str(evidence/'frontend-narrow.png'),full_page=True)
        # Delay all image downloads and compare the actual block boxes before/after.
        measured=anon.new_page()
        captured=[]
        def delay(route):
            route.request  # The fixture remains an actual WordPress endpoint, not a substitute image.
            import time
            time.sleep(0.3)
            captured.append(measured.locator('.wp-block-easy-countdown-timer').evaluate_all('(xs)=>xs.map(x=>({top:x.getBoundingClientRect().top,height:x.getBoundingClientRect().height}))'))
            route.continue_()
        measured.route('**/*ecd_action=render*',delay)
        measured.goto(base+'/?p='+str(post_id),wait_until='networkidle')
        after=measured.locator('.wp-block-easy-countdown-timer').evaluate_all('(xs)=>xs.map(x=>({top:x.getBoundingClientRect().top,height:x.getBoundingClientRect().height}))')
        check(bool(captured) and len(captured[0]) == 2 and len(after) == 2 and all(abs(x['height']-y['height'])<1 and abs(x['top']-y['top'])<1 for x,y in zip(captured[0],after)),'delayed image does not move tested block boxes')
        # Known benign fixture removal, not probing private media or production.
        wp('option','patch','delete','easy_countdown_timers','gutenberg-a')
        front.reload()
        expect(front.locator('.wp-block-easy-countdown-timer img')).to_have_count(1)
        check(front.locator('.wp-block-easy-countdown-timer img').get_attribute('alt')=='Gutenberg campaign B','deleted campaign disappears without affecting other block')
        open_editor(page)
        frame=block_frame(page)
        expect(frame.get_by_text('This timer is no longer in the saved list.',exact=False)).to_be_visible()
        check(True,'missing saved timer shows editor warning')
        check(not errors,'no uncaught editor JavaScript errors')
        complete = True
        browser.close()
finally:
    wp('option','update','easy_countdown_timers',original,'--format=json')
    (evidence/'browser.json').write_text(json.dumps({'checks':checks,'count':len(checks),'page_errors':errors,'theme':os.environ.get('ECD_BLOCK_THEME'),'wordpress':os.environ.get('ECD_WORDPRESS_VERSION'),'complete':complete},indent=2)+'\n')
print('GUTENBERG BROWSER PASS:',len(checks),'checks')
