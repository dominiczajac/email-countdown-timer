"""Native admin checks: explicit probes, anonymous image, redaction and no-JS."""
import json
from playwright.sync_api import expect


def verify_status(page, browser, context, base, evidence, check, campaign='copied-browser-test'):
    requests = []
    listener = lambda request: requests.append(request)
    page.on('request', listener)
    target = base + '/wp-admin/admin.php?page=email-countdown-timer-status'
    page.goto(target)
    expect(page.get_by_role('heading', name='Easy Countdown: Status & Troubleshooting')).to_be_visible()
    check(page.locator('#ect-status-report').count() == 0 and not any('ecd_action=render' in r.url for r in requests), 'opening Status makes no image request and generates no report')
    page.get_by_label('Campaign ID (optional)', exact=True).fill(campaign)
    page.get_by_role('button', name='Run local checks', exact=True).click()
    report = page.get_by_label('Troubleshooting report', exact=True)
    expect(report).to_be_visible()
    data = json.loads(report.input_value())
    check(len(data['checks']) == 5 and data['image_test']['status'] == 'not_run' and not any('ecd_action=render' in r.url for r in requests), 'local POST runs five checks without automatically loading a public image')
    check(all(row['status'] in ['pass', 'warning', 'error', 'info'] and row['message'] and row['action'] for row in data['checks']), 'native results include severity, explanation and recommended action')
    check(campaign not in report.input_value() and base not in report.input_value() and 'wp-content' not in report.input_value(), 'native report excludes actual campaign identity, site URL and paths')
    with page.expect_request(lambda r: 'ecd_action=render' in r.url) as pending:
        page.get_by_role('button', name='Test public image', exact=True).click()
    image_request = pending.value
    expect(page.get_by_text('The anonymous image request loaded successfully.', exact=False)).to_be_visible()
    headers = image_request.all_headers()
    check('cookie' not in headers and 'referer' not in headers and 'mode=static' in image_request.url, 'actual public test request omits WordPress cookies and referrer')
    data = json.loads(report.input_value())
    check(data['image_test'] == {'status': 'pass', 'code': 'loaded', 'http_status': 200} and page.locator('[data-ect-image-test] img').evaluate('(img)=>img.naturalWidth>1'), 'native decoder and report agree on the successful anonymous image')
    count = len([r for r in requests if 'ecd_action=render' in r.url])
    page.wait_for_timeout(1200)
    check(len([r for r in requests if 'ecd_action=render' in r.url]) == count, 'diagnostics do not poll after success')
    def denied(route): route.fulfill(status=403, content_type='text/plain', body='PRIVATE RESPONSE BODY sentinel@example.invalid /private/path')
    page.route('**/*ecd_action=render*', denied)
    page.get_by_role('button', name='Test public image', exact=True).click()
    expect(page.get_by_text('The anonymous image test failed.', exact=False)).to_be_visible()
    data = json.loads(report.input_value())
    check(data['image_test'] == {'status': 'failed', 'code': 'http_error', 'http_status': 403} and 'PRIVATE RESPONSE' not in report.input_value() and 'sentinel@example.invalid' not in page.content(), 'HTTP failure is visible and copies only fixed results, never a raw body')
    page.unroute('**/*ecd_action=render*', denied)
    page.evaluate("Object.defineProperty(navigator,'clipboard',{configurable:true,value:undefined})")
    page.get_by_role('button', name='Copy report', exact=True).click()
    expect(page.locator('#ect-status-report-result')).to_have_text('Report selected. Copy it manually.')
    check(report.evaluate('(el)=>el.selectionStart===0 && el.selectionEnd===el.value.length') and report.evaluate('(el)=>el===document.activeElement'), 'clipboard fallback selects and focuses the complete redacted report')
    for width in [320, 375, 768, 1440]:
        page.set_viewport_size({'width': width, 'height': 1000})
        check(page.locator('[data-ect-admin]').evaluate('(el)=>el.scrollWidth<=el.clientWidth+1'), 'status controls/report fit viewport ' + str(width))
    page.set_viewport_size({'width': 375, 'height': 1000})
    page.screenshot(path=str(evidence / 'status-narrow.png'), full_page=True)
    page.set_viewport_size({'width': 1440, 'height': 1000})
    nojs = browser.new_context(storage_state=context.storage_state(), java_script_enabled=False, viewport={'width': 375, 'height': 900})
    plain = nojs.new_page(); plain.goto(target)
    plain.get_by_label('Campaign ID (optional)', exact=True).fill(campaign)
    plain.get_by_role('button', name='Run local checks', exact=True).click()
    expect(plain.get_by_label('Troubleshooting report', exact=True)).to_be_visible()
    check(not plain.get_by_role('button', name='Test public image', exact=True).is_visible() and plain.get_by_role('link', name='Open image manually (new tab)', exact=True).is_visible(), 'no-JS diagnostics expose a report/manual link without inert enhanced controls')
    check(json.loads(plain.get_by_label('Troubleshooting report', exact=True).input_value())['image_test']['status'] == 'not_run', 'no-JS POST never performs the separate image test')
    nojs.close()
    page.remove_listener('request', listener)
