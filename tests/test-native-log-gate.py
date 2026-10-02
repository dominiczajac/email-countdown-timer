#!/usr/bin/env python3
"""Negative tests for the CI gate; no WordPress or native crash is generated."""
import importlib.util
import json
import os
import shlex
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'tests/gutenberg/check-native-logs.py'
spec = importlib.util.spec_from_file_location('native_gate', SCRIPT)
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)


class NativeGateTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'fpm.log').write_text('NOTICE: ready to handle connections\n')
        (self.root / 'php-errors.log').write_text('')
        self.browser = {'complete': True, 'checks': ['real assertion'], 'count': 1, 'page_errors': []}
        self.write_browser()

    def write_browser(self):
        (self.root / 'browser.json').write_text(json.dumps(self.browser))

    def test_healthy_without_optional_nginx_log(self):
        self.assertTrue(gate.assess(self.root)['passed'])

    def test_crash_and_missing_php_log_reproduces_old_false_pass(self):
        (self.root / 'fpm.log').write_text('child 123 exited on signal 11 (SIGSEGV - core dumped)\n')
        (self.root / 'php-errors.log').unlink()
        old = subprocess.run(['bash', '-c', 'if grep -Ei "SIGSEGV|segmentation fault|exited on signal|PHP Fatal|Maximum execution time" "$1/fpm.log" "$1/php-errors.log" 2>/dev/null; then exit 1; fi', 'gate', str(self.root)], capture_output=True)
        self.assertEqual(0, old.returncode)  # Demonstrates the original false green.
        result = gate.assess(self.root)
        self.assertFalse(result['passed'])
        self.assertEqual('native_or_php_failure', result['errors'][0]['reason'])

    def test_all_fatal_markers(self):
        for line in ['SIGSEGV', 'Segmentation fault', 'child exited on signal 6', 'PHP Fatal error', 'Maximum execution time of 30+2 seconds exceeded']:
            with self.subTest(line=line):
                (self.root / 'fpm.log').write_text(line)
                self.assertFalse(gate.assess(self.root)['passed'])

    def test_required_php_fatal(self):
        (self.root / 'php-errors.log').write_text('PHP Fatal error: synthetic')
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_required_missing(self):
        (self.root / 'fpm.log').unlink()
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_required_empty(self):
        (self.root / 'fpm.log').write_text('')
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_invalid_utf8(self):
        (self.root / 'fpm.log').write_bytes(b'\xff')
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_log_symlink_is_error(self):
        (self.root / 'php-errors.log').unlink()
        (self.root / 'php-errors.log').symlink_to(self.root / 'fpm.log')
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_log_fifo_does_not_block(self):
        (self.root / 'php-errors.log').unlink()
        os.mkfifo(self.root / 'php-errors.log')
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_unreadable_log_is_error(self):
        with mock.patch.object(gate, 'read_regular', side_effect=PermissionError):
            self.assertFalse(gate.assess(self.root)['passed'])

    def test_oversized_log(self):
        with (self.root / 'fpm.log').open('wb') as stream:
            stream.truncate(gate.MAX_BYTES + 1)
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_incomplete_browser(self):
        self.browser['complete'] = False
        self.write_browser()
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_empty_browser_checks(self):
        self.browser.update(checks=[], count=0)
        self.write_browser()
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_browser_count_mismatch(self):
        self.browser['count'] = 2
        self.write_browser()
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_missing_browser(self):
        (self.root / 'browser.json').unlink()
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_browser_page_errors(self):
        self.browser['page_errors'] = ['TypeError']
        self.write_browser()
        self.assertFalse(gate.assess(self.root)['passed'])

    def test_no_log_content_in_report(self):
        (self.root / 'fpm.log').write_text('PHP Fatal error: synthetic-sensitive-text\n')
        self.assertNotIn('synthetic-sensitive-text', json.dumps(gate.assess(self.root)))

    def test_cli_exit_status_and_report(self):
        path = self.root / 'assessment.json'
        first = subprocess.run([sys.executable, str(SCRIPT), str(self.root), '--require-browser', '--report', str(path)], capture_output=True)
        self.assertEqual(0, first.returncode)
        self.assertTrue(json.loads(path.read_text())['passed'])
        (self.root / 'fpm.log').write_text('SIGSEGV\n')
        second = subprocess.run([sys.executable, str(SCRIPT), str(self.root), '--require-browser'], capture_output=True)
        self.assertEqual(1, second.returncode)
        # Refuse accidental overwrite even when logs are healthy.
        again = subprocess.run([sys.executable, str(SCRIPT), str(self.root), '--require-browser', '--report', str(path)], capture_output=True)
        self.assertNotEqual(0, again.returncode)


    def run_exit_handler(self, original_status=0, late_crash=False):
        # Exercise the real shell handler. Process/kernel operations are no-ops;
        # the real Python gate runs against this test's temporary evidence only.
        script = (ROOT / 'tests/gutenberg/run.sh').read_text()
        function = 'finish() {' + script.split('finish() {', 1)[1].split('\ntrap finish EXIT', 1)[0]
        service = self.root / 'services'
        service.mkdir()
        fake = self.root / 'block-venv/bin/python'
        fake.parent.mkdir(parents=True)
        fake.write_text('#!/usr/bin/env bash\nprintf called > "$ECD_BLOCK_EVIDENCE/diagnostic-called"\nexit 0\n')
        fake.chmod(0o700)
        kill = ('printf "child exited on signal 11\\n" >> "$ECD_BLOCK_EVIDENCE/fpm.log"' if late_crash else ':')
        wrapper = ('set -euo pipefail\n'
                   'sudo() { :; }; wait() { :; }; kill() { ' + kill + '; };\n'
                   'root=' + shlex.quote(str(ROOT)) + '\n'
                   'export ECD_BLOCK_EVIDENCE=' + shlex.quote(str(self.root)) + '\n'
                   'export RUNNER_TEMP=' + shlex.quote(str(self.root)) + '\n'
                   'service_dir=' + shlex.quote(str(service)) + '\n'
                   'original_core_pattern=core; php_minor=8.4; server=0; fpm=0\n'
                   + function + '\ntrap finish EXIT\nexit ' + str(original_status) + '\n')
        return subprocess.run(['bash', '-c', wrapper], capture_output=True, timeout=10)

    def test_exit_handler_keeps_clean_success(self):
        result = self.run_exit_handler()
        self.assertEqual(0, result.returncode, result.stderr.decode())
        self.assertFalse((self.root / 'diagnostic-called').exists())
        self.assertTrue(json.loads((self.root / 'native-log-final.json').read_text())['passed'])

    def test_exit_handler_promotes_masked_crash_to_failure(self):
        (self.root / 'fpm.log').write_text('SIGSEGV\n')
        result = self.run_exit_handler()
        self.assertEqual(1, result.returncode, result.stderr.decode())
        self.assertTrue((self.root / 'diagnostic-called').exists())
        self.assertFalse(json.loads((self.root / 'native-log-final.json').read_text())['passed'])

    def test_exit_handler_keeps_original_browser_failure(self):
        result = self.run_exit_handler(7)
        self.assertEqual(7, result.returncode, result.stderr.decode())
        self.assertTrue((self.root / 'diagnostic-called').exists())

    def test_exit_handler_catches_late_native_failure(self):
        result = self.run_exit_handler(late_crash=True)
        self.assertEqual(1, result.returncode, result.stderr.decode())
        self.assertTrue(json.loads((self.root / 'native-log-before-control.json').read_text())['passed'])
        self.assertFalse(json.loads((self.root / 'native-log-final.json').read_text())['passed'])



if __name__ == '__main__':
    unittest.main(verbosity=2)
