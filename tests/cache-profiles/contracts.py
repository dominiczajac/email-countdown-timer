"""Small, dependency-free acceptance gate for the isolated lab's evidence."""
from html.parser import HTMLParser
from collections import Counter
import re
from urllib.parse import parse_qs, urlsplit
from server_contracts import LSC_SETTINGS, litespeed_page_hits, lsapi_baseline_uncached, lsapi_renderer_bypass, lsapi_runtime, server_proof_matches

PROFILES = {'baseline': None, 'wp-super-cache': '3.1.3', 'w3-total-cache': '2.10.6', 'autoptimize': '3.1.16', 'elementor': '4.3.2', 'woocommerce': '11.1.2', 'litespeed-cache': '7.9.3'}
PLUGIN_FILES = {'wp-super-cache': 'wp-super-cache/wp-cache.php', 'w3-total-cache': 'w3-total-cache/w3-total-cache.php', 'autoptimize': 'autoptimize/autoptimize.php', 'elementor': 'elementor/elementor.php', 'woocommerce': 'woocommerce/woocommerce.php', 'litespeed-cache': 'litespeed-cache/litespeed-cache.php'}
CONTENT_PROFILES = {'elementor', 'woocommerce'}
ORDER = ['pair-1-off', 'pair-1-on', 'pair-2-on', 'pair-2-off', 'pair-3-off', 'pair-3-on']
REQUIRED = {'plugin-state', 'page-generation', 'public-output', 'dashboard', 'media-library', 'core-media-control', 'runtime', 'browser-network'}
ACTIVE_REQUIRED = {'image-gif', 'image-static', 'timer-editor', 'live-browser', 'gif-policy', 'static-policy', 'front-browser-http', 'front-browser-image', 'live-pauses', 'no-per-second-image-requests'}
ADMIN_PHASES = {'admin-login', 'dashboard', 'media-library', 'core-media-control', 'timer-editor'}
ELEMENTOR_ADMIN_FONT = 'Roboto:wght@400;500;700'


class Page(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.generations, self.images, self.live, self.assets, self.controls = [], [], [], [], []
        self.elementor_documents, self.elementor_widgets, self.product_body, self.product_ids = [], [], False, []
        self.feed(html)

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        classes = attrs.get('class', '').split()
        if tag == 'body' and 'single-product' in classes:
            self.product_body = True
            self.product_ids = [x.removeprefix('postid-') for x in classes if re.fullmatch(r'postid-[1-9][0-9]*', x)]
        if attrs.get('data-elementor-id'):
            self.elementor_documents.append(attrs['data-elementor-id'])
        if 'elementor-widget-shortcode' in classes:
            self.elementor_widgets.append(attrs)
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


class VendorPage(Page):
    """Require the countdown inside the native widget/long-description subtree."""
    VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}

    def __init__(self, html, profile, post_id):
        self.profile, self.post_id = profile, str(post_id)
        self.stack, self.scoped_images, self.scope_text = [], [], []
        self.scope_found = False
        super().__init__(html)
        self.literal_shortcode = re.search(r'\[ecd_timer\b', ' '.join(self.scope_text)) is not None

    def handle_starttag(self, tag, attributes):
        super().handle_starttag(tag, attributes)
        attrs = dict(attributes)
        classes = attrs.get('class', '').split()
        flags = set(self.stack[-1][1]) if self.stack else set()
        if self.profile == 'elementor':
            if attrs.get('data-elementor-id') == self.post_id:
                flags.add('document')
            if 'elementor-widget-shortcode' in classes and 'document' in flags:
                flags.add('content')
        elif self.profile == 'woocommerce' and self.post_id in self.product_ids and (attrs.get('id') == 'tab-description' or 'woocommerce-Tabs-panel--description' in classes
              or 'wp-block-woocommerce-product-description' in classes):
            flags.add('content')
        if 'content' in flags:
            self.scope_found = True
            if tag == 'img' and 'email-countdown-timer-image' in classes:
                self.scoped_images.append(attrs)
        if tag not in self.VOID:
            self.stack.append((tag, flags))

    def handle_endtag(self, tag):
        for index in range(len(self.stack) - 1, -1, -1):
            if self.stack[index][0] == tag:
                del self.stack[index:]
                break

    def handle_data(self, data):
        if self.stack and 'content' in self.stack[-1][1] and not any(tag in ('script', 'style') for tag, _ in self.stack):
            self.scope_text.append(data)


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


def elementor_admin_font(url, resource_type, profile, phase):
    """The exact observed 4.3.2 admin font resource, blocked rather than fetched.

    Preserve the resource's observed ver=1.0.0, distinct from plugin version.
    Public pages, other profiles, fonts, scripts and query changes still fail.
    """
    parts = urlsplit(url)
    try:
        query = parse_qs(parts.query, strict_parsing=True, max_num_fields=3, errors='strict')
    except (ValueError, UnicodeError):
        return False
    return (profile == 'elementor' and phase in ADMIN_PHASES and resource_type == 'stylesheet'
            and parts.scheme in ('http', 'https') and parts.netloc == 'fonts.googleapis.com'
            and parts.path == '/css2' and not parts.fragment
            and query == {'family': [ELEMENTOR_ADMIN_FONT], 'display': ['swap'], 'ver': ['1.0.0']})


