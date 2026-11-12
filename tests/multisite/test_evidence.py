#!/usr/bin/env python3
"""Regressions for copied context, sibling mutation and false-positive evidence."""
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unittest
import zipfile

from evidence import BASE, DEADLINES, ORDER, PATHS, PLUGIN, ROLES, THEMES, WIDTHS, accept, active_for, assess, image_reference, navigation_abort
from cache_evidence import SETTINGS, VENDOR, cache_path
import edit_refresh

ROOT = Path(__file__).resolve().parents[2]

def edit_body(display, marker):
    base = BASE + '/blue/?ecd_action=render&amp;ecd=shared-timer&amp;mode=anim'
    return ('<span id="ecd-cache-origin" data-generation="' + marker + '"></span>'
            + '<img class="email-countdown-timer-image" width="' + str(display['width']) + '" src="' + base + '&amp;_t=1794399999" data-ecd-src="' + base + '">'
            + '<div class="ecd-live" data-ecd-deadline="' + str(display['deadline']) + '" style="--ecd-bg:' + display['background'] + ';"></div>').encode()

def edit_image_bytes(kind):
    # Synthetic integrity inputs for the gate's unit tests, never native images.
    return (b'\x89PNG\r\n\x1a\n' if kind == 'png' else b'GIF89a') + b'synthetic test data'

def edit_good(report, state):
    cached = report.get('profile') == 'wp-super-cache'; key = state['key']
    row = state['sites'][1]; native = row['page_runtime'][0]
    initial = {r: report['fixture'][r]['campaign_sha256'] for r in ROLES}
    proof = {'complete': True, 'form_saves': ['edited', 'restored'], 'stages': [],
             'after_save_fresh': not cached, 'after_restore_fresh': not cached}
    for name in edit_refresh.STAGES:
        changed = name in ('edited', 'purged')
        stored = edit_refresh.settings(DEADLINES['blue'], WIDTHS['blue'], changed)
        display = edit_refresh.settings(DEADLINES['blue'], WIDTHS['blue'],
            name in ('purged', 'restored') if cached else changed)
        marker = hashlib.sha256((key + name).encode()).hexdigest()[:24]
        body = edit_body(display, marker); sha = hashlib.sha256(body).hexdigest()
        pages = []
        for i in range(4):
            if not cached:
                marker = hashlib.sha256((key + name + str(i)).encode()).hexdigest()[:24]
                body = edit_body(display, marker); sha = hashlib.sha256(body).hexdigest()
            hit = cached and (i > 0 or name in ('edited', 'restored'))
            pages.append({'status': 200, 'content_type': 'text/html', 'cache_header': 'Served supercache file from PHP' if hit else None,
                          'runtime': {} if hit else copy.deepcopy(native), 'generation': marker, 'sha256': sha, 'public': dict(display)})
        stage = {'name': name, 'stored': stored, 'hashes': {**initial, 'blue': 'd' * 64} if changed else dict(initial), 'pages': pages,
                 'body': {'snapshot': edit_refresh.filename(key, name, 'html'), 'bytes': len(body), 'sha256': sha, 'generation': marker,
                          'cache_path': cache_path('blue', key) if cached else None}, 'images': {},
                 'browser': {'cache_header': 'Served supercache file from PHP' if cached else None, 'runtime': {} if cached else copy.deepcopy(native),
                             'generation': marker, 'sha256': sha, 'public': dict(display), 'natural_width': stored['width']}}
        for kind in ('png', 'gif'):
            image = copy.deepcopy(row['images'][kind][0]); data = edit_image_bytes(kind)
            image.update({'width': stored['width'], 'background_rgb': [int(stored['background'][i:i+2],16) for i in (1,3,5)],
                          'url': BASE + '/blue/?ecd_action=render&ecd=shared-timer&mode=' + ('email' if kind == 'gif' else 'static'),
                          'snapshot': edit_refresh.filename(key, name, kind), 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()})
            stage['images'][kind] = image
        proof['stages'].append(stage)
    return proof

def write_edits(root, report):
    for state in report['states']:
        for stage in (state.get('visible_edit') or {}).get('stages', []):
            (root / stage['body']['snapshot']).write_bytes(edit_body(stage['pages'][-1]['public'], stage['body']['generation']))
            for kind, image in stage['images'].items(): (root / image['snapshot']).write_bytes(edit_image_bytes(kind))

