#!/usr/bin/env python3
"""Negative controls for cache-hit evidence and complete off/on/browser acceptance."""
import copy
import json
import re
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from contracts import ACTIVE_REQUIRED, ORDER, PROFILES, REQUIRED, Page, accept, core_avatar, expected_plugins, generation, navigation_cancel, page_evidence


def good(profile='baseline'):
    report = {'profile': profile, 'jit': '1235', 'complete': True, 'failures': [], 'page_errors': [], 'browser_errors': [], 'states': [], 'source_commit': 'a' * 40,
              'plugins': [] if profile == 'baseline' else [{'name': profile, 'version': PROFILES[profile]}]}
    for key in ORDER:
        active = key.endswith('-on')
        checks = REQUIRED | (ACTIVE_REQUIRED if active else {'inactive-endpoint'})
        if profile == 'autoptimize' and active:
            checks |= {'optimized-asset', 'local-optimized-asset', 'served-optimized-asset'}
        markers = [f'{x:024x}' for x in range(4)]
        if profile in ('wp-super-cache', 'w3-total-cache'):
            markers[-1] = markers[-2]
            checks |= {'cache-artifact'}
        path = 'cache/supercache/control' if profile == 'wp-super-cache' else 'cache/page/control'
        runtime = {'x-ecd-lab-sapi': 'fpm-fcgi', 'x-ecd-lab-opcache': 'on', 'x-ecd-lab-jit': '1235', 'x-ecd-lab-jit-active': 'on',
                   'x-ecd-lab-gd': 'yes', 'x-ecd-lab-active': 'on' if active else 'off', 'x-ecd-lab-php': '8.4.27'}
        env = {'active_plugins': sorted(expected_plugins(profile, active)), 'wordpress': '7.1.2', 'php_cli': '8.4.27', 'mysql': '8.4.13', 'theme': {'name': 'twentytwentyfive'}}
        report['states'].append({'key': key, 'active': active, 'complete': True, 'checks': sorted(checks) + ['anonymous-page-http'] * 4, 'failures': [], 'generations': markers,
            'runtime': runtime, 'environment': env, 'engine_cache_files': [{'path': path, 'generation': markers[-1], 'sha256': 'b' * 64}]})
    return report


