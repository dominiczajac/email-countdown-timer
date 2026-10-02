#!/usr/bin/env python3
"""Regression checks for complete, fail-closed native log inspection."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

SCRIPT = Path(__file__).with_name("check-native-logs.py")
spec = importlib.util.spec_from_file_location("native_log_gate", SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class LogGateTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        (self.root / "fpm.log").write_text("NOTICE: ready to handle connections\n")
        (self.root / "php-errors.log").write_text("")

    def test_clean_complete_logs(self):
        self.assertTrue(module.inspect(self.root)["passed"])

    def test_missing_php_log_never_hides_fpm_crash(self):
        (self.root / "fpm.log").write_text("child exited on signal 11 (SIGSEGV)\n")
        (self.root / "php-errors.log").unlink()
        result = module.inspect(self.root)
        self.assertFalse(result["passed"])
        self.assertEqual(len(result["errors"]), 2)

    def test_missing_log_fails_even_without_crash(self):
        (self.root / "php-errors.log").unlink()
        self.assertFalse(module.inspect(self.root)["passed"])

    def test_native_crash(self):
        (self.root / "fpm.log").write_text("child exited on signal 11 (SIGSEGV)\n")
        self.assertFalse(module.inspect(self.root)["passed"])

    def test_php_timeout(self):
        (self.root / "php-errors.log").write_text("PHP Fatal error: Maximum execution time exceeded\n")
        self.assertFalse(module.inspect(self.root)["passed"])

    def test_symbolic_link_refused(self):
        (self.root / "php-errors.log").unlink()
        (self.root / "php-errors.log").symlink_to(self.root / "fpm.log")
        self.assertFalse(module.inspect(self.root)["passed"])

    def test_cli_exit_and_json(self):
        command = [sys.executable, "-S", str(SCRIPT), str(self.root)]
        clean = subprocess.run(command, capture_output=True, text=True, timeout=5)
        self.assertEqual(clean.returncode, 0)
        self.assertTrue(json.loads(clean.stdout)["passed"])
        (self.root / "fpm.log").write_text("Segmentation fault\n")
        failed = subprocess.run(command, capture_output=True, text=True, timeout=5)
        self.assertEqual(failed.returncode, 1)
        self.assertFalse(json.loads(failed.stdout)["passed"])

if __name__ == "__main__":
    unittest.main(verbosity=2)