def cache_body(row):
    marker = row['generations'][-1]
    content = ('<img class="email-countdown-timer-image" src="' + BASE + PATHS[row['role']] + '?ecd_action=render&amp;ecd=shared-timer&amp;mode=anim&amp;_t=1794399999">'
               + '<div class="ecd-live" data-ecd-deadline="' + str(DEADLINES[row['role']]) + '"></div>') if row['active'] else '[ecd_timer id="shared-timer"]'
    return ('<span id="ecd-cache-origin" data-generation="' + marker + '"></span>' + content).encode()

def good(theme='twentytwentyone', activation='network', jit='1235', profile='baseline'):
    initial = {role: str(i + 1) * 64 for i, role in enumerate(ROLES)}
    fixture = {r: {'campaign_sha256': initial[r], 'pages': {k: BASE + PATHS[r] + k + '/' for k in ORDER}} for r in ROLES}
    report = {'theme': theme, 'activation': activation, 'jit': jit, 'complete': True, 'source_commit': 'a' * 40,
              'failures': [], 'page_errors': [], 'browser_errors': [], 'fixture': fixture, 'states': []}
    for key in ORDER:
        state = {'key': key, 'complete': True, 'failures': [], 'sites': [], 'inventory_before': [], 'mutation': None}
        for i, role in enumerate(ROLES):
            active = active_for(activation, key, role)
            h = {'x-ecd-network-' + k: v for k, v in {'blog': str(i + 1), 'home': BASE + PATHS[role], 'theme': theme,
                'sapi': 'fpm-fcgi', 'php': '8.4.27', 'opcache': 'on', 'jit': jit, 'jit-active': 'off' if jit == 'disable' else 'on',
                'gd': 'yes', 'active': 'on' if active else 'off', 'memory': '256M', 'execution': '30'}.items()}
            inventory = {'role': role, 'id': i + 1, 'home': BASE + PATHS[role], 'path': PATHS[role], 'wordpress': '7.1.2',
                         'php_cli': '8.4.27', 'mysql': '8.4.13', 'theme': {'name': theme, 'version': THEMES[theme][0], 'block': THEMES[theme][1]},
                         'width': WIDTHS[role], 'deadline': DEADLINES[role], 'background': '#ffffff', 'campaign_keys': ['shared-timer', 'only-' + role],
                         'campaign_sha256': initial[role], 'wp_cache': False, 'object_cache_dropin': False, 'advanced_cache_dropin': False,
                         'site_plugins': [PLUGIN] if active and activation == 'site' else [],
                         'network_plugins': [PLUGIN] if active and activation == 'network' else []}
            state['inventory_before'].append(inventory)
            site = {'role': role, 'active': active, 'complete': True, 'generations': [str(i) * 22 + str(x) + '0' for x in range(4)],
                    'page_runtime': [copy.deepcopy(h) for _ in range(4)],
                    'public': {'status': 200, 'images': int(active), 'live': int(active), 'deadline': DEADLINES[role] if active else None,
                               'image_home': BASE + PATHS[role] if active else None, 'image_reference_valid': True if active else None,
                               'private_name_absent': True, 'literal_shortcode': not active},
                    'checks': ['dashboard', 'media-library', 'public-browser']}
            def image(kind, missing=False):
                value = {'status': 404 if missing else 200, 'content_type': 'image/' + kind, 'cache_control': 'no-cache, no-store, must-revalidate',
                         'format': kind.upper(), 'width': 1 if missing else WIDTHS[role], 'height': 1 if missing else 79,
                         'frames': 60 if kind == 'gif' else 1, 'sha256': 'e' * 64, 'runtime': copy.deepcopy(h)}
                if kind == 'gif': value['durations'] = [1000] * 60
                return value
            if active:
                site['checks'] += ['preview', 'live-ticks', 'live-pauses', 'live-resumes', 'no-image-polling']
                site['images'] = {kind: [image(kind), image(kind)] for kind in ('gif', 'png')}
                site['missing_campaigns'] = [{'owner': owner, **image('png', True)} for owner in ROLES if owner != role]
            else:
                site['inactive_endpoint'] = {'status': 200, 'is_image': False, 'runtime': copy.deepcopy(h)}
            state['sites'].append(site)
        state['inventory_after'] = copy.deepcopy(state['inventory_before'])
        if key.endswith('-on'):
            state['mutation'] = {'before': dict(initial), 'edited': {**initial, 'blue': 'f' * 64}, 'restored': dict(initial)}
        report['states'].append(state)
    if profile != 'baseline':
        report['profile'] = profile
        for state in report['states']:
            for name in ('inventory_before', 'inventory_after'):
                for site in state[name]:
                    site['network_plugins'] = sorted(site['network_plugins'] + [VENDOR])
                    site['wp_cache'] = site['advanced_cache_dropin'] = True
                    site['cache_configuration'] = copy.deepcopy(SETTINGS)
            for row in state['sites']:
                role = row['role']; key = state['key']; marker = hashlib.sha256((key+role).encode()).hexdigest()[:24]
                row['generations'] = [marker] * 4
                first = copy.deepcopy(row['page_runtime'][0]); row['page_runtime'] = [first, {}, {}, {}]
                body = cache_body(row); sha = hashlib.sha256(body).hexdigest()
                row['cache'] = {'file': {'path': cache_path(role, key), 'snapshot': 'cache-'+key+'-'+role+'.html', 'generation': marker,
                    'sha256': sha, 'bytes': len(body)}, 'response_headers': [{'x-wp-super-cache': None}] + [{'x-wp-super-cache': 'Served supercache file from PHP'} for _ in range(3)],
                    'body_sha256': [sha] * 4}
                browser_file = copy.deepcopy(row['cache']['file']); browser_file['snapshot'] = 'cache-'+key+'-'+role+'-browser.html'
                row['browser_cache'] = {'mode': 'hit', 'header': 'Served supercache file from PHP', 'generation': marker,
                    'runtime': {}, 'file': browser_file, 'body_sha256': sha}
                row['checks'] += ['page-cache-hit', 'browser-cache-context']
    for state in report['states']:
        state['visible_edit'] = edit_good(report, state) if state['key'].endswith('-on') else None
    return report

