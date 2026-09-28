"""Explicit font-rule action and site-zone defaults in real WordPress admin."""
import json, os, pathlib, shutil
from playwright.sync_api import sync_playwright, expect
assert os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('ECD_INTEGRATION_DISPOSABLE')=='1'
base='http://localhost:8081'; checks=[]
def check(value,name):
    if not value:raise AssertionError(name)
    checks.append(name)
with sync_playwright() as p:
    binary=shutil.which('google-chrome') or shutil.which('chromium');assert binary
    browser=p.chromium.launch(executable_path=binary,args=['--no-sandbox']);ctx=browser.new_context();page=ctx.new_page()
    page.goto(base+'/wp-login.php');page.locator('#user_login').fill('ci-admin');page.locator('#user_pass').fill(os.environ['ECD_BROWSER_PASSWORD']);page.locator('#wp-submit').click();page.wait_for_url('**/wp-admin/**')
    page.goto(base+'/wp-admin/admin.php?page=ecd-timers&view=new')
    check(page.locator('#ect-tz').input_value()=='Pacific/Chatham','new editor inherits changed real site timezone')
    page.goto(base+'/wp-admin/admin.php?page=ecd-timers&edit=alt-browser-test')
    check(page.locator('#ect-tz').input_value()=='Europe/Warsaw','existing campaign does not inherit later site change')
    page.goto(base+'/wp-admin/admin.php?page=email-countdown-timer-data')
    expect(page.get_by_role('heading',name='Font File Access',exact=True)).to_be_visible()
    check('nginx ignores .htaccess' in page.locator('#email-countdown-font-access-title').locator('..').inner_text(),'server limitation visible before writing rules')
    page.get_by_role('button',name='Install Font Access Rules (Apache)',exact=True).click();page.wait_for_url('**font_rules=written**')
    check('HTTP protection is not verified.' in page.locator('[role=status]').inner_text(),'write success never claims verified HTTP denial')
    page.get_by_role('button',name='Install Font Access Rules (Apache)',exact=True).click();page.wait_for_url('**font_rules=written**')
    check(page.locator('[name=action][value=email_countdown_timer_font_access]').count()==1,'repeated form action remains available without overwriting existing files')
    page.screenshot(path=os.environ['RUNNER_TEMP']+'/admin-evidence/font-access.png',full_page=True)
    browser.close()
pathlib.Path(os.environ['RUNNER_TEMP']+'/admin-evidence/maintenance.json').write_text(json.dumps({'checks':checks},indent=2)+'\n')
print('PASS',len(checks),'maintenance browser checks')
