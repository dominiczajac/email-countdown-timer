#!/usr/bin/env python3
"""Negative controls for LSAPI logs, cleanup ownership and provisioning exits."""
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'tests/cache-profiles'))
import native_logs
import server


def clean_logs(directory):
    for name in native_logs.FILES:
        (directory / name).write_text('httpdWorkers: 1\nLiteSpeed/1.9.4 Open starts successfully!\n' if name == 'ols-error.log' else 'normal shutdown\n')
    (directory / 'server-stop.json').write_text(json.dumps({'Running': False, 'ExitCode': 0, 'OOMKilled': False}))


class Native(unittest.TestCase):
    def test_clean_native_shutdown_and_retained_notices_pass(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp); clean_logs(root)
            (root / 'php-errors.log').write_text('PHP Deprecated: synthetic vendor notice\n')
            self.assertTrue(native_logs.inspect(root)['passed'])

    def test_crash_timeout_unknown_configuration_or_server_error_fails(self):
        for text in ('child killed by signal 11', 'SIGABRT', 'segmentation fault', 'PHP Fatal error: synthetic',
                     'Maximum execution time of 30 seconds exceeded', '[ERROR] synthetic server error',
                     'Error Timeout, restart and try again', 'Not support [serverworkers 1]'):
            with self.subTest(text=text), tempfile.TemporaryDirectory() as temp:
                root = Path(temp); clean_logs(root)
                with (root / 'ols-error.log').open('a') as stream: stream.write(text + '\n')
                self.assertFalse(native_logs.inspect(root)['passed'])

    def test_missing_linked_logs_or_startup_evidence_fail(self):
        for name in (*native_logs.FILES, 'server-stop.json'):
            with tempfile.TemporaryDirectory() as temp:
                root = Path(temp); clean_logs(root); (root / name).unlink()
                self.assertFalse(native_logs.inspect(root)['passed'])
                (root / name).symlink_to(root / 'ols-access.log')
                self.assertFalse(native_logs.inspect(root)['passed'])
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp); clean_logs(root); (root / 'ols-error.log').write_text('')
            self.assertFalse(native_logs.inspect(root)['passed'])

    def test_running_oom_killed_or_forced_stop_is_not_clean(self):
        for key, value in [('Running', True), ('OOMKilled', True), ('ExitCode', 137), ('ExitCode', False)]:
            with tempfile.TemporaryDirectory() as temp:
                root = Path(temp); clean_logs(root)
                state = json.loads((root / 'server-stop.json').read_text()); state[key] = value
                (root / 'server-stop.json').write_text(json.dumps(state))
                self.assertFalse(native_logs.inspect(root)['passed'])

    def test_existing_or_symlinked_owner_state_cannot_be_overwritten(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp); original = {'token': 'a' * 24}
            server.save_state(root, original)
            with self.assertRaises(FileExistsError): server.save_state(root, {'token': 'b' * 24})
            self.assertEqual(json.loads((root / 'docker-state.json').read_text()), original)
            (root / 'docker-state.json').unlink()
            outside = root / 'outside.json'; outside.write_text('unchanged')
            (root / 'docker-state.json').symlink_to(outside)
            with self.assertRaises(FileExistsError): server.save_state(root, original)
            self.assertEqual(outside.read_text(), 'unchanged')

    def test_cleanup_refuses_foreign_ownership_and_uncertain_inspection(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp); token = 'a' * 24
            state = {'token': token, 'server': 'ect-ols-web-' + token, 'database': 'ect-ols-db-' + token, 'network': 'ect-ols-net-' + token}
            server.save_state(root, state)
            foreign = SimpleNamespace(returncode=0, stdout=json.dumps([{'Config': {'Labels': {server.OWNER_LABEL: 'foreign'}}}]), stderr='')
            with patch.object(server, 'docker', return_value=foreign) as docker:
                with self.assertRaises(ValueError): server.clean(root)
                self.assertEqual(docker.call_count, 1)
                self.assertEqual(docker.call_args.args[:2], ('container', 'inspect'))
            failure = SimpleNamespace(returncode=1, stdout='', stderr='Cannot connect to the Docker daemon')
            with patch.object(server, 'docker', return_value=failure):
                with self.assertRaises(RuntimeError): server.inspect_owned('container', state['server'], token)

    def test_failed_shared_provisioning_stops_before_configuration(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp).resolve(); wp = root / 'ect-cache.test'; wp.mkdir(); (root / 'cache-evidence').mkdir()
            commands = root / 'bin'; commands.mkdir(); marker = root / 'unexpected-command'
            for name, code in [('curl', 'exit 42'), ('wp', 'touch "$ECD_UNEXPECTED_MARKER"; exit 0')]:
                executable = commands / name; executable.write_text('#!/bin/sh\n' + code + '\n'); executable.chmod(0o755)
            env = {**os.environ, 'PATH': str(commands) + ':' + os.environ['PATH'], 'ECD_INTEGRATION_DISPOSABLE': '1',
                   'RUNNER_TEMP': str(root), 'ECD_CACHE_WP': str(wp), 'ECD_CACHE_EVIDENCE': str(root / 'cache-evidence'),
                   'ECD_CACHE_SERVER': 'openlitespeed', 'ECD_CACHE_PROFILE': 'baseline', 'ECD_UNEXPECTED_MARKER': str(marker)}
            result = subprocess.run(['bash', str(ROOT / 'tests/cache-profiles/site.sh')], env=env, capture_output=True)
            self.assertEqual(result.returncode, 42)
            self.assertFalse(marker.exists())

    def test_actual_exit_trap_preserves_errors_and_requires_all_final_gates(self):
        spec = importlib.util.spec_from_file_location('cache_models', ROOT / 'tests/cache-profiles/test-contracts.py')
        models = importlib.util.module_from_spec(spec); spec.loader.exec_module(models)
        function = re.search(r'(?ms)^finish\(\) \{\n.*?^\}\n', (ROOT / 'tests/litespeed/run.sh').read_text()).group(0)
        cases = [('clean', 0, True, False, 0, 0, 0), ('original', 42, True, False, 0, 0, 42),
                 ('late-native', 0, True, True, 0, 0, 1), ('missing-report', 0, False, False, 0, 0, 1),
                 ('stop-failure', 0, True, False, 23, 0, 1), ('cleanup-failure', 0, True, False, 0, 17, 1)]
        for name, original, checks, native, stop, cleanup, expected in cases:
            with self.subTest(name=name), tempfile.TemporaryDirectory() as temp:
                root = Path(temp); clean_logs(root)
                if checks: (root / 'checks.json').write_text(json.dumps(models.good('litespeed-cache')))
                if native:
                    with (root / 'ols-error.log').open('a') as stream: stream.write('child exited on signal 11 SIGSEGV\n')
                commands = root / 'bin'; commands.mkdir()
                executable = commands / 'python3'
                executable.write_text('''#!/bin/sh
if [ "$1" = "$ECD_TEST_SERVER" ]; then
  case "$2" in stop) exit "$ECD_TEST_STOP";; clean) exit "$ECD_TEST_CLEAN";; *) exit 99;; esac
fi
exec "$ECD_TEST_PYTHON" "$@"
'''); executable.chmod(0o755)
                env = {**os.environ, 'PATH': str(commands) + ':' + os.environ['PATH'], 'ECD_TEST_PYTHON': sys.executable,
                       'ECD_TEST_SERVER': str(ROOT / 'tests/litespeed/server.py'), 'ECD_TEST_STOP': str(stop), 'ECD_TEST_CLEAN': str(cleanup)}
                script = 'root=$1\nexport ECD_CACHE_EVIDENCE=$2 ECD_CACHE_PROFILE=litespeed-cache ECD_CACHE_JIT=1235\nphase=exit-test\n' + function + '\ntrap finish EXIT\nexit "$3"\n'
                result = subprocess.run(['bash', '-c', script, 'exit-test', str(ROOT), str(root), str(original)], env=env, capture_output=True)
                self.assertEqual(result.returncode, expected)


if __name__ == '__main__':
    unittest.main()