def logs(root):
    (root / 'fpm.log').write_text('NOTICE: ready to handle connections\nNOTICE: exiting, bye-bye!\n')
    (root / 'php-errors.log').write_text('PHP Deprecated: synthetic retained notice\n')
    (root / 'nginx-error.log').write_text('')
    (root / 'service-stop.json').write_text(json.dumps({'nginx': 0, 'fpm': 0}))
    report = good(); write_edits(root, report)
    (root / 'checks.json').write_text(json.dumps(report))

class Evidence(unittest.TestCase):
    def test_failed_edit_recovery_restores_before_checking_and_retains_failures(self):
        values = {'blue': 'edited', 'red': 'original'}; initial = {'blue': 'original', 'red': 'original'}; calls = []
        def restore():
            calls.append('restore'); values['blue'] = 'original'
        def hashes():
            calls.append('hashes'); return dict(values)
        self.assertEqual(edit_refresh.cleanup_failed_edit(restore, hashes, initial), {'restored': True, 'error': None})
        self.assertEqual(calls, ['restore', 'hashes'])
        self.assertFalse(edit_refresh.cleanup_failed_edit(lambda: None, lambda: {'blue': 'edited'}, initial)['restored'])
        def failed_restore(): raise RuntimeError('synthetic cleanup failure')
        self.assertEqual(edit_refresh.cleanup_failed_edit(failed_restore, hashes, initial), {'restored': False, 'error': 'RuntimeError'})
        report = good(); report['states'][1]['failures'] = [{'reason': 'original failure'}]
        report['states'][1]['failed_edit_cleanup'] = {'restored': True, 'error': None}
        self.assertFalse(accept(report))

    def test_edit_browser_screenshots_cannot_replace_native_response_files(self):
        names = [edit_refresh.filename(key, stage, kind) for key in ORDER if key.endswith('-on')
                 for stage in edit_refresh.STAGES for kind in ('html', 'png', 'gif', 'browser')]
        self.assertEqual(len(names), len(set(names)))
        self.assertTrue(edit_refresh.filename('pair-1-on', 'edited', 'browser').endswith('-browser.png'))
        for key, stage, kind in (('../other', 'edited', 'png'), ('pair-1-off', 'edited', 'png'),
                                 ('pair-1-on', '../other', 'png'), ('pair-1-on', 'edited', '../other')):
            with self.subTest(key=key, stage=stage, kind=kind), self.assertRaises(ValueError):
                edit_refresh.filename(key, stage, kind)

    def test_visible_edits_require_current_images_saved_values_and_exact_restoration(self):
        for field in ('stored', 'hashes', 'image-width', 'image-color', 'image-url', 'frames', 'browser', 'form-saves', 'freshness'):
            report = good(profile='wp-super-cache'); proof = report['states'][1]['visible_edit']; stage = proof['stages'][1]
            if field == 'stored': stage['stored']['deadline'] -= 1
            elif field == 'hashes': stage['hashes']['red'] = 'a' * 64
            elif field == 'image-width': stage['images']['png']['width'] = WIDTHS['blue']
            elif field == 'image-color': stage['images']['gif']['background_rgb'] = [255, 255, 255]
            elif field == 'image-url': stage['images']['png']['url'] += '&cache_buster=new'
            elif field == 'frames': stage['images']['gif']['durations'][-1] = 900
            elif field == 'browser': stage['browser']['natural_width'] = WIDTHS['blue']
            elif field == 'form-saves': proof['form_saves'] = ['edited']
            else: proof['after_save_fresh'] = True
            with self.subTest(field=field): self.assertFalse(accept(report))
        report = good(); report['states'][1]['visible_edit']['stages'][3]['hashes']['blue'] = 'a'*64
        self.assertFalse(accept(report))

    def test_edit_purge_must_refresh_html_and_keep_native_or_cache_provenance(self):
        for field in ('missing', 'order', 'public', 'runtime', 'hash', 'cache-path', 'cache-hit', 'boolean-width'):
            report = good(profile='wp-super-cache'); proof = report['states'][1]['visible_edit']; stage = proof['stages'][2]
            if field == 'missing': proof['stages'].pop()
            elif field == 'order': proof['stages'].reverse()
            elif field == 'public': stage['pages'][3]['public'] = copy.deepcopy(proof['stages'][0]['stored'])
            elif field == 'runtime': stage['pages'][0]['runtime'] = {}
            elif field == 'hash': stage['pages'][2]['sha256'] = '0'*64
            elif field == 'cache-path': stage['body']['cache_path'] = cache_path('red', 'pair-1-on')
            elif field == 'cache-hit': stage['pages'][3]['cache_header'] = None
            else: stage['stored']['width'] = True
            with self.subTest(field=field): self.assertFalse(accept(report))

    def test_baseline_cannot_present_stale_html_after_saving(self):
        report = good(); stage = report['states'][1]['visible_edit']['stages'][1]
        for page in stage['pages']: page['public'] = edit_refresh.settings(DEADLINES['blue'], WIDTHS['blue'])
        self.assertFalse(accept(report))
        report = good(); report['states'][0]['visible_edit'] = report['states'][1]['visible_edit']
        self.assertFalse(accept(report))

    def test_retained_edit_html_and_image_bodies_cannot_be_missing_changed_or_linked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); logs(root); report = good(); self.assertTrue(assess(root)['passed'])
            stage = report['states'][1]['visible_edit']['stages'][1]
            for name in (stage['body']['snapshot'], stage['images']['png']['snapshot'], stage['images']['gif']['snapshot']):
                target = root / name; data = target.read_bytes(); target.unlink()
                self.assertFalse(assess(root)['passed'])
                target.write_bytes(b'changed'); self.assertFalse(assess(root)['passed'])
                target.unlink(); target.symlink_to(root/'checks.json'); self.assertFalse(assess(root)['passed'])
                target.unlink(); target.write_bytes(data)
            self.assertTrue(assess(root)['passed'])

    def test_cache_profile_passes_all_eight_comparisons_with_real_hit_shape(self):
        for theme in THEMES:
            for activation in ('site', 'network'):
                for jit in ('1235', 'disable'):
                    with self.subTest(theme=theme, activation=activation, jit=jit): self.assertTrue(accept(good(theme, activation, jit, 'wp-super-cache')))

    def test_cache_cannot_replace_native_origin_or_image_context(self):
        for field in ('page_runtime', 'images'):
            r = good(profile='wp-super-cache'); row = r['states'][1]['sites'][1]
            if field == 'page_runtime': row[field][0] = {}
            else: row[field]['gif'][1]['cache_header'] = 'Served supercache file from PHP'
            with self.subTest(field=field): self.assertFalse(accept(r))
        r=good(profile='wp-super-cache');r['states'][1]['sites'][1]['page_runtime'][2]={'x-ecd-network-blog':'1'};self.assertFalse(accept(r))

    def test_measured_cache_hits_require_exact_headers_hashes_and_site_file(self):
        for mutation in ('header','hash','marker','path','snapshot','size','label'):
            r=good(profile='wp-super-cache');row=r['states'][1]['sites'][1]
            if mutation=='header':row['cache']['response_headers'][2]['x-wp-super-cache']=None
            elif mutation=='hash':row['cache']['body_sha256'][3]='0'*64
            elif mutation=='marker':row['generations'][2]='0'*24
            elif mutation=='path':row['cache']['file']['path']=cache_path('red','pair-1-on')
            elif mutation=='snapshot':row['cache']['file']['snapshot']='../other.html'
            elif mutation=='size':row['cache']['file']['bytes']=True
            else:row['checks'].remove('page-cache-hit')
            with self.subTest(mutation=mutation):self.assertFalse(accept(r))

    def test_cache_configuration_and_network_plugin_are_mandatory(self):
        for key,value in [('cache_max_time',60),('wp_cache_mod_rewrite',1),('cache_enabled',1),('wpsc_served_header',False),('version','3.1.3')]:
            r=good(profile='wp-super-cache');r['states'][0]['inventory_before'][0]['cache_configuration'][key]=value
            with self.subTest(key=key):self.assertFalse(accept(r))
        r=good(profile='wp-super-cache');r['states'][0]['inventory_after'][2]['network_plugins']=[];self.assertFalse(accept(r))
        r=good(profile='wp-super-cache');r['states'][0]['inventory_after'][0]['object_cache_dropin']=True;self.assertFalse(accept(r))

    def test_browser_cache_or_actual_origin_must_remain_site_bound(self):
        r=good(profile='wp-super-cache');row=r['states'][1]['sites'][1]
        origin={'mode':'origin','header':None,'file':None,'generation':row['generations'][0],'runtime':copy.deepcopy(row['page_runtime'][0])}
        row['browser_cache']=origin;self.assertTrue(accept(r))
        origin['runtime']={};self.assertFalse(accept(r))
        for key,value in [('body_sha256','0'*64),('header','Served WPCache cache file')]:
            r=good(profile='wp-super-cache');r['states'][1]['sites'][1]['browser_cache'][key]=value;self.assertFalse(accept(r))

    def test_retained_cache_bodies_are_required_and_cannot_be_copied_or_linked(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);logs(root);r=good(profile='wp-super-cache');write_edits(root,r);(root/'checks.json').write_text(json.dumps(r))
            for state in r['states']:
                for row in state['sites']:
                    for file in (row['cache']['file'],row['browser_cache']['file']): (root/file['snapshot']).write_bytes(cache_body(row))
            self.assertTrue(assess(root)['passed'])
            target=root/r['states'][0]['sites'][0]['cache']['file']['snapshot'];original=target.read_bytes()
            target.unlink();self.assertFalse(assess(root)['passed'])
            target.write_bytes(b'copied sibling HTML');self.assertFalse(assess(root)['passed'])
            target.unlink();target.symlink_to(root/'checks.json');self.assertFalse(assess(root)['passed'])

    def test_rendered_reference_keeps_the_existing_base_and_cache_buster(self):
        for role in ROLES:
            base = BASE + PATHS[role] + '?ecd_action=render&ecd=shared-timer&mode=anim'
            attrs = {'src': base + '&_t=1794399999', 'data-ecd-src': base}
            self.assertTrue(image_reference(attrs, role))
            for name, value in [('src', base), ('src', base + '&_t=bad'), ('src', base + '&_t=1794399999&_t=1794399999'),
                                ('data-ecd-src', base + '&extra=1'), ('src', base.replace(PATHS[role] + '?', '/other/?') + '&_t=1794399999')]:
                with self.subTest(role=role, name=name, value=value):
                    self.assertFalse(image_reference({**attrs, name: value}, role))

    def test_all_eight_real_matrix_shapes(self):
        for theme in THEMES:
            for activation in ('site', 'network'):
                for jit in ('1235', 'disable'):
                    with self.subTest(theme=theme, activation=activation, jit=jit): self.assertTrue(accept(good(theme, activation, jit)))

    def test_partial_missing_reordered_failed_or_browser_error_reports_fail(self):
        for field, value in [('complete', False), ('states', []), ('source_commit', ''), ('theme', 'unknown'),
                             ('activation', 'unknown'), ('jit', 'off'), ('failures', [{}]), ('page_errors', [{}]), ('browser_errors', [{}])]:
            r = good(); r[field] = value
            with self.subTest(field=field): self.assertFalse(accept(r))
        r = good(); r['states'].reverse(); self.assertFalse(accept(r))

    def test_each_site_is_bound_to_its_observed_http_context(self):
        for field, value in [('blog', '1'), ('home', BASE + '/'), ('theme', 'twentytwentyfive'), ('sapi', 'cli'),
                             ('active', 'off'), ('jit-active', 'off'), ('gd', 'no'), ('memory', '512M'), ('execution', '0')]:
            r = good(); r['states'][1]['sites'][1]['page_runtime'][2]['x-ecd-network-' + field] = value
            with self.subTest(field=field): self.assertFalse(accept(r))

    def test_copied_sibling_options_activation_or_theme_inventory_fails(self):
        for field, value in [('id', 1), ('width', 480), ('deadline', DEADLINES['root']), ('path', '/'), ('campaign_keys', ['shared-timer', 'only-root']),
                             ('network_plugins', []), ('object_cache_dropin', True), ('wp_cache', 0), ('campaign_sha256', '1' * 64)]:
            r = good(); r['states'][1]['inventory_before'][1][field] = value
            with self.subTest(field=field): self.assertFalse(accept(r))
        r = good(activation='site'); r['states'][1]['inventory_after'][0]['site_plugins'] = [PLUGIN]; self.assertFalse(accept(r))

    def test_both_native_image_fetches_must_match_the_site_and_frame_timing(self):
        for kind in ('png', 'gif'):
            for field, value in [('width', 480), ('frames', True), ('height', 1), ('status', 503), ('cache_control', 'no-cache'), ('format', 'HTML')]:
                r = good(); r['states'][1]['sites'][1]['images'][kind][1][field] = value
                with self.subTest(kind=kind, field=field): self.assertFalse(accept(r))
        r = good(); r['states'][1]['sites'][1]['images']['gif'][0]['durations'][59] = 900; self.assertFalse(accept(r))
        r = good(); r['states'][1]['sites'][1]['images']['png'][1]['runtime']['x-ecd-network-blog'] = '3'; self.assertFalse(accept(r))

    def test_missing_campaign_must_remain_missing_on_each_sibling(self):
        for field, value in [('status', 200), ('width', 540), ('owner', 'blue')]:
            r = good(); r['states'][1]['sites'][1]['missing_campaigns'][0][field] = value
            with self.subTest(field=field): self.assertFalse(accept(r))

    def test_public_embedding_deadline_private_name_and_browser_proof(self):
        for field, value in [('deadline', DEADLINES['red']), ('image_home', BASE + '/red/'), ('private_name_absent', False), ('literal_shortcode', True)]:
            r = good(); r['states'][1]['sites'][1]['public'][field] = value
            with self.subTest(field=field): self.assertFalse(accept(r))
        r = good(); r['states'][1]['sites'][1]['checks'].remove('live-pauses'); self.assertFalse(accept(r))

    def test_real_admin_write_must_change_only_blue_and_restore_all_options(self):
        for phase, role, value in [('edited', 'blue', '2' * 64), ('edited', 'red', 'f' * 64), ('restored', 'blue', 'f' * 64), ('before', 'root', 'f' * 64)]:
            r = good(); r['states'][1]['mutation'][phase][role] = value
            with self.subTest(phase=phase, role=role): self.assertFalse(accept(r))

    def test_core_navigation_abort_is_bounded_to_owned_paths_and_real_post_action(self):
        for prefix in ('/', '/blue/', '/red/'):
            url = BASE + prefix + 'wp-admin/admin-ajax.php'
            self.assertTrue(navigation_abort('net::ERR_ABORTED', url, 'media-library', True, 'POST', 'action=query-attachments', 'application/x-www-form-urlencoded'))
            for body in ('action=email_countdown_timer_campaigns', 'bad', 'action=a&action=email_countdown_timer_save', 'x' * 65537):
                self.assertFalse(navigation_abort('net::ERR_ABORTED', url, 'media-library', True, 'POST', body, 'application/x-www-form-urlencoded'))
        for url in ('https://example.invalid/wp-admin/a', BASE + '/bluex/wp-admin/a', BASE + '/blue/?ecd_action=render', BASE + '/blue/wp-content/plugins/easy-countdown/assets/admin.js'):
            self.assertFalse(navigation_abort('net::ERR_ABORTED', url, 'timer-editor', True))
        self.assertFalse(navigation_abort('net::ERR_ABORTED', BASE + '/blue/wp-admin/a', 'public-browser', True))

    def test_missing_logs_unclean_stop_late_fatal_and_incomplete_evidence_fail(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); logs(root); self.assertTrue(assess(root)['passed'])
            for name in ('fpm.log', 'php-errors.log', 'nginx-error.log', 'service-stop.json', 'checks.json'):
                original = (root / name).read_text(); (root / name).unlink()
                self.assertFalse(assess(root)['passed']); (root / name).write_text(original)
            for text in ('SIGBUS', 'worker killed by signal 8', 'PHP Fatal error', '[error] synthetic failure'):
                (root / 'nginx-error.log').write_text(text); self.assertFalse(assess(root)['passed'])
            (root / 'nginx-error.log').write_text(''); (root / 'service-stop.json').write_text('{"nginx":0,"fpm":137}')
            self.assertFalse(assess(root)['passed'])

    def test_actual_exit_trap_preserves_original_failure_and_gates_late_evidence(self):
        function = re.search(r'(?ms)^finish\(\) \{\n.*?^\}\n', (ROOT / 'tests/multisite/run.sh').read_text()).group(0)
        for name, original, stop, extra, expected in [('clean', 0, 0, '', 0), ('original', 42, 0, '', 42),
                ('late-fault', 0, 0, 'SIGBUS', 1), ('unclean-stop', 0, 137, '', 1), ('missing-checks', 0, 0, 'missing', 1)]:
            with self.subTest(name=name), tempfile.TemporaryDirectory() as tmp:
                root = Path(tmp); logs(root)
                if extra == 'missing': (root / 'checks.json').unlink()
                elif extra: (root / 'php-errors.log').write_text(extra)
                script = 'root=$1\nexport ECD_NETWORK_EVIDENCE=$2 ECD_NETWORK_THEME=twentytwentyone ECD_NETWORK_ACTIVATION=network ECD_NETWORK_JIT=1235\nphase=exit-test\nweb=111\nfpm=222\nkill() { return 0; }\nwait() { return "$ECD_TEST_STOP"; }\n' + function + '\ntrap finish EXIT\nexit "$3"\n'
                result = subprocess.run(['bash', '-c', script, 'test', str(ROOT), str(root), str(original)],
                                        env={**os.environ, 'ECD_TEST_STOP': str(stop)}, capture_output=True)
                self.assertEqual(result.returncode, expected)

    def test_pinned_theme_verifier_requires_exact_files_and_rejects_links(self):
        spec = importlib.util.spec_from_file_location('theme_verifier', Path(__file__).with_name('verify-theme.py'))
        verifier = importlib.util.module_from_spec(spec); spec.loader.exec_module(verifier)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); theme = root / 'theme'; theme.mkdir(); archive = root / 'theme.zip'
            with zipfile.ZipFile(archive, 'w') as z: z.writestr('theme/style.css', b'synthetic theme')
            (theme / 'style.css').write_bytes(b'synthetic theme'); expected = hashlib.sha256(archive.read_bytes()).hexdigest()
            self.assertTrue(verifier.verify(archive, theme, 'theme', expected)['passed'])
            with self.assertRaises(ValueError): verifier.verify(archive, theme, 'theme', '0' * 64)
            (theme / 'extra').touch()
            with self.assertRaises(ValueError): verifier.verify(archive, theme, 'theme', expected)
            (theme / 'extra').unlink(); (theme / 'style.css').unlink(); (theme / 'style.css').symlink_to(archive)
            with self.assertRaises(ValueError): verifier.verify(archive, theme, 'theme', expected)

if __name__ == '__main__': unittest.main()
