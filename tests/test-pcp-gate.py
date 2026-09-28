#!/usr/bin/env python3
"""Synthetic negative tests: CLI exit zero must not hide findings."""
import importlib.util
import json
import pathlib
import unittest

path = pathlib.Path(__file__).resolve().parents[1]/'scripts/check-pcp-report.py'
spec = importlib.util.spec_from_file_location('pcp_gate', path)
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)

class GateTests(unittest.TestCase):
    def test_accepts_explicit_clean_report(self):
        self.assertEqual(gate.check_report('Success: Checks complete. No errors found.\n', '', '0\n'), 0)
        self.assertEqual(gate.check_report('[]', '', '0'), 0)

    def test_findings_fail_even_with_zero_exit(self):
        for kind in ('ERROR', 'WARNING'):
            with self.subTest(kind=kind), self.assertRaises(ValueError):
                gate.check_report(json.dumps([dict(type=kind, code='fixture', message='fixture')]), '', '0')

    def test_unknown_output_fails(self):
        for report in ('', '{}', 'Success', '[] trailing noise', '[{}]', '[{"type":"INFO"}]'):
            with self.subTest(report=report), self.assertRaises(ValueError):
                gate.check_report(report, '', '0')

    def test_failure_or_stderr_fails(self):
        for stderr, code in (('', '1'), ('PHP Warning', '0'), ('', ''), ('', 'cancelled')):
            with self.subTest(stderr=stderr, code=code), self.assertRaises(ValueError):
                gate.check_report('[]', stderr, code)

if __name__ == '__main__':
    unittest.main()
