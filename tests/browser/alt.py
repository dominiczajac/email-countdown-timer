"""Actual admin/shortcode/privacy contracts. No paid optimizer compatibility claim."""
import json
import os
import pathlib
import shutil
from html.parser import HTMLParser
from playwright.sync_api import sync_playwright, expect

assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
base = 'http://localhost:8081'
evidence = pathlib.Path(os.environ['RUNNER_TEMP']) / 'admin-evidence'
checks = []

def check(value, name):
    if not value:
        raise AssertionError(name)
    checks.append(name)

class TimerImageParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.images = []
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'img' and 'email-countdown-timer-image' in values.get('class', '').split():
            self.images.append(values)

with sync_playwright() as p:
    executable = shutil.which('google-chrome') or shutil.which('chromium')
    assert executable
    browser = p.chromium.launch(executable_path=executable, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 1440, 'height': 1000})
    page = context.new_page()
    page.on('dialog', lambda d: d.accept() if d.type == 'beforeunload' else d.dismiss())
    page.goto(base + '/wp-login.php')
    page.locator('#user_login').fill('ci-admin')
    page.locator('#user_pass').fill(os.environ['ECD_BROWSER_PASSWORD'])
    page.locator('#wp-submit').click()
    page.wait_for_url('**/wp-admin/**')
    page.goto(base + '/wp-admin/admin.php?page=ecd-timers&view=new')
    custom_alt = 'Offer "summer" & deadline — 2030'
    page.get_by_label('Alternative Text (alt)', exact=True).fill(custom_alt)
    page.locator('#ect-timer_id').fill('alt-browser-test')
    page.locator('#ect-deadline').fill('2030-12-31T23:59:59')
    page.locator('#ect-editor button[type=submit]').click()
    page.wait_for_url('**edit=alt-browser-test**')
    expect(page.get_by_text('Timer saved.', exact=True)).to_be_visible()
    check(page.locator('#ect-alt').input_value() == custom_alt, 'alt roundtrips through real WordPress database')
    expect(page.locator('#ect-preview')).to_have_attribute('alt', custom_alt)
    parser = TimerImageParser()
    parser.feed(page.locator('#ect-manual-html').input_value())
    check(len(parser.images) == 1 and parser.images[0]['alt'] == custom_alt, 'copied HTML contains exact escaped alternative text')
    check('onerror' not in parser.images[0], 'no injected event-handler attribute')
    page.screenshot(path=str(evidence / 'alternative-text-editor.png'), full_page=True)
    # Unauthenticated HTTP, separate from the administrator's cookie jar.
    anonymous = p.request.new_context()
    response = anonymous.get(base + '/?page_id=' + os.environ['ECD_BROWSER_PAGE_ID'])
    public = TimerImageParser()
    public.feed(response.text())
    check(response.status == 200 and len(public.images) == 1, 'public WordPress page renders real shortcode')
    attrs = public.images[0]
    check(attrs['alt'] == custom_alt, 'shortcode uses saved alternative text')
    check(attrs['loading'] == 'eager' and attrs['data-no-lazy'] == '1', 'shortcode supplies targeted LazyLoad markers')
    check(attrs['referrerpolicy'] == 'no-referrer', 'shortcode minimizes referrer disclosure')
    image = anonymous.get(base + '/?ecd_action=render&ecd=alt-browser-test&mode=email')
    check(image.status == 200 and image.headers.get('content-type', '').startswith('image/gif'), 'anonymous image is GIF, not page HTML')
    check('no-store' in image.headers.get('cache-control', ''), 'image response is not persistently cacheable')
    check(image.headers.get('x-content-type-options') == 'nosniff', 'binary response keeps nosniff')
    check('set-cookie' not in image.headers, 'isolated image endpoint sets no cookie')
    anonymous.dispose()
    page.locator('#ect-tz').fill('invalid/time-zone')
    page.locator('#ect-editor button[type=submit]').click()
    expect(page.locator('#ect-tz')).to_have_attribute('aria-invalid', 'true')
    check(page.locator('#ect-alt').input_value() == custom_alt, 'alt survives server-side validation errors')
    # A separate no-JS browser proves the field is not injected by JavaScript.
    nojs = browser.new_context(storage_state=context.storage_state(), java_script_enabled=False)
    plain = nojs.new_page()
    plain.goto(base + '/wp-admin/admin.php?page=ecd-timers&edit=alt-browser-test')
    plain.get_by_label('Alternative Text (alt)', exact=True).fill('')
    plain.locator('#ect-editor button[type=submit]').click()
    expect(plain.get_by_text('Timer saved.', exact=True)).to_be_visible()
    check(plain.locator('#ect-alt').input_value() == '', 'no-JS save restores automatic alternative text')
    fallback = TimerImageParser()
    fallback.feed(plain.locator('#ect-manual-html').input_value())
    check(fallback.images[0]['alt'].startswith('Countdown ends '), 'blank email alternative text keeps descriptive fallback')
    check('2030-12-31' in plain.locator('#ect-manual-html').input_value(), 'email retains visible absolute deadline')
    nojs.close()
    (evidence / 'alt-checks.json').write_text(json.dumps({'count': len(checks), 'checks': checks, 'browser': browser.version, 'limits': 'Disposable WordPress; paid FlyingPress/WP Rocket binaries, hosting logs and email-client behavior are not tested.'}, indent=2) + '\n')
    browser.close()
print(f'PASS {len(checks)} actual WordPress alt/privacy checks')
