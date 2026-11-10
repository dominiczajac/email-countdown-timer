#!/usr/bin/env python3
"""Negative controls for cache-hit evidence and complete off/on/browser acceptance."""
import copy
import unittest
from contracts import ACTIVE_REQUIRED, ORDER, REQUIRED, Page, accept, core_avatar, generation, page_evidence


def good(profile='baseline'):
    report = {'profile': profile, 'jit': '1235', 'complete': True, 'failures': [], 'page_errors': [], 'browser_errors': [], 'states': []}
    for key in ORDER:
        active = key.endswith('-on')
        checks = REQUIRED | (ACTIVE_REQUIRED if active else {'inactive-endpoint'})
        if profile == 'autoptimize' and active:
            checks |= {'optimized-asset', 'local-optimized-asset', 'served-optimized-asset'}
        markers = [f'{x:024x}' for x in range(4)]
        if profile in ('wp-super-cache', 'w3-total-cache'):
            markers[-1] = markers[-2]
            checks |= {'cache-artifact'}
        report['states'].append({'key': key, 'active': active, 'complete': True, 'checks': sorted(checks), 'failures': [], 'generations': markers, 'engine_cache_files': [{'path': 'cache/control', 'generation': markers[-1], 'sha256': 'b' * 64}]})
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

    def test_stale_cache_artifact_fails(self):
        report = good('w3-total-cache')
        report['states'][1]['engine_cache_files'][0]['generation'] = 'c' * 24
        self.assertFalse(accept(report))

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
