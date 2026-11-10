"""Small, dependency-free acceptance gate for the isolated lab's evidence."""
from html.parser import HTMLParser
import re
from urllib.parse import urlsplit

PROFILES = {'baseline': None, 'wp-super-cache': '3.1.3', 'w3-total-cache': '2.10.6', 'autoptimize': '3.1.16'}
PLUGIN_FILES = {'wp-super-cache': 'wp-super-cache/wp-cache.php', 'w3-total-cache': 'w3-total-cache/w3-total-cache.php', 'autoptimize': 'autoptimize/autoptimize.php'}
ORDER = ['pair-1-off', 'pair-1-on', 'pair-2-on', 'pair-2-off', 'pair-3-off', 'pair-3-on']
REQUIRED = {'plugin-state', 'page-generation', 'public-output', 'dashboard', 'media-library', 'core-media-control', 'runtime', 'browser-network'}
ACTIVE_REQUIRED = {'image-gif', 'image-static', 'timer-editor', 'live-browser', 'gif-policy', 'static-policy', 'front-browser-http', 'front-browser-image', 'live-pauses', 'no-per-second-image-requests'}
ADMIN_PHASES = {'admin-login', 'dashboard', 'media-library', 'core-media-control', 'timer-editor'}


class Page(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.generations, self.images, self.live, self.assets, self.controls = [], [], [], [], []
        self.feed(html)

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        classes = attrs.get('class', '').split()
        if attrs.get('id') == 'ecd-cache-origin':
            self.generations.append(attrs.get('data-generation', ''))
        if tag == 'img' and 'email-countdown-timer-image' in classes:
            self.images.append(attrs)
        if tag == 'div' and 'ecd-live' in classes:
            self.live.append(attrs)
        if tag == 'img' and attrs.get('id') == 'cache-control-image':
            self.controls.append(attrs)
        if tag == 'script' and attrs.get('src'):
            self.assets.append(attrs['src'])
        if tag == 'link' and attrs.get('rel') == 'stylesheet' and attrs.get('href'):
            self.assets.append(attrs['href'])


def generation(html):
    markers = Page(html).generations
    if len(markers) != 1 or not re.fullmatch('[a-f0-9]{24}', markers[0]):
        raise ValueError('missing_or_ambiguous_origin_generation')
    return markers[0]


def page_evidence(markers, cached):
    """Two fixed warm requests followed by two measurements; never retry failures."""
    if not isinstance(markers, list) or len(markers) != 4 or any(not isinstance(x, str) or not re.fullmatch('[a-f0-9]{24}', x) for x in markers):
        return False
    return markers[2] == markers[3] if cached else len(set(markers)) == 4


def core_avatar(url, resource_type):
    """Only core's HTTPS image is expected; scripts or lookalike hosts still fail."""
    parts = urlsplit(url)
    return (resource_type == 'image' and parts.scheme == 'https'
            and parts.netloc == 'secure.gravatar.com'
            and re.fullmatch(r'/avatar/(?:[a-f0-9]{32}|[a-f0-9]{64})', parts.path) is not None)


def navigation_cancel(failure, url, phase, navigating):
    parts = urlsplit(url)
    return (failure == 'net::ERR_ABORTED' and navigating and phase in ADMIN_PHASES
            and parts.scheme == 'http' and parts.netloc == 'localhost:8095'
            and parts.path.startswith(('/wp-includes/', '/wp-admin/', '/wp-json/'))
            and 'ecd_action=render' not in url and '/easy-countdown/' not in url
            and '/cache/autoptimize/' not in url)


def expected_plugins(profile, active):
    plugins = {'easy-countdown/email-countdown-timer.php'} if active else set()
    if profile != 'baseline':
        plugins.add(PLUGIN_FILES[profile])
    return plugins


def metadata_matches(profile, jit, state):
    env, runtime = state.get('environment'), state.get('runtime')
    if not isinstance(env, dict) or not isinstance(runtime, dict):
        return False
    plugins, theme = env.get('active_plugins'), env.get('theme')
    if (not isinstance(plugins, list) or not all(isinstance(x, str) for x in plugins)
            or set(plugins) != expected_plugins(profile, state['active'])
            or env.get('wordpress') != '7.1.2' or not isinstance(theme, dict)
            or theme.get('name') != 'twentytwentyfive'
            or not str(env.get('mysql', '')).startswith('8.4.')
            or not str(env.get('php_cli', '')).startswith('8.4.')):
        return False
    expected = {'x-ecd-lab-sapi': 'fpm-fcgi', 'x-ecd-lab-opcache': 'on', 'x-ecd-lab-jit': jit,
                'x-ecd-lab-jit-active': 'off' if jit == 'disable' else 'on', 'x-ecd-lab-gd': 'yes',
                'x-ecd-lab-active': 'on' if state['active'] else 'off'}
    return all(runtime.get(k) == value for k, value in expected.items()) and str(runtime.get('x-ecd-lab-php', '')).startswith('8.4.')


def accept(report):
    if (not isinstance(report, dict) or report.get('complete') is not True
            or report.get('profile') not in PROFILES or report.get('jit') not in ('1235', 'disable')
            or report.get('failures') != [] or report.get('page_errors') != [] or report.get('browser_errors') != []):
        return False
    states = report.get('states')
    installed = report.get('plugins')
    if (not isinstance(report.get('source_commit'), str) or not re.fullmatch('[a-f0-9]{40}', report['source_commit'])
            or not isinstance(installed, list) or not all(isinstance(row, dict) for row in installed)):
        return False
    if report['profile'] != 'baseline':
        versions = [row.get('version') for row in installed if row.get('name') == report['profile']]
        if versions != [PROFILES[report['profile']]]:
            return False
    if not isinstance(states, list) or len(states) != len(ORDER) or not all(isinstance(s, dict) for s in states):
        return False
    if [s.get('key') for s in states] != ORDER:
        return False
    for state in states:
        active = state['key'].endswith('-on')
        if state.get('active') is not active or state.get('complete') is not True or state.get('failures') != []:
            return False
        if not metadata_matches(report['profile'], report['jit'], state):
            return False
        markers = state.get('generations')
        if not page_evidence(markers, report['profile'] in ('wp-super-cache', 'w3-total-cache')):
            return False
        checks = state.get('checks')
        required = REQUIRED | (ACTIVE_REQUIRED if active else {'inactive-endpoint'})
        if active and report['profile'] == 'autoptimize':
            required |= {'optimized-asset', 'local-optimized-asset', 'served-optimized-asset'}
        if report['profile'] in ('wp-super-cache', 'w3-total-cache'):
            required |= {'cache-artifact'}
            artifacts = state.get('engine_cache_files')
            if not isinstance(artifacts, list) or not artifacts:
                return False
            if any(not isinstance(item, dict) or item.get('generation') != markers[-1]
                   or not isinstance(item.get('sha256'), str) or not re.fullmatch('[a-f0-9]{64}', item['sha256']) for item in artifacts):
                return False
            prefixes = ('cache/supercache/',) if report['profile'] == 'wp-super-cache' else ('cache/page/', 'cache/page_enhanced/')
            if any(not isinstance(item.get('path'), str) or not item['path'].startswith(prefixes) for item in artifacts):
                return False
        if not isinstance(checks, list) or not all(isinstance(x, str) for x in checks) or not required.issubset(checks):
            return False
        if checks.count('anonymous-page-http') != 4:
            return False
    return True


def main():
    import argparse
    import json
    from pathlib import Path
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('evidence', type=Path)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args()
    result = {'passed': False}
    try:
        if args.evidence.is_symlink() or not args.evidence.is_file() or args.evidence.stat().st_size > 16 * 1024 * 1024:
            raise ValueError('invalid_evidence')
        result['passed'] = accept(json.loads(args.evidence.read_text()))
    except (OSError, ValueError, TypeError, KeyError) as error:
        result['reason'] = type(error).__name__
    encoded = json.dumps(result) + '\n'
    if args.report:
        with args.report.open('x') as stream:
            stream.write(encoded)
    print(encoded, end='')
    return 0 if result['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