def font_query_facts(url):
    """Bounded public font metadata for diagnosis; never arbitrary query data."""
    parts = urlsplit(url)
    if parts.netloc != 'fonts.googleapis.com' or parts.path != '/css2':
        return None
    try:
        query = parse_qs(parts.query, strict_parsing=True, max_num_fields=8, errors='strict')
    except (ValueError, UnicodeError):
        return {'unsupported_query': True}
    facts = {'other_fields': bool(set(query) - {'family', 'display', 'ver'})}
    patterns = {'family': r'(?:Roboto|Poppins|Inter)(?::(?:ital,)?(?:opsz,)?wght@[0-9,;.]{1,224})?',
                'display': r'(?:swap|auto|block|fallback|optional)', 'ver': r'[0-9]{1,3}(?:\.[0-9]{1,3}){1,3}'}
    for key, pattern in patterns.items():
        facts[key] = [value if re.fullmatch(pattern, value) else '<redacted>' for value in query.get(key, [])]
    return facts


def vendor_font_control(report):
    """Require the known blocked import identically in each off/on admin pair."""
    blocks = report.get('blocked_vendor_fonts', [])
    if not isinstance(blocks, list):
        return False
    if report.get('profile') != 'elementor':
        return blocks == []
    if any(not isinstance(x, dict) or x.get('state') not in ORDER or x.get('phase') not in ADMIN_PHASES
           or x.get('font') != 'elementor-admin-roboto-css2' or x.get('blocked') is not True for x in blocks):
        return False
    external = report.get('blocked_external')
    if not isinstance(external, list) or not all(isinstance(x, dict) for x in external):
        return False
    fonts = [x for x in external if x.get('expected_vendor_font') is True]
    expected_query = {'family': [ELEMENTOR_ADMIN_FONT], 'display': ['swap'], 'ver': ['1.0.0'], 'other_fields': False}
    if any(x.get('host') != 'fonts.googleapis.com' or x.get('resource_type') != 'stylesheet'
           or x.get('font_query') != expected_query for x in fonts):
        return False
    if Counter((x['state'], x['phase']) for x in blocks) != Counter((x.get('state'), x.get('phase')) for x in fonts):
        return False
    def counts(state, phase):
        return Counter(x['font'] for x in blocks if x['state'] == state and x['phase'] == phase)
    for pair in (1, 2, 3):
        off, on = f'pair-{pair}-off', f'pair-{pair}-on'
        for phase in ADMIN_PHASES - {'timer-editor'}:
            expected = counts(off, phase)
            if not expected or counts(on, phase) != expected or counts('pair-1-off', phase) != expected:
                return False
        if counts(off, 'timer-editor') or counts(on, 'timer-editor') != counts(off, 'dashboard'):
            return False
    return True


def navigation_cancel(failure, url, phase, navigating, method='GET', post_data=None, content_type=''):
    parts = urlsplit(url)
    try:
        query = parse_qs(parts.query, max_num_fields=64, errors='strict')
        if method == 'POST':
            # Only core AJAX may be interrupted during these read-only admin
            # visits. Never excuse saves, unsupported bodies or candidate AJAX.
            if (parts.path != '/wp-admin/admin-ajax.php'
                    or content_type.split(';', 1)[0].strip().lower() != 'application/x-www-form-urlencoded'
                    or not isinstance(post_data, str) or len(post_data) > 65536):
                return False
            posted = parse_qs(post_data, max_num_fields=64, errors='strict')
            actions = posted.get('action', [])
            if not actions and not query.get('action'):
                return False
            if any(action.startswith('email_countdown_timer_') for action in actions):
                return False
        elif method != 'GET':
            return False
    except (ValueError, UnicodeError):
        return False
    return (failure == 'net::ERR_ABORTED' and navigating and phase in ADMIN_PHASES
            and parts.scheme == 'http' and parts.netloc == 'localhost:8095'
            and parts.path.startswith(('/wp-includes/', '/wp-admin/', '/wp-json/'))
            and 'render' not in query.get('ecd_action', [])
            and not any(action.startswith('email_countdown_timer_') for action in query.get('action', []))
            and '/easy-countdown/' not in url
            and '/cache/autoptimize/' not in url)


def navigation_request_cancel(request, phase, navigating):
    """Inspect the actual Playwright request without retaining its body/nonce."""
    method = request.method
    content_type = request.headers.get('content-type', '')
    body = None
    if method == 'POST' and content_type.split(';', 1)[0].strip().lower() == 'application/x-www-form-urlencoded':
        try:
            body = request.post_data
        except (ValueError, UnicodeError):
            return False
    return navigation_cancel(request.failure, request.url, phase, navigating, method, body, content_type)


