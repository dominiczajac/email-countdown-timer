"""Privacy Policy Guide on real, disposable WordPress; no production target."""
import json
import os
from pathlib import Path
import shutil
from playwright.sync_api import sync_playwright

assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
base = 'http://localhost:8081'
evidence = Path(os.environ['RUNNER_TEMP']) / 'admin-evidence'
checks = []
with sync_playwright() as p:
    executable = shutil.which('google-chrome') or shutil.which('chromium')
    assert executable, 'A real browser is required'
    browser = p.chromium.launch(executable_path=executable, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1440, 'height': 1000})
    page.goto(base + '/wp-login.php')
    page.locator('#user_login').fill('ci-admin')
    page.locator('#user_pass').fill(os.environ['ECD_BROWSER_PASSWORD'])
    page.locator('#wp-submit').click()
    page.wait_for_url('**/wp-admin/**')
    response = page.goto(base + '/wp-admin/privacy-policy-guide.php')
    assert response.status == 200
    checks.append('Privacy Policy Guide is accessible')
    content = page.locator('#wpbody-content').text_content()
    assert 'The countdown plugin does not set tracking cookies' in content
    checks.append('plugin suggestion is registered through actual admin_init')
    assert 'their actual logging, retention and processor details' in content
    checks.append('suggestion includes infrastructure-specific guidance')
    assert page.locator('[src*="assets/admin.js"]').count() == 0
    checks.append('no plugin UI script is loaded by the policy guide')
    page.screenshot(path=str(evidence / 'privacy-guide.png'), full_page=True)
    (evidence / 'privacy-guide-checks.json').write_text(json.dumps({'checks': checks, 'browser': browser.version}, indent=2) + '\n')
    browser.close()
print(f'PASS {len(checks)} real privacy-guide checks; published content is checked separately by WP-CLI')
