"""Paired on/off FPM observations; every attempt, including failures, is retained."""
import concurrent.futures
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import statistics
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://localhost:8091'
ROOT = Path(os.environ['ECD_FPM_ROOT'])
OUT = Path(os.environ['ECD_FPM_EVIDENCE'])
PROFILE = os.environ['ECD_FPM_PROFILE']
assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
assert ROOT.is_relative_to(Path(os.environ['RUNNER_TEMP']))
report = {'profile': PROFILE, 'source': subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip(),
          'design': 'Four independent plugin profiles; each runs three counterbalanced Easy Countdown on/off pairs on one disposable site. Same theme, data, 30s PHP limit, 35s FPM termination, four workers. No failed attempt is silently retried.',
          'requests': [], 'checks': [], 'failures': []}


def persist():
    (OUT / 'fpm-results.json').write_text(json.dumps(report, indent=2) + '\n')


def check(ok, label):
    report['checks'].append({'name': label, 'pass': bool(ok)})
    if not ok:
        report['failures'].append(label)
    persist()


def wp(*args):
    result = subprocess.run(['wp', '--path=' + str(ROOT / 'wp'), '--no-color', *args], text=True,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=80)
    with (OUT / 'cli.log').open('a') as log:
        log.write('COMMAND ' + repr(args) + '\n' + result.stdout + '\n')
    if result.returncode:
        raise RuntimeError('WP-CLI failed: ' + repr(args))
    return result.stdout


jar = http.cookiejar.CookieJar()
session = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
session.open(BASE + '/wp-login.php', timeout=40).read()
login = urllib.parse.urlencode({'log': 'ci-admin', 'pwd': os.environ['ECD_BROWSER_PASSWORD'],
                               'wp-submit': 'Log In', 'redirect_to': BASE + '/wp-admin/', 'testcookie': '1'}).encode()
session.open(BASE + '/wp-login.php', data=login, timeout=40).read()
assert any(c.name.startswith('wordpress_logged_in_') for c in jar), 'Admin login failed'


def request(path, phase, target, sample, auth=True):
    start = time.monotonic()
    item = {'phase': phase, 'target': target, 'sample': sample, 'path': path}
    body = b''
    try:
        opener = session if auth else urllib.request.build_opener()
        with opener.open(BASE + path, timeout=42) as response:
            body = response.read()
            item.update(status=response.status, sapi=response.headers.get('X-ECT-Lab-SAPI'),
                        active=response.headers.get('X-ECT-Lab-Active'), opcache=response.headers.get('X-ECT-Lab-OPcache'), final_url=response.url,
                        content_type=response.headers.get('Content-Type', ''), bytes=len(body),
                        sha256=hashlib.sha256(body).hexdigest())
    except Exception as error:
        item.update(status=getattr(error, 'code', 0), error=type(error).__name__ + ': ' + str(error))
        if isinstance(error, urllib.error.HTTPError):
            body = error.read()
    item['elapsed_ms'] = round((time.monotonic() - start) * 1000, 3)
    report['requests'].append(item)
    persist()
    if item.get('status') != 200 or item['elapsed_ms'] >= 30000:
        # These are synthetic local-site responses only; never export auth cookies.
        (OUT / ('failure-' + str(len(report['requests'])) + '.html')).write_bytes(body[:131072])
    return item, body


try:
    # Each state occurs once per pair. Alternate order to reduce warm-up/order bias.
    for pair in range(3):
        for active in ([False, True] if pair % 2 == 0 else [True, False]):
            phase = f'pair{pair + 1}-' + ('on' if active else 'off')
            wp('plugin', 'activate' if active else 'deactivate', 'easy-countdown')
            if PROFILE in ('woo', 'both'):
                wp('eval', 'delete_transient("_wc_activation_redirect");')
            if PROFILE in ('w3', 'both'):
                wp('w3-total-cache', 'flush', 'all')
            inventory = wp('option', 'get', 'active_plugins', '--format=json')
            (OUT / (phase + '-plugins.txt')).write_text(inventory)
            targets = [('/wp-admin/index.php', 'dashboard', b'wpbody-content'),
                       ('/wp-admin/upload.php', 'media', b'wpbody-content'),
                       ('/wp-admin/admin.php?page=ect-fpm-probe', 'media-control', b'id="ect-fpm-probe"'),
                       ('/index.php?rest_route=/', 'rest-index', b'"namespaces"')]
            if active:
                targets.append(('/wp-admin/admin.php?page=ecd-timers&edit=fpm-timer', 'timer-editor', b'id="ect-editor"'))
            for sample in range(3):
                for path, target, marker in targets:
                    item, body = request(path, phase, target, sample)
                    label = f'{phase}/{target}/{sample}'
                    check(item.get('status') == 200 and item.get('sapi') == 'fpm-fcgi' and item.get('opcache') == 'on' and marker in body
                          and item.get('active') == ('on' if active else 'off')
                          and '/wp-login.php' not in item.get('final_url', '')
                          and b'Fatal error' not in body and item['elapsed_ms'] < 30000, label)
            first, html1 = request('/', phase, 'page-cache-first', 0, False)
            second, html2 = request('/', phase, 'page-cache-second', 1, False)
            markers = [re.search(rb'data-generation="([^"]+)"', b) for b in (html1, html2)]
            cache_hit = bool(all(markers) and markers[0][1] == markers[1][1])
            check(cache_hit == (PROFILE in ('w3', 'both')), phase + '/observed-page-cache')
            if active:
                check(b'data-ecd-src=' in html2, phase + '/shortcode')
                item, image = request('/?ecd_action=render&ecd=fpm-timer&mode=email', phase, 'timer-image', 0, False)
                check(item.get('status') == 200 and image.startswith((b'GIF87a', b'GIF89a'))
                      and image.endswith(b';'), phase + '/binary-GIF')
            if len(report['failures']) >= 6:
                raise RuntimeError('Six failed checks reached; stop bounded experiment, remaining cells untested')
    groups = {}
    for item in report['requests']:
        key = ('on' if item['phase'].endswith('-on') else 'off') + '/' + item['target']
        groups.setdefault(key, []).append(item)
    report['summary'] = {key: {'n': len(items), 'failures': sum(i.get('status') != 200 for i in items),
                              'median_ms': round(statistics.median(i['elapsed_ms'] for i in items), 3),
                              'max_ms': max(i['elapsed_ms'] for i in items)} for key, items in groups.items()}
except Exception as error:
    report['failures'].append(type(error).__name__ + ': ' + str(error))
finally:
    persist()
print(json.dumps({'profile': PROFILE, 'requests': len(report['requests']), 'checks': len(report['checks']),
                  'failures': report['failures'], 'summary': report.get('summary', {})}, indent=2))
raise SystemExit(bool(report['failures']))