def expected_plugins(profile, active):
    plugins = {'easy-countdown/email-countdown-timer.php'} if active else set()
    if profile != 'baseline':
        plugins.add(PLUGIN_FILES[profile])
    return plugins


def metadata_matches(profile, jit, state, server='nginx-fpm'):
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
    expected = {'x-ecd-lab-sapi': 'litespeed' if server == 'openlitespeed' else 'fpm-fcgi', 'x-ecd-lab-opcache': 'on', 'x-ecd-lab-jit': jit,
                'x-ecd-lab-jit-active': 'off' if jit == 'disable' else 'on', 'x-ecd-lab-gd': 'yes',
                'x-ecd-lab-active': 'on' if state['active'] else 'off'}
    config = env.get('configuration')
    if server == 'openlitespeed':
        if not lsapi_runtime(runtime) or env.get('object_cache_dropin') is not False:
            return False
        if profile == 'baseline' and (env.get('wp_cache') is not False or env.get('advanced_cache_dropin') is not False):
            return False
    if profile == 'litespeed-cache' and (config != LSC_SETTINGS or env.get('wp_cache') is not True or env.get('advanced_cache_dropin') is not False):
        return False
    if profile in CONTENT_PROFILES:
        if not isinstance(config, dict) or config.get('version') != PROFILES[profile] or config.get('activation_redirect_pending') is not False or env.get('wp_cache') is not False or env.get('advanced_cache_dropin') is not False:
            return False
        if profile == 'elementor' and config.get('print_google_fonts') is not False:
            return False
        if profile == 'woocommerce' and (config.get('coming_soon') != 'no' or config.get('allow_tracking') != 'no'):
            return False
    return all(runtime.get(k) == value for k, value in expected.items()) and str(runtime.get('x-ecd-lab-php', '')).startswith('8.4.')


def accept(report):
    if (not isinstance(report, dict) or report.get('complete') is not True
            or report.get('profile') not in PROFILES or report.get('jit') not in ('1235', 'disable')
            or report.get('failures') != [] or report.get('page_errors') != [] or report.get('browser_errors') != []
            or not vendor_font_control(report) or not server_proof_matches(report)):
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
    vendor_fixtures = report.get('vendor_fixtures')
    if report['profile'] in CONTENT_PROFILES:
        if not isinstance(vendor_fixtures, dict) or set(vendor_fixtures) != set(ORDER):
            return False
        if any(not isinstance(f, dict) or f.get('profile') != report['profile'] or type(f.get('post_id')) is not int
               or f['post_id'] <= 0 or not isinstance(f.get('path'), str) or not f['path'].startswith('/') for f in vendor_fixtures.values()):
            return False
        if len({f['post_id'] for f in vendor_fixtures.values()}) != 6 or len({f['path'] for f in vendor_fixtures.values()}) != 6:
            return False
    for state in states:
        active = state['key'].endswith('-on')
        if state.get('active') is not active or state.get('complete') is not True or state.get('failures') != []:
            return False
        server = report.get('server', 'nginx-fpm')
        if not metadata_matches(report['profile'], report['jit'], state, server):
            return False
        markers = state.get('generations')
        if not page_evidence(markers, report['profile'] in ('wp-super-cache', 'w3-total-cache', 'litespeed-cache')):
            return False
        checks = state.get('checks')
        required = REQUIRED | (ACTIVE_REQUIRED if active else {'inactive-endpoint'})
        if server == 'openlitespeed' and active:
            required |= {'renderer-cache-bypass'}
            if not lsapi_renderer_bypass(state):
                return False
        if server == 'openlitespeed' and report['profile'] == 'baseline':
            required |= {'server-baseline'}
            if not lsapi_baseline_uncached(state):
                return False
        if report['profile'] == 'litespeed-cache':
            required |= {'server-cache-hits'}
            if not litespeed_page_hits(state):
                return False
        if report['profile'] in CONTENT_PROFILES:
            required |= {'vendor-http', 'vendor-structure', 'vendor-output', 'vendor-browser',
                         'vendor-browser-image' if active else 'vendor-browser-no-timer'}
            proof = state.get('vendor_proof')
            if (not isinstance(proof, dict) or proof.get('profile') != report['profile']
                    or proof.get('status') != 200 or proof.get('native_structure') is not True
                    or type(proof.get('post_id')) is not int or proof['post_id'] <= 0
                    or type(proof.get('timer_images')) is not int or proof['timer_images'] != int(active)):
                return False
            fixture = vendor_fixtures[state['key']]
            if (proof['post_id'] != fixture['post_id'] or proof.get('path') != fixture['path']
                    or proof.get('scoped_timer_images') != int(active) or proof.get('scope_found') is not True
                    or proof.get('literal_shortcode') is not (not active)
                    or not isinstance(proof.get('rendered_post_ids'), list) or str(fixture['post_id']) not in proof['rendered_post_ids']):
                return False
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
