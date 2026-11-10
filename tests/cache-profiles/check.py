#!/usr/bin/env python3
"""Six counterbalanced states on the owned loopback PHP-FPM fixture, never production."""
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
from urllib.parse import urlencode, urljoin, urlsplit

from PIL import Image
from playwright.sync_api import sync_playwright, expect
from contracts import ORDER, PROFILES, Page, accept, generation, page_evidence

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tests/browser'))
from wordpress import log_in


def main():
    if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('ECD_INTEGRATION_DISPOSABLE') != '1':
        raise RuntimeError('disposable_CI_only')
    temp = Path(os.environ['RUNNER_TEMP']).resolve(strict=True)
    wpdir = Path(os.environ['ECD_CACHE_WP']).resolve(strict=True)
    evidence = Path(os.environ['ECD_CACHE_EVIDENCE']).resolve(strict=True)
    if not wpdir.is_relative_to(temp) or not wpdir.name.startswith('ect-cache.') or evidence != temp / 'cache-evidence':
        raise RuntimeError('invalid_owned_paths')
    profile, jit = os.environ['ECD_CACHE_PROFILE'], os.environ['ECD_CACHE_JIT']
    if profile not in PROFILES or jit not in ('1235', 'disable'):
        raise RuntimeError('invalid_profile')
    base = 'http://localhost:8095'
    fixture = json.loads((evidence / 'fixture.json').read_text())
    report = {'profile': profile, 'jit': jit, 'complete': False, 'states': [], 'failures': [], 'page_errors': []}
    current = None
    phase = 'initialization'

    def local(url):
        parsed = urlsplit(url)
        return parsed.scheme == 'http' and parsed.netloc == 'localhost:8095'

    def check(value, label):
        if not value:
            raise AssertionError(label)
        current['checks'].append(label)

    def wp(*args):
        result = subprocess.run(['wp', '--path=' + str(wpdir), '--no-color', *args], capture_output=True, text=True, timeout=45)
        if result.returncode:
            # No passwords are passed to this helper. Retain the original CLI diagnosis.
            with (evidence / 'cli-errors.log').open('a') as log:
                log.write(result.stdout + result.stderr)
            raise RuntimeError('wp_cli_failure')
        return result.stdout.strip()

    def runtime(response):
        headers = response.headers
        expected_jit_active = 'off' if jit == 'disable' else 'on'
        check(response.status == 200
              and headers.get('x-ecd-lab-sapi') == 'fpm-fcgi'
              and headers.get('x-ecd-lab-php', '').startswith('8.4.')
              and headers.get('x-ecd-lab-opcache') == 'on'
              and headers.get('x-ecd-lab-jit') == jit
              and headers.get('x-ecd-lab-jit-active') == expected_jit_active
              and headers.get('x-ecd-lab-gd') == 'yes'
              and headers.get('x-ecd-lab-active') == ('on' if current['active'] else 'off'), 'runtime')
        current['runtime'] = {k: v for k, v in headers.items() if k.startswith('x-ecd-lab-')}

    def navigate(page, path):
        response = page.goto(base + path, wait_until='load', timeout=45000)
        check(response is not None and response.status == 200, 'admin-http')
        return response

    def isolate(context):
        def route(request_route):
            if local(request_route.request.url):
                request_route.continue_()
            else:
                request_route.abort()
        context.route('**/*', route)
        context.on('page', lambda page: page.on('pageerror', lambda error: report['page_errors'].append({
            'state': current['key'], 'phase': phase, 'type': type(error).__name__})))

    def images(request):
        endpoint = base + '/?' + urlencode({'ecd_action': 'render', 'ecd': 'cache-lab', 'mode': 'email'})
        gif = request.get(endpoint, headers={'Accept': 'image/gif'}, timeout=45000)
        runtime(gif)
        check(gif.status == 200 and gif.headers.get('content-type', '').startswith('image/gif')
              and 'no-store' in gif.headers.get('cache-control', '')
              and gif.headers.get('x-content-type-options') == 'nosniff'
              and 'set-cookie' not in gif.headers, 'gif-policy')
        with Image.open(io.BytesIO(gif.body())) as image:
            durations = []
            for frame in range(image.n_frames):
                image.seek(frame)
                image.load()
                durations.append(image.info.get('duration'))
            check(image.format == 'GIF' and image.width == 400 and image.height > 1
                  and len(durations) == 60 and durations == [1000] * 60, 'image-gif')
        still = request.get(base + '/?ecd_action=render&ecd=cache-lab&mode=static', headers={'Accept': 'image/png'}, timeout=45000)
        runtime(still)
        check(still.status == 200 and still.headers.get('content-type', '').startswith('image/png')
              and 'no-store' in still.headers.get('cache-control', ''), 'static-policy')
        with Image.open(io.BytesIO(still.body())) as image:
            image.load()
            check(image.format == 'PNG' and image.width == 400 and image.height > 1, 'image-static')

    def browser_state(browser, url):
        nonlocal phase
        with browser.new_context(viewport={'width': 1280, 'height': 900}, reduced_motion='no-preference') as admin:
            isolate(admin)
            page = admin.new_page()
            page.on('dialog', lambda dialog: dialog.accept() if dialog.type == 'beforeunload' else dialog.dismiss())
            phase = 'admin-login'
            log_in(page, base, 'ci-admin', os.environ['ECD_CACHE_PASSWORD'])
            phase = 'dashboard'
            runtime(navigate(page, '/wp-admin/'))
            expect(page.locator('#wpadminbar')).to_be_visible()
            check(True, 'dashboard')
            phase = 'media-library'
            navigate(page, '/wp-admin/upload.php')
            expect(page.locator('.wrap h1')).to_be_visible()
            check(True, 'media-library')
            phase = 'core-media-control'
            runtime(navigate(page, '/wp-admin/admin.php?page=ecd-cache-media-control'))
            expect(page.locator('#ecd-cache-media-control')).to_be_visible()
            page.wait_for_function('window.wp && wp.media && typeof wp.media === "function"')
            page.evaluate('() => {window.ecdLabMedia = wp.media({title:"Synthetic control",multiple:false}); window.ecdLabMedia.open();}')
            expect(page.locator('.media-modal')).to_be_visible()
            page.locator('.media-modal-close').click()
            check(True, 'core-media-control')
            if current['active']:
                phase = 'timer-editor'
                runtime(navigate(page, '/wp-admin/admin.php?page=ecd-timers&edit=cache-lab'))
                expect(page.locator('#ect-timer_id')).to_have_value('cache-lab')
                expect(page.locator('#ect-preview')).to_have_js_property('complete', True)
                check(page.locator('#ect-preview').evaluate('(img) => img.naturalWidth > 1'), 'timer-editor')
                page.screenshot(path=str(evidence / (current['key'] + '-editor.png')))

        if not current['active']:
            return
        phase = 'live-browser'
        with browser.new_context(viewport={'width': 1280, 'height': 900}, reduced_motion='no-preference') as anonymous:
            isolate(anonymous)
            page = anonymous.new_page()
            requests = []
            page.on('request', lambda request: requests.append(request.url))
            response = page.goto(url, wait_until='load', timeout=45000)
            check(response is not None and response.status == 200, 'front-browser-http')
            image = page.locator('img.email-countdown-timer-image')
            expect(image).to_have_count(1)
            expect(image).to_have_js_property('complete', True)
            check(image.evaluate('(img) => img.naturalWidth > 1') and image.get_attribute('loading') == 'eager'
                  and image.get_attribute('data-no-lazy') == '1', 'front-browser-image')
            seconds = page.locator('.ecd-live [data-ecd-unit="s"]')
            button = page.locator('.ecd-live button')
            expect(button).to_be_visible()
            initial = seconds.inner_text()
            expect(seconds).not_to_have_text(initial, timeout=4000)
            button.click()
            expect(button).to_have_text('Resume countdown')
            paused = seconds.inner_text()
            before = len([x for x in requests if 'ecd_action=render' in x])
            page.wait_for_timeout(1200)
            check(seconds.inner_text() == paused, 'live-pauses')
            button.click()
            expect(button).to_have_text('Pause countdown')
            expect(seconds).not_to_have_text(paused, timeout=4000)
            check(len([x for x in requests if 'ecd_action=render' in x]) == before, 'no-per-second-image-requests')
            check(True, 'live-browser')
            page.screenshot(path=str(evidence / (current['key'] + '-front.png')))

    try:
        installed = json.loads((evidence / 'plugins.json').read_text())
        if profile != 'baseline' and not any(p['name'] == profile and p['version'] == PROFILES[profile] for p in installed):
            raise RuntimeError('vendor_version_mismatch')
        report['plugins'] = installed
        with sync_playwright() as playwright:
            executable = shutil.which('google-chrome') or shutil.which('chromium')
            if not executable:
                raise RuntimeError('chromium_missing')
            with playwright.chromium.launch(executable_path=executable, args=['--no-sandbox']) as browser:
                report['browser'] = browser.version
                for key in ORDER:
                    current = {'key': key, 'active': key.endswith('-on'), 'complete': False, 'checks': [], 'failures': [], 'generations': []}
                    report['states'].append(current)
                    try:
                        phase = 'plugin-state'
                        wp('plugin', 'activate' if current['active'] else 'deactivate', 'easy-countdown')
                        wp('eval-file', str(ROOT / 'tests/cache-profiles/fixture.php'), 'purge')
                        inventory = json.loads(wp('eval-file', str(ROOT / 'tests/cache-profiles/fixture.php'), 'inventory'))
                        current['environment'] = inventory
                        active_plugins = inventory['active_plugins']
                        expected = {'easy-countdown/email-countdown-timer.php'} if current['active'] else set()
                        if profile != 'baseline':
                            expected.add({'wp-super-cache': 'wp-super-cache/wp-cache.php', 'w3-total-cache': 'w3-total-cache/w3-total-cache.php', 'autoptimize': 'autoptimize/autoptimize.php'}[profile])
                        check(set(active_plugins) == expected and inventory['wordpress'] == '7.1.2'
                              and inventory['theme']['name'] == 'twentytwentyfive', 'plugin-state')
                        url = fixture['pages'][key]['url']
                        check(local(url) and not urlsplit(url).query and urlsplit(url).path == '/' + key + '/', 'independent-page-url')
                        with playwright.request.new_context() as request:
                            phase = 'page-generation'
                            html = ''
                            for _ in range(4):
                                response = request.get(url, timeout=45000, max_redirects=0)
                                check(response.status == 200 and response.headers.get('content-type', '').startswith('text/html'), 'anonymous-page-http')
                                html = response.text()
                                current['generations'].append(generation(html))
                            cached = profile in ('wp-super-cache', 'w3-total-cache')
                            check(page_evidence(current['generations'], cached), 'page-generation')
                            phase = 'public-output'
                            parsed = Page(html)
                            check(len(parsed.images) == int(current['active']) and len(parsed.live) == int(current['active'])
                                  and 'PRIVATE-CACHE-LAB-NAME' not in html, 'public-output')
                            current['control_image_attributes'] = parsed.controls
                            phase = 'optimized-assets'
                            assets = sorted({urljoin(base, x) for x in parsed.assets if '/wp-content/cache/autoptimize/' in x})
                            current['optimized_assets'] = []
                            for asset in assets:
                                check(local(asset), 'local-optimized-asset')
                                response = request.get(asset, timeout=45000)
                                check(response.status == 200 and len(response.body()) > 0, 'served-optimized-asset')
                                current['optimized_assets'].append({'path': urlsplit(asset).path, 'status': response.status, 'bytes': len(response.body())})
                            if profile == 'autoptimize' and current['active']:
                                check(bool(assets), 'optimized-asset')
                            phase = 'renderer'
                            if current['active']:
                                images(request)
                            else:
                                response = request.get(base + '/?ecd_action=render&ecd=cache-lab&mode=static', timeout=45000)
                                check(response.status == 200 and not response.headers.get('content-type', '').startswith('image/'), 'inactive-endpoint')
                        browser_state(browser, url)
                        current['complete'] = True
                        print(f'::notice title=Cache state::{profile} JIT {jit} {key}: {len(current["checks"])} checks passed', flush=True)
                    except Exception as error:
                        reason = str(error) if isinstance(error, AssertionError) else type(error).__name__
                        failure = {'state': key, 'phase': phase, 'reason': reason}
                        current['failures'].append(failure)
                        report['failures'].append(failure)
                        print(f'::error title=Cache state::{profile} JIT {jit} {key}: {phase} / {reason}', flush=True)
                    finally:
                        (evidence / 'checks.json').write_text(json.dumps(report, indent=2) + '\n')
                report['complete'] = True
    finally:
        report['passed'] = accept(report)
        (evidence / 'checks.json').write_text(json.dumps(report, indent=2) + '\n')
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
