"""Network report contracts; production code and existing gates stay intact."""
from datetime import datetime, timezone
import importlib.util
import json
from pathlib import Path
import re
import sys
from urllib.parse import parse_qs, urlsplit, urlunsplit

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tests/cache-profiles'))
from contracts import ORDER, Page, core_avatar, generation, navigation_cancel, page_evidence

BASE = 'http://localhost:8097'
PLUGIN = 'easy-countdown/email-countdown-timer.php'
THEMES = {'twentytwentyone': ('2.9', False), 'twentytwentyfour': ('1.6', True)}
ROLES = ('root', 'blue', 'red')
PATHS = {'root': '/', 'blue': '/blue/', 'red': '/red/'}
WIDTHS = {'root': 480, 'blue': 540, 'red': 600}
DEADLINES = {role: int(datetime(2035, 12, 29 + i, 23, 59, 59, tzinfo=timezone.utc).timestamp()) for i, role in enumerate(ROLES)}
HEX = re.compile('[a-f0-9]{64}')

def local(url):
    parts = urlsplit(url)
    return parts.scheme == 'http' and parts.netloc == 'localhost:8097'

def active_for(activation, key, role):
    return key.endswith('-on') and (activation == 'network' or role == 'blue')

def image_reference(attributes, role):
    if not isinstance(attributes, dict): return False
    expected = {'ecd_action': ['render'], 'ecd': ['shared-timer'], 'mode': ['anim']}
    try:
        for name in ('src', 'data-ecd-src'):
            p = urlsplit(attributes.get(name, ''))
            if not local(attributes.get(name, '')) or p.path != PATHS[role] or p.fragment: return False
            query = parse_qs(p.query, strict_parsing=True, max_num_fields=4)
            if name == 'src':
                stamp = query.pop('_t', [])
                if len(stamp) != 1 or not re.fullmatch('[0-9]{10,12}', stamp[0]): return False
            if query != expected: return False
    except (ValueError, TypeError): return False
    return True

def runtime(headers, role, theme, jit, active):
    expected = {'blog': str(ROLES.index(role) + 1), 'home': BASE + PATHS[role], 'theme': theme,
                'sapi': 'fpm-fcgi', 'opcache': 'on', 'jit': jit, 'jit-active': 'off' if jit == 'disable' else 'on',
                'gd': 'yes', 'active': 'on' if active else 'off', 'memory': '256M', 'execution': '30'}
    return (isinstance(headers, dict) and all(headers.get('x-ecd-network-' + k) == v for k, v in expected.items())
            and str(headers.get('x-ecd-network-php', '')).startswith('8.4.'))

def navigation_abort(failure, url, phase, navigating, method='GET', body=None, content_type=''):
    # Reuse the strict core-only policy after validating this fixture's origin.
    # Strip only the two known subsite prefixes. Never retain POST bodies/nonces.
    if not local(url):
        return False
    p = urlsplit(url)
    path = p.path
    for prefix in ('/blue/', '/red/'):
        if path.startswith(prefix):
            path = '/' + path[len(prefix):]
            break
    canonical = urlunsplit(('http', 'localhost:8095', path, p.query, p.fragment))
    return navigation_cancel(failure, canonical, phase, navigating, method, body, content_type)

def inventory_ok(sites, report, key, fixture):
    if not isinstance(sites, list) or len(sites) != 3 or not all(isinstance(x, dict) for x in sites):
        return False
    for i, (role, site) in enumerate(zip(ROLES, sites)):
        active = active_for(report['activation'], key, role)
        expected = {'role': role, 'id': i + 1, 'home': BASE + PATHS[role], 'path': PATHS[role],
                    'wordpress': '7.1.2', 'theme': {'name': report['theme'], 'version': THEMES[report['theme']][0], 'block': THEMES[report['theme']][1]},
                    'width': WIDTHS[role], 'deadline': DEADLINES[role], 'campaign_keys': ['shared-timer', 'only-' + role],
                    'site_plugins': [PLUGIN] if active and report['activation'] == 'site' else [],
                    'network_plugins': [PLUGIN] if active and report['activation'] == 'network' else [],
                    'wp_cache': False, 'object_cache_dropin': False, 'advanced_cache_dropin': False,
                    'campaign_sha256': fixture[role]['campaign_sha256']}
        # A network-active plugin also appears in every inactive site's network inventory.
        if report['activation'] == 'network' and key.endswith('-on'):
            expected['network_plugins'] = [PLUGIN]
        if (any(site.get(k) != v for k, v in expected.items())
                or any(type(site.get(k)) is not int for k in ('id', 'width', 'deadline'))
                or any(site.get(k) is not False for k in ('wp_cache', 'object_cache_dropin', 'advanced_cache_dropin'))
                or site['theme'].get('block') is not THEMES[report['theme']][1]):
            return False
        if not str(site.get('php_cli', '')).startswith('8.4.') or site.get('mysql') != '8.4.13':
            return False
    return True

