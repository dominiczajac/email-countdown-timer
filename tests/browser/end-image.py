"""Native Media Library selection, saving, removal and no-JS fallback in local WP."""
import json,os,pathlib,shutil
from playwright.sync_api import sync_playwright,expect
assert os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE')=='1'
base='http://localhost:8081';checks=[];errors=[];aid=os.environ['ECD_BROWSER_END_ID'].strip()
def check(v,n):
    if not v:raise AssertionError(n)
    checks.append(n)
def login(page):
    page.goto(base+'/wp-login.php');page.locator('#user_login').fill('ci-admin');page.locator('#user_pass').fill(os.environ['ECD_BROWSER_PASSWORD']);page.locator('#wp-submit').click();page.wait_for_url('**/wp-admin/**')
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=shutil.which('google-chrome') or shutil.which('chromium'),args=['--no-sandbox'])
    ctx=browser.new_context();page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.on('dialog',lambda d:d.accept())
    login(page);check(page.locator('[src*="assets/end-image.js"]').count()==0,'no picker script on Dashboard')
    page.goto(base+'/wp-admin/admin.php?page=ecd-timers');check(page.locator('[src*="assets/end-image.js"]').count()==0,'no picker script on timer list')
    page.goto(base+'/wp-admin/admin.php?page=ecd-timers&view=new');expect(page.get_by_role('heading',name='Easy Countdown',exact=True)).to_be_visible()
    check(page.locator('[src*="assets/end-image.js"]').count()==1,'picker script only once on editor')
    page.locator('#ect-timer_id').fill('end-browser');page.locator('#ect-deadline').fill('2030-01-01T00:00:05')
    page.get_by_role('button',name='Choose Image',exact=True).click()
    # Core may initially show Upload files; exercise its visible library tab.
    page.locator('.media-modal .media-router').get_by_role('tab',name='Media Library',exact=True).click()
    # The native modal queries the administrator's Media Library, not a plugin API.
    item=page.locator('.media-modal .attachment[data-id="'+aid+'"]');expect(item).to_be_visible(timeout=20000);item.click()
    page.get_by_role('button',name='Use This Image',exact=True).click()
    expect(page.locator('#ect-expiry_image_id')).to_have_value(aid);check(page.locator('#ect-end-choose').evaluate('(e)=>e===document.activeElement'),'picker returns focus to its trigger')
    page.locator('#ect-editor button[type=submit]').click();page.wait_for_url('**edit=end-browser**')
    check(page.locator('#ect-expiry_image_id').input_value()==aid,'selected ID persists in real WordPress')
    # Nonzero seconds avoid Playwright's strict comparison against minute-normalized input.
    page.locator('#ect-deadline').fill('2001-01-01T00:00:05');page.locator('#ect-editor button[type=submit]').click();page.wait_for_url('**status=saved**')
    expect(page.locator('#ect-preview')).to_have_js_property('complete',True)
    check(page.locator('#ect-preview').evaluate('(e)=>e.naturalWidth>1'),'expired saved preview remains a valid local image')
    page.locator('#ect-expiry_image_id').fill('999999999');page.locator('#ect-editor button[type=submit]').click()
    expect(page.locator('#ect-errors')).to_be_visible();check(page.locator('#ect-expiry_image_id').input_value()=='999999999','unavailable attachment shows field error and retains input')
    page.get_by_role('button',name='Remove End Image',exact=True).click();expect(page.locator('#ect-expiry_image_id')).to_have_value('0')
    page.locator('#ect-editor button[type=submit]').click();page.wait_for_url('**status=saved**')
    check(page.locator('#ect-expiry_image_id').input_value()=='0','removal persists without deleting Media Library file')
    page.screenshot(path=os.environ['RUNNER_TEMP']+'/admin-evidence/end-image.png',full_page=True)
    nojs=browser.new_context(java_script_enabled=False,storage_state=ctx.storage_state());plain=nojs.new_page()
    plain.goto(base+'/wp-admin/admin.php?page=ecd-timers&view=new');plain.locator('#ect-timer_id').fill('end-nojs');plain.locator('#ect-deadline').fill('2001-01-01T00:00:05');plain.locator('#ect-expiry_image_id').fill(aid);plain.locator('#ect-editor button[type=submit]').click();plain.wait_for_url('**edit=end-nojs**')
    check(plain.locator('#ect-expiry_image_id').input_value()==aid,'manual attachment ID works without JavaScript')
    check(not errors,'no browser exceptions');browser.close()
pathlib.Path(os.environ['RUNNER_TEMP']+'/admin-evidence/end-image.json').write_text(json.dumps({'checks':checks,'errors':errors},indent=2)+'\n')
print('PASS',len(checks),'native media/end-image browser checks')
