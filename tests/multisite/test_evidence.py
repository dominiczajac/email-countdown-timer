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

ROOT = Path(__file__).resolve().parents[2]

def good(theme='twentytwentyone', activation='network', jit='1235'):
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
                         'width': WIDTHS[role], 'deadline': DEADLINES[role], 'campaign_keys': ['shared-timer', 'only-' + role],
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
    return report

def logs(root):
    (root / 'fpm.log').write_text('NOTICE: ready to handle connections\nNOTICE: exiting, bye-bye!\n')
    (root / 'php-errors.log').write_text('PHP Deprecated: synthetic retained notice\n')
    (root / 'nginx-error.log').write_text('')
    (root / 'service-stop.json').write_text(json.dumps({'nginx': 0, 'fpm': 0}))
    (root / 'checks.json').write_text(json.dumps(good()))

class Evidence(unittest.TestCase):
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