def image_ok(proof, role, theme, jit, active, kind, missing=False):
    if not isinstance(proof, dict):
        return False
    if not runtime(proof.get('runtime'), role, theme, jit, active):
        return False
    width, frames, status = (1, 1, 404) if missing else (WIDTHS[role], 60 if kind == 'gif' else 1, 200)
    expected = {'format': kind.upper(), 'status': status, 'width': width, 'frames': frames}
    if any(proof.get(k) != v for k, v in expected.items()) or any(type(proof.get(k)) is not int for k in ('status', 'width', 'frames')):
        return False
    if type(proof.get('height')) is not int or proof['height'] < (1 if missing else 2) or missing and proof['height'] != 1:
        return False
    if not str(proof.get('content_type', '')).startswith('image/' + kind) or not isinstance(proof.get('sha256'), str) or not HEX.fullmatch(proof['sha256']):
        return False
    if 'no-store' not in {x.strip().lower() for x in str(proof.get('cache_control', '')).split(',')}:
        return False
    if kind == 'gif' and proof.get('durations') != [1000] * 60:
        return False
    return True

def accept(report):
    if (not isinstance(report, dict) or report.get('complete') is not True
            or report.get('theme') not in THEMES or report.get('activation') not in ('site', 'network')
            or report.get('jit') not in ('1235', 'disable') or report.get('failures') != []
            or report.get('page_errors') != [] or report.get('browser_errors') != []
            or not isinstance(report.get('source_commit'), str) or not re.fullmatch('[a-f0-9]{40}', report['source_commit'])):
        return False
    fixture, states = report.get('fixture'), report.get('states')
    if not isinstance(fixture, dict) or set(fixture) != set(ROLES):
        return False
    for role, entry in fixture.items():
        if not isinstance(entry, dict) or not isinstance(entry.get('campaign_sha256'), str) or not HEX.fullmatch(entry['campaign_sha256']):
            return False
        if not isinstance(entry.get('pages'), dict) or set(entry['pages']) != set(ORDER):
            return False
        if any(entry['pages'][key] != BASE + PATHS[role] + key + '/' for key in ORDER):
            return False
    initial = {role: fixture[role]['campaign_sha256'] for role in ROLES}
    if len(set(initial.values())) != 3:
        return False
    if not isinstance(states, list) or len(states) != 6 or not all(isinstance(s, dict) for s in states) or [s.get('key') for s in states] != ORDER:
        return False
    for state in states:
        key = state['key']
        if state.get('complete') is not True or state.get('failures') != []:
            return False
        if not inventory_ok(state.get('inventory_before'), report, key, fixture) or not inventory_ok(state.get('inventory_after'), report, key, fixture):
            return False
        if not isinstance(state.get('sites'), list) or len(state['sites']) != 3:
            return False
        for role, site in zip(ROLES, state['sites']):
            active = active_for(report['activation'], key, role)
            if (not isinstance(site, dict) or site.get('role') != role or site.get('active') is not active
                    or site.get('complete') is not True or not page_evidence(site.get('generations'), False)):
                return False
            headers = site.get('page_runtime')
            if not isinstance(headers, list) or len(headers) != 4 or not all(runtime(x, role, report['theme'], report['jit'], active) for x in headers):
                return False
            public = site.get('public')
            if (public != {'status': 200, 'images': int(active), 'live': int(active), 'deadline': DEADLINES[role] if active else None,
                          'image_home': BASE + PATHS[role] if active else None, 'image_reference_valid': True if active else None,
                          'private_name_absent': True, 'literal_shortcode': not active}
                    or any(type(public.get(k)) is not int for k in ('status', 'images', 'live'))
                    or public['private_name_absent'] is not True or public['literal_shortcode'] is not (not active)
                    or public['image_reference_valid'] is not (True if active else None)):
                return False
            required = {'dashboard', 'media-library', 'public-browser'}
            if active:
                required |= {'preview', 'live-ticks', 'live-pauses', 'live-resumes', 'no-image-polling'}
                images = site.get('images')
                if not isinstance(images, dict) or set(images) != {'gif', 'png'}:
                    return False
                for kind, values in images.items():
                    if not isinstance(values, list) or len(values) != 2 or not all(image_ok(x, role, report['theme'], report['jit'], active, kind) for x in values):
                        return False
                missing = site.get('missing_campaigns')
                if not isinstance(missing, list) or [x.get('owner') for x in missing if isinstance(x, dict)] != [r for r in ROLES if r != role]:
                    return False
                if not all(image_ok(x, role, report['theme'], report['jit'], active, 'png', missing=True) for x in missing):
                    return False
            elif site.get('inactive_endpoint') != {'status': 200, 'is_image': False, 'runtime': site['page_runtime'][0]}:
                # Compare runtime fields only; generation headers are intentionally absent.
                return False
            checks = site.get('checks')
            if not isinstance(checks, list) or not all(isinstance(x, str) for x in checks) or not required.issubset(checks):
                return False
        mutation = state.get('mutation')
        if key.endswith('-on'):
            if not isinstance(mutation, dict) or mutation.get('before') != initial or mutation.get('restored') != initial:
                return False
            changed = mutation.get('edited')
            if (not isinstance(changed, dict) or set(changed) != set(ROLES)
                    or changed['root'] != initial['root'] or changed['red'] != initial['red']
                    or changed['blue'] == initial['blue'] or not isinstance(changed['blue'], str) or not HEX.fullmatch(changed['blue'])):
                return False
        elif mutation is not None:
            return False
    return True

