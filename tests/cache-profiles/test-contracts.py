#!/usr/bin/env python3
"""Negative controls for cache-hit evidence and complete off/on/browser acceptance."""
import copy
import json
import os
import re
from pathlib import Path
import subprocess
import sys
import tempfile
from types import SimpleNamespace
from urllib.parse import urlencode
import unittest
from contracts import ACTIVE_REQUIRED, ADMIN_PHASES, CONTENT_PROFILES, ELEMENTOR_ADMIN_FONT, ORDER, PROFILES, REQUIRED, Page, VendorPage, accept, core_avatar, elementor_admin_font, expected_plugins, font_query_facts, generation, navigation_cancel, navigation_request_cancel, page_evidence


def good(profile='baseline'):
    report = {'profile': profile, 'jit': '1235', 'complete': True, 'failures': [], 'page_errors': [], 'browser_errors': [], 'states': [], 'source_commit': 'a' * 40,
              'plugins': [] if profile == 'baseline' else [{'name': profile, 'version': PROFILES[profile]}], 'blocked_vendor_fonts': [], 'blocked_external': []}
    report['vendor_fixtures'] = {key: {'profile': profile, 'post_id': 42 + i, 'path': '/' + key + '-vendor/'} for i, key in enumerate(ORDER)}
    for index, key in enumerate(ORDER):
        active = key.endswith('-on')
        checks = REQUIRED | (ACTIVE_REQUIRED if active else {'inactive-endpoint'})
        if profile in CONTENT_PROFILES:
            checks |= {'vendor-http', 'vendor-structure', 'vendor-output', 'vendor-browser',
                       'vendor-browser-image' if active else 'vendor-browser-no-timer'}
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
        env.update(wp_cache=False, advanced_cache_dropin=False,
            configuration={'version': PROFILES[profile], 'print_google_fonts': False, 'coming_soon': 'no', 'allow_tracking': 'no', 'activation_redirect_pending': False})
        report['states'].append({'key': key, 'active': active, 'complete': True, 'checks': sorted(checks) + ['anonymous-page-http'] * 4, 'failures': [], 'generations': markers,
            'runtime': runtime, 'environment': env, 'engine_cache_files': [{'path': path, 'generation': markers[-1], 'sha256': 'b' * 64}],
            'vendor_proof': {'profile': profile, 'post_id': 42 + index, 'path': '/' + key + '-vendor/', 'status': 200,
                'native_structure': True, 'timer_images': int(active), 'scoped_timer_images': int(active), 'scope_found': True, 'literal_shortcode': not active, 'rendered_post_ids': [str(42 + index)]}})
        if profile == 'elementor':
            for phase in ADMIN_PHASES:
                if not active and phase == 'timer-editor': continue
                report['blocked_vendor_fonts'].append({'state': key, 'phase': phase, 'font': 'elementor-admin-roboto-css2', 'blocked': True})
                report['blocked_external'].append({'state': key, 'phase': phase, 'host': 'fonts.googleapis.com', 'resource_type': 'stylesheet', 'expected_vendor_font': True,
                    'font_query': {'family': [ELEMENTOR_ADMIN_FONT], 'display': ['swap'], 'ver': ['1.0.0'], 'other_fields': False}})
    return report


