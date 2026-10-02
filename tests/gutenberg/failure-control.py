"""Bounded diagnostic after a failed test; never converts failure into success.

Only synthetic loopback WordPress installed by run.sh is accepted. No response
body, login cookie or raw process dump is published in the evidence artifact.
"""
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('ECD_INTEGRATION_DISPOSABLE') == '1'
root = Path(os.environ['RUNNER_TEMP']).resolve()
site = Path(os.environ['ECD_BLOCK_WP_PATH']).resolve()
out = Path(os.environ['ECD_BLOCK_EVIDENCE']).resolve()
assert site.is_relative_to(root) and out.is_relative_to(root)
base = 'http://localhost:8093'
post = int(os.environ['ECD_BLOCK_POST_ID'])

def wp(*args):
    return subprocess.check_output(['wp', '--path=' + str(site), '--no-color', *args], text=True, timeout=20).strip()

assert wp('config', 'get', 'table_prefix') == 'ectblock_'
assert wp('config', 'get', 'WP_ENVIRONMENT_TYPE') == 'local'
assert wp('option', 'get', 'home') == base

class LocalRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if urllib.parse.urlsplit(newurl).netloc != 'localhost:8093':
            raise RuntimeError('Non-loopback redirect refused')
        return super().redirect_request(req, fp, code, msg, headers, newurl)

client = urllib.request.build_opener(urllib.request.ProxyHandler({}), LocalRedirect(),
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
results = {'diagnostic_only': True, 'original_test_failed': True, 'states': []}
try:
    client.open(base + '/wp-login.php', timeout=40).close()
    data = urllib.parse.urlencode({'log': 'ci-admin', 'pwd': os.environ['ECD_BLOCK_PASSWORD'],
        'wp-submit': 'Log In', 'redirect_to': base + '/wp-admin/', 'testcookie': '1'}).encode()
    client.open(base + '/wp-login.php', data=data, timeout=40).close()
    for enabled in (False, True):
        wp('plugin', 'activate' if enabled else 'deactivate', 'easy-countdown')
        row = {'easy_countdown_active': enabled, 'active_plugins': wp('option', 'get', 'active_plugins', '--format=json')}
        start = time.monotonic()
        try:
            response = client.open(base + '/wp-admin/post.php?post=' + str(post) + '&action=edit', timeout=40)
            body = response.read()
            row.update(status=response.status, bytes=len(body), body_sha256=hashlib.sha256(body).hexdigest(),
                sapi=response.headers.get('X-Ect-Test-Sapi'), php=response.headers.get('X-Ect-Test-PHP'),
                editor_bootstrap=b'wp.editPost.initializeEditor' in body or b'wp.editor.initializeEditor' in body)
        except urllib.error.HTTPError as error:
            row.update(status=error.code)
            error.close()
        except Exception as error:
            row['error_type'] = type(error).__name__
        row['elapsed_ms'] = round((time.monotonic() - start) * 1000, 1)
        results['states'].append(row)
except Exception as error:
    results['error_type'] = type(error).__name__
finally:
    try:
        wp('plugin', 'activate', 'easy-countdown')
    except Exception:
        results['reactivation_failed'] = True
    (out / 'failure-control.json').write_text(json.dumps(results, indent=2) + '\n')
