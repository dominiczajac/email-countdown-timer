"""Separate FPM and native LSAPI evidence; no server or Docker side effects."""
import re

OLS_IMAGE = 'litespeedtech/openlitespeed:1.9.4-lsphp84@sha256:84b5a648789f0c4ba1da9ffe3cb3ba79ae30a9c5c95f1e6a7f9302f8b273f2f8'
MYSQL_IMAGE = 'mysql:8.4.13@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
LSC_SETTINGS = {'version': '7.9.3', 'cache': True, 'cache-ttl_pub': 1800, 'guest': False,
                'object': False, 'crawler': False, 'cdn': False, 'cdn-quic': False,
                'optm-css_min': False, 'optm-js_min': False, 'optm-html_min': False, 'media-lazy': False}


def server_allowed(server, profile):
    if server == 'nginx-fpm':
        return profile in ('baseline', 'wp-super-cache', 'w3-total-cache', 'autoptimize', 'elementor', 'woocommerce')
    return server == 'openlitespeed' and profile in ('baseline', 'litespeed-cache')


def server_proof_matches(report):
    server, profile = report.get('server', 'nginx-fpm'), report.get('profile')
    if not server_allowed(server, profile):
        return False
    if server != 'openlitespeed':
        return True
    proof = report.get('server_proof')
    if not isinstance(proof, dict):
        return False
    expected = {'name': 'openlitespeed', 'image_reference': OLS_IMAGE, 'architecture': 'linux/amd64',
                'php_lsapi': '8.4.27', 'connection_timeout': 40, 'php_workers': 4, 'lsapi_process_limit': 35,
                'packages': {'lsphp84': '8.4.27-1+resolute', 'lsphp84-imagick': '3.8.1-1+resolute'}}
    if any(proof.get(key) != value for key, value in expected.items()):
        return False
    if proof.get('cache_enabled') is not (profile == 'litespeed-cache') or proof.get('query_cache') is not True:
        return False
    version = proof.get('version_output')
    if not isinstance(version, str) or not version.startswith('LiteSpeed/1.9.4 Open ') or not re.search(r'\bcache 1\.66\b', version):
        return False
    for a, b, pattern in [('image_id', 'executed_image_id', r'sha256:[a-f0-9]{64}'),
                           ('configuration_sha256', 'executed_configuration_sha256', r'[a-f0-9]{64}')]:
        if not isinstance(proof.get(a), str) or not re.fullmatch(pattern, proof[a]) or proof.get(b) != proof[a]:
            return False
    return True


def lsapi_runtime(headers):
    expected = {'x-ecd-lab-memory-limit': '256M', 'x-ecd-lab-execution-limit': '30',
                'x-ecd-lab-locked-memory-limit': 'yes', 'x-ecd-lab-locked-max-execution-time': 'yes'}
    return all(headers.get(key) == value for key, value in expected.items())


def lsapi_renderer_bypass(state):
    proof = state.get('renderer_cache_proof')
    if not isinstance(proof, dict) or set(proof) != {'gif', 'png'}:
        return False
    for kind, responses in proof.items():
        if not isinstance(responses, list) or len(responses) != 2:
            return False
        for response in responses:
            if (not isinstance(response, dict) or response.get('status') != 200
                    or not str(response.get('content_type', '')).startswith('image/' + kind)
                    or 'no-store' not in {x.strip().lower() for x in str(response.get('cache_control', '')).split(',')}
                    or response.get('cache') not in (None, 'miss') or response.get('format') != kind.upper()
                    or type(response.get('width')) is not int or response['width'] != 600
                    or type(response.get('height')) is not int or response['height'] <= 1
                    or type(response.get('frames')) is not int or response['frames'] != (60 if kind == 'gif' else 1)):
                return False
    return True


def litespeed_page_hits(state):
    responses = state.get('page_response_headers')
    markers = state.get('generations')
    return (isinstance(responses, list) and len(responses) == 4 and all(isinstance(x, dict) for x in responses)
            and isinstance(markers, list) and len(markers) == 4
            and responses[0].get('x-litespeed-cache') == 'miss'
            and responses[1].get('x-litespeed-cache') in ('miss', 'hit')
            and [x.get('x-litespeed-cache') for x in responses[2:]] == ['hit', 'hit']
            and all(responses[i].get('x-litespeed-cache') != 'hit' or markers[i] == markers[i - 1] for i in range(1, 4)))


def lsapi_baseline_uncached(state):
    responses = state.get('page_response_headers')
    return (isinstance(responses, list) and len(responses) == 4
            and all(isinstance(x, dict) and x.get('x-litespeed-cache') is None for x in responses))