class Contracts(unittest.TestCase):
    def test_all_profiles_have_positive_controls(self):
        for profile in ('baseline', 'wp-super-cache', 'w3-total-cache', 'autoptimize'):
            self.assertTrue(accept(good(profile)))

    def test_noop_cached_profile_fails(self):
        report = good('wp-super-cache')
        report['states'][0]['generations'] = [f'{x:024x}' for x in range(4)]
        self.assertFalse(accept(report))

    def test_accidental_cache_on_baseline_fails(self):
        report = good()
        report['states'][0]['generations'][-1] = report['states'][0]['generations'][-2]
        self.assertFalse(accept(report))

    def test_empty_skipped_or_incomplete_states_fail(self):
        report = good()
        for states in ([], report['states'][:-1], [None] * 6):
            candidate = copy.deepcopy(report)
            candidate['states'] = states
            self.assertFalse(accept(candidate))
        report['states'][2]['complete'] = False
        self.assertFalse(accept(report))

    def test_missing_on_off_counterbalance_fails(self):
        report = good()
        report['states'][2], report['states'][3] = report['states'][3], report['states'][2]
        self.assertFalse(accept(report))

    def test_browser_or_failed_control_cannot_be_skipped(self):
        for check in ('core-media-control', 'live-browser', 'image-gif', 'timer-editor'):
            report = good()
            report['states'][1]['checks'].remove(check)
            self.assertFalse(accept(report))
        report = good()
        report['states'][0]['failures'].append({'phase': 'dashboard'})
        self.assertFalse(accept(report))

    def test_browser_exception_or_incomplete_run_fails(self):
        for field, value in (('complete', False), ('page_errors', [{'type': 'Error'}]), ('failures', [{'phase': 'renderer'}]), ('browser_errors', [{'reason': 'http_error'}])):
            report = good()
            report[field] = value
            self.assertFalse(accept(report))

    def test_autoptimize_activation_is_insufficient(self):
        report = good('autoptimize')
        report['states'][1]['checks'].remove('optimized-asset')
        self.assertFalse(accept(report))

    def test_missing_cache_artifact_fails(self):
        report = good('w3-total-cache')
        report['states'][1]['engine_cache_files'] = []
        self.assertFalse(accept(report))

    def test_wrong_runtime_or_plugin_versions_cannot_pass_by_label(self):
        for field, value in (('x-ecd-lab-jit', 'disable'), ('x-ecd-lab-active', 'off'), ('x-ecd-lab-php', '8.3.1'), ('x-ecd-lab-sapi', 'cli-server')):
            report = good()
            report['states'][1]['runtime'][field] = value
            self.assertFalse(accept(report))
        report = good('autoptimize'); report['plugins'][0]['version'] = '0.0.0'
        self.assertFalse(accept(report))
        report = good(); report['states'][1]['environment']['active_plugins'] = []
        self.assertFalse(accept(report))

    def test_unrelated_cache_file_cannot_count_as_page_cache_proof(self):
        report = good('w3-total-cache')
        report['states'][1]['engine_cache_files'][0]['path'] = 'cache/db/control'
        self.assertFalse(accept(report))

    def test_stale_cache_artifact_fails(self):
        report = good('w3-total-cache')
        report['states'][1]['engine_cache_files'][0]['generation'] = 'c' * 24
        self.assertFalse(accept(report))

    def test_missing_malformed_generation_is_rejected(self):
        for markers in (None, [], '', [None] * 4):
            report = good('wp-super-cache')
            report['states'][0]['generations'] = markers
            self.assertFalse(accept(report))

    def test_independent_exit_gate_rejects_missing_incomplete_or_failed_report(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'checks.json'
            command = [sys.executable, str(Path(__file__).with_name('contracts.py')), str(path)]
            self.assertNotEqual(subprocess.run(command, capture_output=True).returncode, 0)
            for value, passed in ((good(), True), ({}, False), ([], False)):
                path.write_text(json.dumps(value))
                result = subprocess.run(command, capture_output=True)
                self.assertEqual(result.returncode == 0, passed)
            candidate = good(); candidate['states'][0]['failures'] = [{'phase': 'dashboard'}]
            path.write_text(json.dumps(candidate))
            self.assertNotEqual(subprocess.run(command, capture_output=True).returncode, 0)

    def test_actual_exit_trap_preserves_failures_and_requires_complete_evidence(self):
        root = Path(__file__).resolve().parents[2]
        function = re.search(r'(?ms)^finish\(\) \{\n.*?^\}\n', Path(__file__).with_name('run.sh').read_text()).group(0)
        cases = [('clean', 0, True, False, 0), ('original', 42, True, False, 42),
                 ('late-native', 0, True, True, 1), ('no-checks', 0, False, False, 1)]
        for name, original, checks, native, expected in cases:
            with self.subTest(name=name), tempfile.TemporaryDirectory() as directory:
                evidence = Path(directory)
                (evidence / 'fpm.log').write_text('ready to handle connections\n' + ('child exited on signal 11 SIGSEGV\n' if native else ''))
                (evidence / 'php-errors.log').touch()
                if checks:
                    (evidence / 'checks.json').write_text(json.dumps(good()))
                script = 'root=$1\nexport ECD_CACHE_EVIDENCE=$2 ECD_CACHE_PROFILE=baseline ECD_CACHE_JIT=1235\nphase=exit-test\n' + function + '\ntrap finish EXIT\nexit "$3"\n'
                result = subprocess.run(['bash', '-c', script, 'exit-test', str(root), directory, str(original)], capture_output=True)
                self.assertEqual(result.returncode, expected)

    def test_only_core_admin_requests_aborted_during_navigation_are_expected(self):
        url = 'http://localhost:8095/wp-includes/js/wp-emoji-release.min.js'
        self.assertTrue(navigation_cancel('net::ERR_ABORTED', url, 'dashboard', True))
        for candidate, phase, navigating, code in ((url, 'live-browser', True, 'net::ERR_ABORTED'),
                (url, 'dashboard', False, 'net::ERR_ABORTED'), (url, 'dashboard', True, 'net::ERR_FAILED'),
                ('http://localhost:8095/?ecd_action=render', 'dashboard', True, 'net::ERR_ABORTED'),
                ('http://localhost:8095/wp-admin/admin-ajax.php?ecd_action=%72ender', 'dashboard', True, 'net::ERR_ABORTED'),
                ('http://localhost:8095/wp-admin/admin-ajax.php?action=email_countdown_timer_campaigns', 'timer-editor', True, 'net::ERR_ABORTED'),
                ('http://localhost:8095/wp-content/cache/autoptimize/main.js', 'timer-editor', True, 'net::ERR_ABORTED'),
                ('https://external.invalid/wp-includes/script.js', 'dashboard', True, 'net::ERR_ABORTED')):
            self.assertFalse(navigation_cancel(code, candidate, phase, navigating))

    def test_only_known_core_avatar_is_expected(self):
        url = 'https://secure.gravatar.com/avatar/' + 'a' * 32
        self.assertTrue(core_avatar(url, 'image'))
        self.assertTrue(core_avatar('https://secure.gravatar.com/avatar/' + 'b' * 64, 'image'))
        for candidate, resource in ((url, 'script'), (url.replace('https:', 'http:'), 'image'), (url.replace('gravatar.com', 'gravatar.com.evil.invalid'), 'image')):
            self.assertFalse(core_avatar(candidate, resource))

    def test_missing_duplicate_or_malformed_origin_marker_fails(self):
        duplicate = ('<span id="ecd-cache-origin" data-generation="' + 'a' * 24 + '"></span>') * 2
        for html in ('', '<span id="ecd-cache-origin" data-generation="invalid"></span>', duplicate):
            with self.assertRaises(ValueError):
                generation(html)
        self.assertFalse(page_evidence([], True))

    def test_parser_ignores_class_names_inside_scripts(self):
        parsed = Page('<script>const text="email-countdown-timer-image ecd-live";</script>')
        self.assertEqual(parsed.images + parsed.live, [])
        html = '<span hidden id="ecd-cache-origin" data-generation="' + 'a' * 24 + '"></span>'
        self.assertEqual(generation(html), 'a' * 24)


if __name__ == '__main__':
    unittest.main()
