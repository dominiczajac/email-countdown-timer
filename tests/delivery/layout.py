"""Actual WP markup/images, controlled browser loading; not field Core Web Vitals."""
import json
import os
from pathlib import Path
import re
import shutil
from playwright.sync_api import sync_playwright

assert os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
assert os.environ.get('GITHUB_ACTIONS') == 'true'
out = Path(os.environ['ECD_DELIVERY_EVIDENCE'])
f = json.loads((out / 'fixture.json').read_text())
checks, samples = [], []

def check(ok, label):
    checks.append({'name': label, 'pass': bool(ok)})
    if not ok: raise AssertionError(label)

try:
    with sync_playwright() as p:
        exe = shutil.which('google-chrome') or shutil.which('chromium')
        assert exe, 'Real Chromium is required'
        browser = p.chromium.launch(executable_path=exe, args=['--no-sandbox'])
        for width in (320, 768, 1280):
            for control in (False, True):
                context = browser.new_context(viewport={'width':width,'height':900})
                page = context.new_page()
                pending, errors = [], []
                page.on('pageerror', lambda e: errors.append(str(e)))
                page.add_init_script("window.ectShifts=[];new PerformanceObserver(l=>l.getEntries().forEach(e=>{if(!e.hadRecentInput)window.ectShifts.push(e.value)})).observe({type:'layout-shift',buffered:true});")
                html=f['shortcode']
                if control:
                    html=re.sub(r' (?:width|height|decoding)="[^"]*"','',html)
                    html=re.sub(r'style="[^"]*"','style="display:block;max-width:100%;height:auto;"',html)
                shell='<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><h1>Delivery fixture</h1>'+html+'<p id="after">This text must not move.</p></body></html>'
                def route(r):
                    if r.request.url.endswith('/layout-fixture'):
                        r.fulfill(status=200,content_type='text/html',body=shell)
                    elif 'ecd_action=render' in r.request.url:
                        pending.append(r)
                    else: r.abort()
                page.route('**/*',route)
                page.goto('http://localhost:8092/layout-fixture',wait_until='domcontentloaded')
                page.wait_for_timeout(200)
                before=page.locator('#after').bounding_box()
                check(len(pending)==1,f'{width}/{control}: one timer request')
                held=pending.pop();response=held.fetch()
                check(response.status==200 and response.body().startswith(b'GIF'),f'{width}/{control}: actual GIF')
                page.wait_for_timeout(800)
                held.fulfill(response=response)
                page.wait_for_function('document.querySelector("img").complete && document.querySelector("img").naturalWidth > 0')
                page.wait_for_timeout(200)
                after=page.locator('#after').bounding_box()
                cls=sum(page.evaluate('window.ectShifts'))
                shift=abs(after['y']-before['y'])
                check((shift>1 and cls>0) if control else (shift<0.1 and cls<0.00001),f'{width}/{control}: reservation/positive-control')
                check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),f'{width}/{control}: no horizontal overflow')
                samples.append({'width':width,'without_reservation_control':control,'delta_y':shift,'observed_cls':cls})
                if not control:
                    page.locator('img').evaluate('(im)=>im.src=im.src.replace("layout-live","layout-ended")')
                    page.wait_for_timeout(150)
                    check(len(pending)==1,f'{width}: expiry refetch requested')
                    held=pending.pop();response=held.fetch();held.fulfill(response=response)
                    page.wait_for_function('document.querySelector("img").complete')
                    page.wait_for_timeout(200)
                    check(abs(page.locator('#after').bounding_box()['y']-after['y'])<0.1,f'{width}: changed image ratio stays within reserved box')
                    check(sum(page.evaluate('window.ectShifts'))<0.00001,f'{width}: no timer CLS after expiry')
                    page.screenshot(path=str(out/f'layout-{width}.png'),full_page=True)
                check(not errors,f'{width}/{control}: no JS exceptions')
                context.close()
        # These are explicit degradation simulations, NOT Gmail/Outlook/Apple Mail.
        for mode in ('normal','blocked','css-stripped','dark'):
            context=browser.new_context(viewport={'width':800 if mode=='css-stripped' else 320,'height':700})
            page=context.new_page();html=f['email_ended']
            if mode=='css-stripped': html=re.sub(r' style="[^"]*"','',html)
            style='background:#111;color:#eee;' if mode=='dark' else ''
            def mail_route(r):
                if r.request.url.endswith('/mail-fixture'):
                    r.fulfill(status=200,content_type='text/html',body='<!doctype html><html><body style="'+style+'">'+html+'</body></html>')
                elif 'ecd_action=render' in r.request.url and mode!='blocked': r.continue_()
                else: r.abort()
            page.route('**/*',mail_route)
            page.goto('http://localhost:8092/mail-fixture');page.wait_for_timeout(300)
            check('Ends:' in page.locator('body').inner_text(),mode+': visible text survives')
            check(page.locator('img').get_attribute('alt')=='Offer countdown',mode+': meaningful alt survives')
            if mode!='blocked': check(page.locator('img').evaluate('(im)=>im.complete&&im.naturalWidth>0'),mode+': image decodes')
            check(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),mode+': fits test viewport')
            page.screenshot(path=str(out/('email-simulation-'+mode+'.png')),full_page=True)
            context.close()
        browser.close()
finally:
    (out/'layout-results.json').write_text(json.dumps({'checks':checks,'samples':samples,'scope':'Chromium controlled loading and four HTML degradation simulations, not real provider inboxes or field CWV.'},indent=2)+'\n')
print('PASS',len(checks),'layout/delivery browser checks')
