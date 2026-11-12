"""Saved timer values versus anonymous HTML, fresh images and explicit purges."""
import hashlib
from pathlib import Path
import re
import sys

from cache_evidence import HITS, bounded_bytes, cache_path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'cache-profiles'))
from contracts import Page, generation

STAGES = ('before', 'edited', 'purged', 'restored', 'restored-purged')
ORIGINAL_BACKGROUND = '#ffffff'
EDITED_BACKGROUND = '#f8fafc'


def settings(deadline, width, edited=False):
    return {'deadline': deadline + (86400 if edited else 0), 'width': width + (120 if edited else 0),
            'background': EDITED_BACKGROUND if edited else ORIGINAL_BACKGROUND}


def filename(key, stage, kind):
    if not re.fullmatch('pair-[1-3]-on', key) or stage not in STAGES or kind not in ('html', 'png', 'gif', 'browser'):
        raise ValueError('invalid_edit_snapshot')
    return 'edit-' + key + '-' + stage + ('-browser.png' if kind == 'browser' else '.' + kind)


def public_values(html, reference_valid):
    parsed = Page(html)
    if (len(parsed.images) != 1 or len(parsed.live) != 1 or 'PRIVATE-NETWORK-' in html
            or '[ecd_timer' in html or not reference_valid(parsed.images[0])):
        raise ValueError('invalid_edit_public_context')
    style = dict(item.strip().split(':', 1) for item in parsed.live[0].get('style', '').split(';') if ':' in item)
    return {'deadline': int(parsed.live[0]['data-ecd-deadline']), 'width': int(parsed.images[0]['width']),
            'background': style.get('--ecd-bg', '').lower()}


def digest(value, length=64):
    return isinstance(value, str) and re.fullmatch('[a-f0-9]{' + str(length) + '}', value) is not None


def values_equal(actual, expected):
    return (actual == expected and isinstance(actual, dict)
            and type(actual.get('deadline')) is int and type(actual.get('width')) is int)


def accept(proof, key, cached, initial, original, runtime_valid, image_valid):
    if (not isinstance(proof, dict) or proof.get('complete') is not True or proof.get('form_saves') != ['edited', 'restored']
            or not isinstance(proof.get('stages'), list) or [x.get('name') for x in proof['stages'] if isinstance(x, dict)] != list(STAGES)
            or len(proof['stages']) != len(STAGES)):
        return False
    edited = settings(original['deadline'], original['width'], True)
    edited_hashes = None
    for stage in proof['stages']:
        name = stage['name']; changed = name in ('edited', 'purged')
        expected = edited if changed else original
        hashes = stage.get('hashes')
        if (not values_equal(stage.get('stored'), expected) or not isinstance(hashes, dict) or set(hashes) != set(initial)
                or any(not digest(x) for x in hashes.values())
                or any(hashes[r] != initial[r] for r in initial if r != 'blue')):
            return False
        if changed:
            if hashes['blue'] == initial['blue']: return False
            if edited_hashes is not None and hashes != edited_hashes: return False
            edited_hashes = hashes
        elif hashes != initial: return False
        pages = stage.get('pages')
        if not isinstance(pages, list) or len(pages) != 4 or not all(isinstance(p, dict) for p in pages): return False
        allowed = [original, edited] if cached and name in ('edited', 'restored') else [expected]
        for index, page in enumerate(pages):
            if (page.get('status') != 200 or type(page.get('status')) is not int
                    or not str(page.get('content_type', '')).startswith('text/html')
                    or not digest(page.get('generation'), 24) or not digest(page.get('sha256'))
                    or not any(values_equal(page.get('public'), value) for value in allowed)):
                return False
            hit = page.get('cache_header')
            if hit is not None and (not cached or hit not in HITS): return False
            if not (hit in HITS and page.get('runtime') == {}) and not runtime_valid(page.get('runtime')): return False
            if cached and index >= 2 and hit not in HITS: return False
            if hit in HITS and index and page['generation'] != pages[index - 1]['generation']: return False
        if any(page['public'] != pages[-1]['public'] for page in pages): return False
        if cached:
            if pages[2]['sha256'] != pages[3]['sha256']: return False
            if name in ('before', 'purged', 'restored-purged') and pages[0].get('cache_header') is not None: return False
        elif len({p['generation'] for p in pages}) != 4: return False
        body = stage.get('body')
        if (not isinstance(body, dict) or body.get('snapshot') != filename(key, name, 'html')
                or body.get('sha256') != pages[-1]['sha256'] or body.get('generation') != pages[-1]['generation']
                or type(body.get('bytes')) is not int or not 0 < body['bytes'] <= 16 * 1024 * 1024
                or body.get('cache_path') != (cache_path('blue', key) if cached else None)):
            return False
        browser = stage.get('browser')
        if (not isinstance(browser, dict) or not values_equal(browser.get('public'), pages[-1]['public'])
                or browser.get('natural_width') != expected['width'] or type(browser.get('natural_width')) is not int
                or not digest(browser.get('generation'), 24) or not digest(browser.get('sha256'))):
            return False
        hit = browser.get('cache_header')
        if cached:
            if hit not in HITS or browser['sha256'] != body['sha256'] or browser['generation'] != body['generation']: return False
        elif hit is not None: return False
        if not (hit in HITS and browser.get('runtime') == {}) and not runtime_valid(browser.get('runtime')): return False
        images = stage.get('images')
        if not isinstance(images, dict) or set(images) != {'png', 'gif'}: return False
        rgb = [int(expected['background'][i:i + 2], 16) for i in (1, 3, 5)]
        for kind, image in images.items():
            mode = 'email' if kind == 'gif' else 'static'
            if (not image_valid(image, kind, expected['width']) or image.get('background_rgb') != rgb
                    or image.get('url') != 'http://localhost:8097/blue/?ecd_action=render&ecd=shared-timer&mode=' + mode
                    or image.get('snapshot') != filename(key, name, kind)
                    or type(image.get('bytes')) is not int or not 0 < image['bytes'] <= 16 * 1024 * 1024):
                return False
    for name, stage in (('after_save_fresh', proof['stages'][1]), ('after_restore_fresh', proof['stages'][3])):
        if type(proof.get(name)) is not bool or proof[name] is not values_equal(stage['pages'][-1]['public'], stage['stored']): return False
    return True


def replay(directory, report, reference_valid):
    errors = []
    for state in report.get('states', []):
        proof = state.get('visible_edit')
        if proof is None: continue
        try:
            for stage in proof['stages']:
                key, name = state['key'], stage['name']
                body = bounded_bytes(directory / filename(key, name, 'html'))
                saved = stage['body']
                if (hashlib.sha256(body).hexdigest() != saved['sha256'] or len(body) != saved['bytes']
                        or generation(body.decode('utf-8')) != saved['generation']
                        or public_values(body.decode('utf-8'), reference_valid) != stage['pages'][-1]['public']):
                    raise ValueError('edit_html_snapshot_mismatch')
                for kind, image in stage['images'].items():
                    data = bounded_bytes(directory / filename(key, name, kind))
                    magic = b'\x89PNG\r\n\x1a\n' if kind == 'png' else b'GIF8'
                    if hashlib.sha256(data).hexdigest() != image['sha256'] or len(data) != image['bytes'] or not data.startswith(magic):
                        raise ValueError('edit_image_snapshot_mismatch')
        except (OSError, ValueError, UnicodeError, TypeError, KeyError) as error:
            errors.append({'file': 'edit-snapshot', 'reason': type(error).__name__})
    return errors
