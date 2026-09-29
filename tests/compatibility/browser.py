"""Browser compatibility of the real package; no plugin or WordPress API doubles."""
import json,os,pathlib,shutil
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright,expect
assert os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE')=='1'
base='http://localhost:8081';evidence=pathlib.Path(os.environ['ECD_COMPAT_EVIDENCE'])
from cli_json import read_cli_json
fixture=read_cli_json(evidence/'fixture.json'); checks=[]; failures=[]; js_errors=[];blocked=set()
def check(value,name):
    (checks if value else failures).append(name)
def submit(page):
    with page.expect_navigation(wait_until='domcontentloaded'):
        page.locator('#ect-editor button[type=submit]').click()
def route(r):
    host=urlsplit(r.request.url).hostname
    if host and host not in ('localhost','127.0.0.1'):
        blocked.add(host);r.abort()
    else:r.continue_()
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=shutil.which('google-chrome') or shutil.which('chromium'),args=['--no-sandbox'])
    ctx=browser.new_context(viewport={'width':1440,'height':1000});ctx.route('**/*',route)
    page=ctx.new_page();page.on('pageerror',lambda e:js_errors.append(str(e)));page.on('dialog',lambda d:d.accept())
    try:
        page.goto(base+'/',wait_until='domcontentloaded')
        image=page.locator('img[data-ecd-src]').first
        expect(image).to_be_visible();expect(image).to_have_js_property('complete',True)
        check(image.evaluate('(e)=>e.naturalWidth>1'),'anonymous shortcode image loads through active optimizers')
        page.wait_for_function('window.emailCountdownTimerRefreshInstalled === true')
        old=image.get_attribute('src');page.evaluate("document.dispatchEvent(new Event('visibilitychange'))")
        expect(image).not_to_have_attribute('src',old)
        check('ecd_action=render' in image.get_attribute('src'),'optimized refresh script reacts to visibility event')
        check(page.locator('[src*="assets/admin.js"],[src*="assets/end-image.js"]').count()==0,'no plugin admin/media scripts on public page')
        for name,url in (fixture['extra'] or {}).items():
            page.goto(url,wait_until='domcontentloaded')
            if name=='product_url':
                expect(page.get_by_role('heading',name='Compatibility sample product',exact=True)).to_be_visible()
                check(page.locator('.single_add_to_cart_button').count()>0,'WooCommerce product page and add-to-cart control render')
            if name=='elementor_url':
                expect(page.locator('.elementor-widget-shortcode')).to_be_visible()
                check(page.locator('.elementor-widget-shortcode img[data-ecd-src]').count()>0,'real Elementor shortcode widget renders countdown')
        page.goto(base+'/wp-login.php');page.locator('#user_login').fill('ci-admin');page.locator('#user_pass').fill(os.environ['ECD_BROWSER_PASSWORD'])
        # WooCommerce can legitimately redirect a successful login to the storefront.
        with page.expect_navigation(wait_until='domcontentloaded'):
            page.locator('#wp-submit').click()
        page.goto(base+'/wp-admin/index.php');expect(page.locator('#adminmenu')).to_be_visible()
        check(page.locator('[src*="assets/admin.js"]').count()==0,'Easy Countdown assets stay off Dashboard with other plugins active')
        page.goto(base+'/wp-admin/admin.php?page=ecd-timers&view=new')
        page.locator('#ect-timer_id').fill('compat-browser');page.locator('#ect-deadline').fill('2001-01-01T00:00:05')
        page.locator('#ect-alt').fill('Compatibility image after expiry')
        page.get_by_role('button',name='Choose Image',exact=True).click()
        page.locator('.media-modal .media-router').get_by_role('tab',name='Media Library',exact=True).click()
        item=page.locator('.media-modal .attachment[data-id="'+str(fixture['attachment_id'])+'"]')
        expect(item).to_be_visible(timeout=20000);item.click();page.get_by_role('button',name='Use This Image',exact=True).click()
        submit(page);expect(page.get_by_text('Timer saved.',exact=True)).to_be_visible()
        check(page.locator('#ect-expiry_image_id').input_value()==str(fixture['attachment_id']),'native Media Library choice saves with other plugins active')
        expect(page.locator('#ect-preview')).to_have_js_property('complete',True)
        check(page.locator('#ect-preview').evaluate('(e)=>e.naturalWidth>1'),'expired admin preview is a valid image')
        page.locator('#ect-alt').fill('Updated compatibility alt');submit(page)
        check(page.locator('#ect-alt').input_value()=='Updated compatibility alt','existing timer edits and alt persist')
        missing=page.locator('.email-countdown-admin input:not([type=hidden]),.email-countdown-admin select,.email-countdown-admin textarea').evaluate_all('(es)=>es.filter(e=>!e.labels.length).map(e=>e.id)')
        check(not missing,'plugin controls retain associated labels')
        page.set_viewport_size({'width':375,'height':900})
        check(page.locator('.email-countdown-admin').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'),'plugin editor retains narrow-screen layout')
        page.screenshot(path=str(evidence/'editor.png'),full_page=True)
        nojs=browser.new_context(java_script_enabled=False,storage_state=ctx.storage_state());nojs.route('**/*',route);plain=nojs.new_page()
        plain.goto(base+'/wp-admin/admin.php?page=ecd-timers&view=new');plain.locator('#ect-timer_id').fill('compat-nojs');plain.locator('#ect-deadline').fill('2001-01-01T00:00:05');plain.locator('#ect-expiry_image_id').fill(str(fixture['attachment_id']));submit(plain)
        check(plain.locator('#ect-expiry_image_id').input_value()==str(fixture['attachment_id']),'no-JavaScript form remains functional')
        check(not js_errors,'no uncaught JavaScript exceptions in tested pages')
    except Exception as e:
        failures.append(type(e).__name__+': '+str(e))
        page.screenshot(path=str(evidence/'failure.png'),full_page=True)
    finally: browser.close()
report={'profile':os.environ['ECD_COMPAT_PROFILE'],'checks':checks,'failures':failures,'js_errors':js_errors,'blocked_external_hosts':sorted(blocked),'limitations':'Remote services intentionally blocked. No payment, newsletter, WAF-cloud, editor drag-and-drop or screen-reader certification.'}
(evidence/'browser-checks.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2));raise SystemExit(1 if failures else 0)