def load_module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    obj = importlib.util.module_from_spec(spec); spec.loader.exec_module(obj)
    return obj

native = load_module('network_fpm_reader', ROOT / 'tests/gutenberg/check-native-logs.py')
strict_native = load_module('network_fatal_markers', ROOT / 'tests/litespeed/native_logs.py')

def assess(directory):
    result = native.inspect(directory)
    try:
        for name in ('fpm.log', 'php-errors.log', 'nginx-error.log'):
            text = native.read_regular(directory / name)
            if name == 'fpm.log' and 'ready to handle connections' not in text:
                result['errors'].append({'file': name, 'reason': 'missing_startup'})
            for number, line in enumerate(text.splitlines(), 1):
                if strict_native.BAD.search(line):
                    result['errors'].append({'file': name, 'line': number, 'reason': 'native_or_server_failure'})
        stop = json.loads(native.read_regular(directory / 'service-stop.json'))
        if not isinstance(stop, dict) or set(stop) != {'nginx', 'fpm'} or any(type(x) is not int or x != 0 for x in stop.values()):
            result['errors'].append({'file': 'service-stop.json', 'reason': 'unclean_stop'})
        report = json.loads(native.read_regular(directory / 'checks.json'))
        if not accept(report):
            result['errors'].append({'file': 'checks.json', 'reason': 'incomplete_or_failed_report'})
    except (OSError, ValueError, UnicodeError, TypeError, KeyError) as error:
        result['errors'].append({'file': 'evidence', 'reason': type(error).__name__})
    result['passed'] = not result['errors']
    return result

if __name__ == '__main__':
    result = assess(Path(sys.argv[1]))
    if len(sys.argv) == 3:
        with Path(sys.argv[2]).open('x') as stream: stream.write(json.dumps(result) + '\n')
    print(json.dumps(result))
    raise SystemExit(0 if result['passed'] else 1)