class Contracts(unittest.TestCase):
    def cli_writer(self, temp, name='fixture', evidence=None, invalid=False):
        code = '''
function wp_get_environment_type() { return 'local'; }
function wp_json_encode($value) { return json_encode($value); }
class WP_CLI { public static function error($message) { fwrite(STDERR, $message); exit(2); } }
$GLOBALS['wpdb'] = (object) array('base_prefix' => 'ectcache_');
require $argv[1];
echo "synthetic CLI startup diagnostic\\n";
register_shutdown_function(static function() { echo "synthetic CLI shutdown diagnostic\\n"; });
Email_Countdown_Timer_Lab_CLI::write_json($argv[2], array('proof' => $argv[3] === 'invalid' ? NAN : 42));
'''
        return subprocess.run(['php', '-r', code, str(Path(__file__).with_name('cli-evidence.php')), name, 'invalid' if invalid else 'valid'],
            env={**os.environ, 'RUNNER_TEMP': str(temp), 'ECD_CACHE_EVIDENCE': str(evidence or temp / 'cache-evidence'), 'ECD_INTEGRATION_DISPOSABLE': '1'},
            capture_output=True, text=True)

    def test_cli_json_is_separate_from_startup_and_shutdown_diagnostics(self):
        with tempfile.TemporaryDirectory() as directory:
            temp = Path(directory); evidence = temp / 'cache-evidence'; evidence.mkdir()
            result = self.cli_writer(temp)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(json.loads((evidence / 'fixture.json').read_text()), {'proof': 42})
            self.assertIn('startup diagnostic', result.stdout); self.assertIn('shutdown diagnostic', result.stdout)
            with self.assertRaises(json.JSONDecodeError):
                json.loads(result.stdout)

    def test_cli_writer_rejects_stale_files_and_symlinks(self):
        with tempfile.TemporaryDirectory() as directory:
            temp = Path(directory); evidence = temp / 'cache-evidence'; evidence.mkdir()
            self.assertEqual(self.cli_writer(temp).returncode, 0)
            self.assertNotEqual(self.cli_writer(temp).returncode, 0)
            self.assertEqual(json.loads((evidence / 'fixture.json').read_text()), {'proof': 42})
            (evidence / 'fixture.json').unlink()
            outside = temp / 'outside.json'; outside.write_text('unchanged')
            (evidence / 'fixture.json').symlink_to(outside)
            self.assertNotEqual(self.cli_writer(temp).returncode, 0)
            self.assertEqual(outside.read_text(), 'unchanged')

    def test_cli_writer_rejects_other_paths_and_unserializable_data(self):
        with tempfile.TemporaryDirectory() as directory:
            temp = Path(directory); evidence = temp / 'cache-evidence'; evidence.mkdir()
            self.assertNotEqual(self.cli_writer(temp, name='../other').returncode, 0)
            self.assertNotEqual(self.cli_writer(temp, evidence=temp).returncode, 0)
            self.assertNotEqual(self.cli_writer(temp, invalid=True).returncode, 0)
            self.assertEqual(list(temp.rglob('*.json')), [])

    def test_all_profiles_have_positive_controls(self):
        for profile in PROFILES:
            self.assertTrue(accept(good(profile)))

    def test_vendor_activation_without_actual_content_or_browser_proof_fails(self):
        for profile in CONTENT_PROFILES:
            for label in ('vendor-structure', 'vendor-output', 'vendor-browser', 'vendor-browser-image'):
                report = good(profile); report['states'][1]['checks'].remove(label)
                self.assertFalse(accept(report))
            report = good(profile); report['states'][1].pop('vendor_proof')
            self.assertFalse(accept(report))

    def test_missing_or_wrong_vendor_output_cannot_pass_by_label(self):
        for profile in CONTENT_PROFILES:
            for field, value in (('status', 403), ('native_structure', False), ('timer_images', 0), ('post_id', True), ('profile', 'baseline')):
                report = good(profile); report['states'][1]['vendor_proof'][field] = value
                self.assertFalse(accept(report))

    def test_copied_vendor_proof_or_reused_fixture_is_rejected(self):
        for profile in CONTENT_PROFILES:
            report = good(profile); report['states'][2]['vendor_proof'] = copy.deepcopy(report['states'][1]['vendor_proof'])
            self.assertFalse(accept(report))
            report = good(profile); report['vendor_fixtures'][ORDER[2]] = copy.deepcopy(report['vendor_fixtures'][ORDER[1]])
            self.assertFalse(accept(report))

    def test_vendor_configuration_is_enforced(self):
        for profile, field, value in (('elementor', 'print_google_fonts', True), ('woocommerce', 'coming_soon', 'yes'), ('woocommerce', 'allow_tracking', 'yes'), ('elementor', 'activation_redirect_pending', True), ('woocommerce', 'activation_redirect_pending', True)):
            report = good(profile); report['states'][1]['environment']['configuration'][field] = value
            self.assertFalse(accept(report))

    def test_timer_outside_native_vendor_container_is_not_proof(self):
        image = '<img class="email-countdown-timer-image" src="/timer">'
        html = '<div data-elementor-id="42"><div class="elementor-widget-shortcode"></div></div>' + image
        page = VendorPage(html, 'elementor', 42)
        self.assertEqual(len(page.images), 1); self.assertEqual(len(page.scoped_images), 0)
        page = VendorPage('<body class="single-product postid-42"><div id="tab-description"></div>' + image + '</body>', 'woocommerce', 42)
        self.assertEqual(len(page.images), 1); self.assertEqual(len(page.scoped_images), 0)

    def test_scoped_native_output_and_unprocessed_off_control(self):
        for profile, prefix, suffix in (('elementor', '<div data-elementor-id="42"><div class="elementor-widget-shortcode">', '</div></div>'),
                ('woocommerce', '<body class="single-product postid-42"><div class="wp-block-woocommerce-product-description">', '</div></body>')):
            page = VendorPage(prefix + '<br><img class="email-countdown-timer-image" src="/timer">' + suffix, profile, 42)
            self.assertEqual(len(page.scoped_images), 1); self.assertFalse(page.literal_shortcode)
            page = VendorPage(prefix + '[ecd_timer id=&quot;cache-lab&quot;]' + suffix, profile, 42)
            self.assertEqual(len(page.scoped_images), 0); self.assertTrue(page.literal_shortcode)

    def test_wrong_rendered_product_id_cannot_satisfy_fixture_binding(self):
        page = VendorPage('<body class="single-product postid-99"><div id="tab-description"><img class="email-countdown-timer-image"></div></body>', 'woocommerce', 42)
        self.assertTrue(page.product_body); self.assertEqual(page.product_ids, ['99'])
        self.assertFalse(page.scope_found); self.assertEqual(page.scoped_images, [])
        for profile in CONTENT_PROFILES:
            report = good(profile); report['states'][1]['vendor_proof']['rendered_post_ids'] = ['99']
            self.assertFalse(accept(report))

    def test_only_exact_upstream_elementor_admin_font_import_is_known(self):
        url = 'https://fonts.googleapis.com/css2?' + urlencode({'family': ELEMENTOR_ADMIN_FONT, 'display': 'swap', 'ver': '1.0.0'})
        self.assertTrue(elementor_admin_font(url, 'stylesheet', 'elementor', 'dashboard'))
        self.assertTrue(elementor_admin_font(url.replace('https:', 'http:'), 'stylesheet', 'elementor', 'dashboard'))
        for candidate, resource, profile, phase in ((url, 'script', 'elementor', 'dashboard'),
                (url, 'image', 'elementor', 'dashboard'), (url, 'stylesheet', 'baseline', 'dashboard'),
                (url, 'stylesheet', 'elementor', 'vendor-browser'), (url, 'stylesheet', 'elementor', 'live-browser'),
                (url.replace('googleapis.com', 'googleapis.com.evil.invalid'), 'stylesheet', 'elementor', 'dashboard'),
                (url.replace('Roboto', 'OtherFont'), 'stylesheet', 'elementor', 'dashboard'),
                (url.replace('1.0.0', '4.3.2'), 'stylesheet', 'elementor', 'dashboard'),
                (url + '&extra=1', 'stylesheet', 'elementor', 'dashboard'),
                (url + '#fragment', 'stylesheet', 'elementor', 'dashboard')):
            self.assertFalse(elementor_admin_font(candidate, resource, profile, phase))

    def test_font_attribution_requires_actual_balanced_off_on_controls(self):
        for change in ('missing', 'extra-on', 'wrong-font', 'wrong-phase', 'not-blocked'):
            report = good('elementor')
            blocks = report['blocked_vendor_fonts']
            if change == 'missing':
                report['blocked_vendor_fonts'] = []
            elif change == 'extra-on':
                blocks.append({'state': 'pair-1-on', 'phase': 'dashboard', 'font': 'elementor-admin-roboto-css2', 'blocked': True})
            else:
                blocks[0][{'wrong-font': 'font', 'wrong-phase': 'phase', 'not-blocked': 'blocked'}[change]] = {'wrong-font': 'other', 'wrong-phase': 'vendor-browser', 'not-blocked': False}[change]
            self.assertFalse(accept(report), change)

    def test_font_diagnosis_never_copies_private_or_arbitrary_query_fields(self):
        url = 'https://fonts.googleapis.com/css2?' + urlencode({'family': 'Roboto:wght@400;500', 'ver': '4.3.2', 'display': 'swap', 'token': 'synthetic-private-token'})
        facts = font_query_facts(url)
        self.assertEqual(facts, {'family': ['Roboto:wght@400;500'], 'display': ['swap'], 'ver': ['4.3.2'], 'other_fields': True})
        self.assertNotIn('synthetic-private', json.dumps(facts))
        facts = font_query_facts('https://fonts.googleapis.com/css2?family=synthetic-private-name&ver=private&display=private')
        self.assertNotIn('private', json.dumps(facts))
        self.assertNotIn('private', json.dumps(font_query_facts('https://fonts.googleapis.com/css2?family=Roboto:synthetic-private-token')))
        self.assertIsNone(font_query_facts('https://other.invalid/css2?family=Roboto'))

    def test_font_evidence_must_match_external_observations_and_all_pairs(self):
        report = good('elementor'); report['blocked_external'] = []
        self.assertFalse(accept(report))
        report = good('elementor'); report['blocked_external'][0]['font_query']['ver'] = ['4.3.2']
        self.assertFalse(accept(report))
        report = good('elementor')
        # Copy both channels for an entire second pair. Pairing alone would pass.
        for field in ('blocked_vendor_fonts', 'blocked_external'):
            report[field].extend(copy.deepcopy([x for x in report[field] if x['state'].startswith('pair-2-')]))
        self.assertFalse(accept(report))

    def test_malformed_or_unknown_font_control_states_fail(self):
        for value in (None, {}, 'invalid'):
            report = good('elementor'); report['blocked_vendor_fonts'] = value
            self.assertFalse(accept(report))
        report = good('elementor'); report['blocked_vendor_fonts'][0]['state'] = 'other'
        self.assertFalse(accept(report))

    def test_other_profiles_cannot_claim_elementor_font_exemptions(self):
        for profile in PROFILES.keys() - {'elementor'}:
            report = good(profile)
            report['blocked_vendor_fonts'] = good('elementor')['blocked_vendor_fonts']
            self.assertFalse(accept(report))

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

    def test_actual_post_campaign_refresh_shape_is_never_a_navigation_exemption(self):
        request = SimpleNamespace(method='POST', url='http://localhost:8095/wp-admin/admin-ajax.php',
            headers={'content-type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            post_data='action=email_countdown_timer_campaigns&nonce=synthetic-private-value', failure='net::ERR_ABORTED')
        self.assertFalse(navigation_request_cancel(request, 'timer-editor', True))
        request.post_data = 'act%69on=email_countdown_timer_campaigns&nonce=synthetic-private-value'
        self.assertFalse(navigation_request_cancel(request, 'timer-editor', True))
        request.post_data = 'action=query-attachments&_ajax_nonce=synthetic-private-value'
        self.assertTrue(navigation_request_cancel(request, 'core-media-control', True))
        self.assertFalse(navigation_request_cancel(request, 'core-media-control', False))

    def test_unsupported_or_oversized_post_cannot_be_excused(self):
        request = SimpleNamespace(method='POST', url='http://localhost:8095/wp-admin/admin-ajax.php',
            headers={'content-type': 'multipart/form-data'}, post_data='action=query-attachments', failure='net::ERR_ABORTED')
        self.assertFalse(navigation_request_cancel(request, 'core-media-control', True))
        request.headers['content-type'] = 'application/x-www-form-urlencoded'
        for body in (None, '', 'action=query-attachments&x=' + 'a' * 65536, 'action=%FF', '&'.join('x=1' for _ in range(65))):
            request.post_data = body
            self.assertFalse(navigation_request_cancel(request, 'core-media-control', True))

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
