#!/usr/bin/env python3
"""Native hosted network experiment. No production or local-CI impersonation."""
from contextlib import ExitStack
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
from urllib.parse import parse_qs, urlencode, urlsplit

from PIL import Image
from playwright.sync_api import expect, sync_playwright
from evidence import BASE, DEADLINES, ORDER, PATHS, PLUGIN, ROLES, THEMES, WIDTHS, Page, accept, active_for, core_avatar, generation, image_reference, inventory_ok, local, navigation_abort, page_evidence, runtime
from cache_evidence import HITS, browser_proof, hit_proof, snapshot

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tests/browser'))
from wordpress import log_in

class Failure(Exception):
    """Only fixed assertion labels enter annotations."""

def main():
    if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('ECD_INTEGRATION_DISPOSABLE') != '1':
        raise RuntimeError('disposable_CI_only')
    temp, wpdir, out = [Path(os.environ[x]).resolve(strict=True) for x in ('RUNNER_TEMP', 'ECD_NETWORK_WP', 'ECD_NETWORK_EVIDENCE')]
    if wpdir.parent != temp or not wpdir.name.startswith('ect-network.') or out != temp / 'network-evidence':
        raise RuntimeError('invalid_owned_paths')
    theme, activation, jit = [os.environ[x] for x in ('ECD_NETWORK_THEME', 'ECD_NETWORK_ACTIVATION', 'ECD_NETWORK_JIT')]
    profile = os.environ.get('ECD_NETWORK_PROFILE', 'baseline')
    cached = profile == 'wp-super-cache'
    if theme not in THEMES or activation not in ('site', 'network') or jit not in ('1235', 'disable') or profile not in ('baseline', 'wp-super-cache'):
        raise RuntimeError('unsupported_matrix')
    report = {'theme': theme, 'activation': activation, 'jit': jit, 'profile': profile, 'complete': False, 'states': [],
              'failures': [], 'page_errors': [], 'browser_errors': [], 'blocked_avatars': [], 'navigation_cancellations': []}
    current, phase, navigating = None, 'initialization', False

    def check(value, label):
        if not value:
            raise Failure(label)

    def wp(*args):
        result = subprocess.run(['wp', '--path=' + str(wpdir), '--no-color', *args], capture_output=True, text=True, timeout=45)
        with (out / 'cli.log').open('a') as stream: stream.write(result.stdout + result.stderr)
        if result.returncode: raise Failure('wp_cli_failure')

    def inventory():
        (out / 'inventory.json').unlink(missing_ok=True)
        wp('eval-file', str(ROOT / 'tests/multisite/fixture.php'), 'inventory')
        return json.loads((out / 'inventory.json').read_text())['sites']

    def hashes():
        return {x['role']: x['campaign_sha256'] for x in inventory()}

    def headers(response):
        return {k: v for k, v in response.headers.items() if k.startswith('x-ecd-network-')}

    def response_runtime(response, role, active):
        result = headers(response)
        check(runtime(result, role, theme, jit, active), 'runtime_context')
        return result

    def image(request, role, active, kind, campaign='shared-timer'):
        url = BASE + PATHS[role] + '?' + urlencode({'ecd_action': 'render', 'ecd': campaign, 'mode': 'email' if kind == 'gif' else 'static'})
        response = request.get(url, headers={'Accept': 'image/' + kind}, timeout=45000)
        body = response.body()
        with Image.open(io.BytesIO(body)) as decoded:
            decoded.load()
            result = {'status': response.status, 'content_type': response.headers.get('content-type'),
                      'cache_control': response.headers.get('cache-control'), 'format': decoded.format,
                      'width': decoded.width, 'height': decoded.height, 'frames': decoded.n_frames,
                      'cache_header': response.headers.get('x-wp-super-cache'),
                      'sha256': hashlib.sha256(body).hexdigest(), 'runtime': response_runtime(response, role, active)}
            if kind == 'gif':
                result['durations'] = []
                for index in range(decoded.n_frames):
                    decoded.seek(index); result['durations'].append(decoded.info.get('duration'))
        return result

    def isolate(context, role):
        expected = set()
        def route(r):
            if local(r.request.url): r.continue_(); return
            avatar = core_avatar(r.request.url, r.request.resource_type)
            if avatar:
                expected.add(r.request.url)
                report['blocked_avatars'].append({'role': role, 'state': current['key'], 'phase': phase})
            else:
                report['browser_errors'].append({'role': role, 'state': current['key'], 'phase': phase, 'reason': 'external_request', 'host': urlsplit(r.request.url).hostname})
            r.abort()
        context.route('**/*', route)
        def observe(page):
            def error(reason):
                report['browser_errors'].append({'role': role, 'state': current['key'], 'phase': phase, 'reason': reason})
            page.on('pageerror', lambda e: report['page_errors'].append({'role': role, 'state': current['key'], 'phase': phase, 'type': type(e).__name__}))
            page.on('console', lambda m: error('console_error') if m.type == 'error' and m.location.get('url') not in expected else None)
            page.on('response', lambda r: error('http_error') if local(r.url) and r.status >= 400 else None)
            def failed(r):
                if r.url in expected: return
                content_type = r.headers.get('content-type', '')
                body = None
                if r.method == 'POST' and content_type.split(';', 1)[0].lower().strip() == 'application/x-www-form-urlencoded':
                    try: body = r.post_data
                    except (ValueError, UnicodeError): pass
                if navigation_abort(r.failure, r.url, phase, navigating, r.method, body, content_type):
                    report['navigation_cancellations'].append({'role': role, 'state': current['key'], 'phase': phase, 'path': urlsplit(r.url).path})
                else: error('request_failed')
            page.on('requestfailed', failed)
        context.on('page', observe)

    def browser_site(browser, site, row):
        nonlocal phase, navigating
        role, active, home = row['role'], row['active'], site['home'].rstrip('/')
        def visit(page, path):
            nonlocal navigating
            navigating = True
            try: response = page.goto(home + path, wait_until='load', timeout=45000)
            finally: navigating = False
            check(response is not None and response.status == 200, 'admin_http')
            response_runtime(response, role, active)
        with browser.new_context(viewport={'width': 1280, 'height': 900}) as admin:
            isolate(admin, role); page = admin.new_page()
            page.on('dialog', lambda d: d.accept() if d.type == 'beforeunload' else d.dismiss())
            phase = 'admin-login'; navigating = True
            try: log_in(page, home, 'ci-admin', os.environ['ECD_NETWORK_PASSWORD'])
            finally: navigating = False
            phase = 'dashboard'; visit(page, '/wp-admin/')
            expect(page.locator('#wpadminbar')).to_be_visible(); row['checks'].append('dashboard')
            phase = 'media-library'; visit(page, '/wp-admin/upload.php')
            expect(page.locator('#wp-media-grid .media-frame-content')).to_be_visible(); row['checks'].append('media-library')
            if active:
                phase = 'timer-editor'; visit(page, '/wp-admin/admin.php?page=ecd-timers&edit=shared-timer')
                expect(page.locator('[name=campaign_name]')).to_have_value('PRIVATE-NETWORK-' + role)
                expect(page.locator('[name=fixed_width]')).to_have_value(str(WIDTHS[role]))
                expect(page.locator('#ect-preview')).to_have_js_property('complete', True)
                check(page.locator('#ect-preview').evaluate('(img)=>img.naturalWidth') == WIDTHS[role], 'preview_width')
                row['checks'].append('preview')
                if role == 'blue':
                    mutation = {'before': hashes()}
                    for name, target in [('edited', 'PRIVATE-NETWORK-EDIT'), ('restored', 'PRIVATE-NETWORK-blue')]:
                        page.locator('[name=campaign_name]').fill(target)
                        page.wait_for_load_state('networkidle')
                        navigating = True
                        try:
                            page.get_by_role('button', name='Save Changes', exact=True).click()
                            expect(page.get_by_text('Timer saved.', exact=True)).to_be_visible()
                            expect(page.locator('[name=campaign_name]')).to_have_value(target)
                        finally: navigating = False
                        mutation[name] = hashes()
                    current['mutation'] = mutation
                page.screenshot(path=str(out / (current['key'] + '-' + role + '-admin.png')))
            navigating = True
        navigating = False
        phase = 'public-browser'
        with browser.new_context(viewport={'width': 1280, 'height': 900}, reduced_motion='no-preference') as context:
            isolate(context, role); page = context.new_page(); requests = []
            page.on('request', lambda r: requests.append(r.url))
            response = page.goto(site['pages'][current['key']], wait_until='load', timeout=45000)
            check(response is not None and response.status == 200, 'public_browser_http')
            if cached:
                hit = response.headers.get('x-wp-super-cache')
                check(hit is None or hit in HITS, 'browser_cache_header')
                row['browser_cache'] = {'mode': 'hit' if hit in HITS else 'origin', 'header': hit,
                    'generation': generation(response.text()), 'body_sha256': hashlib.sha256(response.body()).hexdigest(),
                    'runtime': headers(response), 'file': snapshot(wpdir, out, role, current['key'], browser=True) if hit in HITS else None}
                check(browser_proof(row, current['key'], lambda h: runtime(h, role, theme, jit, active)), 'browser_cache_context')
                row['checks'].append('browser-cache-context')
            else: response_runtime(response, role, active)
            expect(page.locator('img.email-countdown-timer-image')).to_have_count(int(active))
            expect(page.locator('.ecd-live')).to_have_count(int(active))
            if active:
                preview = page.locator('img.email-countdown-timer-image')
                expect(preview).to_have_js_property('complete', True)
                check(preview.evaluate('(img)=>img.naturalWidth') == WIDTHS[role], 'browser_image_width')
                expect(page.locator('.ecd-live')).to_have_attribute('data-ecd-deadline', str(DEADLINES[role]))
                button = page.locator('.ecd-live button'); seconds = page.locator('.ecd-live [data-ecd-unit=s]')
                expect(button).to_have_text('Pause countdown')
                initial = seconds.inner_text(); expect(seconds).not_to_have_text(initial, timeout=4000); row['checks'].append('live-ticks')
                button.click(); expect(button).to_have_text('Resume countdown')
                paused, count = seconds.inner_text(), sum('ecd_action=render' in x for x in requests)
                page.wait_for_timeout(1200); check(seconds.inner_text() == paused, 'paused'); row['checks'].append('live-pauses')
                button.click(); expect(button).to_have_text('Pause countdown')
                expect(seconds).not_to_have_text(paused, timeout=4000); row['checks'].append('live-resumes')
                check(sum('ecd_action=render' in x for x in requests) == count, 'image_polling'); row['checks'].append('no-image-polling')
            row['checks'].append('public-browser')
            check('PRIVATE-NETWORK-' not in page.content(), 'browser_private_name')
            page.screenshot(path=str(out / (current['key'] + '-' + role + '-front.png')))

    try:
        report['source_commit'] = (out / 'source-commit.txt').read_text().strip()
        report['distribution'] = json.loads((out / 'distribution.json').read_text())
        fixture = json.loads((out / 'fixture.json').read_text())['sites']
        report['fixture'] = {x['role']: x for x in fixture}
        packages = subprocess.check_output(['dpkg-query', '-W', '-f=${Package} ${Version}\n', 'nginx', 'php8.4-fpm'], text=True)
        report['native_packages'] = dict(line.split(' ', 1) for line in packages.strip().splitlines())
        with sync_playwright() as p:
            executable = shutil.which('google-chrome') or shutil.which('chromium')
            check(executable, 'chromium_missing')
            with p.chromium.launch(executable_path=executable, args=['--no-sandbox']) as browser:
                report['browser'] = browser.version
                wp('plugin', 'deactivate', 'easy-countdown', '--network')
                for key in ORDER:
                    current = {'key': key, 'complete': False, 'failures': [], 'sites': [], 'mutation': None}; report['states'].append(current)
                    try:
                        phase = 'plugin-state'
                        args = ['plugin', 'activate' if key.endswith('-on') else 'deactivate', 'easy-countdown']
                        args += ['--network'] if activation == 'network' else ['--url=' + BASE + PATHS['blue']]
                        wp(*args)
                        if cached: wp('eval-file', str(ROOT / 'tests/multisite/fixture.php'), 'purge-cache')
                        current['inventory_before'] = inventory()
                        check(inventory_ok(current['inventory_before'], report, key, report['fixture']), 'inventory_before')
                        with ExitStack() as resources:
                            request = p.request.new_context(); resources.callback(request.dispose)
                            for site in fixture:
                                role = site['role']; active = active_for(activation, key, role)
                                row = {'role': role, 'active': active, 'complete': False, 'checks': [], 'generations': [], 'page_runtime': []}; current['sites'].append(row)
                                if cached: row['cache'] = {'response_headers': [], 'body_sha256': []}
                                phase = 'anonymous-http'
                                for _ in range(4):
                                    response = request.get(site['pages'][key], headers={'Accept': 'text/html'}, timeout=45000, max_redirects=0)
                                    check(response.status == 200 and response.headers.get('content-type', '').startswith('text/html'), 'public_http'); html = response.text()
                                    observed = headers(response)
                                    if cached:
                                        hit = response.headers.get('x-wp-super-cache')
                                        check(hit is None or hit in HITS, 'page_cache_header')
                                        if hit is None or observed: response_runtime(response, role, active)
                                        row['cache']['response_headers'].append({k: response.headers.get(k) for k in ('x-wp-super-cache', 'content-type', 'cache-control')})
                                        row['cache']['body_sha256'].append(hashlib.sha256(response.body()).hexdigest())
                                    else: response_runtime(response, role, active)
                                    row['page_runtime'].append(observed); row['generations'].append(generation(html))
                                if cached:
                                    phase = 'cache-proof'
                                    row['cache']['file'] = snapshot(wpdir, out, role, key)
                                    check(hit_proof(row, key), 'page_cache_hit'); row['checks'].append('page-cache-hit')
                                parsed = Page(html); image_home = None
                                if parsed.images:
                                    src = urlsplit(parsed.images[0].get('src', ''))
                                    check(image_reference(parsed.images[0], role), 'image_reference')
                                    image_home = src.scheme + '://' + src.netloc + src.path
                                row['public'] = {'status': response.status, 'images': len(parsed.images), 'live': len(parsed.live),
                                    'deadline': int(parsed.live[0]['data-ecd-deadline']) if parsed.live else None,
                                    'image_home': image_home, 'private_name_absent': 'PRIVATE-NETWORK-' not in html,
                                    'image_reference_valid': True if parsed.images else None,
                                    'literal_shortcode': '[ecd_timer' in html}
                                phase = 'images'
                                if active:
                                    row['images'] = {kind: [image(request, role, active, kind) for _ in range(2)] for kind in ('gif', 'png')}
                                    row['missing_campaigns'] = [{'owner': owner, **image(request, role, active, 'png', 'only-' + owner)} for owner in ROLES if owner != role]
                                else:
                                    response = request.get(BASE + PATHS[role] + '?ecd_action=render&ecd=shared-timer&mode=static', timeout=45000)
                                    row['inactive_endpoint'] = {'status': response.status, 'is_image': response.headers.get('content-type', '').startswith('image/'),
                                                                'cache_header': response.headers.get('x-wp-super-cache'),
                                                                'runtime': response_runtime(response, role, active)}
                                browser_site(browser, site, row)
                                row['complete'] = True
                        current['inventory_after'] = inventory()
                        check(inventory_ok(current['inventory_after'], report, key, report['fixture']), 'inventory_after')
                        current['complete'] = True
                    except Exception as error:
                        failure = {'state': key, 'phase': phase, 'reason': str(error) if isinstance(error, Failure) else type(error).__name__}
                        current['failures'].append(failure); report['failures'].append(failure)
                        print('::error title=Network state::' + json.dumps(failure), flush=True)
                    finally:
                        (out / 'checks.json').write_text(json.dumps(report, indent=2) + '\n')
        report['complete'] = len(report['states']) == 6 and all(x['complete'] for x in report['states'])
    except Exception as error:
        report['failures'].append({'phase': phase, 'reason': type(error).__name__})
    finally:
        report['passed'] = accept(report)
        (out / 'checks.json').write_text(json.dumps(report, indent=2) + '\n')
    print('::notice title=Network result::' + json.dumps({'theme': theme, 'activation': activation, 'jit': jit, 'profile': profile,
          'passed': report['passed'], 'states': len(report['states']), 'site_probes': sum(len(s['sites']) for s in report['states'])}), flush=True)
    return 0 if report['passed'] else 1

if __name__ == '__main__':
    raise SystemExit(main())
