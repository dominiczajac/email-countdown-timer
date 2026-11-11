"""Separate native-origin and early PHP cache-hit proof for the owned network."""
import hashlib
import os
from pathlib import Path
import re
import stat
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'cache-profiles'))
from contracts import Page, generation, page_evidence
from urllib.parse import urlsplit

VENDOR = 'wp-super-cache/wp-cache.php'
VERSION = '3.1.5'
ARCHIVE_SHA256 = '5a1af59e8a8289083de64d28ded9fb1dfb56ee996d4a84d3ed1f76c5f2224eb6'
HITS = {'Served supercache file from PHP', 'Served supercache gzip file from PHP'}
SETTINGS = {'version': VERSION, 'dropin': 'WPSC', 'cache_enabled': True, 'super_cache_enabled': True,
            'wp_cache_mod_rewrite': 0, 'cache_max_time': 3600, 'cache_rebuild_files': 1, 'cache_compression': 0,
            'ossdlcdn': 0, 'wp_cache_not_logged_in': 2, 'wp_cache_slash_check': 1, 'wpsc_served_header': True,
            'wp_super_cache_late_init': 0, 'wp_cache_preload_on': 0, 'wp_cache_debug_to_file': 0,
            'wp_super_cache_advanced_debug': 0, 'wp_cache_mfunc_enabled': 0, 'wpsc_save_headers': 0}
PATHS = {'root': '/', 'blue': '/blue/', 'red': '/red/'}

def cache_path(role, key):
    if role not in PATHS or not re.fullmatch('pair-[1-3]-(?:off|on)', key): raise ValueError('invalid_cache_binding')
    return 'cache/supercache/localhost' + PATHS[role] + key + '/index.html'

def bounded_bytes(path):
    if path.resolve(strict=True) != path: raise ValueError('linked_cache_file')
    fd = os.open(path, os.O_RDONLY | os.O_NONBLOCK | os.O_NOFOLLOW)
    with os.fdopen(fd, 'rb') as stream:
        info = os.fstat(stream.fileno())
        if not stat.S_ISREG(info.st_mode) or info.st_size > 16 * 1024 * 1024: raise ValueError('invalid_cache_file')
        data = stream.read(16 * 1024 * 1024 + 1)
        if len(data) > 16 * 1024 * 1024: raise ValueError('oversized_cache_file')
    return data

def snapshot(wpdir, out, role, key, browser=False):
    relative = cache_path(role, key)
    body = bounded_bytes(wpdir / 'wp-content' / relative)
    marker = generation(body.decode('utf-8'))
    filename = 'cache-' + key + '-' + role + ('-browser' if browser else '') + '.html'
    with (out / filename).open('xb') as stream: stream.write(body)
    return {'path': relative, 'snapshot': filename, 'sha256': hashlib.sha256(body).hexdigest(),
            'bytes': len(body), 'generation': marker}

def file_proof(file, role, key, marker, browser=False):
    return (isinstance(file, dict) and role in PATHS and file.get('path') == cache_path(role, key)
            and file.get('snapshot') == 'cache-' + key + '-' + role + ('-browser' if browser else '') + '.html'
            and file.get('generation') == marker and isinstance(file.get('sha256'), str) and re.fullmatch('[a-f0-9]{64}', file['sha256'])
            and type(file.get('bytes')) is int and 0 < file['bytes'] <= 16 * 1024 * 1024)

def hit_proof(row, key):
    proof = row.get('cache')
    if not isinstance(proof, dict) or not page_evidence(row.get('generations'), True): return False
    file = proof.get('file'); headers = proof.get('response_headers'); hashes = proof.get('body_sha256')
    if not isinstance(file, dict) or not isinstance(headers, list) or len(headers) != 4 or not all(isinstance(x, dict) for x in headers): return False
    if headers[0].get('x-wp-super-cache') is not None or any(x.get('x-wp-super-cache') not in HITS for x in headers[2:]): return False
    markers = row['generations']
    for i in range(1, 4):
        if headers[i].get('x-wp-super-cache') in HITS and markers[i] != markers[i - 1]: return False
    if not file_proof(file, row.get('role'), key, markers[-1]): return False
    return (isinstance(hashes, list) and len(hashes) == 4 and all(isinstance(x, str) and re.fullmatch('[a-f0-9]{64}', x) for x in hashes)
            and hashes[2:] == [file['sha256'], file['sha256']])

def browser_proof(row, key, runtime_valid):
    proof = row.get('browser_cache')
    if not isinstance(proof, dict) or not isinstance(proof.get('generation'), str) or not re.fullmatch('[a-f0-9]{24}', proof['generation']): return False
    if proof.get('mode') == 'origin':
        return proof.get('header') is None and proof.get('file') is None and runtime_valid(proof.get('runtime'))
    return (proof.get('mode') == 'hit' and proof.get('header') in HITS
            and (proof.get('runtime') == {} or runtime_valid(proof.get('runtime')))
            and file_proof(proof.get('file'), row.get('role'), key, proof['generation'], browser=True)
            and proof.get('body_sha256') == proof['file']['sha256'])

def body_context(body, row):
    html = body.decode('utf-8'); parsed = Page(html); public = row.get('public', {})
    if (len(parsed.images) != int(row['active']) or len(parsed.live) != int(row['active'])
            or 'PRIVATE-NETWORK-' in html or ('[ecd_timer' in html) is not (not row['active'])): return False
    if row['active']:
        src = urlsplit(parsed.images[0].get('src', ''))
        if (src.scheme + '://' + src.netloc + src.path != public.get('image_home')
                or parsed.live[0].get('data-ecd-deadline') != str(public.get('deadline'))): return False
    return True

def snapshot_replay(directory, report):
    errors = []
    if not isinstance(report, dict): return [{'file': 'cache-snapshot', 'reason': 'invalid_report'}]
    if report.get('profile', 'baseline') != 'wp-super-cache': return errors
    states = report.get('states')
    if not isinstance(states, list): return [{'file': 'cache-snapshot', 'reason': 'invalid_states'}]
    for state in states:
        if not isinstance(state, dict) or not isinstance(state.get('sites'), list):
            errors.append({'file': 'cache-snapshot', 'reason': 'invalid_state'}); continue
        for row in state['sites']:
            try:
                if not isinstance(row, dict): raise ValueError('invalid_site')
                if not hit_proof(row, state['key']): raise ValueError('invalid_cache_proof')
                files = [row['cache']['file']]
                browser = row.get('browser_cache', {})
                if not isinstance(browser, dict): raise ValueError('invalid_browser_proof')
                if browser.get('mode') == 'hit':
                    if not file_proof(browser.get('file'), row['role'], state['key'], browser.get('generation'), browser=True): raise ValueError('invalid_browser_snapshot')
                    files.append(browser['file'])
                for proof in files:
                    body = bounded_bytes(directory / proof['snapshot'])
                    if (hashlib.sha256(body).hexdigest() != proof['sha256'] or len(body) != proof['bytes']
                            or generation(body.decode('utf-8')) != proof['generation'] or not body_context(body, row)): raise ValueError('cache_snapshot_mismatch')
            except (OSError, ValueError, UnicodeError, TypeError, KeyError) as error:
                errors.append({'file': 'cache-snapshot', 'reason': type(error).__name__})
    return errors
