#!/usr/bin/env python3
"""Six counterbalanced states on owned FPM/LSAPI loopback fixtures."""
import io
import gzip
import hashlib
from contextlib import ExitStack
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
from urllib.parse import urlencode, urljoin, urlsplit

from PIL import Image
from playwright.sync_api import sync_playwright, expect
from contracts import ADMIN_PHASES, CONTENT_PROFILES, ORDER, PROFILES, Page, VendorPage, accept, core_avatar, elementor_admin_font, expected_plugins, font_query_facts, generation, navigation_request_cancel, page_evidence, vendor_font_control
from server_contracts import litespeed_page_hits, lsapi_baseline_uncached, lsapi_renderer_bypass, lsapi_runtime, server_allowed, server_proof_matches

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tests/browser'))
from wordpress import log_in

def annotation(level, title, message):
    message = message.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')
    print(f'::{level} title={title}::{message}', flush=True)


class LabFailure(Exception):
    """Only fixed check labels are copied into public CI annotations."""


def main():
    if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('ECD_INTEGRATION_DISPOSABLE') != '1':
        raise RuntimeError('disposable_CI_only')
    temp = Path(os.environ['RUNNER_TEMP']).resolve(strict=True)
    wpdir = Path(os.environ['ECD_CACHE_WP']).resolve(strict=True)
    evidence = Path(os.environ['ECD_CACHE_EVIDENCE']).resolve(strict=True)
    if not wpdir.is_relative_to(temp) or not wpdir.name.startswith('ect-cache.') or evidence != temp / 'cache-evidence':
        raise RuntimeError('invalid_owned_paths')
    profile, jit = os.environ['ECD_CACHE_PROFILE'], os.environ['ECD_CACHE_JIT']
    server = os.environ.get('ECD_CACHE_SERVER', 'nginx-fpm')
    if profile not in PROFILES or jit not in ('1235', 'disable') or not server_allowed(server, profile):
        raise RuntimeError('invalid_profile')
    base = 'http://localhost:8095'
    report = {'profile': profile, 'jit': jit, 'server': server, 'complete': False, 'states': [], 'failures': [], 'page_errors': [], 'browser_errors': [], 'blocked_external': [], 'blocked_vendor_fonts': [], 'navigation_cancellations': []}
    current = None
    phase = 'initialization'
    navigating = False

    def local(url):
        parsed = urlsplit(url)
        return parsed.scheme == 'http' and parsed.netloc == 'localhost:8095'

    def check(value, label):
        if not value:
            raise LabFailure(label)
        current['checks'].append(label)

    def wp(*args):
        result = subprocess.run(['wp', '--path=' + str(wpdir), '--no-color', *args], capture_output=True, text=True, timeout=45)
        # Successful commands can also emit vendor shutdown diagnostics. Retain
        # both streams verbatim; fixture data uses a separate, strict JSON file.
        with (evidence / 'cli-commands.log').open('a') as log:
            log.write(result.stdout + result.stderr)
        if result.returncode:
            raise RuntimeError('wp_cli_failure')
        return result.stdout.strip()

    def runtime(response):
        headers = response.headers
        expected_jit_active = 'off' if jit == 'disable' else 'on'
        check(response.status == 200
              and headers.get('x-ecd-lab-sapi') == ('litespeed' if server == 'openlitespeed' else 'fpm-fcgi')
              and headers.get('x-ecd-lab-php', '').startswith('8.4.')
              and headers.get('x-ecd-lab-opcache') == 'on'
              and headers.get('x-ecd-lab-jit') == jit
              and headers.get('x-ecd-lab-jit-active') == expected_jit_active
              and headers.get('x-ecd-lab-gd') == 'yes'
              and headers.get('x-ecd-lab-active') == ('on' if current['active'] else 'off')
              and (server != 'openlitespeed' or lsapi_runtime(headers)), 'runtime')
        current['runtime'] = {k: v for k, v in headers.items() if k.startswith('x-ecd-lab-')}

    def navigate(page, path):
        nonlocal navigating
        navigating = True
        try:
            response = page.goto(base + path, wait_until='load', timeout=45000)
        finally:
            navigating = False
        check(response is not None and response.status == 200, 'admin-http')
        return response

    def isolate(context):
        expected_aborted = set()
        def route(request_route):
            request = request_route.request
            if local(request.url):
                request_route.continue_()
            else:
                known_avatar = core_avatar(request.url, request.resource_type)
                known_font = elementor_admin_font(request.url, request.resource_type, profile, phase)
                item = {'state': current['key'], 'phase': phase, 'host': urlsplit(request.url).hostname,
                        'resource_type': request.resource_type, 'expected_core_avatar': known_avatar, 'expected_vendor_font': known_font}
                font_facts = font_query_facts(request.url)
                if font_facts is not None:
                    item['font_query'] = font_facts
                report['blocked_external'].append(item)
                if known_font:
                    report['blocked_vendor_fonts'].append({'state': current['key'], 'phase': phase, 'font': 'elementor-admin-roboto-css2', 'blocked': True})
                if known_avatar or known_font:
                    expected_aborted.add(request.url)
                else:
                    report['browser_errors'].append({**item, 'reason': 'unexpected_external_request'})
                request_route.abort()
        context.route('**/*', route)
        def observe(page):
            page.on('pageerror', lambda error: report['page_errors'].append({
                'state': current['key'], 'phase': phase, 'type': type(error).__name__}))
            def console(message):
                if message.type == 'error' and message.location.get('url') not in expected_aborted:
                    report['browser_errors'].append({'state': current['key'], 'phase': phase, 'reason': 'console_error'})
            def failed(request):
                if request.url in expected_aborted:
                    return
                item = {'state': current['key'], 'phase': phase, 'path': urlsplit(request.url).path,
                        'resource_type': request.resource_type, 'failure': request.failure, 'method': request.method}
                if navigation_request_cancel(request, phase, navigating):
                    # Navigating between real admin pages can cancel outstanding AJAX.
                    # Required image/script behavior is separately checked before leaving.
                    report['navigation_cancellations'].append(item)
                else:
                    report['browser_errors'].append({**item, 'reason': 'request_failed'})
            def response_received(response):
                if response.status >= 400 and local(response.url):
                    report['browser_errors'].append({'state': current['key'], 'phase': phase,
                        'path': urlsplit(response.url).path, 'status': response.status, 'reason': 'http_error'})
            page.on('console', console)
            page.on('requestfailed', failed)
            page.on('response', response_received)
        context.on('page', observe)

    def cache_files(marker):
        cache = wpdir / 'wp-content/cache'
        found = []
        directories = ['supercache'] if profile == 'wp-super-cache' else ['page', 'page_enhanced']
        paths = [path for directory in directories for path in (cache / directory).rglob('*')]
        for path in sorted(paths):
            if path.is_symlink() or not path.is_file() or not path.resolve().is_relative_to(cache.resolve()) or path.stat().st_size > 2 * 1024 * 1024:
                continue
            data = path.read_bytes()
            if data.startswith(b'\x1f\x8b'):
                with gzip.GzipFile(fileobj=io.BytesIO(data)) as stream:
                    data = stream.read(2 * 1024 * 1024 + 1)
                if len(data) > 2 * 1024 * 1024:
                    raise ValueError('decoded_cache_file_exceeds_bound')
            if marker.encode() in data:
                found.append({'path': path.relative_to(wpdir / 'wp-content').as_posix(), 'sha256': hashlib.sha256(data).hexdigest(), 'generation': marker})
        return found

    def images(request):
        def bypass(kind, url, first):
            if server != 'openlitespeed':
                return
            proof = current.setdefault('renderer_cache_proof', {}).setdefault(kind, [])
            def capture(response):
                with Image.open(io.BytesIO(response.body())) as decoded:
                    decoded.load()
                    proof.append({'status': response.status, 'content_type': response.headers.get('content-type'),
                        'cache_control': response.headers.get('cache-control'), 'cache': response.headers.get('x-litespeed-cache'),
                        'format': decoded.format, 'width': decoded.width, 'height': decoded.height, 'frames': decoded.n_frames})
            capture(first)
            second = request.get(url, headers={'Accept': 'image/' + kind}, timeout=45000)
            capture(second)
            runtime(second)

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
            check(image.format == 'GIF' and image.width == 600 and image.height > 1
                  and len(durations) == 60 and durations == [1000] * 60, 'image-gif')
        bypass('gif', endpoint, gif)
        static_url = base + '/?ecd_action=render&ecd=cache-lab&mode=static'
        still = request.get(static_url, headers={'Accept': 'image/png'}, timeout=45000)
        runtime(still)
        check(still.status == 200 and still.headers.get('content-type', '').startswith('image/png')
              and 'no-store' in still.headers.get('cache-control', ''), 'static-policy')
        with Image.open(io.BytesIO(still.body())) as image:
            image.load()
            check(image.format == 'PNG' and image.width == 600 and image.height > 1, 'image-static')
        bypass('png', static_url, still)
        if server == 'openlitespeed':
            check(lsapi_renderer_bypass(current), 'renderer-cache-bypass')

    def browser_state(browser, url):
        nonlocal phase, navigating
        with browser.new_context(viewport={'width': 1280, 'height': 900}, reduced_motion='no-preference') as admin:
            isolate(admin)
            page = admin.new_page()
            page.on('dialog', lambda dialog: dialog.accept() if dialog.type == 'beforeunload' else dialog.dismiss())
            phase = 'admin-login'
            navigating = True
            try:
                log_in(page, base, 'ci-admin', os.environ['ECD_CACHE_PASSWORD'])
            finally:
                navigating = False
            phase = 'dashboard'
            runtime(navigate(page, '/wp-admin/'))
            expect(page.locator('#wpadminbar')).to_be_visible()
            check(True, 'dashboard')
            phase = 'media-library'
            navigate(page, '/wp-admin/upload.php')
            expect(page.locator('#wp-media-grid .media-frame-content')).to_be_visible()
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
                live_preview=page.locator('#ect-preview').content_frame.locator('#ect-frame-canvas')
                expect(live_preview).to_be_visible()
                check(live_preview.evaluate('(canvas)=>canvas.width>1'), 'timer-editor')
                page.screenshot(path=str(evidence / (current['key'] + '-editor.png')))
            navigating = True
        navigating = False

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

    def vendor_http(request, fixture_page):
        nonlocal phase
        phase = 'vendor-http'
        check(fixture_page['profile'] == profile and local(fixture_page['url'])
              and not urlsplit(fixture_page['url']).query, 'vendor-local-url')
        response = request.get(fixture_page['url'], headers={'Accept': 'text/html'}, timeout=45000, max_redirects=0)
        check(response.status == 200 and response.headers.get('content-type', '').startswith('text/html'), 'vendor-http')
        html = response.text()
        parsed = VendorPage(html, profile, fixture_page['post_id'])
        structure = ((str(fixture_page['post_id']) in parsed.elementor_documents and len(parsed.elementor_widgets) == 1)
                     if profile == 'elementor' else parsed.product_body and str(fixture_page['post_id']) in parsed.product_ids)
        check(structure, 'vendor-structure')
        current['vendor_proof'] = {'profile': profile, 'post_id': fixture_page['post_id'], 'status': response.status,
            'native_structure': structure, 'timer_images': len(parsed.images), 'path': urlsplit(fixture_page['url']).path,
            'rendered_post_ids': parsed.elementor_documents if profile == 'elementor' else parsed.product_ids,
            'scoped_timer_images': len(parsed.scoped_images), 'scope_found': parsed.scope_found, 'literal_shortcode': parsed.literal_shortcode}
        check(len(parsed.images) == len(parsed.scoped_images) == int(current['active'])
              and parsed.scope_found and parsed.literal_shortcode is (not current['active'])
              and 'PRIVATE-CACHE-LAB-NAME' not in html, 'vendor-output')

    def vendor_browser(browser, fixture_page):
        nonlocal phase
        phase = 'vendor-browser'
        with browser.new_context(viewport={'width': 1280, 'height': 900}) as context:
            isolate(context)
            page = context.new_page()
            response = page.goto(fixture_page['url'], wait_until='load', timeout=45000)
            check(response is not None and response.status == 200, 'vendor-browser-http')
            if profile == 'elementor':
                document = page.locator('[data-elementor-id="' + str(fixture_page['post_id']) + '"]')
                expect(document).to_have_count(1)
                scope = document.locator('.elementor-widget-shortcode')
                expect(scope).to_be_visible()
            else:
                expect(page.locator('body.postid-' + str(fixture_page['post_id']))).to_have_count(1)
                expect(page.get_by_role('heading', level=1, name=fixture_page['title'], exact=True)).to_be_visible()
                expect(page.get_by_role('button', name='Add to cart', exact=True)).to_be_visible()
                # Classic and block product templates can expose the description as a tab.
                description = page.get_by_role('tab', name='Description', exact=True)
                if description.count() and description.is_visible():
                    description.click()
                scope = page.locator('#tab-description')
                if not scope.count():
                    scope = page.locator('.wp-block-woocommerce-product-description')
                expect(scope).to_be_visible()
            image = scope.locator('img.email-countdown-timer-image')
            expect(image).to_have_count(int(current['active']))
            if current['active']:
                expect(image).to_be_visible()
                expect(image).to_have_js_property('complete', True)
                check(image.evaluate('img => img.naturalWidth === 600') and image.get_attribute('data-no-lazy') == '1', 'vendor-browser-image')
            else:
                check(True, 'vendor-browser-no-timer')
            check(True, 'vendor-browser')
            page.screenshot(path=str(evidence / (current['key'] + '-' + profile + '.png')))

    try:
        phase = 'fixture-json'
        fixture = json.loads((evidence / 'fixture.json').read_text())
        if profile in CONTENT_PROFILES:
            report['vendor_fixtures'] = {key: {'profile': fixture['pages'][key]['vendor']['profile'],
                'post_id': fixture['pages'][key]['vendor']['post_id'], 'path': urlsplit(fixture['pages'][key]['vendor']['url']).path} for key in ORDER}
        phase = 'installed-package-json'
        installed = json.loads((evidence / 'environment.json').read_text())['installed_plugins']
        if profile != 'baseline' and not any(p['name'] == profile and p['version'] == PROFILES[profile] for p in installed):
            raise RuntimeError('vendor_version_mismatch')
        report['plugins'] = installed
        report['source_commit'] = (evidence / 'source-commit.txt').read_text().strip()
        report['distribution'] = json.loads((evidence / 'distribution.json').read_text())
        if server == 'openlitespeed':
            phase = 'native-server-json'
            report['server_proof'] = json.loads((evidence / 'server.json').read_text())
            if not server_proof_matches(report):
                raise RuntimeError('native_server_mismatch')
            report['native_packages'] = {'openlitespeed_binary': '1.9.4', **report['server_proof']['packages']}
        else:
            packages = subprocess.check_output(['dpkg-query', '-W', '-f=${Package} ${Version}\n', 'nginx'], text=True)
            report['native_packages'] = dict(line.split(' ', 1) for line in packages.strip().splitlines())
            # setup-php's cached FPM is executable but need not be registered in dpkg.
            fpm = Path('/usr/sbin/php-fpm8.4')
            report['native_binaries'] = {'php-fpm8.4': {'path': str(fpm), 'sha256': hashlib.sha256(fpm.read_bytes()).hexdigest(),
                'version_output': subprocess.check_output([str(fpm), '-v'], text=True).strip()}}
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
                        (evidence / 'environment.json').unlink()
                        wp('eval-file', str(ROOT / 'tests/cache-profiles/fixture.php'), 'inventory')
                        inventory = json.loads((evidence / 'environment.json').read_text())
                        current['environment'] = inventory
                        if key == ORDER[0]:
                            annotation('notice', 'Cache environment', json.dumps({'profile': profile, 'jit': jit, 'state': key, **inventory}))
                        active_plugins = inventory['active_plugins']
                        expected = expected_plugins(profile, current['active'])
                        check(set(active_plugins) == expected and inventory['wordpress'] == '7.1.2'
                              and inventory['theme']['name'] == 'twentytwentyfive', 'plugin-state')
                        url = fixture['pages'][key]['url']
                        check(local(url) and not urlsplit(url).query and urlsplit(url).path == '/' + key + '/', 'independent-page-url')
                        with ExitStack() as resources:
                            request = playwright.request.new_context()
                            resources.callback(request.dispose)
                            phase = 'page-generation'
                            html = ''
                            current['page_accept'] = 'text/html'
                            current['page_response_headers'] = []
                            for _ in range(4):
                                response = request.get(url, headers={'Accept': 'text/html'}, timeout=45000, max_redirects=0)
                                check(response.status == 200 and response.headers.get('content-type', '').startswith('text/html'), 'anonymous-page-http')
                                html = response.text()
                                current['page_response_headers'].append({k: v for k, v in response.headers.items() if k.startswith('x-ecd-lab-') or k in ('x-litespeed-cache', 'x-litespeed-cache-control')})
                                current['generations'].append(generation(html))
                            cached = profile in ('wp-super-cache', 'w3-total-cache', 'litespeed-cache')
                            if not page_evidence(current['generations'], cached):
                                (evidence / (key + '-public.html')).write_text(html)
                                eligibility = evidence / 'cache-eligibility.jsonl'
                                if eligibility.is_file():
                                    current['cache_eligibility'] = [x for line in eligibility.read_text().splitlines()
                                        if (x := json.loads(line)).get('state') == key]
                                    annotation('error', 'Cache eligibility', json.dumps(current['cache_eligibility'][-1:]))
                                annotation('error', 'Cache page details', json.dumps({'profile': profile, 'state': key,
                                    'unique_generations': len(set(current['generations'])), 'accept': current['page_accept'],
                                    'headers': current['page_response_headers'][-1],
                                    'cache_comment': 'Cached page generated by WP-Super-Cache' in html,
                                    'uncached_comment': 'Page not cached by WP Super Cache' in html}))
                            check(page_evidence(current['generations'], cached), 'page-generation')
                            if profile == 'litespeed-cache':
                                check(litespeed_page_hits(current), 'server-cache-hits')
                            if server == 'openlitespeed' and profile == 'baseline':
                                check(lsapi_baseline_uncached(current), 'server-baseline')
                            if profile in ('wp-super-cache', 'w3-total-cache'):
                                current['engine_cache_files'] = cache_files(current['generations'][-1])
                                check(bool(current['engine_cache_files']), 'cache-artifact')
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
                                current['optimized_assets'].append({'path': urlsplit(asset).path, 'status': response.status, 'bytes': len(response.body()),
                                    'contains_live_script': b'emailCountdownTimerLiveInstalled' in response.body()})
                            if profile == 'autoptimize' and current['active']:
                                standalone_live = [x for x in parsed.assets if '/easy-countdown/assets/live-countdown.js' in x]
                                check(not standalone_live and any(x['contains_live_script'] for x in current['optimized_assets']), 'optimized-asset')
                            phase = 'renderer'
                            if current['active']:
                                images(request)
                            else:
                                response = request.get(base + '/?ecd_action=render&ecd=cache-lab&mode=static', timeout=45000)
                                check(response.status == 200 and not response.headers.get('content-type', '').startswith('image/'), 'inactive-endpoint')
                            if profile in CONTENT_PROFILES:
                                vendor_http(request, fixture['pages'][key]['vendor'])
                        browser_state(browser, url)
                        if profile in CONTENT_PROFILES:
                            vendor_browser(browser, fixture['pages'][key]['vendor'])
                        phase = 'browser-network'
                        state_errors = [x for x in report['browser_errors'] + report['page_errors'] if x['state'] == key]
                        if state_errors:
                            annotation('error', 'Cache browser details', json.dumps(state_errors[:12]))
                        check(not any(x['state'] == key for x in report['browser_errors'] + report['page_errors']), 'browser-network')
                        current['complete'] = True
                        annotation('notice', 'Cache state', f'{profile} JIT {jit} {key}: {len(current["checks"])} checks passed')
                    except Exception as error:
                        reason = str(error) if isinstance(error, LabFailure) else type(error).__name__
                        failure = {'state': key, 'phase': phase, 'reason': reason}
                        current['failures'].append(failure)
                        report['failures'].append(failure)
                        annotation('error', 'Cache state', f'{profile} JIT {jit} {key}: {phase} / {reason}')
                    finally:
                        (evidence / 'checks.json').write_text(json.dumps(report, indent=2) + '\n')
                report['complete'] = True
    except Exception as error:
        failure = {'phase': phase, 'reason': type(error).__name__}
        report['failures'].append(failure)
        annotation('error', 'Profile initialization', json.dumps(failure))
        raise
    finally:
        report['vendor_font_control'] = vendor_font_control(report)
        if report['complete'] and not report['vendor_font_control']:
            annotation('error', 'Vendor font attribution', 'Known blocked imports must match every off/on admin control.')
        report['passed'] = accept(report)
        (evidence / 'checks.json').write_text(json.dumps(report, indent=2) + '\n')
        annotation('notice', 'Cache profile result', json.dumps({'profile': profile, 'jit': jit, 'server': server, 'passed': report['passed'],
            'complete_states': sum(x['complete'] for x in report['states']), 'checks': sum(len(x['checks']) for x in report['states']),
            'blocked_vendor_fonts': len(report['blocked_vendor_fonts']), 'vendor_font_control': report['vendor_font_control'],
            'vendor_font_counts': {phase: [sum(x['state'] == key and x['phase'] == phase for x in report['blocked_vendor_fonts']) for key in ORDER] for phase in sorted(ADMIN_PHASES)} if profile == 'elementor' else None,
            'source_commit': report.get('source_commit'), 'zip_sha256': report.get('distribution', {}).get('zip_sha256'),
            'native_packages': report.get('native_packages')}))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
